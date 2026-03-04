<?php
require_once 'includes/database.php';
$conn = db();

echo "Dropping existing ID column...\n";
$conn->query("ALTER TABLE `randomizer_exercicios` DROP COLUMN `id`");

echo "Re-adding ID column as Auto Increment Primary Key...\n";
if ($conn->query("ALTER TABLE `randomizer_exercicios` ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST")) {
    echo "Successfully created true Primary Key and Auto Increment.\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}
