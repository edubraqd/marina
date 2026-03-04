<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/training_store.php';
require_once __DIR__ . '/includes/library_store.php';
require_once __DIR__ . '/includes/completion_store.php';

$current_user = area_guard_require_login();
$title = 'Área do Aluno | Treinos';
$css = '<link rel="stylesheet" href="assets/css/area.css">';

$isAdmin = ($current_user['role'] ?? 'student') === 'admin';
$selectedEmail = $current_user['email'];

// Compatibilidade quando a extensão mbstring não está carregada
function nf_lower(string $value): string
{
  return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

if ($isAdmin && isset($_GET['user_email'])) {
  $selectedEmail = nf_lower(trim((string) $_GET['user_email']));
}

$selectedUser = user_store_find($selectedEmail);
if (!$selectedUser) {
  $selectedEmail = $current_user['email'];
  $selectedUser = $current_user;
}

// Carrega treino e aplica links da biblioteca caso faltem
$plan = training_store_find_for_user($selectedEmail) ?? [];
$planExercises = $plan['exercises'] ?? [];
$feedback = '';
$canEditLoad = (!$isAdmin && $selectedEmail === $current_user['email']);
$libraryMap = [];
foreach (library_store_load() as $lib) {
  $k = nf_lower(trim($lib['name'] ?? ''));
  if ($k !== '') {
    $libraryMap[$k] = $lib['link'] ?? '';
  }
}
foreach ($planExercises as $i => &$ex) {
  $ex['_idx'] = $i; // índice original para mapear cargas
  if ((!isset($ex['video_url']) || trim((string) $ex['video_url']) === '') && isset($ex['name'])) {
    $key = nf_lower(trim((string) $ex['name']));
    if ($key !== '' && isset($libraryMap[$key])) {
      $ex['video_url'] = $libraryMap[$key];
    }
  }
}
unset($ex);

/**
 * Normaliza cues em campos estruturados.
 */
function nf_parse_cues(?string $cues): array
{
  $base = ['series' => '', 'reps' => '', 'load' => '', 'notes' => '', 'order' => ''];
  if (!$cues) {
    return $base;
  }
  $decoded = json_decode($cues, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    return array_merge($base, array_intersect_key($decoded, $base));
  }
  $base['notes'] = trim($cues);
  return $base;
}

/**
 * Reempacota cues em JSON para salvar.
 */
function nf_pack_cues(array $c): string
{
  return json_encode([
    'series' => trim($c['series'] ?? ''),
    'reps' => trim($c['reps'] ?? ''),
    'load' => trim($c['load'] ?? ''),
    'notes' => trim($c['notes'] ?? ''),
    'order' => trim($c['order'] ?? ''),
  ], JSON_UNESCAPED_UNICODE);
}

// Permite que o aluno registre a carga utilizada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load'])) {
  // apenas o próprio aluno pode registrar a carga
  if ($selectedEmail === $current_user['email']) {
    $loadMap = array_map(function ($v) {
      return trim((string) $v);
    }, $_POST['load']);

    // marca índice original para mapear na renderização
    foreach ($planExercises as $i => &$ex) {
      $ex['_idx'] = $i;
    }
    unset($ex);

    foreach ($planExercises as &$ex) {
      $idx = $ex['_idx'] ?? null;
      if ($idx !== null && array_key_exists($idx, $loadMap)) {
        $cues = nf_parse_cues($ex['cues'] ?? '');
        $cues['load'] = $loadMap[$idx];
        $ex['cues'] = nf_pack_cues($cues);
      }
    }
    unset($ex);

    // salva apenas a ficha, preservando demais campos
    try {
      training_store_save_for_user($selectedEmail, [
        'title' => $plan['title'] ?? 'Treino do aluno',
        'instructions' => $plan['instructions'] ?? '',
        'exercises' => array_map(function ($ex) {
          unset($ex['_idx']);
          return $ex;
        }, $planExercises),
      ]);
      $feedback = 'Cargas atualizadas com sucesso.';
      $plan = training_store_find_for_user($selectedEmail) ?? [];
      $planExercises = $plan['exercises'] ?? [];
      foreach ($planExercises as $i => &$ex) {
        $ex['_idx'] = $i;
      }
      unset($ex);
    } catch (Throwable $e) {
      $feedback = 'Não foi possível salvar as cargas. Tente novamente.';
    }
  } else {
    $feedback = 'Somente o próprio aluno pode registrar a carga.';
  }
}

// Agrupa exercícios por ficha (sheet_idx)
$sheets = [];
foreach ($planExercises as $ex) {
  $name = trim((string) ($ex['name'] ?? ''));
  if ($name === '') {
    continue;
  }
  $sid = trim((string) ($ex['sheet_idx'] ?? '')) ?: 'sheet1';
  if (!isset($sheets[$sid])) {
    $sheets[$sid] = [
      'id' => $sid,
      'title' => trim((string) ($ex['sheet_title'] ?? 'Ficha')),
      'month' => trim((string) ($ex['sheet_ref_month'] ?? '')),
      'year' => trim((string) ($ex['sheet_ref_year'] ?? '')),
      'exercises' => [],
    ];
  }
  $sheets[$sid]['exercises'][] = $ex;
}

// Se não houver ficha, manter estrutura vazia
$hasExercises = !empty($sheets);
$completionCount = completion_store_count_month($selectedEmail);

// Ordenar fichas pelo título para consistência visual
if ($hasExercises) {
  uasort($sheets, function ($a, $b) {
    return strcmp($a['title'] ?? '', $b['title'] ?? '');
  });
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<?php include './partials/head.php'; ?>

<body class="area-shell">
  <?php include './partials/preloader.php' ?>
  <?php include './partials/header.php' ?>

  <style>
    :root {
      --nf-bg: #030303;
      --nf-card: #0a0a0a;
      --nf-stroke: #222;
      --nf-soft: #111;
      --nf-text: #fff;
      --nf-sub: #888;
      --nf-accent: #ff7a00;
      --nf-accent-2: #ffb37f;
    }

    .train-hero {
      background: var(--nf-bg);
      padding: 100px 0 60px;
      color: #fff;
      border-bottom: 1px solid var(--nf-stroke);
    }

    .train-hero h1 {
      font-size: clamp(3rem, 10vw, 8rem);
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: -0.04em;
      line-height: 0.9;
      margin-bottom: 0px;
      background: linear-gradient(180deg, #fff, #555);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .train-card {
      background: var(--nf-card);
      border: 1px solid var(--nf-stroke);
      border-radius: 0px;
      /* Brutalist sharp */
      padding: 24px;
      box-shadow: 4px 4px 0 rgba(255, 122, 0, 0.1);
    }

    .sheet-head {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 14px;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      border-bottom: 2px solid var(--nf-stroke);
      padding-bottom: 16px;
    }

    .badge-soft {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 2px;
      background: var(--nf-soft);
      color: var(--nf-text);
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border: 1px solid var(--nf-stroke);
    }

    .completion-badge {
      background: transparent !important;
      border: 1px solid rgba(0, 255, 153, 0.3) !important;
      color: #00ff99 !important;
      box-shadow: 2px 2px 0 rgba(0, 255, 153, 0.2) !important;
    }

    /* Vertical Timeline Style */
    .sheet-wrapper {
      padding: 0;
      overflow: hidden;
      border-radius: 2px;
      border: 1px solid var(--nf-stroke);
      box-shadow: 4px 4px 0 var(--nf-stroke);
      margin-bottom: 32px;
      transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .sheet-wrapper:hover {
      transform: translate(-2px, -2px);
      box-shadow: 6px 6px 0 var(--nf-accent);
      border-color: var(--nf-accent);
    }

    .sheet-body {
      padding: 0 0 0 0;
      border-top: 1px solid var(--nf-stroke);
      display: none;
    }

    .sheet-body.is-open {
      display: block;
    }

    .sheet-toggle {
      border-radius: 0;
      padding: 20px;
      background: #0d0d0d;
      width: 100%;
      border: none;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      cursor: pointer;
    }

    .sheet-toggle h5 {
      font-weight: 700;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      margin: 0;
      color: #fff;
    }

    .sheet-meta {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 6px;
    }

    .sheet-chevron {
      width: 34px;
      height: 34px;
      border-radius: 2px;
      border: 1px solid var(--nf-stroke);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: transform .2s ease;
      background: transparent;
      color: var(--nf-sub);
    }

    .sheet-toggle[aria-expanded="true"] .sheet-chevron {
      transform: rotate(180deg);
    }

    .sheet-toggle:hover .sheet-chevron {
      border-color: var(--nf-accent);
      color: var(--nf-accent);
    }

    .exercise-view {
      background: transparent;
      border: none;
      border-bottom: 1px solid var(--nf-stroke);
      border-radius: 0;
      padding: 24px 20px;
      position: relative;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      /* Intersection Observer Initial State */
      opacity: 0;
      transform: translateY(20px);
    }

    .exercise-view.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    .exercise-view:last-child {
      border-bottom: none;
    }

    .exercise-view:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .exercise-view::before {
      content: "";
      position: absolute;
      left: 0;
      top: 0;
      height: 100%;
      width: 4px;
      background: var(--nf-accent);
      transform: scaleY(0);
      transform-origin: top;
      transition: transform 0.3s ease;
    }

    .exercise-view:hover::before {
      transform: scaleY(1);
    }

    .exercise-title {
      font-weight: 800;
      letter-spacing: -0.02em;
      font-size: 1.25rem;
      margin-bottom: 12px;
      color: #fff;
      text-transform: uppercase;
    }

    .exercise-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 16px;
    }

    .meta-box {
      background: transparent;
      border: 1px solid var(--nf-stroke);
      border-radius: 2px;
      padding: 10px 14px;
      font-size: 12px;
      color: var(--nf-sub);
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 90px;
    }

    .meta-box strong {
      color: var(--nf-accent-2);
      text-transform: uppercase;
      font-size: 10px;
      letter-spacing: 0.05em;
    }

    .nf-load-input {
      background: transparent;
      border: none;
      border-bottom: 1px solid var(--nf-stroke);
      color: #fff;
      padding: 2px 0;
      border-radius: 0;
      font-size: 16px;
      font-weight: 600;
      text-align: left;
      transition: border-color 0.2s;
    }

    .nf-load-input:focus {
      outline: none;
      border-color: var(--nf-accent);
    }

    .exercise-notes {
      color: var(--nf-sub);
      font-size: 13px;
      margin: 0;
      border-left: 2px solid var(--nf-stroke);
      padding-left: 10px;
      font-style: italic;
    }

    .nf-btn-video {
      border-radius: 2px;
      background: transparent;
      border: 1px solid var(--nf-stroke);
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.05em;
      padding: 10px 16px;
      color: #fff;
      transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      font-weight: 600;
    }

    .nf-btn-video:hover {
      background: #fff;
      color: #000;
      transform: translateX(4px);
      border-color: #fff;
    }

    .nf-btn-done {
      border-radius: 2px;
      border: 1px solid var(--nf-stroke);
      background: #000;
      color: #fff;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 0.05em;
      padding: 10px 16px;
      transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 700;
    }

    .nf-btn-done.is-active {
      background: #00ff99;
      border-color: #00ff99;
      color: #000;
    }

    .nf-btn-done:hover:not(.is-active) {
      border-color: #00ff99;
      color: #00ff99;
      transform: translateX(4px);
    }

    .exercise-view.is-done {
      background: rgba(0, 255, 153, 0.02);
      opacity: 0.7;
      transform: scale(0.99);
    }

    .exercise-view.is-done::before {
      background: #00ff99;
      transform: scaleY(1);
    }

    .exercise-view.is-done .exercise-title {
      color: #00ff99;
      text-decoration: none;
      position: relative;
      display: inline-block;
    }

    .exercise-view.is-done .exercise-title::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      height: 2px;
      background: #00ff99;
      animation: strike 0.3s ease-out forwards;
    }

    /* Completion & Save buttons */
    .btn_one {
      border-radius: 2px !important;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      font-weight: 700;
      box-shadow: 4px 4px 0 rgba(255, 122, 0, 0.2) !important;
      transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
      border: 1px solid var(--nf-accent) !important;
    }

    .btn_one:hover {
      transform: translate(-2px, -2px) !important;
      box-shadow: 6px 6px 0 rgba(255, 122, 0, 0.5) !important;
    }

    .nf-empty {
      background: var(--nf-soft);
      border: 1px dashed var(--nf-stroke);
      border-radius: 2px;
      padding: 32px;
      color: var(--nf-sub);
      text-align: center;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .nf-save-floating {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 1200;
      box-shadow: 6px 6px 0 rgba(0, 0, 0, 0.5) !important;
      border-radius: 2px !important;
      padding: 16px 24px !important;
    }

    @media (max-width: 768px) {
      .nf-save-floating {
        left: 16px;
        right: 16px;
        width: calc(100% - 32px);
        max-width: none;
        text-align: center;
      }
    }

    /* --- UX PROGRESS BAR & ANIMATIONS --- */
    .nf-progress-container {
      width: 100%;
      height: 8px;
      background: var(--nf-soft);
      border-radius: 4px;
      margin-bottom: 8px;
      overflow: hidden;
      border: 1px solid var(--nf-stroke);
    }

    .nf-progress-bar {
      height: 100%;
      width: 0%;
      background: var(--nf-accent);
      transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), background-color 0.4s ease;
      box-shadow: 0 0 10px rgba(255, 122, 0, 0.5);
    }

    .nf-progress-bar.is-complete {
      background: #00ff99;
      box-shadow: 0 0 15px rgba(0, 255, 153, 0.6);
    }

    .nf-progress-text {
      font-size: 11px;
      color: var(--nf-sub);
      text-align: right;
      margin-bottom: 24px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      transition: color 0.4s ease;
    }

    .nf-progress-text.is-complete {
      color: #00ff99;
    }

    @keyframes popIn {
      0% {
        transform: scale(0.9);
      }

      50% {
        transform: scale(1.05);
      }

      100% {
        transform: scale(1);
      }
    }

    @keyframes strike {
      0% {
        width: 0;
      }

      100% {
        width: 100%;
      }
    }

    @keyframes pulseSuccess {
      0% {
        box-shadow: 0 0 0 0 rgba(0, 255, 153, 0.4);
        border-color: #00ff99;
      }

      70% {
        box-shadow: 0 0 0 15px rgba(0, 255, 153, 0);
        border-color: #00ff99;
      }

      100% {
        box-shadow: 0 0 0 0 rgba(0, 255, 153, 0);
        border-color: #00ff99;
      }
    }

    .nf-btn-done.is-active {
      animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    .btn-ready {
      animation: pulseSuccess 2s infinite !important;
      background: rgba(0, 255, 153, 0.1) !important;
      color: #00ff99 !important;
      border-color: #00ff99 !important;
    }
  </style>

  <section class="train-hero text-center">
    <div class="container">
      <div class="col-lg-12">
        <h1>Treino</h1>
        <p
          style="max-width:760px;margin:24px auto 0;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.1em;font-size:14px;font-weight:600;">
          Sua ficha técnica. Execute com precisão.
        </p>
        <?php if ($isAdmin): ?>
          <p class="mb-0 mt-3" style="color:var(--nf-accent);">
            ALERTA: Visualizando como aluno. Para gerenciar, acesse <a href="/area-admin-treinos"
              style="color:#fff;text-decoration:underline;font-weight:700;">Treinos Administrativos</a>.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="dashboard-wrap py-5" style="background:var(--nf-bg);">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
          <?php $area_nav_active = 'treinos';
          include './partials/area-nav.php'; ?>
        </div>
        <div class="col-lg-8 col-xl-9">
          <?php if ($isAdmin): ?>
            <div class="train-card mb-3">
              <form method="get" class="row g-2 align-items-end">
                <div class="col-md-8">
                  <label class="form-label">Ver ficha do aluno</label>
                  <input type="email" class="form-control" name="user_email"
                    value="<?php echo htmlspecialchars($selectedEmail, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="email@aluno.com">
                </div>
                <div class="col-md-4 text-md-end">
                  <button class="btn_one w-100" type="submit">Carregar</button>
                </div>
              </form>
            </div>
          <?php endif; ?>

          <div class="train-card mb-4">
            <?php if ($feedback): ?>
              <div class="alert alert-info"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="sheet-head mb-2">
              <div>
                <h4 class="mb-1">
                  <?php echo htmlspecialchars($plan['title'] ?? 'Ficha de treino', ENT_QUOTES, 'UTF-8'); ?>
                </h4>
                <?php if (!empty($plan['instructions'])): ?>
                  <p class="mb-0" style="color:var(--nf-sub);">
                    <?php echo nl2br(htmlspecialchars($plan['instructions'], ENT_QUOTES, 'UTF-8')); ?>
                  </p>
                <?php else: ?>
                  <p class="mb-0" style="color:var(--nf-sub);">Acompanhe abaixo os exercícios, séries e vídeos.</p>
                <?php endif; ?>
              </div>
              <?php if (!empty($plan['updated_at'] ?? $plan['created_at'] ?? '')): ?>
                <span class="badge-soft">
                  <i class="ti-time"></i>
                  Atualizado em <?php echo date('d/m/Y H:i', strtotime($plan['updated_at'] ?? $plan['created_at'])); ?>
                </span>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
              <div class="badge-soft completion-badge" id="completion-counter"
                data-user-email="<?php echo htmlspecialchars($selectedEmail, ENT_QUOTES, 'UTF-8'); ?>"
                data-count-url="/training-completion-count">
                <strong id="completion-count"
                  style="font-size:14px; margin-right:4px;"><?php echo $completionCount; ?></strong>
                <span id="completion-label">treino<?php echo $completionCount !== 1 ? 's' : ''; ?>
                  concluído<?php echo $completionCount !== 1 ? 's' : ''; ?> este mês</span>
              </div>
            </div>

            <?php if (!$hasExercises): ?>
              <div class="nf-empty">Nenhum treino cadastrado ainda. Assim que o treinador liberar, sua ficha aparece aqui.
              </div>
            <?php else: ?>

              <!-- UX Progress Bar -->
              <div class="nf-progress-container">
                <div class="nf-progress-bar" id="training-progress-bar"></div>
              </div>
              <div class="nf-progress-text" id="training-progress-text">0% Completo</div>

              <!-- Auto-save replaces form -->
              <?php $isFirstSheet = true; ?>
              <?php foreach ($sheets as $sheetId => $sheet): ?>
                <?php
                $sheetExercises = $sheet['exercises'] ?? [];
                $isOpenSheet = $isFirstSheet;
                $isFirstSheet = false;
                $safeSheetId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $sheetId) ?: ('sheet' . uniqid());
                ?>
                <div class="train-card mb-3 sheet-wrapper" style="background:#0c101d;">
                  <button type="button" class="sheet-toggle"
                    data-target="sheet-body-<?php echo htmlspecialchars($safeSheetId, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-expanded="<?php echo $isOpenSheet ? 'true' : 'false'; ?>">
                    <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start;">
                      <h5 class="mb-0" style="color:#fff;">
                        <?php echo htmlspecialchars($sheet['title'] ?: 'Ficha', ENT_QUOTES, 'UTF-8'); ?>
                      </h5>
                      <div class="sheet-meta">
                        <?php if ($sheet['month']): ?>
                          <span class="badge-soft">Mês:
                            <?php echo htmlspecialchars($sheet['month'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if ($sheet['year']): ?>
                          <span class="badge-soft">Ano:
                            <?php echo htmlspecialchars($sheet['year'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span class="badge-soft"><?php echo count($sheetExercises); ?> exercícios</span>
                      </div>
                    </div>
                    <span class="sheet-chevron"><i class="ti-angle-down"></i></span>
                  </button>

                  <div class="sheet-body <?php echo $isOpenSheet ? 'is-open' : ''; ?>"
                    id="sheet-body-<?php echo htmlspecialchars($safeSheetId, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($sheetExercises as $idx => $ex): ?>
                      <?php
                      $c = nf_parse_cues($ex['cues'] ?? '');
                      $order = $c['order'] !== '' ? $c['order'] : ($idx + 1);
                      $exIdx = (int) ($ex['_idx'] ?? $idx);
                      $doneId = $safeSheetId . '-' . $exIdx;
                      ?>
                      <div class="exercise-view">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                          <div>
                            <div class="exercise-title"><?php echo htmlspecialchars($ex['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="exercise-meta">
                              <div class="meta-box">
                                <strong>Série</strong><?php echo htmlspecialchars($c['series'] ?: '—', ENT_QUOTES, 'UTF-8'); ?>
                              </div>
                              <div class="meta-box">
                                <strong>Rep.</strong><?php echo htmlspecialchars($c['reps'] ?: '—', ENT_QUOTES, 'UTF-8'); ?>
                              </div>
                              <div class="meta-box">
                                <strong>Carga</strong>
                                <?php if ($canEditLoad): ?>
                                  <input type="text" class="nf-load-input w-100 auto-save-input"
                                    data-ex-idx="<?php echo $exIdx; ?>" data-field="load"
                                    value="<?php echo htmlspecialchars($c['load'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Ex: 30kg">
                                <?php else: ?>
                                  <?php echo htmlspecialchars($c['load'] ?: '—', ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                          <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if (!empty($ex['video_url'])): ?>
                              <a class="nf-btn-video" target="_blank" rel="noopener noreferrer"
                                href="<?php echo htmlspecialchars($ex['video_url'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="ti-control-play"></i> Ver vídeo
                              </a>
                            <?php endif; ?>
                            <button type="button" class="nf-btn-done"
                              data-ex-done="<?php echo htmlspecialchars($doneId, ENT_QUOTES, 'UTF-8'); ?>">
                              Marcar como feito
                            </button>
                          </div>
                        </div>
                        <?php if ($c['notes']): ?>
                          <p class="exercise-notes mb-0"><strong>Observações:
                            </strong><?php echo htmlspecialchars($c['notes'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if ($canEditLoad || $isAdmin): ?>
                <div class="d-flex justify-content-between align-items-center mt-5 flex-wrap gap-3">
                  <button type="button" class="btn_one btn_one_complete" id="btn-complete-training"
                    data-user-email="<?php echo htmlspecialchars($selectedEmail, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="ti-check-box"></i> Finalizar Treino
                  </button>
                </div>
              <?php endif; ?>
              <!-- Auto-save Replaced Form End -->
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($canEditLoad): ?>
    <style>
      @keyframes autoSaveSpin {
        100% {
          transform: rotate(360deg);
        }
      }
    </style>
    <div id="auto-save-status" class="nf-save-floating badge-soft"
      style="display:none; align-items:center; gap:8px; font-weight:700; pointer-events:none; background:#0a0a0a; border:1px solid var(--nf-stroke); z-index:9999; color:var(--nf-text);">
      <i class="ti-reload" style="font-size:16px;"></i> <span>Aguardando...</span>
    </div>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // --- Initialization for existing observers ... ---
      var observerOptions = { root: null, rootMargin: '0px 0px -50px 0px', threshold: 0.1 };
      var observer = new IntersectionObserver(function (entries, obs) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            var idx = Array.from(entry.target.parentElement.querySelectorAll('.exercise-view')).indexOf(entry.target);
            setTimeout(function () { entry.target.classList.add('is-visible'); }, (idx % 10) * 50);
            obs.unobserve(entry.target);
          }
        });
      }, observerOptions);

      document.querySelectorAll('.exercise-view').forEach(function (el) { observer.observe(el); });

      function triggerVisibleOnOpen(container) {
        container.querySelectorAll('.exercise-view:not(.is-visible)').forEach(function (el, i) {
          setTimeout(function () { el.classList.add('is-visible'); }, i * 50);
        });
      }

      var completionCounter = document.getElementById('completion-counter');
      var completionCountEl = document.getElementById('completion-count');
      var completionLabelEl = document.getElementById('completion-label');
      var completionInFlight = false;
      var completionTimer = null;

      function renderCompletionCount(rawCount) {
        var c = Number(rawCount || 0);
        if (!Number.isFinite(c) || c < 0) c = 0;
        if (completionCountEl) completionCountEl.textContent = String(c);
        if (completionLabelEl) completionLabelEl.textContent = 'treino' + (c !== 1 ? 's' : '') + ' concluído' + (c !== 1 ? 's' : '') + ' este mês';
      }

      function refreshCompletionCount() {
        if (!completionCounter || completionInFlight) return;
        var endpoint = completionCounter.getAttribute('data-count-url') || '/training-completion-count';
        var selectedEmail = completionCounter.getAttribute('data-user-email') || '';
        var url = endpoint + (selectedEmail ? ('?user_email=' + encodeURIComponent(selectedEmail)) : '');
        completionInFlight = true;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(res => res.json())
          .then(data => { if (data && data.ok) renderCompletionCount(data.count || 0); })
          .catch(() => { })
          .finally(() => { completionInFlight = false; });
      }

      refreshCompletionCount();
      completionTimer = setInterval(refreshCompletionCount, 15000);
      document.addEventListener('visibilitychange', function () { if (!document.hidden) refreshCompletionCount(); });

      var sheetButtons = document.querySelectorAll('.sheet-toggle');
      function setSheetState(button, body, open) {
        if (!button || !body) return;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.dataset.open = open ? 'true' : 'false';
        body.classList.toggle('is-open', !!open);
        if (open) triggerVisibleOnOpen(body);
        if (typeof updateProgress === 'function') updateProgress();
      }
      sheetButtons.forEach(function (btn) {
        var targetId = btn.getAttribute('data-target');
        var body = document.getElementById(targetId);
        setSheetState(btn, body, btn.getAttribute('aria-expanded') === 'true');
        btn.addEventListener('click', function () {
          var willOpen = btn.getAttribute('aria-expanded') !== 'true';
          sheetButtons.forEach(function (otherBtn) {
            if (otherBtn === btn) return;
            var otherBody = document.getElementById(otherBtn.getAttribute('data-target'));
            setSheetState(otherBtn, otherBody, false);
          });
          setSheetState(btn, body, willOpen);
        });
      });

      var selectedEmailJson = <?php echo json_encode($selectedEmail, JSON_UNESCAPED_UNICODE); ?>;
      var doneKey = 'nf_train_done_' + selectedEmailJson;
      var doneState = {};
      try {
        var parsed = JSON.parse(localStorage.getItem(doneKey) || '{}');
        if (parsed && typeof parsed === 'object') doneState = parsed;
      } catch (e) { doneState = {}; }

      function persistDone() {
        try { localStorage.setItem(doneKey, JSON.stringify(doneState)); } catch (e) { }
      }

      function updateProgress() {
        var openSheet = document.querySelector('.sheet-body.is-open');
        var allDoneBtns = openSheet ? openSheet.querySelectorAll('.nf-btn-done') : document.querySelectorAll('.nf-btn-done');
        if (!allDoneBtns.length && !openSheet) {
          allDoneBtns = document.querySelectorAll('.nf-btn-done');
        }
        if (!allDoneBtns.length) return;

        var totalEx = allDoneBtns.length;
        var doneEx = Array.from(allDoneBtns).filter(function (b) { return b.classList.contains('is-active'); }).length;
        var pct = Math.round((doneEx / totalEx) * 100);

        var pBar = document.getElementById('training-progress-bar');
        var pText = document.getElementById('training-progress-text');
        var cBtn = document.getElementById('btn-complete-training');

        if (pBar) pBar.style.width = pct + '%';
        if (pct === 100) {
          if (pBar) pBar.classList.add('is-complete');
          if (pText) {
            pText.classList.add('is-complete');
            pText.textContent = 'Treino Concluído! 🏆';
          }
          if (cBtn) cBtn.classList.add('btn-ready');
        } else {
          if (pBar) pBar.classList.remove('is-complete');
          if (pText) {
            pText.classList.remove('is-complete');
            pText.textContent = pct + '% Completo';
          }
          if (cBtn) cBtn.classList.remove('btn-ready');
        }
      }

      function applyDone(btn, card, isDone) {
        if (!btn) return;
        btn.classList.toggle('is-active', !!isDone);
        btn.innerHTML = isDone ? '<i class="ti-check"></i> Feito' : 'Marcar como feito';
        if (card) { card.classList.toggle('is-done', !!isDone); }
        updateProgress();
      }

      document.querySelectorAll('.nf-btn-done').forEach(function (btn) {
        var exId = btn.getAttribute('data-ex-done');
        var card = btn.closest('.exercise-view');
        applyDone(btn, card, !!doneState[exId]);
        btn.addEventListener('click', function () {
          var newState = !doneState[exId];
          doneState[exId] = newState;
          applyDone(btn, card, newState);
          persistDone();
        });
      });

      updateProgress();

      var completeBtn = document.getElementById('btn-complete-training');
      if (completeBtn) {
        completeBtn.addEventListener('click', function () {
          // Identifica qual é a ficha alvo. Primeira prioridade: a que estiver aberta.
          var activeSheet = document.querySelector('.sheet-body.is-open');

          // Se nenhuma estiver aberta visualmente, busca a primeira ficha que possua pelo menos 1 exercício marcado como feito.
          if (!activeSheet) {
            var sheets = document.querySelectorAll('.sheet-body');
            for (var i = 0; i < sheets.length; i++) {
              if (sheets[i].querySelectorAll('.nf-btn-done.is-active').length > 0) {
                activeSheet = sheets[i];
                break;
              }
            }
          }

          // Se ainda não encontrou (nenhuma aberta e nenhuma com marcações), pega a primeira ficha da página.
          if (!activeSheet) {
            activeSheet = document.querySelector('.sheet-body');
          }

          // Se a página não tiver nenhuma ficha, ignora.
          if (!activeSheet) return;

          // Extrair o nome da ficha ativa para dar contexto na mensagem
          var sheetTitleElement = activeSheet.previousElementSibling.querySelector('h5');
          var sheetTitle = sheetTitleElement ? sheetTitleElement.textContent.trim() : 'a Ficha';

          var allBtns = activeSheet.querySelectorAll('.nf-btn-done');
          var doneBtns = activeSheet.querySelectorAll('.nf-btn-done.is-active');
          var total = allBtns.length;
          var doneCount = doneBtns.length;
          var missingCount = total - doneCount;

          if (missingCount > 0) {
            // Interação guiada: se a ficha não estava aberta, vamos abri-la para o usuário ver
            var sheetToggleBtn = activeSheet.previousElementSibling;
            if (!activeSheet.classList.contains('is-open') && sheetToggleBtn) {
              sheetToggleBtn.click();
            }

            // Msg contextual
            var msg = "";
            if (doneCount === 0) {
              msg = 'Você ainda não marcou nenhum exercício da ' + sheetTitle + '.';
            } else {
              msg = 'Você ainda não marcou todos os exercícios da ' + sheetTitle + '. ' +
                (missingCount === 1 ? 'Falta 1 exercício' : 'Faltam ' + missingCount + ' exercícios') + ' para concluir esta ficha.';
            }

            alert(msg);

            // Rolar suavemente até o primeiro botão não marcado
            var firstMissing = Array.from(allBtns).find(btn => !btn.classList.contains('is-active'));
            if (firstMissing) {
              firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            return;
          }

          completeBtn.disabled = true;
          completeBtn.innerHTML = 'Registrando... <i class="ti-reload"></i>';
          var completeUserEmail = completeBtn.getAttribute('data-user-email') || '';
          var completePayload = new URLSearchParams();
          if (completeUserEmail) completePayload.append('user_email', completeUserEmail);

          fetch(<?php echo json_encode(function_exists('nf_url') ? nf_url('/training-complete') : '/training-complete.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: completePayload.toString()
          })
            .then(res => res.json())
            .then(data => {
              if (data && data.ok) {
                allBtns.forEach(function (b) {
                  var id = b.getAttribute('data-ex-done');
                  doneState[id] = false;
                  applyDone(b, b.closest('.exercise-view'), false);
                });
                persistDone();
                renderCompletionCount(data.count || 0);

                // UX: Fecha a ficha concluída
                var sheetToggleBtn = activeSheet.previousElementSibling;
                if (activeSheet.classList.contains('is-open') && sheetToggleBtn) {
                  sheetToggleBtn.click();
                }

                alert('TREINO CONCLUÍDO! EXCELENTE TRABALHO! 🦾');
              } else { alert('Erro ao registrar. Tente novamente.'); }
            })
            .catch(() => { alert('Erro de rede. Tente novamente.'); })
            .finally(() => {
              completeBtn.disabled = false;
              completeBtn.innerHTML = '<i class="ti-check-box"></i> Finalizar Treino';
            });
        });
      }

      <?php if ($canEditLoad): ?>
        // --- AUTO-SAVE LOGIC ---
        var saveStatus = document.getElementById('auto-save-status');
        var syncQueue = [];
        try {
          syncQueue = JSON.parse(localStorage.getItem('nf_sync_queue_' + selectedEmailJson) || '[]');
        } catch (e) { }
        var saveTimer = null;
        var isSyncing = false;

        function setSaveStatus(estado, texto) {
          if (!saveStatus) return;
          saveStatus.style.display = 'inline-flex';
          var icon = saveStatus.querySelector('i');
          var span = saveStatus.querySelector('span');
          span.textContent = texto;

          saveStatus.style.borderColor = 'var(--nf-stroke)';
          saveStatus.style.color = 'var(--nf-text)';
          icon.className = 'ti-save';
          icon.style.animation = 'none';

          if (estado === 'saving') {
            saveStatus.style.borderColor = 'var(--nf-accent)';
            saveStatus.style.color = 'var(--nf-accent)';
            icon.className = 'ti-reload';
            icon.style.animation = 'autoSaveSpin 1s linear infinite';
          } else if (estado === 'success') {
            saveStatus.style.borderColor = '#00ff99';
            saveStatus.style.color = '#00ff99';
            icon.className = 'ti-check';
            setTimeout(function () { if (saveStatus.style.borderColor === 'rgb(0, 255, 153)' || saveStatus.style.borderColor === '#00ff99') saveStatus.style.display = 'none'; }, 2000);
          } else if (estado === 'error') {
            saveStatus.style.borderColor = '#ff4444';
            saveStatus.style.color = '#ff4444';
            icon.className = 'ti-signal';
          }
        }

        function flushSyncQueue() {
          if (isSyncing || syncQueue.length === 0) return;
          isSyncing = true;
          setSaveStatus('saving', 'Sincronizando...');

          var payload = {
            user_email: selectedEmailJson,
            updates: syncQueue
          };

          fetch('/api/save-training-cues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          })
            .then(function (res) {
              if (!res.ok) throw new Error('Bad response');
              return res.json();
            })
            .then(function (data) {
              if (data.ok) {
                syncQueue = [];
                localStorage.removeItem('nf_sync_queue_' + selectedEmailJson);
                setSaveStatus('success', 'Salvo!');
              } else {
                setSaveStatus('error', 'Sem conexão. Salvando local...');
              }
            })
            .catch(function (err) {
              setSaveStatus('error', 'Offline. Salvo no dispositivo.');
            })
            .finally(function () {
              isSyncing = false;
            });
        }

        function queueUpdate(idx, field, value) {
          if (isNaN(idx)) return;
          var existing = !syncQueue ? null : syncQueue.find(function (item) { return item.idx === idx; });
          if (existing) {
            existing.cues[field] = value;
          } else {
            var cues = {};
            cues[field] = value;
            syncQueue.push({ idx: idx, cues: cues });
          }
          localStorage.setItem('nf_sync_queue_' + selectedEmailJson, JSON.stringify(syncQueue));

          clearTimeout(saveTimer);
          setSaveStatus('saving', 'Aguardando digitação...');
          saveTimer = setTimeout(flushSyncQueue, 600);
        }

        document.querySelectorAll('.auto-save-input').forEach(function (input) {
          input.addEventListener('input', function () {
            queueUpdate(parseInt(this.getAttribute('data-ex-idx')), this.getAttribute('data-field') || 'load', this.value);
          });
        });

        if (document.querySelectorAll('.nf-btn-done').length > 0) {
          document.querySelectorAll('.nf-btn-done').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var card = btn.closest('.exercise-view');
              var input = card.querySelector('.auto-save-input');
              if (input) {
                var isDone = btn.classList.contains('is-active') ? '1' : '0';
                queueUpdate(parseInt(input.getAttribute('data-ex-idx')), 'is_done', isDone);
              }
            });
          });
        }

        if (syncQueue.length > 0) flushSyncQueue();
        window.addEventListener('online', flushSyncQueue);

      <?php endif; ?>
    });
  </script>

  <?php include './partials/footer.php' ?>
  <?php include './partials/script.php' ?>
</body>

</html>