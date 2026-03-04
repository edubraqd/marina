<?php
// webhooks/mpago.php

// 1) Segurança básica e logs
http_response_code(200); // responde rápido ao MP
$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/mpago_webhook.log', date('c') . " | " . $raw . PHP_EOL, FILE_APPEND);

$payload = json_decode($raw, true) ?: [];
$type    = $payload['type']    ?? ($_GET['type'] ?? null);     // webhooks novos usam JSON
$id      = $payload['data']['id'] ?? ($_GET['id'] ?? null);    // fallback antigo (IPN)

// 2) Se o evento for de pagamento, consulta o pagamento na API do MP
if ($type === 'payment' && $id) {
  $ACCESS_TOKEN = getenv('MP_ACCESS_TOKEN') ?: 'SEU_ACCESS_TOKEN_AQUI';

  $ch = curl_init("https://api.mercadopago.com/v1/payments/{$id}");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$ACCESS_TOKEN}"]
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http === 200 && $resp) {
    $p = json_decode($resp, true);

    // 3) Dados úteis
    $status         = $p['status'] ?? '';
    $status_detail  = $p['status_detail'] ?? '';
    $payer_email    = $p['payer']['email'] ?? '';
    $description    = $p['description'] ?? '';      // costuma vir o nome do link (ex.: "Plano Semestral")
    $transaction_id = $p['id'] ?? '';

    // 4) Identificar o plano pelo título/descrição do link
    //    (ou crie um mapeamento pela URL clicada, ver observação abaixo)
    $plano = 'mensal';
    if (stripos($description, 'semestral') !== false) $plano = 'semestral';
    if (stripos($description, 'anual')     !== false) $plano = 'anual';

    // 5) Se aprovado e ainda não processado, ativa o acesso
    if ($status === 'approved') {
      // TODO: verifique idempotência (ex.: procurar $transaction_id no seu DB antes de inserir)

      // Exemplo simples: salvar um CSV/DB, disparar e-mail etc.
      $linha = implode(';', [date('c'), $transaction_id, $status, $plano, $payer_email]) . PHP_EOL;
      file_put_contents(__DIR__ . '/mpago_pagamentos.csv', $linha, FILE_APPEND);

      // (Opcional) Chamar sua rotina de liberação de acesso/CRM/WhatsApp aqui
      // liberaAcesso($payer_email, $plano);
    }
  }
}

// Sempre devolva 200 OK
echo 'ok';
