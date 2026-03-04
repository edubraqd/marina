<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/completion_store.php';

$current_user = area_guard_require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método não permitido']);
    exit;
}

$email = (string) ($current_user['email'] ?? '');
$requestedEmail = trim((string) ($_POST['user_email'] ?? ($_GET['user_email'] ?? '')));
$isAdmin = (($current_user['role'] ?? 'student') === 'admin');

if ($requestedEmail !== '') {
    $requestedEmail = function_exists('mb_strtolower') ? mb_strtolower($requestedEmail) : strtolower($requestedEmail);
    if (!filter_var($requestedEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'E-mail inválido']);
        exit;
    }
    if (!$isAdmin && $requestedEmail !== $email) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acesso negado']);
        exit;
    }
    $email = $requestedEmail;
}

$ok = completion_store_record($email);
$count = completion_store_count_month($email);

echo json_encode(['ok' => $ok, 'count' => $count, 'email' => $email]);
