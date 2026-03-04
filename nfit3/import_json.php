<?php
set_time_limit(0);
require_once __DIR__ . '/includes/database.php';

$conn = db();
$conn->query("TRUNCATE TABLE randomizer_exercicios;");

$json = file_get_contents(__DIR__ . '/catalog.json');
$catalog = json_decode($json, true);

$stmt = $conn->prepare("INSERT INTO randomizer_exercicios (grupo_muscular, nome_exercicio, link) VALUES (?, ?, ?)");

$total = 0;
foreach ($catalog as $grupo => $exercicios) {
    if ($grupo === 'programs')
        continue; // skip programs metadata

    $conn->begin_transaction();
    foreach ($exercicios as $ex) {
        $nome = trim($ex[0]);
        $link = trim($ex[1] ?? '');
        if (!$nome)
            continue;

        $stmt->bind_param("sss", $grupo, $nome, $link);
        $stmt->execute();
        $total++;
    }
    $conn->commit();
}
echo "SUCCESS! Inserted $total exercises from JSON.\n";
