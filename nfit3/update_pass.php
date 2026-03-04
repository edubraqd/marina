<?php
require_once 'includes/database.php';
$conn = db();
$hash = password_hash('123456', PASSWORD_DEFAULT);
$conn->query("UPDATE users SET password_hash = '$hash' WHERE email = 'teste@exemplo.com'");
echo "Password updated for teste@exemplo.com";
