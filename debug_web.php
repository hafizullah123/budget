<?php
// debug_web.php - browser-accessible database debug
header('Content-Type: text/plain; charset=utf-8');

echo "Debugging from web (Apache PHP)\n";
echo "SAPI: " . php_sapi_name() . "\n";

echo "\n-- PDO and driver checks --\n";
echo "PDO available: " . (class_exists('PDO') ? 'yes' : 'no') . "\n";
echo "pdo_mysql loaded: " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";

echo "\n-- Attempting to require config/database.php and connect --\n";

$configPath = __DIR__ . '/config/database.php';
if (!file_exists($configPath)) {
    echo "config/database.php not found at: $configPath\n";
    exit;
}

require_once $configPath;

if (!class_exists('Database')) {
    echo "Class Database not found after requiring config/database.php\n";
    // Optionally show the file contents for inspection (safe for local dev)
    echo "\n--- config/database.php contents ---\n";
    echo file_get_contents($configPath);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "getConnection(): OK\n";
        try {
            $stmt = $conn->query('SELECT 1 as test');
            $row = $stmt->fetch();
            echo "Test query result: " . ($row['test'] ?? 'no result') . "\n";
        } catch (Throwable $e) {
            echo "Query failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "getConnection(): FAILED (returned null)\n";
    }
} catch (Throwable $e) {
    echo "Exception while connecting: " . $e->getMessage() . "\n";
}

?>