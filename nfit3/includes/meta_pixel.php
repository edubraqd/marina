<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const META_CAPI_LOG_FILE = __DIR__ . '/../storage/meta_capi.log';

/**
 * Base config for Meta Pixel + CAPI.
 *
 * Env vars:
 * - META_PIXEL_ID
 * - META_ACCESS_TOKEN
 * - META_TEST_EVENT_CODE (optional)
 */
function meta_pixel_config(): array
{
    $base = function_exists('nf_base_url') ? nf_base_url() : getenv('APP_URL');
    if (!$base) {
        $base = 'https://nutremfit.com.br';
    }

    $pixelId = trim((string) getenv('META_PIXEL_ID'));
    if ($pixelId === '') {
        $pixelId = '1815210352491615';
    }

    return [
        'pixel_id'        => $pixelId,
        'access_token'    => trim((string) getenv('META_ACCESS_TOKEN')),
        'test_event_code' => trim((string) getenv('META_TEST_EVENT_CODE')),
        'app_url'         => rtrim($base ?: 'http://localhost/nfit3', '/'),
    ];
}

function meta_pixel_id(): string
{
    $cfg = meta_pixel_config();
    return $cfg['pixel_id'] ?? '';
}

function meta_capi_enabled(): bool
{
    $cfg = meta_pixel_config();
    return ($cfg['pixel_id'] ?? '') !== '' && ($cfg['access_token'] ?? '') !== '';
}

function meta_event_source_url(?string $fallback = null): string
{
    $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref !== '') {
        return $ref;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($host !== '') {
        return $scheme . '://' . $host . $uri;
    }

    return $fallback ?? '';
}

function meta_capi_event_id(string $prefix = ''): string
{
    try {
        $rand = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $rand = uniqid('', true);
    }
    return $prefix . $rand;
}

/**
 * Send a single CAPI event.
 *
 * @param string $eventName
 * @param array<string,mixed> $userData
 * @param array<string,mixed> $customData
 * @return array{ok:bool,status:int,response:array<string,mixed>|null,error:?string,raw:?string}
 */
function meta_capi_send_event(
    string $eventName,
    array $userData = [],
    array $customData = [],
    ?string $eventId = null,
    ?string $eventSourceUrl = null,
    string $actionSource = 'website'
): array {
    $cfg = meta_pixel_config();
    if (($cfg['pixel_id'] ?? '') === '' || ($cfg['access_token'] ?? '') === '') {
        return [
            'ok' => false,
            'status' => 0,
            'response' => null,
            'error' => 'missing_config',
            'raw' => null,
        ];
    }

    $event = [
        'event_name'   => $eventName,
        'event_time'   => time(),
        'action_source'=> $actionSource,
        'user_data'    => meta_capi_build_user_data($userData),
    ];
    if ($eventId) {
        $event['event_id'] = $eventId;
    }
    if ($eventSourceUrl) {
        $event['event_source_url'] = $eventSourceUrl;
    }
    if (!empty($customData)) {
        $event['custom_data'] = $customData;
    }

    $payload = ['data' => [$event]];
    if (($cfg['test_event_code'] ?? '') !== '') {
        $payload['test_event_code'] = $cfg['test_event_code'];
    }

    $url = sprintf(
        'https://graph.facebook.com/v18.0/%s/events?access_token=%s',
        $cfg['pixel_id'],
        urlencode($cfg['access_token'])
    );

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    $response = null;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response = $decoded;
        }
    }

    $ok = $raw !== false && $http >= 200 && $http < 300;
    if (!$ok) {
        meta_capi_log('capi_error', [
            'event' => $eventName,
            'status' => $http,
            'error' => $error,
            'raw' => $raw,
        ]);
    }

    return [
        'ok' => $ok,
        'status' => (int) $http,
        'response' => $response,
        'error' => $error,
        'raw' => is_string($raw) ? $raw : null,
    ];
}

/**
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function meta_capi_build_user_data(array $data): array
{
    if (empty($data['first_name']) || empty($data['last_name'])) {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name) ?: [];
            $first = $parts[0] ?? '';
            $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
            if (!isset($data['first_name']) && $first !== '') {
                $data['first_name'] = $first;
            }
            if (!isset($data['last_name']) && $last !== '') {
                $data['last_name'] = $last;
            }
        }
    }

    $user = [];

    $email = (string) ($data['email'] ?? $data['em'] ?? '');
    $emailNorm = meta_capi_normalize_email($email);
    if ($emailNorm !== '') {
        $user['em'] = hash('sha256', $emailNorm);
    }

    $phone = (string) ($data['phone'] ?? $data['ph'] ?? '');
    $phoneNorm = meta_capi_normalize_phone($phone);
    if ($phoneNorm !== '') {
        $user['ph'] = hash('sha256', $phoneNorm);
    }

    $first = meta_capi_normalize_name((string) ($data['first_name'] ?? ''));
    if ($first !== '') {
        $user['fn'] = hash('sha256', $first);
    }
    $last = meta_capi_normalize_name((string) ($data['last_name'] ?? ''));
    if ($last !== '') {
        $user['ln'] = hash('sha256', $last);
    }

    $externalId = (string) ($data['external_id'] ?? '');
    if ($externalId !== '') {
        $user['external_id'] = hash('sha256', meta_capi_normalize_external_id($externalId));
    }

    $fbp = (string) ($data['fbp'] ?? ($_COOKIE['_fbp'] ?? ''));
    if ($fbp !== '') {
        $user['fbp'] = $fbp;
    }
    $fbc = (string) ($data['fbc'] ?? ($_COOKIE['_fbc'] ?? ''));
    if ($fbc !== '') {
        $user['fbc'] = $fbc;
    }

    $clientIp = trim((string) ($data['client_ip_address'] ?? ''));
    if ($clientIp !== '') {
        $user['client_ip_address'] = $clientIp;
    }
    $clientUa = trim((string) ($data['client_user_agent'] ?? ''));
    if ($clientUa !== '') {
        $user['client_user_agent'] = $clientUa;
    }

    return $user;
}

function meta_capi_normalize_email(string $email): string
{
    $email = trim($email);
    if ($email === '') {
        return '';
    }
    $email = meta_capi_lower($email);
    return preg_replace('/\s+/', '', $email) ?? '';
}

function meta_capi_normalize_phone(string $phone): string
{
    $phone = preg_replace('/\D+/', '', $phone ?? '') ?? '';
    return trim($phone);
}

function meta_capi_normalize_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $name = meta_capi_lower($name);
    $name = meta_capi_transliterate($name);
    $name = preg_replace('/[^a-z]/', '', $name) ?? '';
    return $name;
}

function meta_capi_normalize_external_id(string $value): string
{
    $value = meta_capi_lower(trim($value));
    return preg_replace('/\s+/', '', $value) ?? '';
}

function meta_capi_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

function meta_capi_transliterate(string $value): string
{
    if (function_exists('iconv')) {
        $out = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($out)) {
            return $out;
        }
    }
    return $value;
}

/**
 * @param array<string,mixed> $data
 */
function meta_capi_log(string $type, array $data = []): void
{
    $line = json_encode([
        'ts' => date('c'),
        'type' => $type,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);

    $dir = dirname(META_CAPI_LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(META_CAPI_LOG_FILE, $line . PHP_EOL, FILE_APPEND);
}
