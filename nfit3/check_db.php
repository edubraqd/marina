<?php
require_once 'includes/database.php';
$conn = db();
$res = $conn->query("SELECT count(*) as c FROM randomizer_exercicios");
echo "Total in DB: " . $res->fetch_assoc()['c'] . "\n";
