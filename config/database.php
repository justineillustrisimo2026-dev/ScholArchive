<?php
// /config/database.php

function get_db(): PDO {
    // Use a static variable so the connection is reused within the same request
    static $pdo = null;

    if ($pdo === null) {
        // Database configuration (Adjust these if your local setup is different)
        $host = '127.0.0.1';
        $dbname = 'scholarchive';
        $username = 'root'; // Default XAMPP/WAMP username
        $password = '';     // Default XAMPP/WAMP password (usually blank)

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}