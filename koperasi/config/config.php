<?php
session_start();

// Base URL - sesuaikan dengan environment
define('BASE_URL', 'http://localhost:8000/koperasi');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Include database connection
require_once __DIR__ . '/database.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Constants
define('ROLE_ADMIN', 'admin');
define('ROLE_JURUBAYAR', 'jurubayar');
define('ROLE_USER', 'anggota');

// Global Functions
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function checkRole($allowedRoles) {
    if (!isLoggedIn()) {
        redirect('/index.php');
    }
    $user = getCurrentUser();
    if (!in_array($user['role'], (array)$allowedRoles)) {
        redirect('/index.php');
    }
    return $user;
}

function flashMessage($message, $type = 'success') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Security Functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
