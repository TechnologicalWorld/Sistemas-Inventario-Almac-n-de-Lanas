<?php
require_once 'config.php';

$query = "SELECT MAX(CAST(SUBSTRING(codigo, 4) AS UNSIGNED)) as max_num 
          FROM clientes WHERE codigo REGEXP '^CLT[0-9]+$'";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$next_num = ($row['max_num'] ?? 0) + 1;

echo 'CLT' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
?>