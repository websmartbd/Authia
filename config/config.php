<?php

// Check if accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header("Location: ../dashboard.php");
    exit;
}

// Database configuration
// TO BE POPULATED BY INSTALLER
$host = '';
$username = '';
$password = '';
$database = '';

// Auto-redirect to installer if not configured
if (empty($host) && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    header("Location: install");
    exit;
}
?>