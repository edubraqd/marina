<?php
require_once 'includes/database.php';
$conn = db();

$sqlContent = file_get_contents('import_randomizer.sql');

// Extract the header (DROP and CREATE) which is the first part
$pos = strpos($sqlContent, "INSERT INTO");
$schema = trim(substr($sqlContent, 0, $pos));
$inserts = trim(substr($sqlContent, $pos));

echo "Executing Schema...\n";
if ($conn->multi_query($schema)) {
    do {
        $conn->store_result();
    } while ($conn->more_results() && $conn->next_result());
} else {
    die("Schema Error: " . $conn->error);
}

echo "Executing Inserts...\n";
$statements = explode(";\n", $inserts);
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
        echo "Error: " . $conn->error . "\n";
        $errorCount++;
    }
}

if ($errorCount === 0) {
    $conn->commit();
    echo "SUCCESS: Executed all inserts beautifully!\n";
} else {
    $conn->rollback();
    echo "FAILED with $errorCount errors.\n";
}

$res = $conn->query("SELECT count(*) as c FROM randomizer_exercicios");
echo "Total in DB: " . $res->fetch_assoc()['c'] . "\n";
