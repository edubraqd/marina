<?php
/**
 * Randomizer API - Returns random exercises for given muscle group(s) from Database.
 * GET /randomizer-api.php?group=Peitoral          → { name, url }
 * GET /randomizer-api.php?batch=Peitoral,Costas   → { Peitoral: {...}, Costas: {...} }
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/area_guard.php';
require_once __DIR__ . '/includes/database.php';

$current_user = area_guard_require_login();
area_guard_require_admin($current_user);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$conn = db();

// Batch mode: ?batch=Peitoral,Costas,Biceps
if (!empty($_GET['batch'])) {
    $groups = array_unique(array_filter(array_map('trim', explode(',', $_GET['batch']))));
    $result = [];

    if (!empty($groups)) {
        $queries = [];
        $types = '';
        $params = [];
        foreach ($groups as $g) {
            $queries[] = "(SELECT ? AS requested_group, nome_exercicio AS name, link AS url FROM randomizer_exercicios WHERE grupo_muscular = ? ORDER BY RAND() LIMIT 1)";
            $types .= 'ss';
            $params[] = $g;
            $params[] = $g;
        }

        $sql = implode(" UNION ALL ", $queries);
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();

            // Initialize empty
            foreach ($groups as $g) {
                $result[$g] = ['name' => '', 'url' => ''];
            }
            // Populate found
            while ($row = $res->fetch_assoc()) {
                $result[$row['requested_group']] = [
                    'name' => $row['name'],
                    'url' => $row['url'] ?? ''
                ];
            }
        }
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// Single group mode: ?group=Peitoral
$group = trim($_GET['group'] ?? '');
if (!$group) {
    http_response_code(400);
    echo json_encode(['error' => 'group parameter required']);
    exit;
}

$sql = "SELECT nome_exercicio AS name, link AS url FROM randomizer_exercicios WHERE grupo_muscular = ? ORDER BY RAND() LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $group);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if ($row) {
    echo json_encode(['name' => $row['name'], 'url' => $row['url'] ?? ''], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['name' => '', 'url' => '']);
}
