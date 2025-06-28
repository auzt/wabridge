<?php

/**
 * WhatsApp Bridge - Main Index
 * Redirects to admin login
 */

// Get the current script directory relative to document root
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$redirectUrl = $scriptDir . '/admin/login.php';

header('Location: ' . $redirectUrl);
exit;
