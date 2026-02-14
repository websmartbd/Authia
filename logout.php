<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Destroy session securely
SessionSecurity::destroy();

// Clear the "remember_me" cookie
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, "/");
}

// Redirect to login page
header("Location: login");
exit;