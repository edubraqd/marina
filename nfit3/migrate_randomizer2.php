<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/randomizer_catalog.php';

$conn = db();
$conn->query("TRUNCATE TABLE randomizer_exercicios");

$catalog = randomizer_catalog();
echo "Loaded catalog with " . count($catalog) . " groups.\n";

$total_inserted = 0;
$stmt = $conn->prepare("INSERT INTO randomizer_exercicios (grupo_muscular, nome_exercicio, link) VALUES (?, ?, ?)");
if (!$stmt)
    die("Prepare failed: " . $conn->error);

$conn->begin_transaction();
try {
    foreach ($catalog as $grupo => $exercicios) {
        $c = 0;
        foreach ($exercicios as $ex) {
            $nome = trim($ex[0]);
            $link = trim($ex[1] ?? '');
            if (!$nome)
                continue;

            $stmt->bind_param("sss", $grupo, $nome, $link);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed for $nome: " . $stmt->error);
            }
            $total_inserted++;
            $c++;
        }
        echo "Inserted $c for $grupo\n";
    }
    $conn->commit();
    echo "SUCCESS: Inserted {$total_inserted} exercises into the database.\n";
} catch (Exception $e) {
    if ($conn)
        $conn->rollback();
    echo "Transaction failed: " . $e->getMessage() . "\n";
}
