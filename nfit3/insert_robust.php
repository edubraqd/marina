<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/randomizer_catalog.php';

$conn = db();
$conn->query("DROP TABLE IF EXISTS randomizer_exercicios;");
$conn->query("CREATE TABLE randomizer_exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_muscular VARCHAR(100) NOT NULL,
    nome_exercicio VARCHAR(255) NOT NULL,
    link VARCHAR(300) DEFAULT '',
    INDEX idx_grupo (grupo_muscular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$catalog = randomizer_catalog();
$total = 0;

$stmt = $conn->prepare("INSERT INTO randomizer_exercicios (grupo_muscular, nome_exercicio, link) VALUES (?, ?, ?)");

foreach ($catalog as $grupo => $exercicios) {
    echo "Inserting $grupo...\n";
    $conn->begin_transaction();
    $inserted = 0;
    foreach ($exercicios as $ex) {
        $nome = trim($ex[0]);
        $link = trim($ex[1] ?? '');
        if (!$nome)
            continue;

        $stmt->bind_param("sss", $grupo, $nome, $link);
        $stmt->execute();
        $inserted++;
        $total++;
    }
    $conn->commit();
    echo " - inserted $inserted.\n";
}

echo "\nSUCCESS! Total rows: $total\n";
