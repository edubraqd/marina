<?php
// Extremely fast, memory-safe, stream-based SQL importer
require_once 'includes/database.php';
$conn = db();

if ($conn->query("DROP TABLE IF EXISTS randomizer_exercicios"))
    echo "Dropped.\n";
if (
    $conn->query("CREATE TABLE randomizer_exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_muscular VARCHAR(100) NOT NULL,
    nome_exercicio VARCHAR(255) NOT NULL,
    link TEXT,
    INDEX idx_grupo (grupo_muscular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")
)
    echo "Created.\n";

$file = fopen('import_randomizer.sql', 'r');
if (!$file)
    die("Could not open file.");

$conn->begin_transaction();
$query = "";
$successCount = 0;
while (($line = fgets($file)) !== false) {
    // Skip the schema part since we did it above
    if (strpos($line, 'DROP') !== false || strpos($line, 'CREATE') !== false) {
        $query = ""; // reset
        continue;
    }

    $query .= $line;
    if (substr(trim($line), -1, 1) === ';') {
        if ($conn->query(trim($query))) {
            $successCount++;
        }
        $query = "";
    }
}
$conn->commit();
fclose($file);

$res = $conn->query("SELECT count(*) as c FROM randomizer_exercicios");
echo "Total in DB: " . $res->fetch_assoc()['c'] . "\n";
echo "SUCCESS: $successCount chunks inserted.\n";
