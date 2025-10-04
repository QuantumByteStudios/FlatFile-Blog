<?php

/**
 * Admin Logout
 */

// Ensure config is loaded so BASE_URL exists
$cfg = file_exists(__DIR__ . '/../config.php') ? __DIR__ . '/../config.php' : __DIR__ . '/config.php';
if (file_exists($cfg)) {
    require_once $cfg;
}

session_start();

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page (fallback to relative if BASE_URL missing)
$redirect = defined('BASE_URL') ? BASE_URL . 'admin/login' : 'login';
header('Location: ' . $redirect);
exit;
