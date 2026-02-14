<?php
// Check if accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header("Location: ../dashboard.php");
    exit;
}