<?php
require_once 'includes/database.php';
$conn = db();

$group = 'Costas'; // Group with many options
$iterations = 500;

echo "Benchmarking $iterations randomizations for '$group'...\n";

$start = microtime(true);
$sql = "SELECT nome_exercicio AS name, link AS url FROM randomizer_exercicios WHERE grupo_muscular = ? ORDER BY RAND() LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $group);

for ($i = 0; $i < $iterations; $i++) {
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
}

$end = microtime(true);
$total = $end - $start;
$avg = ($total / $iterations) * 1000; // in milliseconds

echo "Total time: " . number_format($total, 4) . " seconds\n";
echo "Average time per query: " . number_format($avg, 2) . " ms\n";

if ($avg < 5) {
    echo "Performance is EXCELLENT (under 5ms).\n";
} else {
    echo "Performance needs tuning.\n";
}
