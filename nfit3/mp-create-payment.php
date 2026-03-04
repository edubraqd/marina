<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/mpago.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (function_exists('nf_url') ? nf_url('/pagamento') : '/pagamento.php'));
    exit;
}

$name  = trim((string) ($_POST['name'] ?? ''));
$email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
$planSlug = (string) ($_POST['plan'] ?? 'essencial');
$termsAccepted = isset($_POST['terms_accept']) && (string) $_POST['terms_accept'] === '1';

$plan = mpago_plan($planSlug);
$config = mpago_config();

if ($email === '') {
    exit('E-mail inválido. Volte e confira os dados.');
}
if (!$termsAccepted) {
    exit('É necessário aceitar o Termo de Consentimento antes de prosseguir com o pagamento.');
}

if (($config['access_token'] ?? '') === '') {
    mpago_log('preference.error', [
        'plan'  => $planSlug,
        'email' => $email,
        'err'   => 'missing_access_token',
    ]);
    exit('Defina a variável de ambiente MP_ACCESS_TOKEN para criar a preferência de pagamento seguro.');
}

$appBase = rtrim($config['app_url'], '/');

// Usar URL limpa (sem caminho de servidor ou .php) para evitar retornos quebrados do MP
$returnUrl = function_exists('nf_url') ? nf_url('/payment-return') : ($appBase . '/payment-return.php');
$notificationUrl = function_exists('nf_url') ? nf_url('/webhook-mp') : ($appBase . '/webhook-mp.php');

// Dispara e-mails imediatamente sem atrasar o usuário
$planName = $plan['name'] ?? ucfirst($planSlug);
send_welcome_pending_email($email, $name, $planName);
send_admin_notification(
    'Nova tentativa de compra (checkout iniciado)',
    [
        'Evento: checkout iniciado',
        'Plano: ' . $planSlug,
        'Nome: ' . ($name ?: 'n/d'),
        'E-mail: ' . $email,
        'Telefone: ' . ($phone ?: 'n/d'),
        'Data: ' . date('d/m/Y H:i'),
    ]
);

$preference = mpago_create_preference(
    $plan,
    ['name' => $name, 'email' => $email, 'phone' => $phone],
    $returnUrl,
    $notificationUrl,
    $config['access_token']
);

if ($preference['ok'] && ($preference['init_point'] || $preference['sandbox_init_point'])) {
    $redirectUrl = $preference['init_point'] ?: $preference['sandbox_init_point'];
    header("Location: {$redirectUrl}");
    exit;
}

mpago_log('preference.error', [
    'plan'  => $planSlug,
    'email' => $email,
    'err'   => $preference['error'] ?? 'unknown',
    'resp'  => $preference['payload'] ?? null,
]);

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Falha ao iniciar pagamento</title>
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
</head>
<body style="background:#0d1117;color:#fff;">
  <div class="container" style="max-width:720px;padding:60px 15px;">
    <h1 style="font-size:28px;margin-bottom:18px;">Não foi possível abrir o pagamento</h1>
    <p>O Mercado Pago não retornou o link de checkout. Verifique se o <code>MP_ACCESS_TOKEN</code> está configurado corretamente e tente novamente.</p>
    <p>Caso o erro persista, verifique o log em <code>storage/mpago.log</code>.</p>
    <a href="/pagamento?plan=<?php echo htmlspecialchars($planSlug, ENT_QUOTES, 'UTF-8')?>" class="btn btn-primary mt-3">Voltar</a>
  </div>
</body>
</html>
