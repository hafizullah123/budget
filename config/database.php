<?php
// Database connection wrapper providing a PDO instance
class Database {
    private $host = 'localhost';
    private $db_name = 'budget_system';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $conn = null;

    public function __construct($options = []) {
        // allow overriding via options array if needed
        if (!empty($options)) {
            foreach ($options as $k => $v) {
                if (property_exists($this, $k)) $this->{$k} = $v;
            }
        }
    }

    public function getConnection() {
        if ($this->conn !== null) return $this->conn;

        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // ensure connection uses utf8mb4 and proper collation for Unicode (Persian/Dari)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci",
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (PDOException $e) {
            // In web context, avoid exposing credentials or stack traces.
            error_log('Database connection error: ' . $e->getMessage());
            return null;
        }
    }
}

?>
