<?php

class Database {
    private static $instance = null;
    private $conn = null;

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        if ($this->conn === null) {
            $host = "localhost";
            $dbname = "inventory_db";
            $username = "root";
            $password = "";
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->conn = new PDO($dsn, $username, $password, $options);
        }

        return $this->conn;
    }
}

// Create a global PDO instance for backward compatibility
// This makes $pdo available in all files that include database.php
$pdo = Database::getInstance()->getConnection();