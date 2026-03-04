<?php
require_once 'includes/database.php';
$conn = db();
// Fill required fields to bypass area_guard.php onboarding check
$conn->query("UPDATE users SET objective = 'Saúde', weight = 70, height = 175, sex = 'Masculino', age = 30 WHERE email = 'teste@exemplo.com'");
echo "Test user onboarding data filled.";
