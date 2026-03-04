<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user_store.php';

function completion_store_ensure_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS training_completions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tc_user_date (user_id, completed_at),
  CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    try {
        db()->query($sql);
        $ready = true;
    } catch (Throwable $e) {
        // silencioso
    }
}

function completion_store_record(string $email): bool
{
    $user = user_store_find($email);
    if (!$user) {
        return false;
    }
    completion_store_ensure_table();
    $userId = (int) $user['id'];
    $stmt = db()->prepare('INSERT INTO training_completions (user_id) VALUES (?)');
    $stmt->bind_param('i', $userId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function completion_store_count_month(string $email, int $month = 0, int $year = 0): int
{
    $user = user_store_find($email);
    if (!$user) {
        return 0;
    }
    completion_store_ensure_table();
    if ($month === 0) {
        $month = (int) date('m');
    }
    if ($year === 0) {
        $year = (int) date('Y');
    }
    $userId = (int) $user['id'];
    $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $end = date('Y-m-t 23:59:59', strtotime($start));
    $stmt = db()->prepare('SELECT COUNT(*) as cnt FROM training_completions WHERE user_id = ? AND completed_at BETWEEN ? AND ?');
    $stmt->bind_param('iss', $userId, $start, $end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['cnt'] ?? 0);
}
