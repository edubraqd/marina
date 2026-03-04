<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/user_store.php';
require_once __DIR__ . '/includes/training_store.php';
require_once __DIR__ . '/includes/checkin_store.php';
require_once __DIR__ . '/includes/completion_store.php';
require_once __DIR__ . '/includes/onboarding_mailer.php';
require_once __DIR__ . '/includes/library_store.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/training_pdf.php';
require_once __DIR__ . '/includes/randomizer_catalog.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

$title = 'Admin | Treinos por aluno';
$css = '<link rel="stylesheet" href="assets/css/area.css">';
$feedback = '';
$error = '';

/**
 * Descompacta o campo "cues" em partes estruturadas (séries, repetições, carga, notas, ordem).
 */
function training_parse_cues(?string $cues): array
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
 * Compacta os campos estruturados em JSON para serem salvos no campo "cues".
 */
function training_pack_cues(array $fields): string
{
  return json_encode([
    'series' => trim($fields['series'] ?? ''),
    'reps' => trim($fields['reps'] ?? ''),
    'load' => trim($fields['load'] ?? ''),
    'notes' => trim($fields['notes'] ?? ''),
    'order' => trim($fields['order'] ?? ''),
  ], JSON_UNESCAPED_UNICODE);
}

/**
 * Armazena PDF do plano alimentar na tabela dedicada (privado).
 */
function plan_files_ensure_table(): void
{
  static $ready = false;
  if ($ready) {
    return;
  }
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS plan_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
  mime VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  data LONGBLOB NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_plan_files_user (user_id),
  CONSTRAINT fk_plan_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  try {
    db()->query($sql);
    $ready = true;
  } catch (Throwable $e) {
    // silencioso
  }
}

/**
 * Salva/atualiza o PDF do usuário. Remove anteriores.
 * @return int|null ID do registro salvo
 */
function plan_files_save_for_user(string $email, string $filename, string $mime, int $size, string $data): ?int
{
  $user = user_store_find($email);
  if (!$user || !isset($user['id'])) {
    return null;
  }
  plan_files_ensure_table();
  $userId = (int) $user['id'];
  try {
    $conn = db();
    $del = $conn->prepare('DELETE FROM plan_files WHERE user_id = ?');
    $del->bind_param('i', $userId);
    $del->execute();
    $del->close();

    $stmt = $conn->prepare('INSERT INTO plan_files (user_id, filename, mime, file_size, data) VALUES (?,?,?,?,?)');
    $null = null;
    $stmt->bind_param('issib', $userId, $filename, $mime, $size, $null);
    $stmt->send_long_data(4, $data);
    $stmt->execute();
    $id = $stmt->insert_id ?: null;
    $stmt->close();
    return $id;
  } catch (Throwable $e) {
    return null;
  }
}

function admin_treinos_notify(string $to, string $subject, string $text): void
{
  $lines = preg_split('/\r\n|\r|\n/', $text) ?: [$text];
  nf_send_plain_email($to, $subject, array_filter($lines, function ($line) {
    return trim((string) $line) !== '';
  }));
}

function admin_treinos_delete(string $email): void
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

$dayOptions = [
  'geral' => 'Sem dia fixo',
  'segunda' => 'Segunda',
  'terca' => 'Terça',
  'quarta' => 'Quarta',
  'quinta' => 'Quinta',
  'sexta' => 'Sexta',
  'sabado' => 'Sábado',
  'domingo' => 'Domingo',
];

/**
 * Carrega exercícios cadastrados no MySQL (tabela exercicios).
 *
 * @return array<string,string> mapa nome -> link
 */
function nf_exercise_catalog(): array
{
  $map = [];
  try {
    $conn = db();
    $sql = 'SELECT nome_exercicio, link FROM exercicios WHERE nome_exercicio IS NOT NULL AND nome_exercicio <> "" ORDER BY nome_exercicio ASC';
    $res = $conn->query($sql);
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $nameKey = mb_strtolower(trim($row['nome_exercicio'] ?? ''));
        if ($nameKey === '') {
          continue;
        }
        $map[$nameKey] = trim($row['link'] ?? '');
      }
      $res->free();
    }
  } catch (Throwable $e) {
    // silencioso: se falhar, usa fallback
  }
  if (empty($map)) {
    foreach (library_store_load() as $lib) {
      $nameKey = mb_strtolower(trim($lib['name'] ?? ''));
      if ($nameKey === '') {
        continue;
      }
      $map[$nameKey] = $lib['link'] ?? '';
    }
  }
  return $map;
}

$libraryMap = nf_exercise_catalog();

$dayOrder = ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'];
$defaultFreq = 5;
$selectedFreq = $defaultFreq;
$activeDays = array_merge(['geral'], array_slice($dayOrder, 0, $selectedFreq));
$refMonthSelected = isset($_POST['ref_month']) ? (int) $_POST['ref_month'] : (int) date('m');
$refYearSelected = isset($_POST['ref_year']) ? (int) $_POST['ref_year'] : (int) date('Y');
$whatsLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedUser) {
  $branding = nf_branding();
  $baseUrl = $branding['base'] ?? '';
  $planFile = $_FILES['plan_pdf'] ?? null;
  $hasPlanFile = is_array($planFile) && (($planFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
  $triggeredByPdfButton = isset($_POST['send_plan_pdf']);
  $shouldHandlePdf = $triggeredByPdfButton || ($hasPlanFile && isset($_POST['save_training']));
  $allowTraining = !$triggeredByPdfButton;
  $notices = [];

  // Upload e envio de plano alimentar em PDF
  if ($shouldHandlePdf) {
    if (!$hasPlanFile || ($planFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $error = 'Envie um arquivo PDF válido.';
    } else {
      $ext = strtolower(pathinfo($planFile['name'], PATHINFO_EXTENSION));
      if ($ext !== 'pdf') {
        $error = 'Apenas PDFs são permitidos.';
      } elseif (($planFile['size'] ?? 0) > 12 * 1024 * 1024) {
        $error = 'Arquivo muito grande (limite 12MB).';
      } else {
        $data = file_get_contents($planFile['tmp_name']);
        if ($data === false) {
          $error = 'Falha ao ler o PDF enviado.';
        } else {
          $savedId = plan_files_save_for_user(
            $selectedUser['email'],
            $planFile['name'],
            $planFile['type'] ?: 'application/pdf',
            (int) $planFile['size'],
            $data
          );
          if ($savedId) {
            $firstName = trim((string) ($selectedUser['name'] ?? ''));
            $firstName = $firstName !== '' ? explode(' ', $firstName)[0] : 'Olá';
            $msgLines = [
              "{$firstName}, seu plano alimentar foi atualizado na plataforma.",
              "Faça login para baixar o PDF em Planos & arquivos.",
            ];
            nf_send_student_notification(
              $selectedUser['email'],
              'Plano alimentar atualizado',
              $msgLines,
              'Acessar Planos',
              rtrim($baseUrl, '/') . '/area-planos'
            );
            $notices[] = 'PDF salvo de forma privada e link enviado por e-mail.';
            if (!empty($_POST['notify_whatsapp'])) {
              $phoneRaw = trim((string) ($selectedUser['phone'] ?? ''));
              $phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
              $waBase = $phoneDigits ? 'https://wa.me/' . $phoneDigits : 'https://wa.me/';
              $waMsg = "{$firstName}, atualizamos seu plano alimentar na NutremFit. Baixe em Planos & arquivos: " . rtrim($baseUrl, '/') . "/area-planos";
              $whatsLink = $waBase . '?text=' . urlencode($waMsg);
            }
          } else {
            $error = 'Falha ao salvar o PDF.';
          }
        }
      }
    }
  }

  if (isset($_POST['save_training']) && $error === '' && ($allowTraining || !$shouldHandlePdf)) {
    $title = trim($_POST['training_title'] ?? '');
    $instructions = trim($_POST['training_instructions'] ?? '');
    $notifyWhats = !empty($_POST['notify_whatsapp']);

    // fichas dinâmicas
    $sheetIdxList = $_POST['sheet_idx'] ?? [];
    $sheetTitles = $_POST['sheet_title'] ?? [];
    $sheetMonths = $_POST['sheet_month'] ?? [];
    $sheetYears = $_POST['sheet_year'] ?? [];
    $sheetMeta = [];
    foreach ($sheetIdxList as $k => $sid) {
      $sidClean = trim((string) $sid);
      if ($sidClean === '') {
        $sidClean = (string) ($k + 1);
      }
      $sheetMeta[$sidClean] = [
        'title' => trim($sheetTitles[$k] ?? ''),
        'month' => trim($sheetMonths[$k] ?? ''),
        'year' => trim($sheetYears[$k] ?? ''),
      ];
    }

    $names = $_POST['exercise_name'] ?? [];
    $videos = $_POST['exercise_video'] ?? [];
    $seriesList = $_POST['exercise_series'] ?? [];
    $repsList = $_POST['exercise_reps'] ?? [];
    $loadList = $_POST['exercise_load'] ?? [];
    $notesList = $_POST['exercise_notes'] ?? [];
    $orderList = $_POST['exercise_order'] ?? [];
    $sheetOfEx = $_POST['exercise_sheet_idx'] ?? [];

    $exercises = [];
    foreach ($names as $i => $name) {
      $nameClean = trim($name);
      if ($nameClean === '') {
        continue;
      }
      $sheetId = trim((string) ($sheetOfEx[$i] ?? ''));
      $meta = $sheetMeta[$sheetId] ?? ['title' => '', 'month' => '', 'year' => ''];
      $cuesPayload = training_pack_cues([
        'series' => $seriesList[$i] ?? '',
        'reps' => $repsList[$i] ?? '',
        'load' => $loadList[$i] ?? '',
        'notes' => $notesList[$i] ?? '',
        'order' => $orderList[$i] ?? '',
      ]);
      $exercises[] = [
        'name' => $nameClean,
        'video_url' => trim($videos[$i] ?? ''),
        'cues' => $cuesPayload,
        'day' => 'geral',
        'sheet_idx' => $sheetId,
        'sheet_title' => $meta['title'] ?? '',
        'sheet_ref_month' => $meta['month'] ?? '',
        'sheet_ref_year' => $meta['year'] ?? '',
      ];
    }
    try {
      training_store_save_for_user($selectedUser['email'], [
        'title' => $title,
        'instructions' => $instructions,
        'exercises' => $exercises,
      ]);
      $firstName = trim((string) ($selectedUser['name'] ?? ''));
      $firstName = $firstName !== '' ? explode(' ', $firstName)[0] : 'Olá';
      $lines = [
        "{$firstName}, seu treino foi atualizado na plataforma.",
        'Entre com seu login para ver a nova ficha.',
      ];
      nf_send_student_notification(
        $selectedUser['email'],
        'Seu treino foi atualizado',
        $lines,
        'Ver treino',
        rtrim($baseUrl, '/') . '/area-treinos'
      );
      $notices[] = 'Treino salvo e e-mail enviado ao aluno.';

      // Gerar PDF de backup automaticamente
      $planForPdf = training_store_find_for_user($selectedUser['email']);
      if ($planForPdf && !empty($planForPdf['exercises'])) {
        try {
          $pdfData = training_generate_pdf($planForPdf, $selectedUser);
          $backupPath = training_pdf_save_backup($pdfData, $selectedUser['email']);
          $notices[] = 'PDF de backup gerado.';

          // Enviar copia por e-mail ao admin
          $adminEmail = admin_notification_recipient();
          $pdfFilename = 'treino_' . preg_replace('/[^a-z0-9]/i', '_', mb_strtolower($selectedUser['name'] ?: $selectedUser['email'])) . '_' . date('Y-m-d') . '.pdf';
          nf_send_email_with_attachment(
            $adminEmail,
            'Backup treino: ' . ($selectedUser['name'] ?: $selectedUser['email']),
            ['Segue em anexo o PDF de backup do treino atualizado para ' . ($selectedUser['name'] ?: $selectedUser['email']) . '.'],
            $pdfData,
            $pdfFilename
          );
          $notices[] = 'Copia enviada ao e-mail do admin.';
        } catch (Throwable $pdfErr) {
          $notices[] = 'Aviso: nao foi possivel gerar o PDF de backup.';
          app_log('training_pdf_error', ['error' => $pdfErr->getMessage()]);
        }
      }

      if ($notifyWhats) {
        $phoneRaw = trim((string) ($selectedUser['phone'] ?? ''));
        $phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
        $waBase = $phoneDigits ? 'https://wa.me/' . $phoneDigits : 'https://wa.me/';
        $waMsg = "{$firstName}, atualizamos seu treino na NutremFit. Veja na Área do Aluno: " . rtrim($baseUrl, '/') . "/area-treinos";
        $whatsLink = $waBase . '?text=' . urlencode($waMsg);
      }
    } catch (Throwable $e) {
      $error = 'Erro ao salvar treino: ' . $e->getMessage();
    }
  } elseif (isset($_POST['delete_training'])) {
    admin_treinos_delete($selectedUser['email']);
    nf_send_student_notification(
      $selectedUser['email'],
      'Seu treino foi removido',
      [
        'Olá, removemos o treino anterior.',
        'Em breve disponibilizaremos uma nova ficha na plataforma.',
      ],
      'Abrir Área do Aluno',
      rtrim($baseUrl, '/') . '/area-treinos'
    );
    $notices[] = 'Treino removido e aluno notificado.';
  }

  if ($error === '' && $notices) {
    $feedback = implode(' ', $notices);
  }
}

$userTraining = $selectedUser ? training_store_find_for_user($selectedEmail) : null;
$recentCheckins = $selectedUser ? array_slice(array_reverse(checkin_store_for_user($selectedEmail)), 0, 5) : [];
$lastUpdated = $userTraining['updated_at'] ?? ($userTraining['created_at'] ?? null);
$exByDay = [];
if ($userTraining && !empty($userTraining['exercises'])) {
  foreach ($userTraining['exercises'] as $ex) {
    $d = $ex['day'] ?? 'geral';
    if (!isset($exByDay[$d])) {
      $exByDay[$d] = [];
    }
    $exByDay[$d][] = $ex;
  }
  // ajusta dias ativos para refletir a ficha atual
  $activeDays = array_unique(array_merge($activeDays, array_keys($exByDay)));
}
$calendarDays = [];
if ($refMonthSelected >= 1 && $refMonthSelected <= 12 && $refYearSelected > 2000) {
  $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $refMonthSelected, $refYearSelected);
  for ($d = 1; $d <= $daysInMonth; $d++) {
    $ts = strtotime(sprintf('%04d-%02d-%02d', $refYearSelected, $refMonthSelected, $d));
    $weekday = date('N', $ts); // 1 (Mon) to 7 (Sun)
    $slug = $dayOrder[$weekday - 1] ?? 'geral';
    $count = isset($exByDay[$slug]) ? count($exByDay[$slug]) : 0;
    $calendarDays[] = [
      'day' => $d,
      'slug' => $slug,
      'count' => $count,
    ];
  }
}

function nf_blank_exercise_row(): array
{
  return [
    'name' => '',
    'video_url' => '',
    'cues' => '',
    'day' => 'geral',
    'sheet_idx' => '',
    'sheet_title' => '',
    'sheet_ref_month' => '',
    'sheet_ref_year' => '',
  ];
}

// Organiza fichas existentes em grupos
$sheets = [];
$rawExercises = $userTraining['exercises'] ?? [];
if (!empty($rawExercises)) {
  foreach ($rawExercises as $ex) {
    $sid = trim((string) ($ex['sheet_idx'] ?? '')) ?: 'sheet1';
    if (!isset($sheets[$sid])) {
      $sheets[$sid] = [
        'id' => $sid,
        'title' => trim($ex['sheet_title'] ?? ''),
        'month' => trim($ex['sheet_ref_month'] ?? ''),
        'year' => trim($ex['sheet_ref_year'] ?? ''),
        'exercises' => [],
      ];
    }
    $sheets[$sid]['exercises'][] = $ex;
  }
}

if (empty($sheets)) {
  $sid = 'sheet1';
  $sheets[$sid] = [
    'id' => $sid,
    'title' => 'Ficha A',
    'month' => str_pad((string) $refMonthSelected, 2, '0', STR_PAD_LEFT),
    'year' => (string) $refYearSelected,
    'exercises' => [],
  ];
}

// garante ao menos 2 linhas por ficha
foreach ($sheets as &$sheet) {
  $sheet['exercises'] = array_values($sheet['exercises']);
  $minRows = 2;
  $need = $minRows - count($sheet['exercises']);
  for ($i = 0; $i < $need; $i++) {
    $blank = nf_blank_exercise_row();
    $blank['sheet_idx'] = $sheet['id'];
    $blank['sheet_title'] = $sheet['title'];
    $blank['sheet_ref_month'] = $sheet['month'];
    $blank['sheet_ref_year'] = $sheet['year'];
    $sheet['exercises'][] = $blank;
  }
}
unset($sheet);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<?php include './partials/head.php'; ?>

<body class="area-shell">
  <?php include './partials/preloader.php' ?>
  <?php include './partials/header.php' ?>

  <section class="section-top text-center">
    <div class="container">
      <h1>Treinos por aluno</h1>
      <p style="max-width:760px;margin:12px auto 0;color:rgba(255,255,255,0.78);">
        Escolha um aluno, veja a ficha e vincule exercícios por dia da semana. Ao salvar, o aluno é notificado por
        e-mail.
      </p>
    </div>
  </section>

  <section class="dashboard-wrap py-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
          <?php $area_nav_active = 'admin_treinos';
          include './partials/area-nav.php'; ?>
        </div>
        <div class="col-lg-8 col-xl-9">
          <div class="dash-card mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <h4>Selecione o aluno</h4>
              <form method="get" class="d-flex gap-2 align-items-center" id="student-select-form">
                <div style="position:relative;flex:1;">
                  <input type="text" id="student-search" list="student-list" class="form-control"
                    placeholder="Digite o nome do aluno..."
                    value="<?php echo htmlspecialchars($selectedUser['name'] ?: $selectedEmail, ENT_QUOTES, 'UTF-8'); ?>"
                    autocomplete="off">
                  <datalist id="student-list">
                    <?php foreach ($users as $u): ?>
                      <option value="<?php echo htmlspecialchars($u['name'] ?: $u['email'], ENT_QUOTES, 'UTF-8'); ?>">
                      </option>
                    <?php endforeach; ?>
                  </datalist>
                  <input type="hidden" name="user" id="student-email"
                    value="<?php echo htmlspecialchars($selectedEmail, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <button class="btn_one" type="submit">Abrir ficha</button>
              </form>
              <?php
              $completionCount = $selectedUser ? completion_store_count_month($selectedEmail) : 0;
              ?>
              <span class="badge-soft mt-2" id="admin-completion-badge" data-count-url="/training-completion-count"
                data-user-email="<?php echo htmlspecialchars($selectedEmail, ENT_QUOTES, 'UTF-8'); ?>"
                style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:10px;background:<?php echo $completionCount > 0 ? 'rgba(0,255,153,0.1)' : 'rgba(255,255,255,0.06)'; ?>;border:1px solid <?php echo $completionCount > 0 ? 'rgba(0,255,153,0.3)' : 'rgba(255,255,255,0.14)'; ?>;color:<?php echo $completionCount > 0 ? '#a7ffd9' : 'rgba(255,255,255,0.75)'; ?>;font-size:13px;">
                <strong id="admin-completion-count"><?php echo $completionCount; ?></strong>
                <span id="admin-completion-label">treino<?php echo $completionCount !== 1 ? 's' : ''; ?>
                  concluído<?php echo $completionCount !== 1 ? 's' : ''; ?> este mês</span>
              </span>
            </div>
            <?php if ($feedback): ?>
              <div class="alert alert-success mt-3"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </div>

          <?php if ($selectedUser): ?>
            <?php
            // Export catalog to JS
            $randCatalog = randomizer_catalog();
            $randPrograms = randomizer_programs();
            ?>
            <div class="dash-card mb-4" id="randomizer-block">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                  <h5 class="mb-1" style="color:#ff7a00;letter-spacing:.5px;">🎲 Randomizador de Fichas</h5>
                  <small style="color:rgba(255,255,255,0.6);">Monte a ficha por sorteio, edite e transfira para o
                    formulário abaixo.</small>
                </div>
                <div class="d-flex gap-2 flex-wrap" id="program-selector">
                  <div class="form-check me-2 d-flex align-items-center" style="margin-bottom:0;">
                    <input class="form-check-input" type="checkbox" id="rand-bw-checkbox" value="1"
                      style="margin-top:0; border-color:rgba(255,122,0,0.5);">
                    <label class="form-check-label ms-2" for="rand-bw-checkbox"
                      style="color:rgba(255,255,255,0.8);font-size:13px;cursor:pointer;">
                      Somente Peso Corporal
                    </label>
                  </div>
                  <button type="button" class="btn-prog" data-prog="geral">Geral</button>
                  <button type="button" class="btn-prog" data-prog="ab">A / B</button>
                  <button type="button" class="btn-prog" data-prog="abc">A / B / C</button>
                  <button type="button" class="btn-prog" data-prog="abg">A / B / G</button>
                  <button type="button" class="btn-prog" data-prog="circuito">Circuito</button>
                </div>
              </div>
              <div id="randomizer-sheets"></div>
              <div id="randomizer-actions" style="display:none;" class="d-flex gap-2 flex-wrap align-items-center mt-3">
                <button type="button" id="rand-transfer-btn" class="btn_one">✔ Transferir para Fichas</button>
                <button type="button" id="rand-reset-btn" class="btn_two" style="opacity:.7;font-size:13px;">↺
                  Limpar</button>
                <small style="color:rgba(255,255,255,0.5);margin-left:4px;">Os exercícios serão adicionados às fichas do
                  formulário abaixo.</small>
              </div>
            </div>
            <style>
              .btn-prog {
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(255, 122, 0, 0.35);
                color: rgba(255, 255, 255, 0.82);
                padding: 6px 16px;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all .18s;
              }

              .btn-prog:hover,
              .btn-prog.active {
                background: rgba(255, 122, 0, 0.18);
                border-color: #ff7a00;
                color: #ff7a00;
              }

              .rand-sheet {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 16px;
              }

              .rand-sheet-title {
                font-weight: 700;
                font-size: 14px;
                color: #ff7a00;
                margin-bottom: 10px;
                letter-spacing: .5px;
                border-bottom: 1px solid rgba(255, 122, 0, 0.2);
                padding-bottom: 6px;
              }

              .rand-row {
                display: grid;
                grid-template-columns: 140px 1fr auto;
                gap: 8px;
                align-items: center;
                background: rgba(255, 255, 255, 0.04);
                border: 1px solid rgba(255, 255, 255, 0.07);
                border-radius: 6px;
                padding: 8px 10px;
                margin-bottom: 6px;
                transition: border-color .18s;
              }

              .rand-row:hover {
                border-color: rgba(255, 122, 0, 0.3);
              }

              .rand-group-label {
                font-size: 12px;
                font-weight: 700;
                color: rgba(255, 122, 0, 0.9);
                letter-spacing: .3px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
              }

              .rand-exercise-name {
                font-size: 13px;
                color: rgba(255, 255, 255, 0.9);
                line-height: 1.3;
                background: transparent;
                border: none;
                width: 100%;
                outline: none;
                padding: 0;
              }

              .rand-exercise-name:focus {
                color: #fff;
              }

              .rand-row-actions {
                display: flex;
                gap: 4px;
                white-space: nowrap;
              }

              .rand-btn {
                background: transparent;
                border: 1px solid rgba(255, 255, 255, 0.15);
                color: rgba(255, 255, 255, 0.6);
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 11px;
                cursor: pointer;
                transition: all .15s;
              }

              .rand-btn:hover {
                border-color: #ff7a00;
                color: #ff7a00;
              }

              .rand-btn.danger:hover {
                border-color: #f55;
                color: #f55;
              }

              .rand-add-manual {
                background: transparent;
                border: 1px dashed rgba(255, 255, 255, 0.2);
                color: rgba(255, 255, 255, 0.5);
                padding: 5px 14px;
                border-radius: 4px;
                font-size: 12px;
                cursor: pointer;
                transition: all .18s;
                width: 100%;
                margin-top: 4px;
                text-align: left;
              }

              .rand-add-manual:hover {
                border-color: #ff7a00;
                color: #ff7a00;
              }

              .rand-manual-tag {
                font-size: 10px;
                background: rgba(0, 200, 100, 0.15);
                border: 1px solid rgba(0, 200, 100, 0.3);
                color: #5fd;
                border-radius: 3px;
                padding: 1px 5px;
                margin-left: 4px;
              }

              @media(max-width:600px) {
                .rand-row {
                  grid-template-columns: 1fr;
                }

                .rand-row-actions {
                  margin-top: 4px;
                }
              }
            </style>
            <script>
              (function () {
                var PROGRAMS = <?php echo json_encode($randPrograms, JSON_UNESCAPED_UNICODE); ?>;
                var currentProg = null;

                function escHtml(s) {
                  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                }

                function makeRowHtml(group, exName, exUrl, isManual) {
                  var manualTag = isManual ? '<span class="rand-manual-tag">Manual</span>' : '';
                  return '<div class="rand-group-label" title="' + escHtml(group) + '">' + escHtml(group) + manualTag + '</div>' +
                    '<input class="rand-exercise-name" type="text" value="' + escHtml(exName) + '" placeholder="Nome do exercício..." autocomplete="off">' +
                    '<div class="rand-row-actions">' +
                    (isManual ? '' : '<button type="button" class="rand-btn btn-trocar" title="Trocar exercício">↺</button>') +
                    '<button type="button" class="rand-btn btn-duplicar" title="Duplicar">⧉</button>' +
                    '<button type="button" class="rand-btn danger btn-excluir" title="Excluir">✕</button>' +
                    '</div>';
                }

                function createRow(group, ex, isManual) {
                  var row = document.createElement('div');
                  row.className = 'rand-row';
                  row.dataset.group = group;
                  row.dataset.url = ex.url || '';
                  row.dataset.manual = isManual ? '1' : '0';
                  row.innerHTML = makeRowHtml(group, ex.name || '', ex.url || '', isManual);
                  bindRowEvents(row);
                  return row;
                }

                function bindRowEvents(row) {
                  var btnTrocar = row.querySelector('.btn-trocar');
                  var btnDuplicar = row.querySelector('.btn-duplicar');
                  var btnExcluir = row.querySelector('.btn-excluir');

                  if (btnTrocar) {
                    btnTrocar.onclick = function () {
                      var g = row.dataset.group;
                      btnTrocar.textContent = '...';
                      var bwParam = document.getElementById('rand-bw-checkbox') && document.getElementById('rand-bw-checkbox').checked ? '&bw=1' : '';
                      fetch('randomizer-api.php?group=' + encodeURIComponent(g) + bwParam)
                        .then(r => r.json())
                        .then(data => {
                          row.querySelector('.rand-exercise-name').value = data.name || '';
                          row.dataset.url = data.url || '';
                          btnTrocar.textContent = '↺';
                        }).catch(() => { btnTrocar.textContent = '↺'; });
                    };
                  }
                  if (btnDuplicar) {
                    btnDuplicar.onclick = function () {
                      var c = row.cloneNode(true);
                      row.parentNode.insertBefore(c, row.nextSibling);
                      bindRowEvents(c);
                    };
                  }
                  if (btnExcluir) {
                    btnExcluir.onclick = function () {
                      if (row.parentNode.querySelectorAll('.rand-row').length > 1) {
                        row.remove();
                      } else {
                        row.querySelector('.rand-exercise-name').value = '';
                      }
                    };
                  }
                }

                function renderProgram(prog) {
                  currentProg = prog;
                  var sheets = PROGRAMS[prog];
                  var container = document.getElementById('randomizer-sheets');
                  container.innerHTML = '<div style="color:#ff7a00;padding:20px;font-weight:bold;font-size:14px;display:flex;align-items:center;gap:10px;"><div class="spinner-border spinner-border-sm" role="status"></div> Sorteando exercícios...</div>';
                  document.getElementById('randomizer-actions').style.display = 'none';

                  var neededGroups = [];
                  Object.keys(sheets).forEach(function (sheetLabel) {
                    sheets[sheetLabel].forEach(function (g) { neededGroups.push(g); });
                  });

                  // AJAX list 
                  var bwParam = document.getElementById('rand-bw-checkbox') && document.getElementById('rand-bw-checkbox').checked ? '&bw=1' : '';
                  fetch('randomizer-api.php?batch=' + encodeURIComponent(neededGroups.join(',')) + bwParam)
                    .then(r => r.json())
                    .then(data => {
                      container.innerHTML = '';
                      Object.keys(sheets).forEach(function (sheetLabel) {
                        var groups = sheets[sheetLabel];
                        var sheetEl = document.createElement('div');
                        sheetEl.className = 'rand-sheet';
                        sheetEl.dataset.sheetLabel = sheetLabel;
                        var title = document.createElement('div');
                        title.className = 'rand-sheet-title';
                        title.textContent = sheetLabel;
                        sheetEl.appendChild(title);
                        var list = document.createElement('div');
                        list.className = 'rand-rows-list';
                        groups.forEach(function (group) {
                          var ex = data[group] || { name: '', url: '' };
                          list.appendChild(createRow(group, ex, false));
                        });
                        sheetEl.appendChild(list);
                        var addBtn = document.createElement('button');
                        addBtn.type = 'button';
                        addBtn.className = 'rand-add-manual';
                        addBtn.textContent = '+ Adicionar exercício manual';
                        addBtn.onclick = function () {
                          list.appendChild(createRow('Manual', { name: '', url: '' }, true));
                        };
                        sheetEl.appendChild(addBtn);
                        container.appendChild(sheetEl);
                      });
                      document.getElementById('randomizer-actions').style.display = 'flex';
                    })
                    .catch(e => {
                      container.innerHTML = '<div style="color:#f55;padding:20px;">Erro ao sortear exercícios. Tente novamente.</div>';
                    });
                }

                // Program selector buttons
                document.querySelectorAll('.btn-prog').forEach(function (btn) {
                  btn.addEventListener('click', function () {
                    document.querySelectorAll('.btn-prog').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    renderProgram(btn.dataset.prog);
                  });
                });

                // Transfer to form
                document.getElementById('rand-transfer-btn').addEventListener('click', function () {
                  var sheetsContainer = document.getElementById('sheets-container');
                  if (!sheetsContainer) { alert('Formulário de fichas não encontrado.'); return; }

                  var randSheets = document.querySelectorAll('#randomizer-sheets .rand-sheet');
                  if (!randSheets.length) { alert('Nenhuma ficha gerada. Selecione um programa primeiro.'); return; }

                  // Remove existing empty sheets (sheets without any exercise names typed)
                  var existingCards = sheetsContainer.querySelectorAll('.sheet-card');
                  var hasData = false;
                  existingCards.forEach(function (card) {
                    var names = card.querySelectorAll('input[name="exercise_name[]"]');
                    names.forEach(function (inp) { if (inp.value.trim()) hasData = true; });
                  });

                  // If form is empty, clear it and replace
                  if (!hasData) {
                    sheetsContainer.innerHTML = '';
                  }

                  randSheets.forEach(function (randSheet) {
                    var label = randSheet.dataset.sheetLabel || 'Ficha';
                    var rows = randSheet.querySelectorAll('.rand-row');
                    var sid = 'rand_' + Date.now() + '_' + Math.floor(Math.random() * 9999);

                    // Build sheet card HTML matching existing structure
                    var cardHtml = '<div class="dash-card mb-3 sheet-card" data-sheet-id="' + sid + '">' +
                      '<div class="row g-2 align-items-end">' +
                      '<div class="col-md-6"><label class="form-label">Ficha</label>' +
                      '<input type="text" name="sheet_title[]" class="form-control" value="' + escHtml(label) + '">' +
                      '<input type="hidden" name="sheet_idx[]" value="' + sid + '">' +
                      '</div>' +
                      '<div class="col-md-3"><label class="form-label">Mês de referência</label>' +
                      '<input type="text" name="sheet_month[]" class="form-control" value="' + (new Date().getMonth() + 1) + '">' +
                      '</div>' +
                      '<div class="col-md-3"><label class="form-label">Ano</label>' +
                      '<input type="text" name="sheet_year[]" class="form-control" value="' + new Date().getFullYear() + '">' +
                      '</div>' +
                      '</div>' +
                      '<div class="exercise-list mt-3" data-sheet-id="' + sid + '"></div>' +
                      '<div class="d-flex justify-content-between align-items-center mt-2">' +
                      '<button type="button" class="btn_one btn-sm add-exercise" data-sheet-id="' + sid + '">Adicionar exercício</button>' +
                      '<button type="button" class="btn_two btn-sm remove-sheet">Remover ficha</button>' +
                      '</div>' +
                      '</div>';

                    sheetsContainer.insertAdjacentHTML('beforeend', cardHtml);
                    var newCard = sheetsContainer.lastElementChild;
                    var exList = newCard.querySelector('.exercise-list');

                    rows.forEach(function (row, idx) {
                      var name = (row.querySelector('.rand-exercise-name') || {}).value || '';
                      if (!name.trim()) return;
                      var url = row.dataset.url || '';
                      var group = row.dataset.group || '';
                      var rowHtml = '<div class="exercise-row card p-2 mb-2" style="background:#0f1320;border:1px solid rgba(255,255,255,0.08);">' +
                        '<div class="drag-handle" style="cursor:grab;text-align:center;color:rgba(255,255,255,0.3);padding:2px 0 4px;font-size:16px;user-select:none;line-height:1;" title="Arraste para reordenar">&#9776;</div>' +
                        '<input type="hidden" name="exercise_sheet_idx[]" value="' + sid + '">' +
                        '<input type="hidden" name="exercise_order[]" value="' + (idx + 1) + '">' +
                        '<div class="row g-2 align-items-center">' +
                        '<div class="col-md-6"><label class="form-label mb-1">Nome do exercício:</label>' +
                        '<input type="text" name="exercise_name[]" class="form-control form-control-sm exercise-name" list="nf-library-options" value="' + escHtml(name) + '">' +
                        '</div>' +
                        '<div class="col-md-2"><label class="form-label mb-1">Série</label>' +
                        '<input type="text" name="exercise_series[]" class="form-control form-control-sm" placeholder="Ex: 3x">' +
                        '</div>' +
                        '<div class="col-md-2"><label class="form-label mb-1">Rep.</label>' +
                        '<input type="text" name="exercise_reps[]" class="form-control form-control-sm" placeholder="Ex: 12">' +
                        '</div>' +
                        '<div class="col-md-2"><label class="form-label mb-1">Carga</label>' +
                        '<input type="text" name="exercise_load[]" class="form-control form-control-sm" placeholder="Ex: 30kg">' +
                        '</div>' +
                        '<div class="col-md-12"><input type="text" name="exercise_video[]" class="form-control form-control-sm mt-1 exercise-video" placeholder="Link do vídeo (opcional)" value="' + escHtml(url) + '"></div>' +
                        '<div class="col-md-12"><input type="text" name="exercise_notes[]" class="form-control form-control-sm mt-1" placeholder="Observações (grupo: ' + escHtml(group) + ')"></div>' +
                        '</div>' +
                        '<div class="text-end mt-2"><button type="button" class="btn_two btn-sm remove-exercise">Remover exercício</button></div>' +
                        '</div>';
                      exList.insertAdjacentHTML('beforeend', rowHtml);
                    });

                    // Trigger existing JS to attach sortable/events
                    if (typeof window.nfSyncExerciseOrders === 'function') {
                      window.nfSyncExerciseOrders();
                    }
                    var ev = new Event('nf-sheet-added', { bubbles: true });
                    newCard.dispatchEvent(ev);
                    if (typeof window._nfAttachSheet === 'function') {
                      window._nfAttachSheet(newCard);
                    }
                  });

                  sheetsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

                  var tip = document.createElement('div');
                  tip.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#ff7a00;color:#000;font-weight:700;padding:10px 18px;border-radius:8px;z-index:9999;font-size:14px;box-shadow:0 4px 20px rgba(0,0,0,.4);';
                  tip.textContent = '✔ Fichas transferidas! Revise e salve abaixo.';
                  document.body.appendChild(tip);
                  setTimeout(function () { tip.remove(); }, 4000);

                  if (typeof window.scheduleAutosave === 'function') window.scheduleAutosave();
                });

                // Reset
                document.getElementById('rand-reset-btn').addEventListener('click', function () {
                  if (confirm('Limpar o randomizador?')) {
                    document.getElementById('randomizer-sheets').innerHTML = '';
                    document.getElementById('randomizer-actions').style.display = 'none';
                    document.querySelectorAll('.btn-prog').forEach(function (b) { b.classList.remove('active'); });
                    currentProg = null;
                  }
                });
              })();
            </script>

            <div class="dash-card mb-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                  <h5 class="mb-1">Referência anual / período</h5>
                  <small style="color:rgba(255,255,255,0.75);">Título livre: Ficha A, Ficha B, Ficha Geral, etc.</small>
                </div>
                <?php if ($lastUpdated): ?>
                  <span class="plan-badge" style="background:rgba(255,255,255,0.08);border:none;">Última ficha:
                    <?php echo date('d/m/Y H:i', strtotime($lastUpdated)); ?></span>
                <?php endif; ?>
              </div>
              <form id="training-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="user_email"
                  value="<?php echo htmlspecialchars($selectedUser['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row g-3 mb-3">
                  <div class="col-md-8">
                    <label class="form-label">Referência geral (opcional)</label>
                    <input type="text" name="training_title" class="form-control"
                      value="<?php echo htmlspecialchars($userTraining['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                      placeholder="Ex.: Fichas A/B/C - Novembro 2025">
                  </div>
                </div>
                <div class="dash-card mb-3"
                  style="background:rgba(255,255,255,0.03);border:1px dashed rgba(255,255,255,0.12);">
                  <div class="row g-2 align-items-end">
                    <div class="col-md-9">
                      <label class="form-label">Enviar plano alimentar (PDF)</label>
                      <input type="file" name="plan_pdf" accept="application/pdf" class="form-control">
                      <small class="text-muted">Opcional. Enviaremos o link por e-mail ao aluno.</small>
                    </div>
                    <div class="col-md-3 text-end">
                      <button type="submit" name="send_plan_pdf" value="1" class="btn_two w-100" formaction=""
                        formmethod="post">Enviar PDF</button>
                    </div>
                  </div>
                </div>
                <div class="mt-3">
                  <label class="form-label">Fichas e exercícios</label>
                  <datalist id="nf-library-options">
                    <?php foreach ($libraryMap as $nameKey => $url): ?>
                      <option value="<?php echo htmlspecialchars($nameKey, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </datalist>
                  <div class="d-flex flex-wrap gap-2 mb-3 align-items-center justify-content-between">
                    <div>
                      <strong style="color:rgba(255,255,255,0.9);">Monte quantas fichas quiser</strong>
                      <small class="text-muted d-block">Adicione/remova fichas e exercícios como no layout de
                        exemplo.</small>
                    </div>
                    <button type="button" class="btn_two btn-sm" id="add-sheet">Adicionar ficha</button>
                  </div>
                  <div id="sheets-container">
                    <?php foreach ($sheets as $sheet): ?>
                      <div class="dash-card mb-3 sheet-card"
                        data-sheet-id="<?php echo htmlspecialchars($sheet['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="row g-2 align-items-end">
                          <div class="col-md-6">
                            <label class="form-label">Ficha</label>
                            <input type="text" name="sheet_title[]" class="form-control"
                              value="<?php echo htmlspecialchars($sheet['title'], ENT_QUOTES, 'UTF-8'); ?>"
                              placeholder="Ficha A, Ficha B...">
                            <input type="hidden" name="sheet_idx[]"
                              value="<?php echo htmlspecialchars($sheet['id'], ENT_QUOTES, 'UTF-8'); ?>">
                          </div>
                          <div class="col-md-3">
                            <label class="form-label">Mês de referência</label>
                            <input type="text" name="sheet_month[]" class="form-control"
                              value="<?php echo htmlspecialchars($sheet['month'], ENT_QUOTES, 'UTF-8'); ?>"
                              placeholder="11">
                          </div>
                          <div class="col-md-3">
                            <label class="form-label">Ano</label>
                            <input type="text" name="sheet_year[]" class="form-control"
                              value="<?php echo htmlspecialchars($sheet['year'], ENT_QUOTES, 'UTF-8'); ?>"
                              placeholder="2025">
                          </div>
                        </div>
                        <div class="exercise-list mt-3"
                          data-sheet-id="<?php echo htmlspecialchars($sheet['id'], ENT_QUOTES, 'UTF-8'); ?>">
                          <?php foreach ($sheet['exercises'] as $rowIndex => $ex): ?>
                            <?php $parsedCues = training_parse_cues($ex['cues'] ?? ''); ?>
                            <div class="exercise-row card p-2 mb-2"
                              style="background:#0f1320;border:1px solid rgba(255,255,255,0.08);">
                              <div class="drag-handle"
                                style="cursor:grab;text-align:center;color:rgba(255,255,255,0.3);padding:2px 0 4px;font-size:16px;user-select:none;line-height:1;"
                                title="Arraste para reordenar">&#x2630;</div>
                              <input type="hidden" name="exercise_sheet_idx[]"
                                value="<?php echo htmlspecialchars($sheet['id'], ENT_QUOTES, 'UTF-8'); ?>">
                              <input type="hidden" name="exercise_order[]"
                                value="<?php echo htmlspecialchars((string) (($parsedCues['order'] !== '' ? $parsedCues['order'] : ($rowIndex + 1))), ENT_QUOTES, 'UTF-8'); ?>">
                              <div class="row g-2 align-items-center">
                                <div class="col-md-6">
                                  <label class="form-label mb-1">Nome do exercício:</label>
                                  <input type="text" name="exercise_name[]" class="form-control form-control-sm exercise-name"
                                    list="nf-library-options" placeholder="Ex: abdução de ombros com halteres"
                                    value="<?php echo htmlspecialchars($ex['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-2">
                                  <label class="form-label mb-1">Série</label>
                                  <input type="text" name="exercise_series[]" class="form-control form-control-sm"
                                    placeholder="Ex: 3x"
                                    value="<?php echo htmlspecialchars($parsedCues['series'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-2">
                                  <label class="form-label mb-1">Rep.</label>
                                  <input type="text" name="exercise_reps[]" class="form-control form-control-sm"
                                    placeholder="Ex: 12"
                                    value="<?php echo htmlspecialchars($parsedCues['reps'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-2">
                                  <label class="form-label mb-1">Carga</label>
                                  <input type="text" name="exercise_load[]" class="form-control form-control-sm"
                                    placeholder="Ex: 30kg"
                                    value="<?php echo htmlspecialchars($parsedCues['load'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-12">
                                  <input type="text" name="exercise_video[]"
                                    class="form-control form-control-sm mt-1 exercise-video"
                                    placeholder="Link do vídeo (opcional)"
                                    value="<?php echo htmlspecialchars($ex['video_url'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-12">
                                  <input type="text" name="exercise_notes[]" class="form-control form-control-sm mt-1"
                                    placeholder="Observações (respiração, cadência, etc.)"
                                    value="<?php echo htmlspecialchars($parsedCues['notes'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                              </div>
                              <div class="text-end mt-2">
                                <button type="button" class="btn_two btn-sm remove-exercise">Remover exercício</button>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                          <button type="button" class="btn_one btn-sm add-exercise"
                            data-sheet-id="<?php echo htmlspecialchars($sheet['id'], ENT_QUOTES, 'UTF-8'); ?>">Adicionar
                            exercício</button>
                          <button type="button" class="btn_two btn-sm remove-sheet">Remover ficha</button>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
                  <div class="form-check d-flex align-items-center gap-2">
                    <input class="form-check-input" type="checkbox" name="notify_whatsapp" value="1" id="notifyWhatsapp">
                    <label class="form-check-label" for="notifyWhatsapp" style="color:rgba(255,255,255,0.8);">Notificar
                      via WhatsApp</label>
                  </div>
                  <div class="small text-muted" id="autosave-status" aria-live="polite">Auto-salvamento aguardando
                    alterações.</div>
                  <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="save_training" value="1" class="btn_one">Salvar e notificar</button>
                    <?php if ($userTraining): ?>
                      <a href="/training-pdf-download?user=<?php echo urlencode($selectedEmail); ?>" class="btn_one"
                        style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);text-decoration:none;display:inline-flex;align-items:center;gap:6px;"
                        title="Baixar PDF do treino atual">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                          <polyline points="7 10 12 15 17 10" />
                          <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        Baixar PDF
                      </a>
                      <button type="submit" name="delete_training" value="1" class="btn_two"
                        onclick="return confirm('Remover treino deste aluno?')">Remover treino</button>
                    <?php endif; ?>
                  </div>
                </div>
              </form>
            </div>

            <?php
            // Historico de backups em PDF
            $pdfBackups = training_pdf_list_backups($selectedEmail);
            if (!empty($pdfBackups)):
              ?>
              <div class="dash-card mb-4">
                <h5 class="mb-3" style="color:rgba(255,255,255,0.9);">
                  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    style="vertical-align:text-bottom;margin-right:6px;">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                  Backups de treino (PDF)
                </h5>
                <div style="max-height:240px;overflow-y:auto;">
                  <table class="table table-sm" style="color:rgba(255,255,255,0.85);font-size:13px;">
                    <thead>
                      <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                        <th style="color:rgba(255,255,255,0.6);font-weight:600;">Data</th>
                        <th style="color:rgba(255,255,255,0.6);font-weight:600;">Tamanho</th>
                        <th style="color:rgba(255,255,255,0.6);font-weight:600;text-align:right;">Acao</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($pdfBackups as $bk): ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                          <td><?php echo htmlspecialchars($bk['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                          <td><?php echo number_format(($bk['size'] ?? 0) / 1024, 1, ',', '.'); ?> KB</td>
                          <td style="text-align:right;">
                            <a href="/training-pdf-download?file=<?php echo urlencode($bk['filename']); ?>"
                              class="text-warning" style="text-decoration:none;font-weight:600;font-size:12px;">
                              Baixar
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>

          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php include './partials/footer.php' ?>
  <?php include './partials/script.php' ?>
  <script>
    (function () {
      // mapa de biblioteca para auto-preencher vídeo
      const libMap = <?php echo json_encode($libraryMap); ?>;
      const trainingForm = document.getElementById('training-form');
      const autosaveStatus = document.getElementById('autosave-status');
      const autosaveUrl = '/training-autosave';
      let autosaveTimer = null;
      let autosaveInFlight = false;

      function setAutosaveStatus(text, isError = false) {
        if (!autosaveStatus) return;
        autosaveStatus.textContent = text;
        autosaveStatus.style.color = isError ? '#f5b5b5' : 'rgba(255,255,255,0.75)';
      }

      function hasAnyExerciseFilled() {
        if (!trainingForm) return false;
        const fields = trainingForm.querySelectorAll('input[name="exercise_name[]"]');
        return Array.from(fields).some(inp => (inp.value || '').trim() !== '');
      }

      window.scheduleAutosave = scheduleAutosave;
      function scheduleAutosave() {
        if (!trainingForm) return;
        if (!hasAnyExerciseFilled()) {
          setAutosaveStatus('Digite o nome de um exercício para salvar automaticamente.');
          return;
        }
        if (autosaveTimer) {
          clearTimeout(autosaveTimer);
        }
        autosaveTimer = setTimeout(runAutosave, 900);
      }

      function runAutosave() {
        if (!trainingForm || !hasAnyExerciseFilled()) {
          return;
        }
        if (autosaveInFlight) {
          scheduleAutosave();
          return;
        }
        autosaveInFlight = true;
        setAutosaveStatus('Salvando automaticamente...');
        const fd = new FormData(trainingForm);
        fd.delete('plan_pdf'); // evita reupload de PDF a cada mudança
        fetch(autosaveUrl, {
          method: 'POST',
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
          .then(res => res.json())
          .then(data => {
            if (data && data.ok) {
              const label = data.updated_at ? new Date(data.updated_at).toLocaleString('pt-BR') : 'agora';
              setAutosaveStatus('Salvo automaticamente (' + label + ').');
            } else {
              setAutosaveStatus((data && data.message) ? data.message : 'Não foi possível salvar automaticamente.', true);
            }
          })
          .catch(() => setAutosaveStatus('Erro de rede ao salvar automaticamente.', true))
          .finally(() => { autosaveInFlight = false; });
      }

      function fillFromLibrary(row) {
        const nameInput = row.querySelector('.exercise-name');
        const videoInput = row.querySelector('.exercise-video');
        if (!nameInput || !videoInput) return;
        const key = (nameInput.value || '').trim().toLowerCase();
        if (key && libMap[key] && (!videoInput.value || videoInput.value.trim() === '')) {
          videoInput.value = libMap[key];
        }
      }
      function attachExerciseRow(row) {
        const nameInput = row.querySelector('.exercise-name');
        if (nameInput) {
          nameInput.addEventListener('change', function () { fillFromLibrary(row); });
          nameInput.addEventListener('blur', function () { fillFromLibrary(row); });
          nameInput.addEventListener('input', scheduleAutosave);
        }
        row.querySelectorAll('input[type="text"], input[type="number"]').forEach((inp) => {
          inp.addEventListener('input', scheduleAutosave);
        });
        const removeBtn = row.querySelector('.remove-exercise');
        if (removeBtn) {
          removeBtn.addEventListener('click', function () {
            const list = row.parentElement;
            if (list && list.children.length > 1) {
              row.remove();
            } else if (list) {
              list.querySelectorAll('input').forEach(inp => { if (inp.type !== 'hidden') inp.value = ''; });
            }
            scheduleAutosave();
          });
        }
      }
      document.querySelectorAll('.exercise-row').forEach(attachExerciseRow);
      if (trainingForm) {
        const titleInput = trainingForm.querySelector('input[name="training_title"]');
        if (titleInput) {
          titleInput.addEventListener('input', scheduleAutosave);
        }
        trainingForm.querySelectorAll('input[name="sheet_title[]"], input[name="sheet_month[]"], input[name="sheet_year[]"]').forEach((inp) => {
          inp.addEventListener('input', scheduleAutosave);
        });
      }

      const sheetsContainer = document.getElementById('sheets-container');
      const sheetTemplate = (sid) => `
          <div class="dash-card mb-3 sheet-card" data-sheet-id="${sid}">
            <div class="row g-2 align-items-end">
              <div class="col-md-6">
                <label class="form-label">Ficha</label>
                <input type="text" name="sheet_title[]" class="form-control" placeholder="Ficha A, Ficha B...">
                <input type="hidden" name="sheet_idx[]" value="${sid}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Mês de referência</label>
                <input type="text" name="sheet_month[]" class="form-control" placeholder="11">
              </div>
              <div class="col-md-3">
                <label class="form-label">Ano</label>
                <input type="text" name="sheet_year[]" class="form-control" placeholder="2025">
              </div>
            </div>
            <div class="exercise-list mt-3" data-sheet-id="${sid}"></div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <button type="button" class="btn_one btn-sm add-exercise" data-sheet-id="${sid}">Adicionar exercício</button>
              <button type="button" class="btn_two btn-sm remove-sheet">Remover ficha</button>
            </div>
          </div>
        `;
      const exerciseTemplate = (sid) => `
          <div class="exercise-row card p-2 mb-2" style="background:#0f1320;border:1px solid rgba(255,255,255,0.08);">
            <div class="drag-handle" style="cursor:grab;text-align:center;color:rgba(255,255,255,0.3);padding:2px 0 4px;font-size:16px;user-select:none;line-height:1;" title="Arraste para reordenar">&#9776;</div>
            <input type="hidden" name="exercise_sheet_idx[]" value="${sid}">
            <input type="hidden" name="exercise_order[]" value="">
            <div class="row g-2 align-items-center">
              <div class="col-md-6">
                <label class="form-label mb-1">Nome do exercício:</label>
                <input type="text" name="exercise_name[]" class="form-control form-control-sm exercise-name" list="nf-library-options" placeholder="Ex: abdução de ombros com halteres">
              </div>
              <div class="col-md-2">
                <label class="form-label mb-1">Série</label>
                <input type="text" name="exercise_series[]" class="form-control form-control-sm" placeholder="Ex: 3x">
              </div>
              <div class="col-md-2">
                <label class="form-label mb-1">Rep.</label>
                <input type="text" name="exercise_reps[]" class="form-control form-control-sm" placeholder="Ex: 12">
              </div>
              <div class="col-md-2">
                <label class="form-label mb-1">Carga</label>
                <input type="text" name="exercise_load[]" class="form-control form-control-sm" placeholder="Ex: 30kg">
              </div>
              <div class="col-md-12">
                <input type="text" name="exercise_video[]" class="form-control form-control-sm mt-1 exercise-video" placeholder="Link do vídeo (opcional)">
              </div>
              <div class="col-md-12">
                <input type="text" name="exercise_notes[]" class="form-control form-control-sm mt-1" placeholder="Observações (respiração, cadência, etc.)">
              </div>
            </div>
            <div class="text-end mt-2">
              <button type="button" class="btn_two btn-sm remove-exercise">Remover exercício</button>
            </div>
          </div>
        `;

      function addExercise(sid) {
        const list = sheetsContainer.querySelector('.exercise-list[data-sheet-id=\"' + sid + '\"]');
        if (!list) return;
        list.insertAdjacentHTML('beforeend', exerciseTemplate(sid));
        const row = list.lastElementChild;
        attachExerciseRow(row);
      }

      function attachSheet(card) {
        const addBtn = card.querySelector('.add-exercise');
        if (addBtn) {
          addBtn.addEventListener('click', function () {
            const sid = this.dataset.sheetId || card.dataset.sheetId;
            addExercise(sid);
          });
        }
        card.querySelectorAll('input[name="sheet_title[]"], input[name="sheet_month[]"], input[name="sheet_year[]"]').forEach((inp) => {
          inp.addEventListener('input', scheduleAutosave);
        });
        const removeSheetBtn = card.querySelector('.remove-sheet');
        if (removeSheetBtn) {
          removeSheetBtn.addEventListener('click', function () {
            const cards = sheetsContainer.querySelectorAll('.sheet-card');
            if (cards.length > 1) {
              card.remove();
              scheduleAutosave();
            }
          });
        }
        card.querySelectorAll('.exercise-row').forEach(attachExerciseRow);
      }

      sheetsContainer.querySelectorAll('.sheet-card').forEach(attachSheet);

      // Expose globally for the randomizer transfer feature
      window._nfAttachSheet = attachSheet;


      const addSheetBtn = document.getElementById('add-sheet');
      if (addSheetBtn) {
        addSheetBtn.addEventListener('click', function () {
          const sid = 'sheet' + Date.now();
          sheetsContainer.insertAdjacentHTML('beforeend', sheetTemplate(sid));
          const card = sheetsContainer.querySelector('.sheet-card[data-sheet-id=\"' + sid + '\"]');
          if (card) {
            addExercise(sid);
            addExercise(sid);
            attachSheet(card);
          }
        });
      }

    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
    (function () {
      function requestAutosave() {
        if (typeof window.scheduleAutosave === 'function') {
          window.scheduleAutosave();
        }
      }

      function syncListOrders(list) {
        if (!list) return;
        var sheetId = list.getAttribute('data-sheet-id') || '';
        var rows = list.querySelectorAll('.exercise-row');
        rows.forEach(function (row, index) {
          var sheetInput = row.querySelector('input[name="exercise_sheet_idx[]"]');
          if (sheetInput) {
            sheetInput.value = sheetId;
          }
          var orderInput = row.querySelector('input[name="exercise_order[]"]');
          if (!orderInput) {
            orderInput = document.createElement('input');
            orderInput.type = 'hidden';
            orderInput.name = 'exercise_order[]';
            row.insertBefore(orderInput, row.querySelector('.row') || row.firstChild);
          }
          orderInput.value = String(index + 1);
        });
      }

      function bindNativeDrag(list) {
        if (!list) return;

        if (list.dataset.nativeDragBound !== '1') {
          list.dataset.nativeDragBound = '1';
          var draggingRow = null;

          list.addEventListener('dragover', function (event) {
            if (!draggingRow) return;
            event.preventDefault();
            var targetRow = event.target.closest('.exercise-row');
            if (!targetRow || targetRow === draggingRow || targetRow.parentElement !== list) {
              return;
            }
            var rect = targetRow.getBoundingClientRect();
            var placeAfter = (event.clientY - rect.top) > (rect.height / 2);
            list.insertBefore(draggingRow, placeAfter ? targetRow.nextSibling : targetRow);
          });

          list.addEventListener('drop', function (event) {
            if (!draggingRow) return;
            event.preventDefault();
            draggingRow.classList.remove('is-dragging');
            draggingRow = null;
            syncListOrders(list);
            requestAutosave();
          });

          list.addEventListener('dragend', function () {
            if (!draggingRow) return;
            draggingRow.classList.remove('is-dragging');
            draggingRow = null;
            syncListOrders(list);
            requestAutosave();
          });

          list._nfSetDraggingRow = function (row) {
            draggingRow = row;
          };
        }

        list.querySelectorAll('.drag-handle').forEach(function (handle) {
          if (handle.dataset.nativeDragReady === '1') return;
          handle.dataset.nativeDragReady = '1';
          handle.setAttribute('draggable', 'true');

          handle.addEventListener('dragstart', function (event) {
            var row = handle.closest('.exercise-row');
            if (!row) {
              event.preventDefault();
              return;
            }
            row.classList.add('is-dragging');
            if (list._nfSetDraggingRow) {
              list._nfSetDraggingRow(row);
            }
            if (event.dataTransfer) {
              event.dataTransfer.effectAllowed = 'move';
              try {
                var nameField = row.querySelector('input[name="exercise_name[]"]');
                event.dataTransfer.setData('text/plain', nameField ? (nameField.value || '') : '');
              } catch (e) {
                // alguns navegadores bloqueiam setData vazio
              }
            }
          });
        });
      }

      function bindSortable(list) {
        if (!list) return;
        syncListOrders(list);

        if (typeof Sortable === 'undefined') {
          bindNativeDrag(list);
          return;
        }

        if (list.dataset.sortableBound === '1') {
          return;
        }
        list.dataset.sortableBound = '1';

        new Sortable(list, {
          handle: '.drag-handle',
          animation: 150,
          ghostClass: 'sortable-ghost',
          onEnd: function () {
            syncListOrders(list);
            requestAutosave();
          }
        });
      }

      window.nfSyncExerciseOrders = function () {
        document.querySelectorAll('.exercise-list').forEach(syncListOrders);
      };

      document.querySelectorAll('.exercise-list').forEach(bindSortable);
      window.nfSyncExerciseOrders();

      var sheetsContainer = document.getElementById('sheets-container');
      if (sheetsContainer && typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
          var touchedLists = new Set();

          mutations.forEach(function (mutation) {
            if (!mutation || mutation.type !== 'childList') return;

            if (mutation.target && mutation.target.classList && mutation.target.classList.contains('exercise-list')) {
              touchedLists.add(mutation.target);
            }

            mutation.addedNodes.forEach(function (node) {
              if (!(node instanceof Element)) return;
              if (node.classList.contains('exercise-list')) {
                bindSortable(node);
                touchedLists.add(node);
              }
              if (node.classList.contains('exercise-row')) {
                var parentList = node.closest('.exercise-list');
                if (parentList) touchedLists.add(parentList);
              }
              if (node.querySelectorAll) {
                node.querySelectorAll('.exercise-list').forEach(function (list) {
                  bindSortable(list);
                  touchedLists.add(list);
                });
                node.querySelectorAll('.exercise-row').forEach(function (row) {
                  var rowParentList = row.closest('.exercise-list');
                  if (rowParentList) touchedLists.add(rowParentList);
                });
              }
            });

            mutation.removedNodes.forEach(function (node) {
              if (!(node instanceof Element)) return;
              if (node.classList.contains('exercise-row')) {
                var parentList = mutation.target && mutation.target.classList && mutation.target.classList.contains('exercise-list')
                  ? mutation.target
                  : null;
                if (parentList) touchedLists.add(parentList);
              }
              if (node.querySelectorAll) {
                node.querySelectorAll('.exercise-row').forEach(function () {
                  var fallbackList = mutation.target && mutation.target.classList && mutation.target.classList.contains('exercise-list')
                    ? mutation.target
                    : null;
                  if (fallbackList) touchedLists.add(fallbackList);
                });
              }
            });
          });

          touchedLists.forEach(function (list) {
            bindSortable(list);
            syncListOrders(list);
          });
        });

        observer.observe(sheetsContainer, { childList: true, subtree: true });
      }

      // --- Contador mensal de treinos concluídos (tempo real) ---
      var adminCompletionBadge = document.getElementById('admin-completion-badge');
      var adminCompletionCountEl = document.getElementById('admin-completion-count');
      var adminCompletionLabelEl = document.getElementById('admin-completion-label');
      var adminCompletionInFlight = false;

      function renderAdminCompletionCount(rawCount) {
        var c = Number(rawCount || 0);
        if (!Number.isFinite(c) || c < 0) c = 0;
        if (adminCompletionCountEl) {
          adminCompletionCountEl.textContent = String(c);
        }
        if (adminCompletionLabelEl) {
          adminCompletionLabelEl.textContent = 'treino' + (c !== 1 ? 's' : '') + ' concluído' + (c !== 1 ? 's' : '') + ' este mês';
        }
        if (adminCompletionBadge) {
          if (c > 0) {
            adminCompletionBadge.style.background = 'rgba(0,255,153,0.1)';
            adminCompletionBadge.style.borderColor = 'rgba(0,255,153,0.3)';
            adminCompletionBadge.style.color = '#a7ffd9';
          } else {
            adminCompletionBadge.style.background = 'rgba(255,255,255,0.06)';
            adminCompletionBadge.style.borderColor = 'rgba(255,255,255,0.14)';
            adminCompletionBadge.style.color = 'rgba(255,255,255,0.75)';
          }
        }
      }

      function refreshAdminCompletionCount() {
        if (!adminCompletionBadge || adminCompletionInFlight) return;

        var endpoint = adminCompletionBadge.getAttribute('data-count-url') || '/training-completion-count';
        var selectedEmail = adminCompletionBadge.getAttribute('data-user-email') || '';
        if (!selectedEmail) return;

        adminCompletionInFlight = true;
        fetch(endpoint + '?user_email=' + encodeURIComponent(selectedEmail), {
          method: 'GET',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && data.ok) {
              renderAdminCompletionCount(data.count || 0);
            }
          })
          .catch(function () { })
          .finally(function () { adminCompletionInFlight = false; });
      }

      if (adminCompletionBadge) {
        refreshAdminCompletionCount();
        setInterval(refreshAdminCompletionCount, 15000);
        document.addEventListener('visibilitychange', function () {
          if (!document.hidden) {
            refreshAdminCompletionCount();
          }
        });
      }

      // --- Autocomplete de aluno ---
      var studentMap = <?php echo json_encode(array_map(function ($u) {
        return ['name' => $u['name'] ?: $u['email'], 'email' => $u['email']];
      }, $users), JSON_UNESCAPED_UNICODE); ?>;
      var searchInput = document.getElementById('student-search');
      var emailInput = document.getElementById('student-email');
      if (searchInput && emailInput) {
        function updateStudentEmail() {
          var val = (searchInput.value || '').trim().toLowerCase();
          var match = studentMap.find(function (s) {
            return s.name.toLowerCase() === val || s.email.toLowerCase() === val;
          });
          emailInput.value = match ? match.email : val;
        }
        searchInput.addEventListener('input', updateStudentEmail);
        searchInput.addEventListener('change', updateStudentEmail);
      }
    })();
  </script>
  <style>
    .sortable-ghost {
      opacity: .4;
      border: 2px dashed rgba(255, 122, 0, .5) !important;
    }

    .exercise-row.is-dragging {
      opacity: .7;
    }

    .drag-handle:active {
      cursor: grabbing;
    }
  </style>
  <?php if ($whatsLink): ?>
    <script>
      (function () {
        var wa = <?php echo json_encode($whatsLink, JSON_UNESCAPED_SLASHES); ?>;
        if (wa) {
          setTimeout(function () { window.open(wa, '_blank'); }, 300);
        }
      })();
    </script>
  <?php endif; ?>
</body>

</html>