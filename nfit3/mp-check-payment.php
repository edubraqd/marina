<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/mpago.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';

$config = mpago_config();
$accessToken = $config['access_token'] ?? '';

if ($email === '' || $accessToken === '') {
    http_response_code(422);
    echo json_encode(['error' => 'missing_email_or_token']);
    exit;
}

$payment = mpago_find_last_approved_payment_by_email($email, $accessToken);

if ($payment && ($payment['status'] ?? '') === 'approved') {
    $process = mpago_process_payment($payment);
    $paymentId = $payment['id'] ?? null;
    $redirect = $paymentId ? '/payment-return?payment_id=' . urlencode((string) $paymentId) . '&status=approved' : null;
    echo json_encode([
        'ok'         => true,
        'status'     => 'approved',
        'payment_id' => $paymentId,
        'redirect'   => $redirect,
    ]);
    exit;
}

echo json_encode([
    'ok'     => true,
    'status' => $payment['status'] ?? 'pending',
]);
