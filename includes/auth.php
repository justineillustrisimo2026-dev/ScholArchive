<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    $cache = $u ?: null;
    return $cache;
}

function require_admin(): array {
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') {
        header('Location: /scholarchive1/index.php');
        exit;
    }
    return $u;
}

function require_student(): array {
    $u = current_user();
    if (!$u || $u['role'] !== 'student') {
        header('Location: /scholarchive1/index.php');
        exit;
    }
    if ($u['status'] !== 'active') {
        session_destroy();
        header('Location: /scholarchive1/index.php?error=notactive');
        exit;
    }
    return $u;
}

function flash_set(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
    if (!empty($_SESSION['flash'][$key])) {
        $m = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $m;
    }
    return null;
}

function get_initials(string $firstName, string $lastName): string {
    $first = !empty($firstName) ? strtoupper(substr($firstName, 0, 1)) : '';
    $last = !empty($lastName) ? strtoupper(substr($lastName, 0, 1)) : '';
    return $first . $last;
}