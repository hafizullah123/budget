<?php
require __DIR__ . '/config/database.php';
$db = new Database();
$c = $db->getConnection();
if (!$c) { echo "no connection\n"; exit; }
var_dump($c instanceof PDO);
$stmt = $c->query('SHOW VARIABLES LIKE "character_set%"');
$rows = $stmt->fetchAll();
foreach ($rows as $r) {
    echo $r['Variable_name'] . ': ' . $r['Value'] . "\n";
}
?>