<?php
require_once 'includes/database.php';
$conn = db();
$sql = "UPDATE users SET preferences = '{\"skip_forms\":true}' WHERE email = 'teste@exemplo.com'";
$conn->query($sql);
echo "Preferences updated!";
