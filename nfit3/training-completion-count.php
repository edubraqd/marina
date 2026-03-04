<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/completion_store.php';

$current_user = area_guard_require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

$selectedEmail = (string) ($current_user['email'] ?? '');
$requestedEmail = trim((string) ($_GET['user_email'] ?? ($_GET['user'] ?? '')));
$isAdmin = (($current_user['role'] ?? 'student') === 'admin');

if ($requestedEmail !== '') {
    $requestedEmail = function_exists('mb_strtolower') ? mb_strtolower($requestedEmail) : strtolower($requestedEmail);
    if (!filter_var($requestedEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Email invalido']);
        exit;
    }
    if (!$isAdmin && $requestedEmail !== $selectedEmail) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Acesso negado']);
        exit;
    }
    $selectedEmail = $requestedEmail;
}

$month = (int) ($_GET['month'] ?? 0);
$year = (int) ($_GET['year'] ?? 0);
if ($month < 1 || $month > 12) {
    $month = 0;
}
if ($year < 2000 || $year > 2100) {
    $year = 0;
}

$count = completion_store_count_month($selectedEmail, $month, $year);

echo json_encode([
    'ok' => true,
    'email' => $selectedEmail,
    'month' => $month ?: (int) date('m'),
    'year' => $year ?: (int) date('Y'),
    'count' => $count,
]);

