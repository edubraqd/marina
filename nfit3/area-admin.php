<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/user_store.php';
require_once __DIR__ . '/includes/checkin_store.php';
require_once __DIR__ . '/includes/training_store.php';
require_once __DIR__ . '/includes/onboarding_mailer.php';
require_once __DIR__ . '/includes/database.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

$title = 'Admin | Gestão completa';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
$feedback = '';
$error = '';

function admin_notify(string $to, string $subject, string $text): void
{
    $cfg = onboard_mail_config();
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $cfg['from_name'], $cfg['from']),
        sprintf('Reply-To: %s', $cfg['reply_to']),
    ]);
    @mail($to, $subject, $text, $headers);
}

function admin_training_delete(string $email): void
{
    $user = user_store_find($email);
    if (!$user || !isset($user['id'])) {
        return;
    }
    $stmt = db()->prepare('DELETE FROM training_plans WHERE user_id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->close();
}

function admin_message_save(string $email, string $subject, string $message): void
{
    $user = user_store_find($email);
    if (!$user || !isset($user['id'])) {
        return;
    }
    $stmt = db()->prepare(
        'INSERT INTO internal_messages (user_id, channel, subject, message, status, created_at) VALUES (?, "internal", ?, ?, "open", NOW())'
    );
    $stmt->bind_param('iss', $user['id'], $subject, $message);
    $stmt->execute();
    $stmt->close();
}

$users = user_store_all();
usort($users, function ($a, $b) {
    return strcmp($a['email'], $b['email']);
});
$selectedEmail = mb_strtolower(trim($_GET['user'] ?? ($users[0]['email'] ?? '')));
$selectedUser = user_store_find($selectedEmail);
if (!$selectedUser && $users) {
    $selectedUser = $users[0];
    $selectedEmail = $selectedUser['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $plan = trim($_POST['plan'] ?? '');
        $role = $_POST['role'] ?? USER_STORE_DEFAULT_ROLE;
        $sendOnboarding = !empty($_POST['send_onboarding_email']);
        $skipForms = !empty($_POST['skip_forms_create']);
        if ($email === '' || $plan === '') {
            $error = 'Informe e-mail e plano para gerar acesso.';
        } else {
            $result = user_store_provision($email, $plan, $name, $role);
            $createdUser = $result['user'] ?? null;
            if ($createdUser && isset($createdUser['id'])) {
                user_store_ensure_active_subscription((int) $createdUser['id'], $plan ?: 'essencial');
                if ($skipForms) {
                    $prefs = is_array($createdUser['preferences'] ?? null) ? $createdUser['preferences'] : [];
                    $prefs['skip_forms'] = true;
                    $prefs['initial_form_completed'] = true;
                    $prefs['initial_form_completed_at'] = date('Y-m-d H:i:s');
                    $prefs['last_update_form_at'] = date('Y-m-d H:i:s');
                    user_store_update_fields($createdUser['email'], ['preferences' => $prefs]);
                    $createdUser = user_store_find($createdUser['email']);
                }
            }
            $pass = $result['password'];
            if ($pass && $sendOnboarding) {
                send_onboarding_email($email, $name, $pass, $plan);
                send_admin_notification('Novo aluno criado manualmente', [
                    'Nome: ' . ($name ?: 'n/d'),
                    'E-mail: ' . $email,
                    'Plano: ' . $plan,
                    'Senha temporária gerada e enviada.',
                ]);
            }
            if ($pass) {
                $feedback = $sendOnboarding
                    ? 'Aluno criado com senha provisória e e-mail enviado.'
                    : 'Aluno criado com senha provisória: ' . $pass;
            } else {
                $feedback = 'Dados do aluno atualizados.';
            }
        }
    } elseif (isset($_POST['resend_onboarding'])) {
        $targetEmail = trim($_POST['resend_onboarding']);
        $u = $targetEmail ? user_store_find($targetEmail) : null;
        if (!$u) {
            $error = 'Aluno não encontrado para reenviar e-mail.';
        } else {
            $newPass = bin2hex(random_bytes(4));
            user_store_update_password($u['email'], $newPass);
            send_onboarding_email($u['email'], $u['name'] ?? '', $newPass, $u['plan'] ?? 'plano', null, true);
            send_admin_notification('Reenvio de onboarding', [
                'E-mail: ' . $u['email'],
                'Plano: ' . ($u['plan'] ?? 'n/d'),
                'Acao: reenviado manualmente',
            ]);
            $feedback = 'E-mail de onboarding reenviado com nova senha temporária.';
        }
    } elseif (isset($_POST['update_user']) && $selectedUser) {
        $name = trim($_POST['name'] ?? ($selectedUser['name'] ?? ''));
        $plan = trim($_POST['plan'] ?? ($selectedUser['plan'] ?? 'essencial'));
        $role = trim($_POST['role'] ?? ($selectedUser['role'] ?? 'student'));
        $skipForms = !empty($_POST['skip_forms_update']);
        $prefs = is_array($selectedUser['preferences'] ?? null) ? $selectedUser['preferences'] : [];
        if ($skipForms) {
            $prefs['skip_forms'] = true;
            $prefs['initial_form_completed'] = true;
            $prefs['initial_form_completed_at'] = $prefs['initial_form_completed_at'] ?? date('Y-m-d H:i:s');
            $prefs['last_update_form_at'] = $prefs['last_update_form_at'] ?? date('Y-m-d H:i:s');
        } else {
            $prefs['skip_forms'] = false;
        }
        user_store_update_fields($selectedUser['email'], [
            'name' => $name,
            'plan' => $plan,
            'role' => $role,
            'preferences' => $prefs,
        ]);
        $freshUser = user_store_find($selectedEmail);
        if ($freshUser && isset($freshUser['id'])) {
            user_store_ensure_active_subscription((int) $freshUser['id'], $plan ?: 'essencial');
        }
        $selectedUser = user_store_find($selectedEmail);
        $feedback = 'Dados do aluno atualizados.';
    } elseif (isset($_POST['delete_user']) && $selectedUser) {
        if ($selectedUser['email'] !== $current_user['email']) {
            user_store_delete($selectedUser['email']);
            $feedback = 'Aluno removido.';
            $selectedUser = null;
        } else {
            $error = 'Não é possível remover o próprio usuário.';
        }
    } elseif (isset($_POST['delete_user_row'])) {
        $targetEmail = trim($_POST['delete_user_row']);
        if ($targetEmail === $current_user['email']) {
            $error = 'Não é possível remover o próprio usuário.';
        } else {
            user_store_delete($targetEmail);
            $feedback = 'Aluno removido.';
            if ($selectedEmail === $targetEmail) {
                $selectedUser = null;
            }
        }
    } elseif (isset($_POST['save_training']) && $selectedUser) {
        $title = trim($_POST['training_title'] ?? '');
        $instructions = trim($_POST['training_instructions'] ?? '');
        $names = $_POST['exercise_name'] ?? [];
        $videos = $_POST['exercise_video'] ?? [];
        $cues = $_POST['exercise_cues'] ?? [];
        $exercises = [];
        foreach ($names as $i => $name) {
            $nameClean = trim($name);
            if ($nameClean === '') {
                continue;
            }
            $exercises[] = [
                'name' => $nameClean,
                'video_url' => trim($videos[$i] ?? ''),
                'cues' => trim($cues[$i] ?? ''),
            ];
        }
        try {
            training_store_save_for_user($selectedUser['email'], [
                'title' => $title,
                'instructions' => $instructions,
                'exercises' => $exercises,
            ]);
            admin_notify(
                $selectedUser['email'],
                'Seu treino foi atualizado',
                "Olá, atualizamos seu treino.\nAcesse a Área do Aluno para conferir os novos exercícios e orientações."
            );
            $feedback = 'Treino salvo e aluno notificado.';
        } catch (Throwable $e) {
            $error = 'Erro ao salvar treino: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_training']) && $selectedUser) {
        admin_training_delete($selectedUser['email']);
        admin_notify(
            $selectedUser['email'],
            'Seu treino foi removido',
            "Olá, removemos o treino anterior. Enviaremos um novo treino em breve."
        );
        $feedback = 'Treino removido e aluno avisado.';
    } elseif (isset($_POST['send_message']) && $selectedUser) {
        $subject = trim($_POST['msg_subject'] ?? 'Mensagem da NutremFit');
        $message = trim($_POST['msg_body'] ?? '');
        if ($message === '') {
            $error = 'Escreva uma mensagem antes de enviar.';
        } else {
            admin_message_save($selectedUser['email'], $subject, $message);
            if (!empty($_POST['notify_email'])) {
                admin_notify(
                    $selectedUser['email'],
                    $subject,
                    $message . "\n\n— Equipe NutremFit"
                );
            }
            $feedback = 'Mensagem salva ' . (!empty($_POST['notify_email']) ? 'e e-mail enviado.' : '(sem e-mail).');
        }
    }
}

// recarrega dados após ações
if ($selectedEmail) {
    $selectedUser = user_store_find($selectedEmail);
}
$recentCheckins = $selectedUser ? array_slice(array_reverse(checkin_store_for_user($selectedEmail)), 0, 5) : [];
$userTraining = $selectedUser ? training_store_find_for_user($selectedEmail) : null;

function admin_list_forms(string $email): array
{
    $dir = __DIR__ . '/storage/forms';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json') ?: [];
    $out = [];
    foreach ($files as $file) {
        $content = @file_get_contents($file);
        $json = json_decode((string) $content, true);
        $userEmail = $json['user_email'] ?? '';
        if (mb_strtolower(trim($userEmail)) !== mb_strtolower(trim($email))) {
            continue;
        }
        $out[] = [
            'path' => $file,
            'name' => basename($file),
            'submitted_at' => $json['submitted_at'] ?? null,
        ];
    }
    return $out;
}

$forms = $selectedUser ? admin_list_forms($selectedEmail) : [];

function admin_fetch_subscriptions(): array
{
    $sql = "SELECT s.id, s.user_id, s.status, s.started_at, s.expires_at, s.created_at, u.email, u.name, u.plan,
                   (SELECT provider_id FROM payment_logs WHERE subscription_id = s.id ORDER BY paid_at DESC LIMIT 1) AS last_payment_id,
                   (SELECT amount FROM payment_logs WHERE subscription_id = s.id ORDER BY paid_at DESC LIMIT 1) AS last_amount,
                   (SELECT paid_at FROM payment_logs WHERE subscription_id = s.id ORDER BY paid_at DESC LIMIT 1) AS last_paid_at
            FROM subscriptions s
            JOIN users u ON u.id = s.user_id
            ORDER BY s.created_at DESC";
    $rows = [];
    try {
        $res = db()->query($sql);
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Throwable $e) {
        $rows = [];
    }
    return $rows;
}

$subscriptions = admin_fetch_subscriptions();

function admin_revenue_series(): array
{
    $sql = "SELECT DATE(paid_at) as day, SUM(amount) as total
            FROM payment_logs
            WHERE status = 'paid'
            GROUP BY DATE(paid_at)
            ORDER BY day ASC";
    $data = [];
    try {
        $res = db()->query($sql);
        if ($res) {
            foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
                $data[] = [
                    'day' => $row['day'],
                    'total' => (float) $row['total'],
                ];
            }
        }
    } catch (Throwable $e) {
        $data = [];
    }
    return $data;
}

$revenueSeries = admin_revenue_series();

function admin_students_without_training(): array
{
    $sql = "SELECT u.id, u.name, u.email, u.plan, u.last_login_at
            FROM users u
            LEFT JOIN training_plans t ON t.user_id = u.id
            WHERE u.role = 'student' AND t.id IS NULL
            ORDER BY u.name ASC";
    $rows = [];
    try {
        $res = db()->query($sql);
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Throwable $e) {
        $rows = [];
    }
    return $rows;
}

function admin_open_messages(): array
{
    $sql = "SELECT m.id, m.subject, m.message, m.created_at, u.email, u.name
            FROM internal_messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.status = 'open'
            ORDER BY m.created_at DESC";
    $rows = [];
    try {
        $res = db()->query($sql);
        if ($res) {
            $rows = $res->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Throwable $e) {
        $rows = [];
    }
    return $rows;
}

$studentsWithoutTraining = admin_students_without_training();
$openMessages = admin_open_messages();

// métricas
$totalUsers = count($users);
$totalAdmins = count(array_filter($users, function ($u) {
    return ($u['role'] ?? 'student') === 'admin';
}));
$totalAlunos = $totalUsers - $totalAdmins;
$activeSubs = 0;
foreach ($users as $u) {
    $uid = (int) ($u['id'] ?? 0);
    if ($uid > 0 && user_store_has_active_subscription($uid)) {
        $activeSubs++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>
$totalUsers = count($users);
$totalAdmins = count(array_filter($users, function ($u) {
return ($u['role'] ?? 'student') === 'admin';
}));
$totalAlunos = $totalUsers - $totalAdmins;
$activeSubs = 0;
foreach ($users as $u) {
$uid = (int) ($u['id'] ?? 0);
if ($uid > 0 && user_store_has_active_subscription($uid)) {
$activeSubs++;
}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>

<body class="area-shell">
    <?php include './partials/preloader.php' ?>
    <?php include './partials/header.php' ?>

    <style>
        /* Premium Dashboard CSS */
        .admin-tabs {
            border-bottom: 2px solid rgba(255, 122, 0, 0.15);
            margin-bottom: 24px;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            padding-bottom: 8px;
        }

        .admin-tab {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 6px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 13px;
        }

        .admin-tab:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }

        .admin-tab.active {
            color: #ff7a00;
            background: rgba(255, 122, 0, 0.1);
        }

        .tab-content-panel {
            display: none;
            animation: fadeIn 0.4s ease forwards;
        }

        .tab-content-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Metric Cards */
        .metric-card {
            background: linear-gradient(145deg, rgba(20, 24, 36, 0.9) 0%, rgba(12, 16, 29, 0.9) 100%);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 24px;
            text-align: left;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 122, 0, 0.3);
        }

        .metric-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 32px;
            opacity: 0.15;
            color: #ff7a00;
        }

        .metric-title {
            color: rgba(255, 255, 255, 0.5);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 36px;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            line-height: 1;
        }

        /* Better Tables */
        .table-premium {
            --bs-table-bg: transparent;
            --bs-table-color: rgba(255, 255, 255, 0.85);
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table-premium th {
            border: none;
            background: rgba(255, 255, 255, 0.02);
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.1em;
            padding: 12px 16px;
        }

        .table-premium td {
            background: rgba(20, 24, 36, 0.6);
            border-top: 1px solid rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            padding: 16px;
            vertical-align: middle;
            transition: background 0.2s ease;
        }

        .table-premium tr:hover td {
            background: rgba(255, 122, 0, 0.05);
        }

        .table-premium td:first-child {
            border-left: 1px solid rgba(255, 255, 255, 0.03);
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        .table-premium td:last-child {
            border-right: 1px solid rgba(255, 255, 255, 0.03);
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(0, 255, 153, 0.1);
            color: #00ff99;
            border: 1px solid rgba(0, 255, 153, 0.3);
        }

        .status-inactive {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>

    <section class="section-top text-center" style="padding-bottom: 2rem;">
        <div class="container">
            <h1>Painel do Administrador</h1>
            <p style="max-width:760px;margin:12px auto 0;color:rgba(255,255,255,0.5); letter-spacing: 0.5px;">
                Visão unificada: Gestão estratégica, alunos e acompanhamento financeiro.
            </p>
        </div>
    </section>

    <section class="dashboard-wrap py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-xl-3">
                    <?php $area_nav_active = 'admin';
                    include './partials/area-nav.php'; ?>
                </div>
                <div class="col-lg-8 col-xl-9">
                    <div class="admin-tabs" id="adminTabs">
                        <button class="admin-tab active" data-target="tab-overview">Visão Geral</button>
                        <button class="admin-tab" data-target="tab-students">Alunos & Fichas</button>
                        <button class="admin-tab" data-target="tab-finance">Financeiro</button>
                    </div>

                    <?php if ($feedback): ?>
                        <div class="alert alert-success mt-3"
                            style="background: rgba(0,255,153,0.1); border-color:#00ff99; color:#00ff99; border-radius:8px;">
                            <?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger mt-3"
                            style="background: rgba(255,68,68,0.1); border-color:#ff4444; color:#ff4444; border-radius:8px;">
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <!-- TAB 1: VISÃO GERAL -->
                    <div id="tab-overview" class="tab-content-panel active">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="metric-card">
                                    <i class="ti-user metric-icon"></i>
                                    <div class="metric-title">Acessos Totais</div>
                                    <div class="metric-value"><?php echo $totalUsers; ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="metric-card">
                                    <i class="ti-bar-chart metric-icon" style="color:#00ff99;"></i>
                                    <div class="metric-title">Alunos Ativos</div>
                                    <div class="metric-value" style="color:#00ff99;"><?php echo $activeSubs; ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="metric-card">
                                    <i class="ti-clipboard metric-icon"></i>
                                    <div class="metric-title">Formulários</div>
                                    <div class="metric-value">
                                        <?php echo count(glob(__DIR__ . '/storage/forms/*.json') ?: []); ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="metric-card">
                                    <i class="ti-shield metric-icon"></i>
                                    <div class="metric-title">Admins</div>
                                    <div class="metric-value"><?php echo $totalAdmins; ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="dash-card mb-4"
                            style="border:1px solid rgba(255,255,255,0.04); background:rgba(20,24,36,0.5);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h5 class="mb-1" style="font-weight:700;">Desempenho Financeiro</h5>
                                    <small style="color:rgba(255,255,255,0.5);">Valores aprovados nos últimos
                                        dias</small>
                                </div>
                            </div>
                            <?php if (!$revenueSeries): ?>
                                    <p class="mb-0 text-muted">Ainda não há pagamentos registrados para gerar o gráfico.</p>
                            <?php else: ?>
                                    <canvas id="revenueChart" height="90"></canvas>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-lg-6">
                                <div class="dash-card h-100">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <h5 class="mb-0">Atenção: Sem treino</h5>
                                        <a class="btn_one btn-sm" href="/area-admin-treinos"
                                            style="font-size:11px; padding:6px 12px;">Resolver</a>
                                    </div>
                                    <?php if (!$studentsWithoutTraining): ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="ti-check-box mb-2"
                                                    style="font-size:24px; color:#00ff99; opacity:0.5;"></i>
                                                <p class="mb-0 small">Todos os alunos possuem treinos.</p>
                                            </div>
                                    <?php else: ?>
                                            <ul class="list-group list-group-flush" style="border-radius:8px; overflow:hidden;">
                                                <?php foreach ($studentsWithoutTraining as $st): ?>
                                                        <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center"
                                                            style="border-color:rgba(255,255,255,0.05); padding:12px 16px;">
                                                            <div>
                                                                <div style="font-weight:600; font-size:14px;">
                                                                    <?php echo htmlspecialchars($st['name'] ?: $st['email'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </div>
                                                                <small
                                                                    style="color:rgba(255,122,0,0.8); text-transform:uppercase; font-size:10px; font-weight:700;"><?php echo htmlspecialchars($st['plan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></small>
                                                            </div>
                                                            <a class="btn_two btn-sm"
                                                                href="/area-admin-treinos?user=<?php echo urlencode($st['email']); ?>"
                                                                style="padding:4px 10px; font-size:11px;">Criar Ficha</a>
                                                        </li>
                                                <?php endforeach; ?>
                                            </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="dash-card h-100">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                        <h5 class="mb-0">Inbox: Mensagens</h5>
                                        <small style="color:rgba(255,255,255,0.5);">Caixa de entrada</small>
                                    </div>
                                    <?php if (!$openMessages): ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="ti-email mb-2" style="font-size:24px; opacity:0.3;"></i>
                                                <p class="mb-0 small">Caixa de entrada limpa.</p>
                                            </div>
                                    <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-premium align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Aluno</th>
                                                            <th>Assunto</th>
                                                            <th>Data</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach (array_slice($openMessages, 0, 6) as $msg): ?>
                                                                <tr>
                                                                    <td style="font-weight:600; font-size:13px;">
                                                                        <?php echo htmlspecialchars($msg['name'] ?: explode('@', $msg['email'])[0], ENT_QUOTES, 'UTF-8'); ?>
                                                                    </td>
                                                                    <td style="font-size:13px; color:rgba(255,255,255,0.7);">
                                                                        <?php echo htmlspecialchars($msg['subject'] ?: 'Sem assunto', ENT_QUOTES, 'UTF-8'); ?>
                                                                    </td>
                                                                    <td style="font-size:12px; color:rgba(255,255,255,0.4);">
                                                                        <?php echo $msg['created_at'] ? date('d/m H:i', strtotime($msg['created_at'])) : '-'; ?>
                                                                    </td>
                                                                </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div><!-- /TAB 1 -->

                    <!-- TAB 2: ALUNOS -->
                    <div id="tab-students" class="tab-content-panel">
                        <div class="dash-card mb-4" id="form-create-student"
                            style="display:none; border:1px solid var(--nf-accent);">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Adicionar Aluno Manualmente</h4>
                                <button type="button" class="btn text-white"
                                    onclick="document.getElementById('form-create-student').style.display='none'"><i
                                        class="ti-close"></i></button>
                            </div>
                            <form method="post" class="row g-2">
                                <input type="hidden" name="create_user" value="1">
                                <div class="col-md-4">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="name" class="form-control" placeholder="Nome do aluno">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">E-mail</label>
                                    <input type="email" name="email" class="form-control" placeholder="aluno@email.com"
                                        required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Plano</label>
                                    <select name="plan" class="form-control" required>
                                        <option value="gratuito">Gratuito</option>
                                        <option value="essencial">Essencial</option>
                                        <option value="performance">Performance</option>
                                        <option value="vip">Vip</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Função</label>
                                    <select name="role" class="form-control">
                                        <option value="student" selected>Aluno</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mt-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="send_onboarding_email"
                                            value="1" id="sendOnboardingCreate" checked>
                                        <label class="form-check-label" style="font-size:13px;"
                                            for="sendOnboardingCreate">Enviar e-mail de boas-vindas com senha</label>
                                    </div>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="skip_forms_create"
                                            value="1" id="skipFormsCreate" checked>
                                        <label class="form-check-label" style="font-size:13px;"
                                            for="skipFormsCreate">Pular anamnese (liberar login direto)</label>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-3 d-flex align-items-end">
                                    <button type="submit" class="btn_one w-100">Gerar acesso</button>
                                </div>
                            </form>
                        </div>

                        <div class="dash-card mb-4 pb-0"
                            style="border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom:1px solid rgba(255,255,255,0.05);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h4 class="mb-0">Gestão de Alunos</h4>
                                    <small class="text-muted">Selecione para ver a ficha completa</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn_two" style="padding:8px 16px; font-size:12px;"
                                        onclick="document.getElementById('form-create-student').style.display='block'; window.scrollTo(0,0);">
                                        <i class="ti-plus"></i> Novo Aluno
                                    </button>
                                    <form method="get" class="d-flex gap-2">
                                        <select name="user" class="form-control"
                                            style="min-width:200px; background:rgba(0,0,0,0.3);">
                                            <?php foreach ($users as $u): ?>
                                                    <option
                                                        value="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        <?php echo $selectedEmail === $u['email'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($u['name'] ?: $u['email'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn_one" type="submit"
                                            style="padding:8px 16px; font-size:12px;">Visualizar Ficha</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-5">
                            <table class="table table-premium align-middle mb-0" style="margin-top:-8px;">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Status / Plano</th>
                                        <th>E-mail</th>
                                        <th style="text-align:right;">Gerenciar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u):
                                        $uid = (int) ($u['id'] ?? 0);
                                        $isActive = $uid > 0 && user_store_has_active_subscription($uid);
                                        ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:700; font-size:14px; color:#fff;">
                                                        <?php echo htmlspecialchars($u['name'] ?: 'Sem Nome', ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                    <?php if ($u['role'] === 'admin'): ?><span class="badge"
                                                                style="background:var(--nf-accent); font-size:10px;">ADMIN</span><?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>"
                                                        style="margin-bottom:4px;">
                                                        <?php echo $isActive ? '<i class="ti-check"></i> Ativo' : 'Inativo'; ?>
                                                    </div>
                                                    <div
                                                        style="font-size:11px; color:rgba(255,255,255,0.4); text-transform:uppercase; font-weight:700; letter-spacing:0.05em;">
                                                        <?php echo htmlspecialchars($u['plan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                </td>
                                                <td style="font-size:13px; color:rgba(255,255,255,0.7);">
                                                    <?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td style="text-align:right;">
                                                    <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                                        <form method="post" class="d-inline" title="Reenviar Senha">
                                                            <input type="hidden" name="resend_onboarding"
                                                                value="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button type="submit" class="btn"
                                                                style="background:transparent; border:1px solid rgba(255,255,255,0.1); color:#fff; padding:6px 10px; border-radius:4px;"><i
                                                                    class="ti-email"></i></button>
                                                        </form>
                                                        <a class="btn_one"
                                                            style="padding:6px 16px; font-size:12px; font-weight:700;"
                                                            href="/area-admin?user=<?php echo urlencode($u['email']); ?>#ficha-aluno">Ficha
                                                            Técnica</a>
                                                        <?php if ($u['email'] !== $current_user['email']): ?>
                                                                <form method="post" class="d-inline"
                                                                    onsubmit="return confirm('Deseja realmente remover o acesso deste aluno? Esta ação é irreversível.');">
                                                                    <input type="hidden" name="delete_user_row"
                                                                        value="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <button type="submit" class="btn"
                                                                        style="background:transparent; border:1px solid rgba(255,68,68,0.3); color:#ff4444; padding:6px 10px; border-radius:4px;"><i
                                                                            class="ti-trash"></i></button>
                                                                </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sessão Detalhes do Aluno Selecionado -->
                        <div id="ficha-aluno"></div> <!-- Anchor -->

                        <?php if ($selectedUser): ?>
                                <div class="dash-card mb-4">
                                    <h4>Ficha do aluno</h4>
                                    <form method="post" class="row g-3">
                                        <input type="hidden" name="update_user" value="1">
                                        <?php $skipFormsFlag = !empty(($selectedUser['preferences']['skip_forms'] ?? null)); ?>
                                        <div class="col-md-4">
                                            <label class="form-label">Nome</label>
                                            <input type="text" name="name" class="form-control"
                                                value="<?php echo htmlspecialchars($selectedUser['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">E-mail</label>
                                            <input type="email" class="form-control"
                                                value="<?php echo htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Plano</label>
                                            <select name="plan" class="form-control">
                                                <?php foreach (['gratuito', 'essencial', 'performance', 'vip'] as $plan): ?>
                                                        <option value="<?php echo $plan; ?>" <?php echo ($selectedUser['plan'] ?? '') === $plan ? 'selected' : ''; ?>><?php echo ucfirst($plan); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Função</label>
                                            <select name="role" class="form-control">
                                                <option value="student" <?php echo ($selectedUser['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Aluno</option>
                                                <option value="admin" <?php echo ($selectedUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" name="skip_forms_update"
                                                    value="1" id="skipFormsUpdate" <?php echo $skipFormsFlag ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="skipFormsUpdate">Dispensar formulário
                                                    inicial e de atualização para este aluno</label>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <span class="plan-badge">Formulário inicial:
                                                <?php echo !empty(($selectedUser['preferences']['initial_form_completed'] ?? null)) ? 'enviado' : 'pendente'; ?></span>
                                            <span class="plan-badge">Último login:
                                                <?php echo $selectedUser['last_login_at'] ? date('d/m/Y H:i', strtotime($selectedUser['last_login_at'])) : 'Nunca'; ?></span>
                                        </div>
                                        <div class="col-12 d-flex justify-content-between gap-2">
                                            <div></div>
                                            <div class="d-flex gap-2">
                                                <?php if ($selectedUser['email'] !== $current_user['email']): ?>
                                                        <button type="submit" name="delete_user" value="1" class="btn_two"
                                                            onclick="return confirm('Remover este aluno?')">Remover</button>
                                                <?php endif; ?>
                                                <button type="submit" class="btn_one">Salvar ficha</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="dash-card h-100">
                                            <h5>Treino do aluno</h5>
                                            <form method="post" class="mb-3">
                                                <input type="hidden" name="save_training" value="1">
                                                <div class="mb-2">
                                                    <label class="form-label">Título</label>
                                                    <input type="text" name="training_title" class="form-control"
                                                        value="<?php echo htmlspecialchars($userTraining['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="Treino semanal">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Orientações gerais</label>
                                                    <textarea name="training_instructions" class="form-control" rows="3"
                                                        placeholder="Carga, divisão, cadência..."><?php echo htmlspecialchars($userTraining['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Exercícios</label>
                                                    <?php
                                                    $exercises = $userTraining['exercises'] ?? [];
                                                    for ($i = 0; $i < max(3, count($exercises)); $i++):
                                                        $ex = $exercises[$i] ?? ['name' => '', 'video_url' => '', 'cues' => ''];
                                                        ?>
                                                            <div class="border rounded p-2 mb-2">
                                                                <input type="text" name="exercise_name[]" class="form-control mb-1"
                                                                    placeholder="Ex: Agachamento livre"
                                                                    value="<?php echo htmlspecialchars($ex['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <input type="text" name="exercise_video[]" class="form-control mb-1"
                                                                    placeholder="Link do vídeo (opcional)"
                                                                    value="<?php echo htmlspecialchars($ex['video_url'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <textarea name="exercise_cues[]" class="form-control" rows="2"
                                                                    placeholder="Séries, reps, carga alvo, dicas."><?php echo htmlspecialchars($ex['cues'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                            </div>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <button type="submit" class="btn_one">Salvar e notificar</button>
                                                    <?php if ($userTraining): ?>
                                                            <button type="submit" name="delete_training" value="1" class="btn_two"
                                                                onclick="return confirm('Remover treino deste aluno?')">Remover
                                                                treino</button>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="dash-card h-100">
                                            <h5>Mensagens para o aluno</h5>
                                            <form method="post">
                                                <input type="hidden" name="send_message" value="1">
                                                <div class="mb-2">
                                                    <label class="form-label">Assunto</label>
                                                    <input type="text" name="msg_subject" class="form-control"
                                                        placeholder="Atualização do seu plano">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Mensagem</label>
                                                    <textarea name="msg_body" class="form-control" rows="4"
                                                        placeholder="Escreva a mensagem para o aluno."></textarea>
                                                    <p class="form-helper">Será salva no histórico e pode ser enviada por
                                                        e-mail.</p>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="notify_email"
                                                        value="1" id="notifyEmail">
                                                    <label class="form-check-label" for="notifyEmail">Enviar e-mail para o
                                                        aluno</label>
                                                </div>
                                                <div class="text-end">
                                                    <button type="submit" class="btn_one">Enviar mensagem</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-3">
                                    <div class="col-lg-6">
                                        <div class="dash-card h-100">
                                            <h5>Últimos check-ins</h5>
                                            <?php if (!$recentCheckins): ?>
                                                    <p class="mb-0">Nenhum check-in deste aluno.</p>
                                            <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-dark table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Data</th>
                                                                    <th>Energia</th>
                                                                    <th>Rotina</th>
                                                                    <th>Notas</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($recentCheckins as $entry): ?>
                                                                        <tr>
                                                                            <td><?php echo date('d/m/Y H:i', strtotime($entry['submitted_at'])); ?>
                                                                            </td>
                                                                            <td><?php echo $entry['energy']; ?></td>
                                                                            <td><?php echo $entry['routine']; ?>%</td>
                                                                            <td><?php echo htmlspecialchars($entry['notes'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                                                            </td>
                                                                        </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="dash-card h-100">
                                            <h5>Formulários enviados</h5>
                                            <?php if (!$forms): ?>
                                                    <p class="mb-0">Nenhum formulário salvo para este aluno.</p>
                                            <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-dark table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Arquivo</th>
                                                                    <th>Data</th>
                                                                    <th></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($forms as $form): ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($form['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                                            </td>
                                                                            <td><?php echo $form['submitted_at'] ? date('d/m/Y H:i', strtotime($form['submitted_at'])) : '-'; ?>
                                                                            </td>
                                                                            <td class="text-end">
                                                                                <a class="btn_one btn-sm"
                                                                                    href="<?php echo 'storage/forms/' . urlencode(basename($form['path'])); ?>"
                                                                                    download>Baixar</a>
                                                                            </td>
                                                                        </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                            <?php endif; ?>
                                </div>
                        <?php endif; ?>
                    </div> <!-- /TAB 2 (ALUNOS) -->

                    <!-- TAB 3: FINANCEIRO -->
                    <div id="tab-finance" class="tab-content-panel">
                        <div class="dash-card mb-4" style="border:1px solid rgba(255,255,255,0.04); background:rgba(20,24,36,0.5);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h4 class="mb-0">Histórico de Assinaturas</h4>
                                    <small class="text-muted">Acompanhamento contábil dos alunos</small>
                                </div>
                            </div>
                            <?php if (!$subscriptions): ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="ti-wallet mb-2" style="font-size:32px; opacity:0.3;"></i>
                                        <p class="mb-0">Nenhuma transação financeira registrada.</p>
                                    </div>
                            <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-premium align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Aluno</th>
                                                    <th>Plano</th>
                                                    <th>Status</th>
                                                    <th>Ciclo (Início - Fim)</th>
                                                    <th>Últ. Pagamento</th>
                                                    <th style="text-align:right;">Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subscriptions as $sub): ?>
                                                        <tr>
                                                            <td style="font-weight:600; font-size:13px; color:#fff;">
                                                                <?php echo htmlspecialchars($sub['name'] ?: explode('@', $sub['email'])[0], ENT_QUOTES, 'UTF-8'); ?>
                                                            </td>
                                                            <td style="font-size:11px; color:rgba(255,122,0,0.8); text-transform:uppercase; font-weight:700; letter-spacing:0.05em;">
                                                                <?php echo htmlspecialchars($sub['plan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                                            </td>
                                                            <td>
                                                                <span class="status-badge <?php echo (($sub['status'] ?? '') === 'paid' || ($sub['status'] ?? '') === 'active') ? 'status-active' : 'status-inactive'; ?>" style="font-size:10px;">
                                                                    <?php echo htmlspecialchars($sub['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            </td>
                                                            <td style="font-size:12px; color:rgba(255,255,255,0.5);">
                                                                <?php echo $sub['started_at'] ? date('d/m/y', strtotime($sub['started_at'])) : '-'; ?> &rarr; 
                                                                <?php echo $sub['expires_at'] ? date('d/m/y', strtotime($sub['expires_at'])) : '-'; ?>
                                                            </td>
                                                            <td style="font-size:12px; color:rgba(255,255,255,0.7);">
                                                                <?php echo $sub['last_paid_at'] ? date('d/m/y H:i', strtotime($sub['last_paid_at'])) : '-'; ?>
                                                            </td>
                                                            <td style="text-align:right; font-weight:700; color:#00ff99;">
                                                                <?php echo $sub['last_amount'] ? 'R$ ' . number_format((float) $sub['last_amount'], 2, ',', '.') : '-'; ?>
                                                            </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                            <?php endif; ?>
                        </div>
                    </div> <!-- /TAB 3 (FINANCEIRO) -->
                </div>
            </div>
    </section>

    <?php include './partials/footer.php' ?>
    <?php include './partials/script.php' ?>
    <?php if ($revenueSeries): ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                (function () {
                    const ctx = document.getElementById('revenueChart');
                    if (!ctx) return;
                    const labels = <?php echo json_encode(array_map(function ($r) {
                        return date('d/m', strtotime($r['day']));
                    }, $revenueSeries)); ?>;
                    const data = <?php echo json_encode(array_map(function ($r) {
                        return round($r['total'], 2);
                    }, $revenueSeries)); ?>;
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Receita (R$)',
                                data: data,
                                fill: true,
                                borderColor: '#ff7a00',
                                backgroundColor: 'rgba(255,122,0,0.15)',
                                tension: 0.35,
                                borderWidth: 3,
                                pointRadius: 4,
                                pointBackgroundColor: '#ff7a00',
                                pointBorderColor: '#0b0f17'
                            }]
                        },
                        options: {
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) { return 'R$ ' + ctx.parsed.y.toFixed(2).replace('.', ','); }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: { color: 'rgba(255,255,255,0.75)' },
                                    grid: { color: 'rgba(255,255,255,0.08)' }
                                },
                                y: {
                                    ticks: {
                                        color: 'rgba(255,255,255,0.75)',
                                        callback: function (value) { return 'R$ ' + value; }
                                    },
                                    grid: { color: 'rgba(255,255,255,0.08)' }
                                }
                            }
                        }
                    });
                })();
                        }
                    });
                })();
            </script>
    <?php endif; ?>

    <script>
        // Tab Navigation Logic
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.admin-tab');
            const panels = document.querySelectorAll('.tab-content-panel');
            
            // Check URL parameters to auto-open the "Alunos" tab if editing a user
            const urlParams = new URLSearchParams(window.location.search);
            const userFilter = urlParams.get('user');
            
            function switchTab(targetId) {
                tabs.forEach(t => t.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));
                
                const activeTabBtn = document.querySelector(`.admin-tab[data-target="${targetId}"]`);
                const activePanel = document.getElementById(targetId);
                
                if (activeTabBtn && activePanel) {
                    activeTabBtn.classList.add('active');
                    activePanel.classList.add('active');
                }
            }

            // Bind clicks
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    switchTab(tab.getAttribute('data-target'));
                });
            });

            // Auto-switch on load if modifying a student
            if (userFilter) {
                switchTab('tab-students');
                // Allow some time for rendering before scrolling
                setTimeout(() => {
                    const fichaElement = document.getElementById('ficha-aluno');
                    if(fichaElement) {
                        fichaElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 150);
            }
        });
    </script>
</body>

</html>