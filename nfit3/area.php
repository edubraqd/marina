<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/onboarding_mailer.php';

$current_user = area_guard_require_login();
$title = 'Área do Aluno';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
$preferences = is_array($current_user['preferences'] ?? null) ? $current_user['preferences'] : [];
$skipForms = !empty($preferences['skip_forms']);
$needsOnboarding = ($current_user['role'] ?? 'student') !== 'admin'
    && !$skipForms
    && empty($preferences['initial_form_completed']);
$area_nav_onboarding = $needsOnboarding;
$lastUpdateFormAt = !empty($preferences['last_update_form_at']) ? strtotime((string) $preferences['last_update_form_at']) : null;
$initialCompletedAt = !empty($preferences['initial_form_completed_at']) ? strtotime((string) $preferences['initial_form_completed_at']) : null;
$anchorTs = $lastUpdateFormAt ?: ($initialCompletedAt ?: strtotime((string) ($current_user['created_at'] ?? 'now')));
$daysSinceAnchor = (int) floor((time() - $anchorTs) / 86400);
$needsMonthlyUpdate = !$skipForms && $daysSinceAnchor >= 22 && (!$lastUpdateFormAt || (time() - $lastUpdateFormAt) >= 30 * 86400);

$feedback = ['type' => '', 'message' => '']; // senha
$demoFeedback = ['type' => '', 'message' => '']; // gatilho onboarding
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_demo_onboarding'])) {
        $demoEmail = trim((string) ($_POST['demo_email'] ?? ''));
        $demoName  = trim((string) ($_POST['demo_name'] ?? ''));
        if (!filter_var($demoEmail, FILTER_VALIDATE_EMAIL)) {
            $demoFeedback = ['type' => 'danger', 'message' => 'Informe um e-mail válido para testar o onboarding.'];
        } else {
            $tmpPassword = substr(bin2hex(random_bytes(4)), 0, 8);
            $planLabel = 'Demonstração';
            send_onboarding_email($demoEmail, $demoName ?: 'Teste', $tmpPassword, $planLabel);
            $demoFeedback = ['type' => 'success', 'message' => "E-mail de onboarding enviado para {$demoEmail} (senha gerada: {$tmpPassword})."];
        }
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            $feedback = ['type' => 'danger', 'message' => 'A nova senha precisa ter pelo menos 8 caracteres.'];
        } elseif ($newPassword !== $confirmPassword) {
            $feedback = ['type' => 'danger', 'message' => 'As senhas digitadas não conferem.'];
        } else {
            user_store_update_password($current_user['email'], $newPassword);
            $feedback = ['type' => 'success', 'message' => 'Senha atualizada com sucesso!'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>

<body class="area-shell" data-spy="scroll" data-offset="80">
    <?php include './partials/preloader.php'?>
    <?php include './partials/header.php'?>

    <section class="section-top text-center">
        <div class="container">
            <div class="col-lg-10 offset-lg-1">
                <div class="section-top-title wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.2s">
                    <h1>Olá, <?php echo htmlspecialchars($current_user['name'] ?: 'aluno', ENT_QUOTES, 'UTF-8')?>!</h1>
                    <p style="max-width:720px;margin:14px auto 0;color:rgba(255,255,255,0.78);">
                        Aqui você encontra os planos alimentares e treinos atualizados (com vídeos) e recebe notificações da plataforma sem precisar vasculhar e-mails.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-wrap py-5">
        <div class="container">
            <?php if ($needsOnboarding): ?>
                <div class="alert alert-warning">
                    Complete o formulário inicial em até 24h úteis para liberar toda a Área do Aluno, planos e treinos. Após o envio, a navegação completa é liberada automaticamente.
                </div>
            <?php endif;?>
            <?php if ($needsMonthlyUpdate): ?>
                <div class="alert alert-info">
                    Está na hora de enviar o formulário de atualização (faltam 8 dias para fechar o ciclo). Preencha para garantirmos ajustes em até 24h úteis.
                    <div class="mt-2">
                        <a class="btn_one btn-sm" href="/formulario-atualizacao">Preencher atualização</a>
                    </div>
                </div>
            <?php endif;?>
            <div class="row g-4">
                <div class="col-lg-4 col-xl-3">
                    <?php $area_nav_active = 'dashboard'; include './partials/area-nav.php'; ?>

                    <div class="dash-card mt-4">
                        <span class="plan-badge"><i class="ti-crown"></i> Plano <?php echo htmlspecialchars(ucfirst($current_user['plan'] ?? 'Especial'), ENT_QUOTES, 'UTF-8')?></span>
                        <h4 class="mt-3">Status</h4>
                        <p>Início: <?php echo htmlspecialchars(date('d/m/Y', strtotime($current_user['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8')?></p>
                        <p>Último acesso: <?php echo $current_user['last_login_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($current_user['last_login_at'])), ENT_QUOTES, 'UTF-8') : 'Primeiro login';?></p>
                        <div class="quick-actions mt-3">
                            <a href="/contact"><i class="ti-headphone"></i> Suporte da plataforma</a>
                            <a href="/area-logout"><i class="ti-shift-left"></i> Sair</a>
                        </div>
                    </div>

                    <?php if (($current_user['role'] ?? '') === 'admin'): ?>
                    <div class="dash-card mt-3">
                        <h5>Disparo de onboarding (teste)</h5>
                        <p class="mb-2">Envie o e-mail de boas-vindas manualmente para checar entrega e layout.</p>
                        <?php if ($demoFeedback['message']): ?>
                            <div class="alert alert-<?php echo $demoFeedback['type'];?>" role="alert">
                                <?php echo htmlspecialchars($demoFeedback['message'], ENT_QUOTES, 'UTF-8');?>
                            </div>
                        <?php endif;?>
                        <form method="post" class="row g-2">
                            <input type="hidden" name="send_demo_onboarding" value="1">
                            <div class="col-12">
                                <label class="form-label mb-1">E-mail destino</label>
                                <input type="email" name="demo_email" class="form-control form-control-sm" placeholder="teste@exemplo.com" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Nome (opcional)</label>
                                <input type="text" name="demo_name" class="form-control form-control-sm" placeholder="Nome para saudar no e-mail">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn_one btn-sm">Enviar e-mail de teste</button>
                            </div>
                        </form>
                        <small class="d-block mt-2" style="color:rgba(255,255,255,0.7);">Gera uma senha temporária e usa o template padrão de onboarding.</small>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-8 col-xl-9">
                    <div class="dash-card mb-4">
                        <h4>Acompanhamento premium</h4>
                        <p class="mb-2">Entre e encontre tudo em um só lugar.</p>
                        <p class="mb-3">Aqui você baixa os planos alimentares atualizados, acessa aos novos treinos e recebe notificações da plataforma sem precisar vasculhar e-mails.</p>
                        <ul style="margin-bottom:0;">
                            <li>Planos alimentares e treinos organizados por mês.</li>
                            <li>Alertas de novos materiais e ajustes personalizados.</li>
                            <li>Formulário simples para atualização mensal e atalho para falar com a equipe Nutremfit.</li>
                        </ul>
                    </div>

                    <div class="dash-card mb-4">
                        <h4>Primeiro passo: formulário inicial</h4>
                        <?php if (!$needsOnboarding && !empty($preferences['initial_form_completed_at'])): ?>
                            <p class="mb-2">Formulário enviado em <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($preferences['initial_form_completed_at'])), ENT_QUOTES, 'UTF-8');?>. Para ajustes, fale com o suporte.</p>
                        <?php else: ?>
                            <p class="mb-2">Complete em até 24h úteis para liberar toda a Área do Aluno e receber seu plano personalizado.</p>
                            <a href="formulario-inicial" class="btn_one">Preencher agora</a>
                        <?php endif;?>
                    </div>
                    <?php if (!$needsOnboarding): ?>
                    <div class="dash-card mb-4">
                        <h4>Atualizar senha</h4>
                        <?php if ($feedback['message']): ?>
                            <div class="alert alert-<?php echo $feedback['type']?>" role="alert">
                                <?php echo htmlspecialchars($feedback['message'], ENT_QUOTES, 'UTF-8')?>
                            </div>
                        <?php endif;?>
                        <form method="post" class="row g-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">Nova senha</label>
                                <div style="position:relative;">
                                    <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8" autocomplete="new-password" style="padding-right:42px;">
                                    <button
                                        type="button"
                                        class="nf-eye-toggle"
                                        data-target="new_password"
                                        aria-controls="new_password"
                                        aria-label="Mostrar senha"
                                        aria-pressed="false"
                                        title="Mostrar senha"
                                        style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;padding:4px 6px;font-size:16px;"
                                    >
                                        <span class="nf-eye-icon-open" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </span>
                                        <span class="nf-eye-icon-closed" aria-hidden="true" style="display:none;">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                                <path d="M3 3l18 18"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirmar senha</label>
                                <div style="position:relative;">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="8" autocomplete="new-password" style="padding-right:42px;">
                                    <button
                                        type="button"
                                        class="nf-eye-toggle"
                                        data-target="confirm_password"
                                        aria-controls="confirm_password"
                                        aria-label="Mostrar senha"
                                        aria-pressed="false"
                                        title="Mostrar senha"
                                        style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;padding:4px 6px;font-size:16px;"
                                    >
                                        <span class="nf-eye-icon-open" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </span>
                                        <span class="nf-eye-icon-closed" aria-hidden="true" style="display:none;">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                                <path d="M3 3l18 18"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn_one">Salvar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif;?>
                </div>
            </div>
        </div>
    </section>

    <?php include './partials/footer.php'?>
    <?php include './partials/script.php'?>
    <script>
      var showLabel = 'Mostrar senha';
      var hideLabel = 'Ocultar senha';

      document.querySelectorAll('.nf-eye-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var target = btn.getAttribute('data-target');
          var input = target ? document.getElementById(target) : null;
          if (!input) return;

          var revealing = input.type === 'password';
          input.type = revealing ? 'text' : 'password';

          var iconOpen = btn.querySelector('.nf-eye-icon-open');
          var iconClosed = btn.querySelector('.nf-eye-icon-closed');
          if (iconOpen && iconClosed) {
            iconOpen.style.display = revealing ? 'none' : 'inline-flex';
            iconClosed.style.display = revealing ? 'inline-flex' : 'none';
          }

          var legacyIcon = btn.querySelector('i');
          if (legacyIcon) {
            legacyIcon.className = revealing ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
          }

          var label = revealing ? hideLabel : showLabel;
          btn.setAttribute('aria-label', label);
          btn.setAttribute('title', label);
          btn.setAttribute('aria-pressed', revealing ? 'true' : 'false');
        });
      });
    </script>
</body>
</html>
