<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/database.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

$title = 'Admin | Biblioteca de links';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
$feedback = '';
$error = '';
$scrollTargetId = '';
$types = [
    'geral'       => 'Geral',
    'ombro'       => 'Ombro',
    'trapezio'    => 'Trapézio',
    'core'        => 'Core/Mobilidade',
    'cardio'      => 'Cardio',
    'mobilidade'  => 'Mobilidade',
    'peito'       => 'Peito',
    'costas'      => 'Costas',
    'biceps'      => 'Bíceps',
    'triceps'     => 'Tríceps',
    'pernas'      => 'Pernas',
    'gluteos'     => 'Glúteos',
];

/**
 * CRUD simples na tabela exercicios (nome_exercicio, link, grupo_muscular)
 */
function ex_store_all(): array
{
    $rows = [];
    try {
        $res = db()->query('SELECT id, nome_exercicio, link, grupo_muscular FROM exercicios ORDER BY nome_exercicio ASC');
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $res->free();
        }
    } catch (Throwable $e) {
    }
    return $rows;
}

function ex_store_add(string $name, string $link, string $type): void
{
    try {
        $stmt = db()->prepare('INSERT INTO exercicios (nome_exercicio, link, grupo_muscular) VALUES (?,?,?)');
        $stmt->bind_param('sss', $name, $link, $type);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
    }
}

function ex_store_update(int $id, string $name, string $link, string $type): void
{
    try {
        $stmt = db()->prepare('UPDATE exercicios SET nome_exercicio = ?, link = ?, grupo_muscular = ? WHERE id = ?');
        $stmt->bind_param('sssi', $name, $link, $type, $id);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
    }
}

function ex_store_delete(int $id): void
{
    try {
        $stmt = db()->prepare('DELETE FROM exercicios WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_link'])) {
        $name = trim($_POST['name'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $type = trim($_POST['type'] ?? 'geral');
        if ($name === '' || $link === '') {
            $error = 'Informe nome e link para adicionar.';
        } else {
            ex_store_add($name, $link, $type);
            $feedback = 'Link adicionado com sucesso.';
            $scrollTargetId = 'admin-links-list';
        }
    } elseif (isset($_POST['row_action'])) {
        $idx = (int) ($_POST['row_index'] ?? -1);
        $id = (int) ($_POST['row_id'] ?? 0);
        $action = $_POST['row_action'] ?? '';
        if ($action === 'delete' && $id > 0) {
            ex_store_delete($id);
            $feedback = 'Item removido.';
            $scrollTargetId = 'admin-links-list';
        } elseif ($action === 'update' && $id > 0) {
            $name = trim($_POST['name'] ?? '');
            $link = trim($_POST['link'] ?? '');
            $type = trim($_POST['type'] ?? 'geral');
            if ($name === '' || $link === '') {
                $error = 'Informe nome e link para salvar a edição.';
                $scrollTargetId = 'admin-links-form';
            } else {
                ex_store_update($id, $name, $link, $type);
                $feedback = 'Item atualizado.';
                $scrollTargetId = 'admin-links-list';
            }
        }
    }
}

$items = ex_store_all();
$itemsWithIndex = [];
foreach ($items as $it) {
    $itemsWithIndex[] = [
        'idx' => (int) ($it['id'] ?? 0),
        'item' => [
            'name' => $it['nome_exercicio'] ?? '',
            'link' => $it['link'] ?? '',
            'type' => $it['grupo_muscular'] ?? 'geral',
        ],
    ];
}

$filterType = isset($_GET['filter_type']) ? trim((string) $_GET['filter_type']) : '';
$filterName = isset($_GET['filter_name']) ? mb_strtolower(trim((string) $_GET['filter_name'])) : '';

$filteredItems = array_filter($itemsWithIndex, function ($row) use ($filterType, $filterName) {
    $it = $row['item'];
    if ($filterType !== '' && $filterType !== 'todos' && ($it['type'] ?? '') !== $filterType) {
        return false;
    }
    if ($filterName !== '' && mb_strpos(mb_strtolower($it['name'] ?? ''), $filterName) === false) {
        return false;
    }
    return true;
});

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalFiltered = count($filteredItems);
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$pagedItems = array_slice(array_values($filteredItems), $offset, $perPage);

if (!$scrollTargetId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $scrollTargetId = $error ? 'admin-links-form' : 'admin-links-list';
}
if ($scrollTargetId) {
    $safeTargetId = json_encode($scrollTargetId);
    $script = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var targetId = {$safeTargetId};
    var target = document.getElementById(targetId);
    if (!target) {
        return;
    }
    var runScroll = function () {
        var top = target.getBoundingClientRect().top + window.scrollY - 12;
        window.scrollTo({ top: top < 0 ? 0 : top, behavior: 'smooth' });
    };
    window.requestAnimationFrame(function () {
        setTimeout(runScroll, 220);
    });
});
</script>
HTML;
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
            <h1>Biblioteca de links</h1>
            <p style="max-width:760px;margin:12px auto 0;color:rgba(255,255,255,0.78);">
                Cadastre rapidamente nome e link . Use o painel para manter tudo organizado.
            </p>
        </div>
    </section>

    <section class="dashboard-wrap py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-xl-3">
                    <?php $area_nav_active = 'admin_links'; include './partials/area-nav.php'; ?>
                </div>
                <div class="col-lg-8 col-xl-9">
                    <div class="dash-card mb-4" id="admin-links-form">
                        <h4>Adicionar link</h4>
                        <?php if ($feedback): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');?></div>
                        <?php endif;?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');?></div>
                        <?php endif;?>
                        <form method="post" class="row g-3">
                            <input type="hidden" name="add_link" value="1">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" name="name" class="form-control" required placeholder="Ex: Mobilidade gato vaca">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Link</label>
                                <input type="url" name="link" class="form-control" required placeholder="https://">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grupo muscular</label>
                                <select name="type" class="form-control">
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8');?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');?></option>
                                    <?php endforeach;?>
                                </select>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn_one">Salvar</button>
                            </div>
                        </form>
                    </div>

                    <div class="dash-card" id="admin-links-list">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4>Itens cadastrados (<?php echo $totalFiltered;?>)</h4>
                            <form method="get" class="d-flex gap-2">
                                <select name="filter_type" class="form-control form-control-sm" style="max-width:160px;">
                                    <option value="todos">Todos os tipos</option>
                                    <?php foreach ($types as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8');?>" <?php echo $filterType === $key ? 'selected' : '';?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');?></option>
                                    <?php endforeach;?>
                                </select>
                                <input type="text" name="filter_name" class="form-control form-control-sm" placeholder="Filtrar por nome" value="<?php echo htmlspecialchars($filterName, ENT_QUOTES, 'UTF-8');?>" style="max-width:200px;">
                                <button class="btn_one btn-sm" type="submit">Filtrar</button>
                            </form>
                        </div>
                        <?php if (!$pagedItems): ?>
                            <p class="mb-0">Nenhum link cadastrado.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width:40%;">Nome</th>
                                            <th>Link</th>
                                            <th style="width:140px;">Grupo muscular</th>
                                            <th style="width:160px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pagedItems as $row): $idx = $row['idx']; $item = $row['item']; ?>
                                            <tr>
                                                <form method="post" style="display: contents;">
                                                    <input type="hidden" name="row_index" value="<?php echo $idx;?>">
                                                    <td>
                                                        <input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="url" name="link" class="form-control form-control-sm" value="<?php echo htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8');?>" required>
                                                    </td>
                                                    <td>
                                                        <select name="type" class="form-control form-control-sm">
                                                            <?php
                                                              $currentType = $item['type'] ?? 'geral';
                                                              $hasType = isset($types[$currentType]);
                                                            ?>
                                                            <?php foreach ($types as $key => $label): ?>
                                                                <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8');?>" <?php echo $currentType === $key ? 'selected' : '';?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');?></option>
                                                            <?php endforeach;?>
                                                            <?php if (!$hasType): ?>
                                                                <option value="<?php echo htmlspecialchars($currentType, ENT_QUOTES, 'UTF-8');?>" selected><?php echo htmlspecialchars($currentType, ENT_QUOTES, 'UTF-8');?></option>
                                                            <?php endif;?>
                                                        </select>
                                                    </td>
                                                    <td class="d-flex flex-column flex-lg-row gap-2 justify-content-end">
                                                        <button type="submit" name="row_action" value="update" class="btn_one btn-sm w-100">Salvar</button>
                                                        <button type="submit" name="row_action" value="delete" class="btn_two btn-sm w-100" onclick="return confirm('Remover este item?');">Remover</button>
                                                    </td>
                                                </form>
                                            </tr>
                                        <?php endforeach;?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($totalPages > 1): ?>
                                <style>
                                  .nf-pagination {
                                    display: inline-flex;
                                    gap: 6px;
                                    align-items: center;
                                    padding: 10px 12px;
                                    background: rgba(255,255,255,0.03);
                                    border: 1px solid rgba(255,255,255,0.08);
                                    border-radius: 12px;
                                    box-shadow: 0 18px 45px rgba(0,0,0,0.35);
                                  }
                                  .nf-pagination a {
                                    display: inline-flex;
                                    width: 34px;
                                    height: 34px;
                                    border-radius: 10px;
                                    align-items: center;
                                    justify-content: center;
                                    background: rgba(255,255,255,0.05);
                                    color: #fff;
                                    text-decoration: none;
                                    border: 1px solid transparent;
                                    transition: all 0.15s ease;
                                    font-weight: 600;
                                  }
                                  .nf-pagination a:hover {
                                    border-color: rgba(255,122,0,0.35);
                                    background: rgba(255,122,0,0.12);
                                    color: #ffb37f;
                                  }
                                  .nf-pagination .is-active {
                                    background: linear-gradient(120deg,#ff7a00,#ff9f43);
                                    color: #0b0f17;
                                    border-color: rgba(255,122,0,0.6);
                                  }
                                  .nf-pagination .nf-dots {
                                    color: rgba(255,255,255,0.6);
                                    padding: 0 6px;
                                  }
                                </style>
                                <div class="mt-3">
                                  <div class="nf-pagination">
                                    <?php
                                      $queryBase = $_GET;
                                      $makeUrl = function($p) use ($queryBase) {
                                          $q = $queryBase;
                                          $q['page'] = $p;
                                          return '?' . http_build_query($q);
                                      };
                                      $window = 2;
                                      $pages = [];
                                      for ($p = 1; $p <= $totalPages; $p++) {
                                          if ($p === 1 || $p === $totalPages || ($p >= $page - $window && $p <= $page + $window)) {
                                              $pages[] = $p;
                                          }
                                      }
                                      $lastPrinted = 0;
                                      foreach ($pages as $p) {
                                          if ($lastPrinted && $p - $lastPrinted > 1) {
                                              echo '<span class=\"nf-dots\">...</span>';
                                          }
                                          $url = htmlspecialchars($makeUrl($p), ENT_QUOTES, 'UTF-8');
                                          $class = $p === $page ? 'is-active' : '';
                                          echo '<a class=\"'.$class.'\" href=\"'.$url.'\">'.$p.'</a>';
                                          $lastPrinted = $p;
                                      }
                                    ?>
                                  </div>
                                </div>
                            <?php endif;?>
                        <?php endif;?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include './partials/footer.php'?>
    <?php include './partials/script.php'?>
</body>
</html>
