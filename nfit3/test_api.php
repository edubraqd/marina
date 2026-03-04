<?php
// Simulating an API call for batch
$_SERVER['REQUEST_URI'] = '/randomizer-api.php?batch=Peitoral,Costas';
$_GET['batch'] = 'Peitoral,Costas';

// Mock auth bypass for testing script directly
session_start();
$_SESSION['user_email'] = 'test@test.com'; // Fake
function app_log()
{
}
// Just test the querying part
require_once 'includes/database.php';
$conn = db();

$groups = ['Peitoral', 'Costas'];
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
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$result = [];
while ($row = $res->fetch_assoc()) {
    $result[$row['requested_group']] = [
        'name' => $row['name'],
        'url' => $row['url'] ?? ''
    ];
}

echo "BATCH RESULT:\n";
print_r($result);
