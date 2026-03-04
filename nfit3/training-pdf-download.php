<?php
/**
 * Endpoint admin-only para download de PDF de treino.
 *
 * Parametros GET:
 *   ?user=email@aluno.com  -> gera PDF on-the-fly do treino atual
 *   ?file=nome_backup.pdf  -> serve arquivo de backup existente
 */
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/user_store.php';
require_once __DIR__ . '/includes/training_store.php';
require_once __DIR__ . '/includes/training_pdf.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

// Modo 1: servir backup existente
$backupFile = trim($_GET['file'] ?? '');
if ($backupFile !== '') {
    // Prevenir directory traversal
    $backupFile = basename($backupFile);
    $path = __DIR__ . '/storage/training_backups/' . $backupFile;

    if (!file_exists($path) || !is_file($path)) {
        http_response_code(404);
        exit('Arquivo de backup nao encontrado.');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $backupFile . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// Modo 2: gerar PDF on-the-fly
$userEmail = trim($_GET['user'] ?? '');
if ($userEmail === '') {
    http_response_code(400);
    exit('Parametro "user" ou "file" obrigatorio.');
}

$user = user_store_find($userEmail);
if (!$user) {
    http_response_code(404);
    exit('Aluno nao encontrado.');
}

$plan = training_store_find_for_user($userEmail);
if (!$plan || empty($plan['exercises'])) {
    http_response_code(404);
    exit('Nenhum treino encontrado para este aluno.');
}

$pdfData = training_generate_pdf($plan, $user);

$safeName = preg_replace('/[^a-z0-9]/i', '_', trim($user['name'] ?: $userEmail));
$filename = 'treino_' . $safeName . '_' . date('Y-m-d') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfData));
echo $pdfData;
exit;
