<?php

class Database {
    private $conn = null;

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
