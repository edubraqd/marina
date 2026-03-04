<?php
require_once 'includes/database.php';
$conn = db();
$sql = file_get_contents('import_randomizer.sql');

// Split the SQL file by statements
$statements = explode(";\n", $sql);
$successCount = 0;
$errorCount = 0;

$conn->begin_transaction();
foreach ($statements as $stmt) {
    $clean_stmt = trim($stmt);
    if (empty($clean_stmt))
        continue;

    if ($conn->query($clean_stmt)) {
        $successCount++;
    } else {
        echo "Error on statement: " . substr($clean_stmt, 0, 50) . "... -> " . $conn->error . "\n";
        $errorCount++;
    }
}

if ($errorCount === 0) {
    $conn->commit();
    echo "SUCCESS: Executed $successCount SQL statements perfectly.\n";
} else {
    $conn->rollback();
    echo "FAILED: $errorCount statements had errors.\n";
}

$res = $conn->query("SELECT count(*) as c FROM randomizer_exercicios");
echo "Total in DB: " . $res->fetch_assoc()['c'] . "\n";
