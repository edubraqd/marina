<?php
require_once 'includes/database.php';
$conn = db();
$sql = file_get_contents('import_randomizer.sql');

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "SQL Import executed successfully.\n";
} else {
    echo "Error during SQL Import: " . $conn->error . "\n";
}
