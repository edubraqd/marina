<?php

declare(strict_types=1);

/**
 * Configurações padrão do banco (sobrescreva via variáveis de ambiente).
 */
function database_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = [
            'host' => getenv('DB_HOST') ?: 'localhost',
            'name' => getenv('DB_NAME') ?: 'edua0932_nutremfit',
            'user' => getenv('DB_USER') ?: 'edua0932_edu',
            'pass' => getenv('DB_PASS') ?: 'Empresa@77',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        ];
    }

    return $config;
}

/**
 * Retorna conexão singleton com o MySQL.
 */
function db(): mysqli
{
    static $connection;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    // Certifique-se de que erros de conexão virem logs
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $config = database_config();
    $attempts = [$config];

    // Fallback comum em ambientes locais (XAMPP): root sem senha
    $isLocalHost = in_array($config['host'], ['localhost', '127.0.0.1'], true);
    if ($isLocalHost) {
        $attempts[] = array_merge($config, ['user' => 'root', 'pass' => '']);

        // Permite que o localhost use o banco remoto (ex.: ambiente de dev apontando para a hospedagem)
        // Informe hosts adicionais via DB_HOST_REMOTE ou DB_HOSTS_REMOTE (separados por vírgula).
        $remoteHostsEnv = getenv('DB_HOSTS_REMOTE') ?: getenv('DB_HOST_REMOTE') ?: '108.167.188.59,108.167.188.55';
        $remoteHosts = array_filter(array_unique(array_map('trim', preg_split('/[;,\\s]+/', (string) $remoteHostsEnv))));
        foreach ($remoteHosts as $remoteHost) {
            if ($remoteHost !== '' && $remoteHost !== $config['host']) {
                $attempts[] = array_merge($config, ['host' => $remoteHost]);
            }
        }
    }

    $lastError = null;
    foreach ($attempts as $cfg) {
        try {
            $connection = mysqli_init();
            $connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
            $connection->real_connect(
                $cfg['host'],
                $cfg['user'],
                $cfg['pass'],
                $cfg['name'],
                $cfg['port']
            );
            $connection->set_charset($cfg['charset']);
            return $connection;
        } catch (mysqli_sql_exception $e) {
            $lastError = $e;
            if (function_exists('app_log')) {
                app_log('db_connect_error', [
                    'host' => $cfg['host'],
                    'user' => $cfg['user'],
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    $isLocal = in_array(getenv('APP_ENV'), ['local', 'dev', 'development'], true);
    $msgUser = 'Não foi possível conectar ao banco de dados. Tente novamente em instantes.';
    http_response_code(500);
    // Evita tela em branco e mostra mensagem amigável (sem vazar senha)
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Erro de conexão</title></head>';
    echo '<body style="background:#0b0f1a;color:#fff;font-family:Arial,Helvetica,sans-serif;padding:32px;">';
    echo '<h2 style="margin-top:0;">Oops, instabilidade!</h2>';
    echo '<p>' . htmlspecialchars($msgUser, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($isLocal && $lastError) {
        echo '<p style="color:#ffb37f;">Detalhes (ambiente local): ' . htmlspecialchars($lastError->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</body></html>';
    exit;

    return $connection; // nunca alcançado, apenas para satisfazer o retorno
}
