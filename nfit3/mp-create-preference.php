<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/mpago.php';
require_once __DIR__ . '/includes/meta_pixel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$name  = trim((string) ($_POST['name'] ?? ''));
$email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$planSlug = (string) ($_POST['plan'] ?? 'essencial');
$termsAccepted = isset($_POST['terms_accept']) && (string) $_POST['terms_accept'] === '1';

$plan = mpago_plan($planSlug);
$config = mpago_config();
$chargeAmount = mpago_plan_charge_amount($plan);
$plan['amount'] = $chargeAmount;

if ($email === '') {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_email']);
    exit;
}
if (!$termsAccepted) {
    http_response_code(422);
    echo json_encode(['error' => 'terms_not_accepted']);
    exit;
}

if (($config['access_token'] ?? '') === '') {
    mpago_log('preference.error', [
        'plan'  => $planSlug,
        'email' => $email,
        'err'   => 'missing_access_token',
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'missing_access_token']);
    exit;
}

$appBase = rtrim($config['app_url'], '/');

// Dispara e-mails imediatamente
$planName = $plan['name'] ?? ucfirst($planSlug);
send_welcome_pending_email($email, $name, $planName);
send_admin_notification(
    'Nova tentativa de compra (checkout iniciado)',
    [
        'Evento: checkout iniciado',
        'Plano: ' . $planSlug,
        'Nome: ' . ($name ?: 'n/d'),
        'E-mail: ' . $email,
        'Telefone: ' . ($phone ?: 'n/d'),
        'Data: ' . date('d/m/Y H:i'),
    ]
);

$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$clientUa = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$eventSourceUrl = meta_event_source_url($appBase . '/pagamento');

if (meta_capi_enabled()) {
    meta_capi_send_event(
        'InitiateCheckout',
        [
            'email' => $email,
            'phone' => $phone,
            'name'  => $name,
            'client_ip_address' => $clientIp,
            'client_user_agent' => $clientUa,
        ],
        [
            'currency' => 'BRL',
            'value' => (float) $chargeAmount,
            'content_ids' => [$planSlug],
            'content_name' => $planName,
            'content_type' => 'product',
            'contents' => [[
                'id' => $planSlug,
                'quantity' => 1,
                'item_price' => (float) $chargeAmount,
            ]],
        ],
        meta_capi_event_id('init_'),
        $eventSourceUrl
    );
}

$returnUrl = function_exists('nf_url') ? nf_url('/payment-return') : ($appBase . '/payment-return.php');
$notificationUrl = function_exists('nf_url') ? nf_url('/webhook-mp') : ($appBase . '/webhook-mp.php');

$preference = mpago_create_preference(
    $plan,
    ['name' => $name, 'email' => $email, 'phone' => $phone],
    $returnUrl,
    $notificationUrl,
    $config['access_token']
);

if ($preference['ok'] && (!empty($preference['payload']['id']) || !empty($preference['payload']['preference_id']))) {
    $prefId = $preference['payload']['id'] ?? $preference['payload']['preference_id'];
    echo json_encode([
        'ok'            => true,
        'preference_id' => $prefId,
        'init_point'    => $preference['init_point'] ?? ($preference['payload']['init_point'] ?? null),
        'sandbox_init_point' => $preference['sandbox_init_point'] ?? ($preference['payload']['sandbox_init_point'] ?? null),
    ]);
    exit;
}

mpago_log('preference.error', [
    'plan'  => $planSlug,
    'email' => $email,
    'err'   => $preference['error'] ?? 'unknown',
    'resp'  => $preference['payload'] ?? null,
]);

http_response_code(500);
echo json_encode([
    'ok'    => false,
    'error' => $preference['error'] ?? 'unknown',
]);
