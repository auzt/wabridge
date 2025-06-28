<?php

/**
 * WhatsApp Bridge - Logout
 */

require_once __DIR__ . '/../includes/auth.php';

// Logout user
logout();

// Redirect to login page
header('Location: login.php');
exit;
