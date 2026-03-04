<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/training_store.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

header('Content-Type: application/json; charset=utf-8');

// Permite OPTIONS para evitar falhas em preflight/acessos incorretos
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Allow: POST, OPTIONS');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['ok' => false, 'message' => 'Método não permitido']);
    exit;
}

$email = mb_strtolower(trim((string) ($_POST['user_email'] ?? '')));
if ($email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Informe o aluno para salvar o treino.']);
    exit;
}

$selectedUser = user_store_find($email);
if (!$selectedUser) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Aluno não encontrado.']);
    exit;
}

/**
 * Compacta os campos estruturados em JSON para serem salvos no campo "cues".
 */
function autosave_pack_cues(array $fields): string
{
    return json_encode([
        'series' => trim((string) ($fields['series'] ?? '')),
        'reps'   => trim((string) ($fields['reps'] ?? '')),
        'load'   => trim((string) ($fields['load'] ?? '')),
        'notes'  => trim((string) ($fields['notes'] ?? '')),
        'order'  => trim((string) ($fields['order'] ?? '')),
    ], JSON_UNESCAPED_UNICODE);
}

// Metadados das fichas (sheet)
$sheetIdxList = $_POST['sheet_idx'] ?? [];
$sheetTitles  = $_POST['sheet_title'] ?? [];
$sheetMonths  = $_POST['sheet_month'] ?? [];
$sheetYears   = $_POST['sheet_year'] ?? [];
$sheetMeta = [];
foreach ($sheetIdxList as $k => $sid) {
    $sidClean = trim((string) $sid);
    if ($sidClean === '') {
        $sidClean = (string) ($k + 1);
    }
    $sheetMeta[$sidClean] = [
        'title' => trim((string) ($sheetTitles[$k] ?? '')),
        'month' => trim((string) ($sheetMonths[$k] ?? '')),
        'year'  => trim((string) ($sheetYears[$k] ?? '')),
    ];
}

// Exercícios
$names      = $_POST['exercise_name'] ?? [];
$videos     = $_POST['exercise_video'] ?? [];
$seriesList = $_POST['exercise_series'] ?? [];
$repsList   = $_POST['exercise_reps'] ?? [];
$loadList   = $_POST['exercise_load'] ?? [];
$notesList  = $_POST['exercise_notes'] ?? [];
$orderList  = $_POST['exercise_order'] ?? [];
$sheetOfEx  = $_POST['exercise_sheet_idx'] ?? [];

$exercises = [];
foreach ($names as $i => $name) {
    $nameClean = trim((string) $name);
    if ($nameClean === '') {
        continue;
    }
    $sheetId = trim((string) ($sheetOfEx[$i] ?? ''));
    $meta    = $sheetMeta[$sheetId] ?? ['title' => '', 'month' => '', 'year' => ''];
    $exercises[] = [
        'name'             => $nameClean,
        'video_url'        => trim((string) ($videos[$i] ?? '')),
        'cues'             => autosave_pack_cues([
            'series' => $seriesList[$i] ?? '',
            'reps'   => $repsList[$i] ?? '',
            'load'   => $loadList[$i] ?? '',
            'notes'  => $notesList[$i] ?? '',
            'order'  => $orderList[$i] ?? '',
        ]),
        'day'              => 'geral',
        'sheet_idx'        => $sheetId,
        'sheet_title'      => $meta['title'] ?? '',
        'sheet_ref_month'  => $meta['month'] ?? '',
        'sheet_ref_year'   => $meta['year'] ?? '',
    ];
}

if (count($exercises) === 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Nenhum exercício preenchido; preencha o nome para salvar automaticamente.',
    ]);
    exit;
}

try {
    $record = training_store_save_for_user($selectedUser['email'], [
        'title'         => trim((string) ($_POST['training_title'] ?? '')),
        'instructions'  => trim((string) ($_POST['training_instructions'] ?? '')),
        'exercises'     => $exercises,
    ]);

    echo json_encode([
        'ok' => true,
        'saved_at' => date('c'),
        'exercises_saved' => count($exercises),
        'updated_at' => $record['updated_at'] ?? ($record['created_at'] ?? null),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Erro ao salvar treino: ' . $e->getMessage(),
    ]);
}
