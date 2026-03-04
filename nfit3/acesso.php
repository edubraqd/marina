<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/mpago.php';

$title = 'Acesso do aluno';
$css = '<link rel="stylesheet" href="assets/css/area.css">';

$statusMsg = '';
$statusType = ''; // success | error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '';
    if (!$email) {
        $statusMsg = 'Informe um e-mail válido.';
        $statusType = 'error';
    } else {
        $email = mb_strtolower(trim($email));
        $config = mpago_config();
        $token = $config['access_token'] ?? '';

        $user = user_store_find($email);

        // Usuário existente
        if ($user) {
            $userId = (int) ($user['id'] ?? 0);

            if (user_store_has_active_subscription($userId)) {
                // Reenvia um acesso com nova senha temporária
                $sub = user_store_last_subscription($userId);
                $planRow = $sub && isset($sub['plan_id']) ? mpago_get_plan_by_id((int) $sub['plan_id']) : null;
                $planSlug = $planRow['slug'] ?? ($user['plan'] ?? 'essencial');
                $expiresAt = $sub['expires_at'] ?? null;

                $newPass = bin2hex(random_bytes(4));
                user_store_update_password($email, $newPass);
                send_onboarding_email($email, $user['name'] ?? '', $newPass, $planSlug, $expiresAt, true);

                $statusMsg = 'Encontramos seu cadastro e reenviamos um acesso com nova senha temporária para o seu e-mail.';
                $statusType = 'success';
            } else {
                // Sem assinatura ativa: tenta localizar pagamento aprovado para reativar
                if (!$token) {
                    $statusMsg = 'Assinatura expirada. Conclua um novo pagamento para liberar o acesso.';
                    $statusType = 'error';
                } else {
                    $payment = mpago_find_last_approved_payment_by_email($email, $token);
                    if ($payment) {
                        $planInfo = mpago_plan_from_payment($payment);
                        $planId = mpago_get_plan_id($planInfo['slug'] ?? $planInfo['db_plan'] ?? 'essencial');
                        if ($planId !== null) {
                            $sub = mpago_upsert_subscription($userId, $planId, $planInfo['cycle'] ?? 'monthly', $payment);
                            $newPass = bin2hex(random_bytes(4));
                            user_store_update_password($email, $newPass);
                            send_onboarding_email($email, $user['name'] ?? '', $newPass, $planInfo['slug'] ?? $planInfo['db_plan'] ?? 'plano', $sub['expires_at'] ?? null, true);

                            $statusMsg = 'Pagamento aprovado encontrado. Reativamos sua assinatura e reenviamos uma nova senha temporária para o seu e-mail.';
                            $statusType = 'success';
                        } else {
                            $statusMsg = 'Pagamento encontrado, mas não foi possível identificar o plano. Fale com o suporte.';
                            $statusType = 'error';
                        }
                    } else {
                        $statusMsg = 'Não encontramos pagamento ativo para este e-mail. Conclua o pagamento para liberar o acesso.';
                        $statusType = 'error';
                    }
                }
            }
        } else {
            // Primeiro acesso: procurar pagamento aprovado e provisionar
            if (!$token) {
                $statusMsg = 'Não encontramos cadastro. Conclua o pagamento para liberar o acesso.';
                $statusType = 'error';
            } else {
                $payment = mpago_find_last_approved_payment_by_email($email, $token);
                if ($payment) {
                    $planInfo = mpago_plan_from_payment($payment);
                    $name = trim(($payment['payer']['first_name'] ?? '') . ' ' . ($payment['payer']['last_name'] ?? ''));

                    $provision = user_store_provision($email, $planInfo['db_plan'] ?? $planInfo['slug'] ?? 'essencial', $name);
                    $userId = (int) ($provision['user']['id'] ?? 0);
                    $planId = mpago_get_plan_id($planInfo['slug'] ?? $planInfo['db_plan'] ?? 'essencial');
                    $expiresAt = null;

                    if ($userId > 0 && $planId !== null) {
                        $sub = mpago_upsert_subscription($userId, $planId, $planInfo['cycle'] ?? 'monthly', $payment);
                        $expiresAt = $sub['expires_at'] ?? null;
                    }

                    if (!empty($provision['password'])) {
                        send_onboarding_email($email, $name, $provision['password'], $planInfo['slug'] ?? $planInfo['db_plan'] ?? 'plano', $expiresAt);
                    }

                    $statusMsg = 'Pagamento aprovado encontrado. Criamos seu acesso e enviamos e-mail com senha temporária.';
                    $statusType = 'success';
                } else {
                    $statusMsg = 'Não encontramos pagamento aprovado para este e-mail. Finalize o pagamento para liberar o acesso.';
                    $statusType = 'error';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>
<body class="area-theme">
  <?php include './partials/preloader.php'; ?>
  <?php include './partials/header.php'; ?>

  <style>
    .recover-page {background: linear-gradient(135deg,#0b0f1a,#10182b); color:#fff; min-height: 100vh; padding:80px 0;}
    .recover-card {background:#0f1320; border:1px solid rgba(255,255,255,0.08); border-radius:20px; padding:28px; box-shadow:0 25px 70px rgba(0,0,0,0.45);}
  </style>

  <section class="recover-page">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-6">
          <div class="recover-card">
            <h2 class="mb-3">Liberar/recuperar acesso</h2>
            <p class="mb-4" style="color:rgba(255,255,255,0.82);">Informe o e-mail usado no pagamento. Buscaremos a cobrança aprovada, criaremos (ou reativaremos) seu acesso e enviaremos a senha temporária por e-mail.</p>

            <?php if ($statusMsg): ?>
              <div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo htmlspecialchars($statusMsg, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>

            <form method="post" autocomplete="on">
              <div class="form-group mb-3">
                <label for="email">E-mail usado no pagamento</label>
                <input type="email" class="form-control" id="email" name="email" required>
              </div>
              <button type="submit" class="btn_one w-100">Confirmar acesso</button>
            </form>

            <p class="mt-3 mb-0" style="color:rgba(255,255,255,0.7);">Se precisar finalizar um novo pagamento, acesse <a href="/pagamento">pagamento</a>.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php include './partials/footer.php'; ?>
  <?php include './partials/script.php'; ?>
</body>
</html>
