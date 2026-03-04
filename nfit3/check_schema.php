<?php
$db = new mysqli('localhost', 'root', '', 'edua0932_nutremfit');
$out = "USERS TABLE SCHEMA:\n";
$res1 = $db->query('SHOW CREATE TABLE users');
$row1 = $res1->fetch_assoc();
$out .= $row1['Create Table'] . "\n\n";

$out .= "RANDOMIZER_EXERCICIOS TABLE SCHEMA:\n";
$res2 = $db->query('SHOW CREATE TABLE randomizer_exercicios');
$row2 = $res2->fetch_assoc();
$out .= $row2['Create Table'] . "\n\n";

file_put_contents('schema_out.txt', $out);
echo "Written to schema_out.txt";
