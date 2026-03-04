<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/area_guard.php';
require_once __DIR__ . '/../includes/training_store.php';
require_once __DIR__ . '/../includes/user_store.php';

$current_user = area_guard_require_login();
$isAdmin = ($current_user['role'] ?? 'student') === 'admin';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$targetEmail = trim($data['user_email'] ?? '');
$updatesList = $data['updates'] ?? [];

// Fallback to own account
if ($targetEmail === '') {
    $targetEmail = $current_user['email'];
}

// Security Validation
if (!$isAdmin && $targetEmail !== $current_user['email']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    $plan = training_store_find_for_user($targetEmail);
    if (!$plan) {
        throw new Exception('Plano de treino não encontrado.');
    }

    $exercises = $plan['exercises'] ?? [];
    $changed = false;

    // Apply incremental cue changes
    foreach ($updatesList as $upd) {
        $idx = (int) ($upd['idx'] ?? -1);
        $fields = $upd['cues'] ?? [];

        if (isset($exercises[$idx]) && is_array($fields)) {
            $cuesStr = trim((string) ($exercises[$idx]['cues'] ?? ''));
            $cues = [];

            if ($cuesStr !== '') {
                $cuesDec = json_decode($cuesStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($cuesDec)) {
                    $cues = $cuesDec;
                } else {
                    $cues['notes'] = $cuesStr;
                }
            }

            // Overwrite specific keys (load, reps, is_done)
            foreach ($fields as $k => $v) {
                $cues[$k] = trim((string) $v);
            }

            $exercises[$idx]['cues'] = json_encode($cues, JSON_UNESCAPED_UNICODE);
            $changed = true;
        }
    }

    if ($changed) {
        training_store_save_for_user($targetEmail, [
            'title' => $plan['title'] ?? 'Treino do aluno',
            'instructions' => $plan['instructions'] ?? '',
            'exercises' => $exercises
        ]);
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
