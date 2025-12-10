<?php
// migration: add parent_id to categories for hierarchical categories
require __DIR__ . '/../config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    echo "No DB connection\n";
    exit(1);
}

try {
    // add column if not exists (MySQL doesn't support IF NOT EXISTS for ADD COLUMN in older versions)
    $cols = $conn->query("SHOW COLUMNS FROM categories LIKE 'parent_id'")->fetchAll();
    if (count($cols) == 0) {
        $conn->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL AFTER color");
        echo "Added parent_id column to categories\n";
    } else {
        echo "parent_id column already exists\n";
    }
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

?>
