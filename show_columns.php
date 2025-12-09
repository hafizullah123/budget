<?php
require __DIR__ . '/config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) { echo "no connection\n"; exit; }
$stmt = $conn->query('SHOW COLUMNS FROM categories');
$rows = $stmt->fetchAll();
foreach ($rows as $r) {
    echo $r['Field'] . "\n";
}
?>