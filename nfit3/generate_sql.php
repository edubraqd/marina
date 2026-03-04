<?php
require_once __DIR__ . '/includes/randomizer_catalog.php';

$catalog = randomizer_catalog();
$file = fopen('import_randomizer.sql', 'w');

$header = "
DROP TABLE IF EXISTS randomizer_exercicios;
CREATE TABLE randomizer_exercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_muscular VARCHAR(100) NOT NULL,
    nome_exercicio VARCHAR(255) NOT NULL,
    link VARCHAR(300) DEFAULT '',
    INDEX idx_grupo (grupo_muscular)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

";
fwrite($file, $header);

foreach ($catalog as $grupo => $exercicios) {
    if (empty($exercicios))
        continue;
    $values = [];
    foreach ($exercicios as $ex) {
        $nome = trim($ex[0]);
        $link = trim($ex[1] ?? '');
        if (!$nome)
            continue;

        $g_esc = "'" . addslashes($grupo) . "'";
        $n_esc = "'" . addslashes($nome) . "'";
        $l_esc = "'" . addslashes($link) . "'";

        $values[] = "($g_esc, $n_esc, $l_esc)";
    }

    if (!empty($values)) {
        // Chunk into 500 rows per insert
        $chunks = array_chunk($values, 500);
        foreach ($chunks as $chunk) {
            $sql = "INSERT INTO randomizer_exercicios (grupo_muscular, nome_exercicio, link) VALUES\n";
            $sql .= implode(",\n", $chunk) . ";\n";
            fwrite($file, $sql);
        }
    }
}

fclose($file);
echo "SQL File generated successfully.\n";
