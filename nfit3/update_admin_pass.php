<?php
require_once 'includes/database.php';
$conn = db();
$hash = password_hash('123456', PASSWORD_DEFAULT);
$conn->query("UPDATE users SET password_hash = '$hash' WHERE email = 'admin@nutremfit.com'");
echo "Admin password updated successfully.";
