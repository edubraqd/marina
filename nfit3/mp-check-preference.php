<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/mpago.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$preferenceId = trim((string) ($_POST['preference_id'] ?? ''));
$config = mpago_config();
$accessToken = $config['access_token'] ?? '';

if ($preferenceId === '' || $accessToken === '') {
    http_response_code(422);
    echo json_encode(['error' => 'missing_params']);
    exit;
}

$query = http_build_query(['preference_id' => $preferenceId, 'sort' => 'date_created', 'criteria' => 'desc', 'limit' => 1]);
$res = mpago_request('GET', "https://api.mercadopago.com/v1/payments/search?{$query}", null, $accessToken);
$payment = null;
$status = 'pending';

if ($res['ok']) {
    $results = $res['data']['results'] ?? [];
    if (!empty($results)) {
        foreach ($results as $candidate) {
            if (($candidate['status'] ?? '') === 'approved') {
                $payment = $candidate;
                break;
            }
        }
        if (!$payment) {
            $payment = $results[0];
        }
    }
}

// Fallback pelo merchant_order (útil para PIX que acaba de ser pago)
if (!$payment) {
    $moRes = mpago_request('GET', "https://api.mercadopago.com/merchant_orders?preference_id={$preferenceId}", null, $accessToken);
    if ($moRes['ok']) {
        $elements = $moRes['data']['elements'] ?? [];
        $mo = $elements[0] ?? null;
        if ($mo) {
            $payments = $mo['payments'] ?? [];
            foreach ($payments as $p) {
                if (($p['status'] ?? '') === 'approved') {
                    $full = mpago_fetch_payment((string) ($p['id'] ?? ''), $accessToken);
                    $payment = $full ?: $p;
                    break;
                }
                if (!$payment) {
                    $payment = $p;
                }
            }
        }
    }
}

$status = $payment['status'] ?? $status;

if ($payment && $status === 'approved') {
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

mpago_log('check.preference', [
    'preference_id' => $preferenceId,
    'status'        => $status,
    'has_payment'   => (bool) $payment,
]);

echo json_encode([
    'ok'     => true,
    'status' => $status,
]);
