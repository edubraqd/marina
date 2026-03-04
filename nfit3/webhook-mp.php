<?php
/**
 * webhook-mp.php
 * Recebe Webhooks do Mercado Pago, valida a assinatura e confirma o evento na API.
 * Requer variáveis de ambiente:
 *   MP_ACCESS_TOKEN     -> Access Token de PRODUÇÃO (não use public key aqui)
 *   MP_WEBHOOK_SECRET   -> Secret do Webhook (obtido na tela de Webhooks)
 */

// 1) Responder 200 rapidamente (o MP reenvia se demorar)
http_response_code(200);

// 2) Capturar corpo e cabeçalhos
$raw = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Normalizar keys dos headers (case-insensitive)
$norm = [];
foreach ($headers as $k => $v) { $norm[strtolower($k)] = $v; }

// Headers usados na validação
$xSig = $norm['x-signature'] ?? '';
$xReqId = $norm['x-request-id'] ?? '';

// Corpo (pode vir vazio em alguns envios; o MP também manda querystring)
$payload = json_decode($raw, true) ?: [];

// 3) Extrair tipo e id do recurso (data.id) do corpo OU da querystring
$type   = $payload['type'] ?? ($_GET['type'] ?? null);
$dataId = $payload['data']['id'] ?? ($_GET['data_id'] ?? ($_GET['id'] ?? null));

// 4) Validar assinatura do Webhook (recomendado em produção)
$secret = getenv('MP_WEBHOOK_SECRET') ?: '';
$valid  = validate_mp_signature($xSig, $xReqId, $dataId, $secret);

// Log básico (não salve dados sensíveis em produção)
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
  // Assinatura inválida: opte por ignorar silenciosamente (já retornamos 200 no topo)
  exit;
}

// 5) Confirmar na API do MP (sempre busque o recurso pelo ID recebido)
$token = getenv('MP_ACCESS_TOKEN') ?: '';
if (!$token || !$type || !$dataId) {
  // Falta info essencial; apenas finalize
  exit;
}

if ($type === 'payment') {
  $payment = mp_get("https://api.mercadopago.com/v1/payments/{$dataId}", $token);
  if ($payment && ($payment['status'] ?? '') === 'approved') {
    // TODO: marcar como pago no seu sistema (idempotência!)
    // ex.: save_payment($payment);
  }
} elseif ($type === 'subscription_preapproval') {
  $sub = mp_get("https://api.mercadopago.com/preapproval/{$dataId}", $token);
  if ($sub && in_array(($sub['status'] ?? ''), ['authorized','active'], true)) {
    // TODO: ativar/atualizar assinatura no seu sistema
    // ex.: activate_subscription($sub);
  }
} elseif ($type === 'subscription_preapproval_plan') {
  // Opcional: sincronizar dados do plano, se precisar
  // $plan = mp_get("https://api.mercadopago.com/preapproval_plan/{$dataId}", $token);
}

// ---------- Funções utilitárias ----------

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

  // IMPORTANTE: alguns proxies podem sobrescrever X-Request-Id → garanta que chega intacto
  $manifest = "id:{$resourceId};request-id:{$xRequestId};ts:{$ts};";

  $calc = hash_hmac('sha256', $manifest, $secret);
  return hash_equals($calc, $v1);
}

/** Chamada GET simples à API do MP */
function mp_get(string $url, string $accessToken): ?array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER      => ["Authorization: Bearer {$accessToken}"],
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_TIMEOUT         => 15,
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $http === 200 ? json_decode($res, true) : null;
}

/** Log JSON por linha */
function log_event(string $file, array $data): void {
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
  @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
}
