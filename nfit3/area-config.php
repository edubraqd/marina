<?php
require_once __DIR__ . '/includes/area_guard.php';

$current_user = area_guard_require_login();
$title = 'Área do Aluno | Configurações';
$css = '<link rel="stylesheet" href="assets/css/area.css">';

$defaultPrefs = ['notify_email' => true, 'notify_whatsapp' => true];
$preferences = $current_user['preferences'] ?? $defaultPrefs;
$preferences = array_merge($defaultPrefs, is_array($preferences) ? $preferences : []);
$profileFeedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $plan = trim($_POST['plan'] ?? '');
    $goal = trim($_POST['goal'] ?? '');
    $preferences = [
        'notify_email'    => isset($_POST['notify_email']) ? true : $defaultPrefs['notify_email'],
        'notify_whatsapp' => isset($_POST['notify_whatsapp']) ? true : $defaultPrefs['notify_whatsapp'],
    ];

    user_store_update_fields($current_user['email'], [
        'name'        => $name ?: $current_user['name'],
        'plan'        => $plan ?: ($current_user['plan'] ?? ''),
        'goal'        => $goal,
        'preferences' => $preferences,
    ]);

    $current_user = user_store_find($current_user['email']);
    $profileFeedback = 'Informações atualizadas com sucesso.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>

<body class="area-shell">
    <?php include './partials/preloader.php'?>
    <?php include './partials/header.php'?>

    <section class="section-top text-center">
        <div class="container">
            <h1>Configurações</h1>
            <p style="max-width:680px;margin:12px auto 0;color:rgba(255,255,255,0.78);">
                Atualize dados pessoais, plano contratado e preferências de notificação.
            </p>
        </div>
    </section>

    <section class="dashboard-wrap py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-xl-3">
                    <?php $area_nav_active = 'config'; include './partials/area-nav.php'; ?>
                </div>
                <div class="col-lg-8 col-xl-9">
                    <div class="dash-card">
                        <h4>Dados pessoais</h4>
                        <?php if ($profileFeedback): ?>
                            <div class="alert alert-success"><?php echo $profileFeedback?></div>
                        <?php endif;?>
                        <form method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome completo</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($current_user['name'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="form-control">
                            </div>
                             
                            <div class="col-12">
                                <label class="form-label">Objetivo principal</label>
                                <input type="text" name="goal" value="<?php echo htmlspecialchars($current_user['goal'] ?? '', ENT_QUOTES, 'UTF-8')?>" class="form-control" placeholder="Ex: ganhar 3kg de massa magra mantendo percentual de gordura.">
                            </div>
                            <div class="col-12">
                                <label class="form-label d-block mb-2">Notificações</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notify_email" id="notify-email" <?php echo !empty($preferences['notify_email']) ? 'checked' : ''?>>
                                    <label for="notify-email" class="form-check-label">Receber alertas por e-mail</label>
                                </div>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="notify_whatsapp" id="notify-whatsapp" <?php echo !empty($preferences['notify_whatsapp']) ? 'checked' : ''?>>
                                    <label for="notify-whatsapp" class="form-check-label">Receber avisos no WhatsApp</label>
                                </div>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn_one">Salvar alterações</button>
                            </div>
                        </form>
                    </div>

                    <div class="dash-card mt-4">
                        <h4>Segurança</h4>
                        <p>E-mail logado: <strong><?php echo htmlspecialchars($current_user['email'], ENT_QUOTES, 'UTF-8')?></strong></p>
                        <p>Último acesso: <?php echo $current_user['last_login_at'] ? date('d/m/Y H:i', strtotime($current_user['last_login_at'])) : 'Primeiro acesso'?></p>
                        <a href="/area#new_password" class="btn_two">Alterar senha</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include './partials/footer.php'?>
    <?php include './partials/script.php'?>
</body>
</html>
