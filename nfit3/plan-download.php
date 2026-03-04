<?php
require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/user_store.php';

$current_user = area_guard_require_login();

function plan_files_ensure_table(): void
{
    static $ready = false;
    if ($ready) return;
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
    }
}

$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($fileId <= 0) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

plan_files_ensure_table();

$row = null;
try {
    $stmt = db()->prepare('SELECT pf.*, u.email FROM plan_files pf JOIN users u ON u.id = pf.user_id WHERE pf.id = ? LIMIT 1');
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
} catch (Throwable $e) {
    $row = null;
}

if (!$row) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

// somente o próprio aluno ou admin
$isOwner = mb_strtolower(trim($current_user['email'] ?? '')) === mb_strtolower(trim($row['email'] ?? ''));
$isAdmin = ($current_user['role'] ?? 'student') === 'admin';
if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    exit('Acesso negado.');
}

$mime = $row['mime'] ?: 'application/pdf';
$filename = $row['filename'] ?: 'plano.pdf';
$data = $row['data'] ?? '';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . strlen($data));
echo $data;
exit;
