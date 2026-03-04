<?php
// webhooks/mpago.php

require_once __DIR__ . '/../includes/mpago.php';

// 1) Segurança básica e logs
http_response_code(200); // responde rápido ao MP
$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/mpago_webhook.log', date('c') . " | " . $raw . PHP_EOL, FILE_APPEND);

$payload = json_decode($raw, true) ?: [];
$type    = $payload['type']    ?? ($_GET['type'] ?? null);     // webhooks novos usam JSON
$id      = $payload['data']['id'] ?? ($_GET['id'] ?? null);    // fallback antigo (IPN)

// 2) Se o evento for de pagamento, consulta o pagamento na API do MP
if ($type === 'payment' && $id) {
  $config = mpago_config();
  $ACCESS_TOKEN = $config['access_token'] ?? '';

  if ($ACCESS_TOKEN) {
    $p = mpago_fetch_payment((string) $id, $ACCESS_TOKEN);
    if ($p) {
      $process = mpago_process_payment($p);
      mpago_log('webhook.legacy', [
        'id'        => $id,
        'status'    => $p['status'] ?? '',
        'processed' => $process['processed'] ?? false,
      ]);
    }
  }
}

// Sempre devolva 200 OK
echo 'ok';
