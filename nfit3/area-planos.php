<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/database.php';

$current_user = area_guard_require_login();
$title = 'Área do Aluno | Planos & arquivos';
$css = '<link rel="stylesheet" href="assets/css/area.css">';

function plan_files_get_latest_for_user(string $email): ?array
{
    $user = user_store_find($email);
    if (!$user || !isset($user['id'])) {
        return null;
    }
    $row = null;
    try {
        $stmt = db()->prepare('SELECT id, filename, created_at FROM plan_files WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    } catch (Throwable $e) {
        $row = null;
    }
    return $row ?: null;
}

$latestPlan = plan_files_get_latest_for_user($current_user['email']);


?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>

<body class="area-shell">
    <?php include './partials/preloader.php'?>
    <?php include './partials/header.php'?>

    <section class="section-top text-center">
        <div class="container">
            <div class="col-lg-10 offset-lg-1">
                <div class="section-top-title">
                    <h1>Planos & arquivos</h1>
                    <p style="max-width:640px;margin:12px auto 0;color:rgba(255,255,255,0.78);">
                        Tudo o que você precisa para seguir firme: downloads organizados por categorias e histórico para consultas rápidas.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-wrap py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-xl-3">
                    <?php $area_nav_active = 'planos'; include './partials/area-nav.php'; ?>
                </div>
                <div class="col-lg-8 col-xl-9">
                    <?php if ($latestPlan): ?>
                        <div class="dash-card mb-4" style="border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h4 class="mb-1">Plano alimentar (PDF privado)</h4>
                                    <small style="color:rgba(255,255,255,0.7);">Disponível apenas para sua conta.</small>
                                    <p class="mb-0" style="color:rgba(255,255,255,0.8);">Arquivo: <?php echo htmlspecialchars($latestPlan['filename'] ?? 'plano.pdf', ENT_QUOTES, 'UTF-8');?></p>
                                    <?php if (!empty($latestPlan['created_at'])): ?>
                                        <small style="color:rgba(255,255,255,0.6);">Enviado em <?php echo date('d/m/Y H:i', strtotime($latestPlan['created_at']));?></small>
                                    <?php endif;?>
                                </div>
                                <a class="btn_one" href="<?php echo '/plan-download?id=' . urlencode($latestPlan['id']);?>"><i class="ti-download"></i> Baixar PDF</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($materials as $group): ?>
                        <div class="dash-card mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <h4><?php echo $group['category']?></h4>
                            </div>
                            <div class="mt-3">
                                <?php foreach ($group['items'] as $item): ?>
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between p-3 mb-3" style="background:rgba(255,255,255,0.02); border-radius:18px;">
                                        <div>
                                            <p class="mb-1" style="color:#fff;font-weight:600;"><?php echo $item['title']?></p>
                                            <p class="mb-0" style="color:rgba(255,255,255,0.7);font-size:14px;"><?php echo $item['desc']?></p>
                                            <small style="color:#ffb37f;"><?php echo $item['tag']?></small>
                                        </div>
                                        <a href="<?php echo $item['file']?>" class="btn_two mt-3 mt-md-0" target="_blank" rel="noopener noreferrer">
                                            <i class="ti-download"></i> Baixar
                                        </a>
                                    </div>
                                <?php endforeach;?>
                            </div>
                        </div>
                    <?php endforeach;?>

                     
                </div>
            </div>
        </div>
    </section>

    <?php include './partials/footer.php'?>
    <?php include './partials/script.php'?>
</body>
</html>
