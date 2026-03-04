<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/randomizer_catalog.php';

$conn = db();

// 1. Create dedicated table for the randomizer catalog
$sql_create = "CREATE TABLE IF NOT EXISTS randomizer_exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_muscular VARCHAR(100) NOT NULL,
    nome_exercicio VARCHAR(255) NOT NULL,
    link VARCHAR(300) DEFAULT '',
    INDEX idx_grupo (grupo_muscular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!$conn->query($sql_create)) {
    die("Error creating table: " . $conn->error);
}
echo "Table `randomizer_exercicios` created or already exists.\n";

// Clear the table before inserting to prevent duplicates if run multiple times
$conn->query("TRUNCATE TABLE randomizer_exercicios");

// Load the catalog
$catalog = randomizer_catalog();

$total_inserted = 0;
$stmt = $conn->prepare("INSERT INTO randomizer_exercicios (grupo_muscular, nome_exercicio, link) VALUES (?, ?, ?)");

$conn->begin_transaction();
try {
    foreach ($catalog as $grupo => $exercicios) {
        foreach ($exercicios as $ex) {
            $nome = trim($ex[0]);
            $link = trim($ex[1] ?? '');
            if (!$nome)
                continue;

            $stmt->bind_param("sss", $grupo, $nome, $link);
            $stmt->execute();
            $total_inserted++;
        }
    }
    $conn->commit();
    echo "SUCCESS: Inserted {$total_inserted} exercises into the database.\n";
} catch (Exception $e) {
    $conn->rollback();
    die("Transaction failed: " . $e->getMessage());
}

$stmt->close();
$conn->close();
