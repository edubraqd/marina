<?php
// Login da Área do Aluno
session_start();
ob_start(); // previne "headers already sent" em ambientes com BOM/output acidental
require_once __DIR__ . '/includes/user_store.php';
require_once __DIR__ . '/includes/bootstrap.php';

if (function_exists('user_store_seed_admin_from_env')) {
    user_store_seed_admin_from_env();
}

$title = 'Área do Aluno | Login';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
$baseUrl = function_exists('nf_base_url') ? nf_base_url() : rtrim((string) (getenv('APP_URL') ?: ''), '/');
$acessoUrl = function_exists('nf_url') ? nf_url('/acesso') : ($baseUrl ? $baseUrl . '/acesso.php' : '/acesso.php');
$error = '';
$expired = isset($_GET['expired']);
$alreadyLogged = isset($_SESSION['user_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $user = user_store_authenticate($email, $password);
        if ($user) {
            $_SESSION['user_email'] = $user['email'];
            user_store_touch_login($user['email']);
            app_log('login: success', ['email' => $email]);
            header('Location: ' . (function_exists('nf_url') ? nf_url('/area') : '/area.php'));
            ob_end_flush();
            exit;
        }
    }

    app_log('login: failed', ['email' => $email]);
    $error = 'E-mail ou senha inválidos. Verifique os dados do e-mail de primeiro acesso.';
} elseif ($expired) {
    $error = 'Seu plano expirou. Renove o pagamento para liberar novamente o acesso.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>

    <body class="area-theme" data-spy="scroll" data-offset="80">

        <?php include './partials/preloader.php'?>
        <?php include './partials/header.php'?>  

        <style>
          :root {
            --area-dark: #080a0f;
            --area-card: #101320;
            --accent: #ff6b35;
            --accent-light: #ffb37f;
          }
          .area-theme {
            background: var(--area-dark);
            color: rgba(255,255,255,0.85);
          }
          .area-theme .section-top {
            background: linear-gradient(135deg,#0b0f1a,#1a0d09);
            padding: 80px 0 40px;
            margin-bottom: 0;
          }
          .login-wrap {padding: 80px 0;}
          .login-card,
          .perks-card {
            background: var(--area-card);
            border-radius: 22px;
            padding: 32px;
            border: 1px solid rgba(255,255,255,0.07);
            box-shadow: 0 35px 90px rgba(0,0,0,0.4);
          }
          .login-card h3 {color:#fff;margin-bottom:20px;}
          .login-card .form-control {
            background: #0c0f1a;
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
          }
          .login-card .form-control:focus {
            border-color: var(--accent);
            box-shadow: none;
          }
          .perks-card span.neon-pill {
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:8px 16px;
            border-radius:40px;
            background: rgba(255,107,53,0.15);
            border: 1px solid rgba(255,107,53,0.4);
            color:#fff;
          }
          .perks-card ul {padding-left:20px;margin-bottom:0;}
          .perks-card li {margin-bottom:10px;color:rgba(255,255,255,0.8);}
          .demo-credentials {
            background: rgba(255,107,53,0.12);
            border: 1px solid rgba(255,107,53,0.4);
            border-radius: 16px;
            padding: 14px 18px;
            color: #fff;
            font-size: 14px;
            margin-top: 18px;
          }
          .area-theme .btn_one {background: var(--accent);border: none;}
          .area-theme .btn_one:hover {background:#ff834f;}
          .area-theme a {color: var(--accent-light);}
        </style>

        <section class="section-top">
          <div class="container">
            <div class="col-lg-8 offset-lg-2 text-center">
              <div class="section-top-title wow fadeInRight" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
                <h1>Área do Aluno</h1>
                <ul>
                  <li><a href="/">Início</a></li>
                  <li> / Login</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <section class="login-wrap">
            <div class="container">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-6 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
                        <div class="perks-card">
                            <span class="neon-pill"><i class="ti-bolt"></i> Acompanhamento premium</span>
                            <h2 class="mt-3">Entre e encontre tudo em um só lugar</h2>
                            <p>Aqui você baixa os planos atualizados, registra check-ins e recebe notificações da plataforma sem precisar vasculhar e-mails.</p>
                            <ul>
                                <li>Planos, treinos, receitas e listas organizados por ciclo.</li>
                                <li>Alertas de novos materiais e ajustes personalizados.</li>
                                <li>Checklist de hábitos e atalho direto para falar com a Marina.</li>
                            </ul>
                           
                        </div>
                    </div>
                    <div class="col-lg-5 offset-lg-1 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s">
                        <div class="login-card">
                            <h3>Entrar na sua conta</h3>
                            <?php if ($alreadyLogged): ?>
                                <div class="alert alert-warning" role="alert">
                                    Você já possui uma sessão ativa. Deseja continuar ou <a href="/area-logout" class="alert-link">sair</a> para trocar de conta?
                                </div>
                            <?php endif;?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?>
                                </div>
                            <?php endif;?>
                            <form method="post" autocomplete="off">
                                <div class="form-group mb-3">
                                    <label for="login-email">E-mail cadastrado</label>
                                    <input type="email" id="login-email" name="email" class="form-control" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="login-password">Senha</label>
                                    <div style="position:relative;">
                                        <input type="password" id="login-password" name="password" class="form-control" required autocomplete="current-password" style="padding-right:42px;">
                                        <button
                                            type="button"
                                            class="nf-eye-toggle"
                                            data-target="login-password"
                                            aria-controls="login-password"
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
                                <button type="submit" class="btn_one w-100 mt-2">Entrar</button>
                                <p class="mt-3 small text-center">
                                    Não recebeu o acesso? <a href="<?php echo htmlspecialchars($acessoUrl, ENT_QUOTES, 'UTF-8'); ?>">Clique aqui para liberar/reenviar</a>.
                                </p>
                                <p class="mt-3 small text-center">
                                    Não encontrou o e-mail? Procure por <strong>"Seu acesso à Área do Aluno NutremFit"</strong> ou fale comigo pelo <a href="/contact.php">suporte</a>.
                                </p>
                            </form>
                        </div>
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
    <?php ob_end_flush(); ?>
</body>
</html>
