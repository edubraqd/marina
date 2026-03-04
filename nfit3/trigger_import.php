<?php
set_time_limit(0);
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/randomizer_catalog.php';

try {
    $conn = db();
    $conn->query("DROP TABLE IF EXISTS randomizer_exercicios;");
    $conn->query("CREATE TABLE randomizer_exercicios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grupo_muscular VARCHAR(100) NOT NULL,
        nome_exercicio VARCHAR(255) NOT NULL,
        link TEXT,
        INDEX idx_grupo (grupo_muscular)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $catalog = randomizer_catalog();
    $total = 0;

    $stmt = $conn->prepare("INSERT INTO randomizer_exercicios (grupo_muscular, nome_exercicio, link) VALUES (?, ?, ?)");

    foreach ($catalog as $grupo => $exercicios) {
        $conn->begin_transaction();
        foreach ($exercicios as $ex) {
            $nome = trim($ex[0] ?? '');
            $link = trim($ex[1] ?? '');
            if (!$nome)
                continue;

            $stmt->bind_param("sss", $grupo, $nome, $link);
            $stmt->execute();
            $total++;
        }
        $conn->commit();
    }
    echo "SUCCESS_NFIT3_IMPORT_RANDOMIZER: $total rows inserted into randomizer_exercicios!";
} catch (Exception $e) {
    if (isset($conn))
        $conn->rollback();
    echo "ERROR_NFIT3_IMPORT_RANDOMIZER: " . $e->getMessage();
}
