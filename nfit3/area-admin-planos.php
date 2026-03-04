<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/database.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

$title = 'Admin | Planos (valores)';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
$feedback = '';
$error = '';

function admin_plans_load(): array
{
    $rows = [];
    try {
        $res = db()->query('SELECT id, slug, name, description, price_month, billing_cycle, is_active FROM plans ORDER BY id ASC');
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
    } catch (Throwable $e) {
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_plan'])) {
        $id = (int) ($_POST['plan_id'] ?? 0);
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float) str_replace(',', '.', (string) ($_POST['price_month'] ?? '0'));
        $cycle = trim($_POST['billing_cycle'] ?? 'monthly');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($id <= 0 || $slug === '' || $name === '') {
            $error = 'Preencha slug e nome.';
        } else {
            try {
                $stmt = db()->prepare('UPDATE plans SET slug=?, name=?, description=?, price_month=?, billing_cycle=?, is_active=? WHERE id=?');
                $stmt->bind_param('sssdsii', $slug, $name, $desc, $price, $cycle, $isActive, $id);
                $stmt->execute();
                $stmt->close();
                $feedback = 'Plano atualizado.';
            } catch (Throwable $e) {
                $error = 'Erro ao salvar plano: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_plan'])) {
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float) str_replace(',', '.', (string) ($_POST['price_month'] ?? '0'));
        $cycle = trim($_POST['billing_cycle'] ?? 'monthly');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($slug === '' || $name === '') {
            $error = 'Informe slug e nome para criar o plano.';
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO plans (slug, name, description, price_month, billing_cycle, is_active, created_at) VALUES (?,?,?,?,?,?,NOW())');
                $stmt->bind_param('sssdsi', $slug, $name, $desc, $price, $cycle, $isActive);
            } catch (Throwable $e) {
                $stmt = null;
            }
            if ($stmt) {
                try {
                    $stmt->execute();
                    $stmt->close();
                    $feedback = 'Plano criado.';
                } catch (Throwable $e) {
                    $error = 'Erro ao criar plano: ' . $e->getMessage();
                }
            } else {
                $error = 'Erro ao preparar inserção do plano.';
            }
        }
    }
}

$plans = admin_plans_load();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>
<body class="area-shell">
    <?php include './partials/preloader.php'?>
    <?php include './partials/header.php'?>

    <section class="section-top text-center">
        <div class="container">
            <h1>Planos & valores</h1>
            <p style="max-width:760px;margin:12px auto 0;color:rgba(255,255,255,0.78);">
                Ajuste os preços usados no checkout. Alterações valem para novos pagamentos imediatamente.
            </p>
        </div>
    </section>

    <section class="dashboard-wrap py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-xl-3">
                    <?php $area_nav_active = 'admin_planos'; include './partials/area-nav.php'; ?>
                </div>
                <div class="col-lg-8 col-xl-9">
                    <div class="dash-card mb-3">
                        <h4>Planos cadastrados</h4>
                        <?php if ($feedback): ?><div class="alert alert-success mt-2"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');?></div><?php endif;?>
                        <?php if ($error): ?><div class="alert alert-danger mt-2"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');?></div><?php endif;?>
                        <?php if (!$plans): ?>
                            <p class="mb-0">Nenhum plano encontrado.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Slug</th>
                                            <th>Nome</th>
                                            <th>Preço (R$)</th>
                                            <th>Ciclo</th>
                                            <th>Ativo</th>
                                            <th style="width:120px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($plans as $plan): ?>
                                            <tr>
                                                <form method="post" class="row g-2 align-items-center">
                                                    <input type="hidden" name="plan_id" value="<?php echo (int) $plan['id'];?>">
                                                    <td><input type="text" name="slug" class="form-control form-control-sm" value="<?php echo htmlspecialchars($plan['slug'], ENT_QUOTES, 'UTF-8');?>"></td>
                                                    <td><input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8');?>"></td>
                                                    <td><input type="number" step="0.01" min="0" name="price_month" class="form-control form-control-sm" value="<?php echo htmlspecialchars(number_format((float)$plan['price_month'], 2, '.', ''), ENT_QUOTES, 'UTF-8');?>"></td>
                                                    <td>
                                                        <select name="billing_cycle" class="form-control form-control-sm">
                                                        <?php foreach (['oneoff'=>'Teste único','monthly'=>'Mensal','quarterly'=>'Trimestral','semiannual'=>'Semestral','yearly'=>'Anual'] as $val => $label): ?>
                                                            <option value="<?php echo $val;?>" <?php echo ($plan['billing_cycle'] ?? '') === $val ? 'selected' : '';?>><?php echo $label;?></option>
                                                        <?php endforeach;?>
                                                    </select>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="is_active" value="1" <?php echo !empty($plan['is_active']) ? 'checked' : '';?>>
                                                    </td>
                                                    <td class="text-end">
                                                        <button type="submit" name="update_plan" value="1" class="btn_one btn-sm w-100">Salvar</button>
                                                    </td>
                                                    <tr>
                                                        <td colspan="6">
                                                            <input type="text" name="description" class="form-control form-control-sm" placeholder="Descrição (opcional)" value="<?php echo htmlspecialchars($plan['description'] ?? '', ENT_QUOTES, 'UTF-8');?>">
                                                        </td>
                                                    </tr>
                                                </form>
                                            </tr>
                                        <?php endforeach;?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif;?>
                    </div>

                    <div class="dash-card">
                        <h4>Adicionar novo plano</h4>
                        <form method="post" class="row g-2">
                            <input type="hidden" name="add_plan" value="1">
                            <div class="col-md-3">
                                <label class="form-label">Slug</label>
                                <input type="text" name="slug" class="form-control" placeholder="ex: essencial" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" placeholder="Essencial" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Preço (R$)</label>
                                <input type="number" step="0.01" min="0" name="price_month" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ciclo</label>
                                <select name="billing_cycle" class="form-control">
                                    <option value="monthly">Mensal</option>
                                    <option value="oneoff">Único</option>
                                    <option value="quarterly">Trimestral</option>
                                    <option value="semiannual">Semestral</option>
                                    <option value="yearly">Anual</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                    <label class="form-check-label">Ativo</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição (opcional)</label>
                                <input type="text" name="description" class="form-control" placeholder="Breve descrição do plano">
                            </div>
                            <div class="col-12 text-end mt-2">
                                <button type="submit" class="btn_one">Criar plano</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include './partials/footer.php'?>
    <?php include './partials/script.php'?>
</body>
</html>
