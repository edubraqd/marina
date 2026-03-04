<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/mpago.php';

$config = mpago_config();
$accessToken = $config['access_token'];

$paymentId = (string) ($_GET['payment_id'] ?? ($_GET['collection_id'] ?? ''));
$preferenceId = (string) ($_GET['preference_id'] ?? '');
$statusParam = (string) ($_GET['status'] ?? '');

$payment   = null;
$verified  = false;
$processed = false;
$result    = null;
$errorMsg  = '';

if ($paymentId && $accessToken) {
    $payment = mpago_fetch_payment($paymentId, $accessToken);
    if ($payment) {
        $verified = true;
        $result = mpago_process_payment($payment);
        $processed = !empty($result['processed']);
    } else {
        $errorMsg = 'NÃ£o foi possÃ­vel validar o pagamento na API do Mercado Pago.';
    }
} elseif ($paymentId && !$accessToken) {
    $errorMsg = 'Configure MP_ACCESS_TOKEN para validar o pagamento.';
}

$status = $payment['status'] ?? $statusParam;
$planSlug = $payment['metadata']['plan'] ?? '';
$isTest = !empty($payment['metadata']['is_test']);
$email = $payment['metadata']['email'] ?? '';

$title = 'Retorno do pagamento | NutremFit';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>
<body class="area-theme">
  <?php include './partials/preloader.php'; ?>
  <?php include './partials/header.php'; ?>

  <style>
    .return-hero {
      background: linear-gradient(120deg,#0a0d14,#0f1627);
      color: #fff;
      padding: 80px 0 50px;
    }
    .card-return {
      background: #0f1320;
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 18px;
      padding: 26px;
      box-shadow: 0 30px 90px rgba(0,0,0,0.45);
      color: #fff;
    }
    .badge-soft {padding:6px 12px;border-radius:30px;font-size:12px;}
    .badge-approved {background: rgba(66,201,99,0.18);color:#7df0a9;border:1px solid rgba(66,201,99,0.5);}
    .badge-pending {background: rgba(255,198,88,0.15);color:#ffd382;border:1px solid rgba(255,198,88,0.5);}
    .badge-failed {background: rgba(255,99,71,0.15);color:#ff8d7b;border:1px solid rgba(255,99,71,0.5);}
  </style>

  <section class="return-hero">
    <div class="container">
      <div class="col-lg-8 offset-lg-2">
        <div class="card-return">
          <p class="mb-1" style="color:rgba(255,255,255,0.65);">Retorno do pagamento</p>
          <h2 style="font-weight:800;">Status: <?php echo htmlspecialchars($status ?: 'desconhecido', ENT_QUOTES, 'UTF-8'); ?></h2>

          <?php if ($verified && $status === 'approved'): ?>
            <div class="badge-soft badge-approved mb-2">Pagamento aprovado e autenticado</div>
            <p class="mb-2">Recebemos a confirmação diretamente na API<?php echo $isTest ? ' (fluxo de teste R$1)' : ''; ?>.</p>
            <?php if ($email): ?>
              <p class="mb-2">Liberamos o acesso para <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>. O e-mail de boas-vindas com a senha foi enviado.</p>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars(function_exists('nf_url') ? nf_url('/area-login') : (rtrim($config['app_url'], '/') . '/area-login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn_one mt-2">Ir para a área do Aluno</a>
          <?php elseif ($verified && ($status === 'pending' || $status === 'in_process')): ?>
            <div class="badge-soft badge-pending mb-2">Pagamento pendente</div>
            <p>Aguardando aprovação do cartão ou banco. Assim que mudar para <strong>approved</strong>, liberaremos o acesso automaticamente.</p>
          <?php elseif ($verified && $status && $status !== 'approved'): ?>
            <div class="badge-soft badge-failed mb-2">Pagamento não aprovado</div>
            <p>O Mercado Pago retornou status <code><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></code>. Tente novamente ou use outro meio de pagamento.</p>
            <a href="/pagamento<?php echo $planSlug ? '?plan='.urlencode($planSlug) : ''; ?>" class="btn_one mt-2">Tentar novamente</a>
          <?php else: ?>
            <div class="badge-soft badge-pending mb-2">Aguardando confirmação</div>
            <p>Estamos esperando a validação na API do Mercado Pago. <?php echo $errorMsg ? htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') : ''; ?></p>
            <p class="small mb-0">Se jÃ¡ foi cobrado, o acesso serão liberado assim que o webhook confirmar.</p>
          <?php endif; ?>

          <hr style="border-color:rgba(255,255,255,0.08);">
          <div class="small" style="color:rgba(255,255,255,0.65);">
            <p class="mb-1">ID do pagamento: <code><?php echo htmlspecialchars($paymentId ?: 'n/d', ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p class="mb-1">Preferencia: <code><?php echo htmlspecialchars($preferenceId ?: 'n/d', ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p class="mb-0">Webhook: <?php echo $config['app_url']; ?>/webhook-mp.php</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include './partials/footer.php'; ?>
  <?php include './partials/script.php'; ?>
</body>
</html>
