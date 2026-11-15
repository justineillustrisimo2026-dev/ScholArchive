<?php
// /includes/functions.php

/**
 * Safely escapes a string for HTML output to prevent XSS attacks.
 */
if (!function_exists('e')) {
    function e(?string $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Formats a database timestamp into a readable date (e.g., "August 30, 2026").
 */
if (!function_exists('format_date')) {
    function format_date(?string $timestamp): string {
        if (!$timestamp) return '—';
        return date('F j, Y', strtotime($timestamp));
    }
}

/**
 * Sets a temporary "flash" message in the session.
 */
if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'][$key] = $message;
    }
}

/**
 * Retrieves and immediately deletes a flash message from the session.
 */
if (!function_exists('flash_get')) {
    function flash_get(string $key): ?string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }
}

/**
 * Generates user initials for profile avatars.
 */
if (!function_exists('get_initials')) {
    function get_initials(string $firstName, string $lastName): string {
        $first = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : '';
        $last = !empty($lastName) ? strtoupper(substr($lastName, 0, 1)) : '';
        return $first . $last;
    }
}

// Add this to includes/functions.php
function log_audit($pdo, $user_id, $action, $details = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $details]);
}

if (!function_exists('get_db_connection')) {
    function get_db_connection() {
        $host = 'localhost';
        $db   = 'scholarchive'; // Replace with your actual database name if different
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}