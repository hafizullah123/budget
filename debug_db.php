<?php
// debug_db.php - quick diagnostic for DB connection
require __DIR__ . '/config/database.php';

echo "PHP CLI: " . PHP_SAPI . "\n";
echo "PDO available: " . (class_exists('PDO') ? 'yes' : 'no') . "\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    if ($conn) {
        echo "Database connection: OK\n";
        // Do a simple query to confirm
        $stmt = $conn->query('SELECT 1 as test');
        $res = $stmt->fetch();
        echo "Test query result: " . ($res['test'] ?? 'no result') . "\n";
    } else {
        echo "Database connection: FAILED (getConnection returned null)\n";
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

?>