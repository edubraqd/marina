<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/mpago.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

$title = 'Reprocessar pagamento (MP)';
$css = '<link rel="stylesheet" href="assets/css/area.css">';

$config = mpago_config();
$accessToken = $config['access_token'] ?? '';

$message = '';
$messageType = 'info';
$resultData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accessToken) {
    $paymentId = trim((string) ($_POST['payment_id'] ?? ''));
    $email     = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($paymentId !== '') {
        $payment = mpago_fetch_payment($paymentId, $accessToken);
        if ($payment) {
            $res = mpago_process_payment($payment);
            $message = $res['processed']
                ? 'Pagamento reprocessado e acesso liberado (se aprovado).'
                : 'Não foi possível processar o pagamento. Verifique status/metadata.';
            $messageType = $res['processed'] ? 'success' : 'warning';
            $resultData = [
                'payment_id' => $paymentId,
                'status'     => $payment['status'] ?? null,
                'email'      => $payment['metadata']['email'] ?? ($payment['payer']['email'] ?? null),
                'plan'       => $payment['metadata']['plan'] ?? $payment['metadata']['db_plan'] ?? null,
            ];
        } else {
            $message = 'Pagamento não encontrado na API do Mercado Pago.';
            $messageType = 'danger';
        }
    } elseif ($email !== '') {
        $payment = mpago_find_last_approved_payment_by_email($email, $accessToken);
        if ($payment) {
            $res = mpago_process_payment($payment);
            $message = $res['processed']
                ? 'Último pagamento aprovado localizado por e-mail e processado.'
                : 'Pagamento encontrado, mas não foi possível processar. Verifique status/metadata.';
            $messageType = $res['processed'] ? 'success' : 'warning';
            $resultData = [
                'payment_id' => $payment['id'] ?? null,
                'status'     => $payment['status'] ?? null,
                'email'      => $payment['metadata']['email'] ?? ($payment['payer']['email'] ?? null),
                'plan'       => $payment['metadata']['plan'] ?? $payment['metadata']['db_plan'] ?? null,
            ];
        } else {
            $message = 'Nenhum pagamento aprovado encontrado para este e-mail.';
            $messageType = 'danger';
        }
    } else {
        $message = 'Informe um ID de pagamento ou um e-mail.';
        $messageType = 'danger';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$accessToken) {
    $message = 'Configure MP_ACCESS_TOKEN para consultar a API do Mercado Pago.';
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>
<body class="area-theme">
  <?php include './partials/preloader.php'; ?>
  <?php include './partials/header.php'; ?>

  <style>
    .reprocess-wrap {background: #0b0f1a; min-height:100vh; padding:60px 0; color:#fff;}
    .card-reprocess {background:#0f1320; border:1px solid rgba(255,255,255,0.08); border-radius:18px; padding:26px; box-shadow:0 22px 60px rgba(0,0,0,0.45);}
    label {font-weight:600;}
  </style>

  <section class="reprocess-wrap">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
          <?php $area_nav_active = 'admin_reprocess'; include './partials/area-nav.php'; ?>
        </div>
        <div class="col-lg-8 col-xl-9">
          <div class="card-reprocess">
            <h2 class="mb-3">Reprocessar pagamento (Mercado Pago)</h2>
            <p class="text-muted" style="color:rgba(255,255,255,0.65)!important;">
              Use esta tela para reprocessar um pagamento aprovado que não foi gravado no banco. Informe o <strong>ID do pagamento</strong> ou, em último caso, o <strong>e-mail</strong> usado no checkout (busca apenas o último aprovado).
            </p>

            <?php if ($message): ?>
              <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
              <div class="col-md-6">
                <label for="payment_id">ID do pagamento (payment.id)</label>
                <input type="text" class="form-control" id="payment_id" name="payment_id" placeholder="Ex: 1234567890">
              </div>
              <div class="col-md-6">
                <label for="email">E-mail do pagador (opcional)</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="somente se não souber o ID">
              </div>
              <div class="col-12">
                <button type="submit" class="btn_one">Reprocessar</button>
                <a href="/area-admin" class="btn btn-link text-light">Voltar</a>
              </div>
            </form>

            <?php if ($resultData): ?>
              <hr style="border-color:rgba(255,255,255,0.08);">
              <h6 class="mb-2">Resumo</h6>
              <ul class="mb-0" style="color:rgba(255,255,255,0.75);">
                <li>ID: <?php echo htmlspecialchars((string) ($resultData['payment_id'] ?? 'n/d'), ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Status: <?php echo htmlspecialchars((string) ($resultData['status'] ?? 'n/d'), ENT_QUOTES, 'UTF-8'); ?></li>
                <li>E-mail: <?php echo htmlspecialchars((string) ($resultData['email'] ?? 'n/d'), ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Plano: <?php echo htmlspecialchars((string) ($resultData['plan'] ?? 'n/d'), ENT_QUOTES, 'UTF-8'); ?></li>
              </ul>
            <?php endif; ?>

            <?php if (!$accessToken): ?>
              <div class="alert alert-danger mt-3 mb-0">Defina MP_ACCESS_TOKEN no ambiente para usar esta ferramenta.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include './partials/footer.php'; ?>
  <?php include './partials/script.php'; ?>
</body>
</html>
