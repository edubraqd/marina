<?php

declare(strict_types=1);

use RuntimeException;
use Throwable;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user_store.php';

/**
 * Garante que a tabela de treinos existe.
 */
function training_store_ensure_table(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `training_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `instructions` TEXT NULL,
  `exercises` JSON NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_training_user` (`user_id`),
  KEY `fk_training_plans_user` (`user_id`),
  CONSTRAINT `fk_training_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    try {
        db()->query($sql);
        $ready = true;
    } catch (Throwable $e) {
        app_log('training_store_ensure_table_error', ['error' => $e->getMessage()]);
    }
}

/**
 * @param array<string,mixed> $row
 * @param array<string,mixed> $user
 * @return array<string,mixed>
 */
function training_store_normalize(array $row, array $user = []): array
{
    if (isset($row['exercises']) && $row['exercises']) {
        $decoded = json_decode((string) $row['exercises'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $row['exercises'] = array_values(array_filter($decoded, function ($item) {
                return is_array($item) && trim((string) ($item['name'] ?? '')) !== '';
            }));
            foreach ($row['exercises'] as &$ex) {
                $ex['day'] = $ex['day'] ?? 'geral';
                $ex['sheet_idx'] = trim((string) ($ex['sheet_idx'] ?? ''));
                $ex['sheet_title'] = trim((string) ($ex['sheet_title'] ?? ''));
                $ex['sheet_ref_month'] = trim((string) ($ex['sheet_ref_month'] ?? ''));
                $ex['sheet_ref_year'] = trim((string) ($ex['sheet_ref_year'] ?? ''));
            }
        } else {
            $row['exercises'] = [];
        }
    } else {
        $row['exercises'] = [];
    }

    if ($user) {
        $row['user'] = [
            'id'    => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'name'  => $user['name'] ?? '',
        ];
    }

    return $row;
}

/**
 * @return array<string,mixed>|null
 */
function training_store_find_for_user(string $email): ?array
{
    training_store_ensure_table();

    $user = user_store_find($email);
    if (!$user || !isset($user['id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM training_plans WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? training_store_normalize($row, $user) : null;
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function training_store_save_for_user(string $email, array $payload): array
{
    training_store_ensure_table();

    $user = user_store_find($email);
    if (!$user || !isset($user['id'])) {
        throw new RuntimeException('Usuário não encontrado para salvar treino.');
    }

    $title = trim($payload['title'] ?? '');
    $instructions = trim($payload['instructions'] ?? '');
    $exercises = $payload['exercises'] ?? [];

    $cleanExercises = [];
    foreach ($exercises as $exercise) {
        $name = trim($exercise['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $cleanExercises[] = [
            'name'      => $name,
            'video_url' => trim($exercise['video_url'] ?? ''),
            'cues'      => trim($exercise['cues'] ?? ''),
            'day'       => trim($exercise['day'] ?? '') ?: 'geral',
            'sheet_idx'       => trim((string) ($exercise['sheet_idx'] ?? '')),
            'sheet_title'     => trim((string) ($exercise['sheet_title'] ?? '')),
            'sheet_ref_month' => trim((string) ($exercise['sheet_ref_month'] ?? '')),
            'sheet_ref_year'  => trim((string) ($exercise['sheet_ref_year'] ?? '')),
        ];
    }

    if (count($cleanExercises) === 0) {
        throw new RuntimeException('Adicione pelo menos um exercício ao plano.');
    }

    $titleToUse = $title !== '' ? $title : 'Treino do aluno';
    $exercisesJson = json_encode($cleanExercises, JSON_UNESCAPED_UNICODE);

    $stmt = db()->prepare(
        'INSERT INTO training_plans (user_id, title, instructions, exercises, created_at) VALUES (?,?,?,?,NOW())
         ON DUPLICATE KEY UPDATE title = VALUES(title), instructions = VALUES(instructions), exercises = VALUES(exercises), updated_at = NOW()'
    );
    $stmt->bind_param('isss', $user['id'], $titleToUse, $instructions, $exercisesJson);
    $stmt->execute();
    $stmt->close();

    return training_store_find_for_user($email) ?? [];
}
