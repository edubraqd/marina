<?php
require_once 'includes/database.php';
$conn = db();

$queries = [
    "ALTER TABLE `randomizer_exercicios` ADD PRIMARY KEY (`id`)",
    "ALTER TABLE `randomizer_exercicios` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT",
    "CREATE INDEX `idx_grupo_muscular` ON `randomizer_exercicios` (`grupo_muscular`)",
    "CREATE INDEX `idx_users_role` ON `users` (`role`)",
    "CREATE INDEX `idx_users_plan` ON `users` (`plan`)"
];

foreach ($queries as $q) {
    echo "Running: $q...\n";
    try {
        if ($conn->query($q) === TRUE) {
            echo "Success.\n";
        } else {
            echo "Failed: " . $conn->error . "\n";
        }
    } catch (Exception $e) {
        echo "Error/Warning (might already exist): " . $e->getMessage() . "\n";
    }
}
echo "\nAll optimizations applied.\n";
