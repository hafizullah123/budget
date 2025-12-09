<?php
require __DIR__ . '/config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) { echo "no connection\n"; exit; }
try {
    $conn->exec("ALTER TABLE categories ADD COLUMN color VARCHAR(7) DEFAULT '#1a73e8'");
    echo "Added column color\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>