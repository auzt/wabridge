<?php

/**
 * WhatsApp Bridge - Device Management Main Page
 * File: admin/device/index.php
 */

// Include required files - JANGAN panggil session_start() karena auth.php sudah memanggil
require_once __DIR__ . '/../../includes/auth.php';  // Ini sudah include session_start
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_client.php';

// Check authentication
requireLogin();
$currentUser = getCurrentUser();

// Initialize classes
$deviceManager = new DeviceManager();
$apiClient = new WhatsAppApiClient(); // PERBAIKAN: gunakan WhatsAppApiClient

// Initialize variables
$error = '';
$success = '';

// Handle POST requests SEBELUM include handlers.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Include handlers.php hanya ketika ada POST request
        $handlersFile = __DIR__ . '/handlers.php';
        if (file_exists($handlersFile)) {
            require_once $handlersFile;

            // Call handler function berdasarkan action
            switch ($action) {
                case 'create':
                    if (function_exists('handleCreateDevice')) {
                        handleCreateDevice();
                    }
                    break;
                case 'connect':
                    if (function_exists('handleConnectDevice')) {
                        handleConnectDevice();
                    }
                    break;
                case 'disconnect':
                    if (function_exists('handleDisconnectDevice')) {
                        handleDisconnectDevice();
                    }
                    break;
                case 'delete':
                    if (function_exists('handleDeleteDevice')) {
                        handleDeleteDevice();
                    }
                    break;
                default:
                    $error = 'Invalid action.';
            }
        } else {
            $error = 'Handler functions not found.';
        }
    }
}

// Get devices and statistics with error handling
try {
    $devices = $deviceManager->getAllDevices($currentUser['id']) ?? [];

    // Calculate statistics
    $stats = [
        'total' => count($devices),
        'connected' => 0,
        'connecting' => 0,
        'disconnected' => 0,
        'inactive' => 0
    ];

    foreach ($devices as $device) {
        $status = $device['status'] ?? 'disconnected';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
} catch (Exception $e) {
    $error = 'Error loading devices: ' . $e->getMessage();
    $devices = [];
    $stats = [
        'total' => 0,
        'connected' => 0,
        'connecting' => 0,
        'disconnected' => 0,
        'inactive' => 0
    ];
}

// Generate CSRF token
$csrfToken = generateCsrfToken();

// Page title
$pageTitle = 'Device Management';

// Include the view
$viewFile = __DIR__ . '/views.php';
if (file_exists($viewFile)) {
    require_once $viewFile;
} else {
    // Basic fallback view
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }

            .alert {
                padding: 10px;
                margin: 10px 0;
                border-radius: 4px;
            }

            .alert-error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .alert-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .stats {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }

            .stat-card {
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
            }
        </style>
    </head>

    <body>
        <h1><?php echo $pageTitle; ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Devices</h3>
                <p><?php echo $stats['total']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Connected</h3>
                <p><?php echo $stats['connected']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Connecting</h3>
                <p><?php echo $stats['connecting']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Disconnected</h3>
                <p><?php echo $stats['disconnected']; ?></p>
            </div>
        </div>

        <nav>
            <a href="../dashboard.php">← Back to Dashboard</a>
        </nav>
    </body>

    </html>
<?php
}
