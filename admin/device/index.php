<?php

/**
 * WhatsApp Bridge - Device Management Main Page
 * File: admin/devices/index.php
 */

session_start();

// Include required files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/node_api.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_client.php';

// Check authentication
requireLogin();
$currentUser = getCurrentUser();

// Initialize classes
$deviceManager = new DeviceManager();
$apiClient = new ApiClient();

// Initialize variables
$error = '';
$success = '';

// Include form handlers
require_once __DIR__ . '/handlers.php';

// Get devices and statistics
$devicesResult = $deviceManager->getUserDevices($currentUser['id']);
$devices = $devicesResult['success'] ? $devicesResult['data']['devices'] : [];

$statsResult = $deviceManager->getDeviceStats($currentUser['id']);
$stats = $statsResult['success'] ? $statsResult['data'] : [
    'total' => 0,
    'connected' => 0,
    'connecting' => 0,
    'disconnected' => 0,
    'inactive' => 0
];

// Generate CSRF token
$csrfToken = generateCsrfToken();

// Page title
$pageTitle = 'Device Management';

// Include the view
require_once __DIR__ . '/views.php';
