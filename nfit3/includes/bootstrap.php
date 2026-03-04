<?php
// includes/bootstrap.php
// Inicializa registro de erros e helper de log para diagnósticos.

if (!defined('NF_BOOTSTRAPPED')) {
    define('NF_BOOTSTRAPPED', true);

    $logDir = __DIR__ . '/../storage';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $appLog = $logDir . '/app.log';
    $phpLog = $logDir . '/php_errors.log';
    @touch($appLog);
    @touch($phpLog);

    // Em modo de diagnóstico, exibimos erros para evitar tela branca
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    date_default_timezone_set(getenv('APP_TZ') ?: 'America/Sao_Paulo');
    $logFile = $logDir . '/php_errors.log';

    // Erros e exceções
    ini_set('log_errors', '1');
    ini_set('error_reporting', (string) E_ALL);
    ini_set('display_errors', '0');
    ini_set('error_log', $logFile);

    /**
     * Registro simples de mensagens (nível app).
     *
     * @param string $message
     * @param array<string,mixed> $context
     */
    function app_log(string $message, array $context = []): void
    {
        $line = json_encode([
            'ts' => date('c'),
            'msg' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE);

        @file_put_contents(__DIR__ . '/../storage/app.log', $line . PHP_EOL, FILE_APPEND);
    }

    /**
     * Resolve the current request scheme, including reverse proxy headers.
     */
    function nf_request_scheme(): string
    {
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', strtolower($forwarded)));
            if (!empty($parts[0])) {
                return $parts[0] === 'https' ? 'https' : 'http';
            }
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
        return $port === 443 ? 'https' : 'http';
    }

    /**
     * Base path for subfolder installs (e.g. /nfit3). Empty on root installs.
     */
    function nf_base_path(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }

        return rtrim($dir, '/');
    }

    /**
     * Base URL for the active environment. In web requests, prefer current host.
     */
    function nf_base_url(): string
    {
        static $baseUrl = null;

        if (is_string($baseUrl) && $baseUrl !== '') {
            return $baseUrl;
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $basePath = nf_base_path();
            $baseUrl = nf_request_scheme() . '://' . $host . $basePath;
            return $baseUrl;
        }

        $envBase = trim((string) getenv('APP_URL'));
        if ($envBase !== '') {
            $baseUrl = rtrim($envBase, '/');
            return $baseUrl;
        }

        $baseUrl = 'http://localhost';
        return $baseUrl;
    }

    /**
     * Builds an absolute URL using the current base URL.
     */
    function nf_url(string $path = ''): string
    {
        if ($path === '') {
            return rtrim(nf_base_url(), '/');
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = '/' . ltrim($path, '/');

        // Compatibilidade com hospedagens sem URL amigavel: se existir arquivo .php,
        // gera a URL com extensao para evitar 404.
        $pathOnly = (string) (parse_url($normalized, PHP_URL_PATH) ?? $normalized);
        $suffix = substr($normalized, strlen($pathOnly)) ?: '';
        if (
            $pathOnly !== '/'
            && !preg_match('/\.[a-z0-9]+$/i', $pathOnly)
        ) {
            $candidate = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $pathOnly . '.php');
            if (is_file($candidate)) {
                $normalized = $pathOnly . '.php' . $suffix;
            }
        }

        return rtrim(nf_base_url(), '/') . $normalized;
    }

    // Marca inicial para saber que o bootstrap foi carregado
    app_log('bootstrap_loaded', ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        app_log('php_error', ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line]);
        return false; // deixa o handler padrão continuar
    });

    set_exception_handler(function ($ex) {
        app_log('php_exception', [
            'type' => get_class($ex),
            'message' => $ex->getMessage(),
            'file' => $ex->getFile(),
            'line' => $ex->getLine(),
            'trace' => $ex->getTraceAsString(),
        ]);

        // Evita tela branca (HTTP 200 silencioso) quando ocorre exceção
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        $isLocal = in_array(getenv('APP_ENV'), ['local', 'dev', 'development'], true);

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erro no Sistema</title></head>';
        echo '<body style="background:#0b0f1a;color:#fff;font-family:Arial,Helvetica,sans-serif;padding:32px;">';
        echo '<h2 style="margin-top:0;">Oops, algo deu errado!</h2>';
        echo '<p>Não foi possível processar sua requisição no momento.</p>';

        if ($isLocal || strpos($_SERVER['HTTP_HOST'] ?? '', 'auditoriabh.com') !== false) {
            echo '<div style="background:#1a0d09;border:1px solid #ff6b35;padding:16px;border-radius:8px;margin-top:20px;">';
            echo '<h4 style="margin-top:0;color:#ffb37f;">Detalhes do Erro (Modo Debug):</h4>';
            echo '<p><strong>Exceção:</strong> ' . htmlspecialchars(get_class($ex), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>Mensagem:</strong> ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>Arquivo:</strong> ' . htmlspecialchars($ex->getFile(), ENT_QUOTES, 'UTF-8') . ' na linha ' . $ex->getLine() . '</p>';
            echo '</div>';
        }

        echo '</body></html>';
        exit;
    });

    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
            app_log('php_fatal', $err);
        }
    });
}
