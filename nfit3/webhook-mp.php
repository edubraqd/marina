<?php
/**
 * webhook-mp.php
 * Recebe Webhooks do Mercado Pago, valida a assinatura e confirma o evento na API.
 * Requer variÃ¡veis de ambiente:
 *   MP_ACCESS_TOKEN     -> Access Token de PRODUÃ‡ÃƒO (nÃ£o use public key aqui)
 *   MP_WEBHOOK_SECRET   -> Secret do Webhook (obtido na tela de Webhooks)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/mpago.php';

// 1) Responder 200 rapidamente (o MP reenvia se demorar)
http_response_code(200);

// 2) Capturar corpo e cabeÃ§alhos
$raw = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Normalizar keys dos headers (case-insensitive)
$norm = [];
foreach ($headers as $k => $v) { $norm[strtolower($k)] = $v; }

// Headers usados na validaÃ§Ã£o
$xSig = $norm['x-signature'] ?? '';
$xReqId = $norm['x-request-id'] ?? '';

// Corpo (pode vir vazio em alguns envios; o MP tambÃ©m manda querystring)
$payload = json_decode($raw, true) ?: [];

// 3) Extrair tipo e id do recurso (data.id) do corpo OU da querystring
$type   = $payload['type'] ?? ($_GET['type'] ?? null);
$dataId = $payload['data']['id'] ?? ($_GET['data_id'] ?? ($_GET['id'] ?? null));

// 4) Validar assinatura do Webhook (recomendado em produÃ§Ã£o)
$config = mpago_config();
$secret = $config['webhook_secret'] ?? '';
$valid  = $secret ? validate_mp_signature($xSig, $xReqId, $dataId, $secret) : true;

// Log bÃ¡sico (nÃ£o salve dados sensÃ­veis em produÃ§Ã£o)
log_event(__DIR__ . '/logs/webhook-mp.log', [
  'ts'        => date('c'),
  'type'      => $type,
  'data_id'   => $dataId,
  'x_req_id'  => $xReqId,
  'x_sig'     => $xSig,
  'signature' => $valid ? 'ok' : 'fail',
  'body'      => $payload,
]);

if (!$valid) {
  // Assinatura invÃ¡lida: opte por ignorar silenciosamente (jÃ¡ retornamos 200 no topo)
  exit;
}

// 5) Confirmar na API do MP (sempre busque o recurso pelo ID recebido)
$token = $config['access_token'] ?? '';
if (!$token || !$type || !$dataId) {
  // Falta info essencial; apenas finalize
  exit;
}

if ($type === 'payment') {
  $payment = mpago_fetch_payment($dataId, $token);
  if ($payment) {
    $process = mpago_process_payment($payment);
    mpago_log('webhook.payment', [
      'id'        => $dataId,
      'status'    => $payment['status'] ?? null,
      'processed' => $process['processed'] ?? false,
    ]);
  }
} elseif ($type === 'subscription_preapproval') {
  $sub = mpago_request('GET', "https://api.mercadopago.com/preapproval/{$dataId}", null, $token);
  mpago_log('webhook.subscription', ['id' => $dataId, 'ok' => $sub['ok'] ?? false]);
} elseif ($type === 'subscription_preapproval_plan') {
  $plan = mpago_request('GET', "https://api.mercadopago.com/preapproval_plan/{$dataId}", null, $token);
  mpago_log('webhook.preapproval_plan', ['id' => $dataId, 'ok' => $plan['ok'] ?? false]);
}

// ---------- FunÃ§Ãµes utilitÃ¡rias ----------

/**
 * Valida x-signature do Mercado Pago.
 * Header vem como "ts=1704908010,v1=hashhex".
 * Manifesto: "id:{data.id};request-id:{x-request-id};ts:{ts};"
 * Hash = HMAC-SHA256(manifesto, MP_WEBHOOK_SECRET) em hex, comparar com v1.
 */
function validate_mp_signature(string $xSignature, string $xRequestId, ?string $resourceId, string $secret): bool {
  if (!$xSignature || !$xRequestId || !$resourceId || !$secret) return false;

  // Extrair ts e v1
  $ts = null; $v1 = null;
  foreach (explode(',', $xSignature) as $part) {
    [$k, $v] = array_map('trim', explode('=', $part, 2) + [null, null]);
    if ($k === 'ts') $ts = $v;
    if ($k === 'v1') $v1 = $v;
  }
  if (!$ts || !$v1) return false;

  // IMPORTANTE: alguns proxies podem sobrescrever X-Request-Id â†’ garanta que chega intacto
  $manifest = "id:{$resourceId};request-id:{$xRequestId};ts:{$ts};";

  $calc = hash_hmac('sha256', $manifest, $secret);
  return hash_equals($calc, $v1);
}

/** Log JSON por linha */
function log_event(string $file, array $data): void {
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
  @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
}

?>
