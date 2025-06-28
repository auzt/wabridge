<?php

/**
 * WhatsApp Bridge - Webhook Management
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireLogin();

$currentUser = getCurrentUser();
$deviceManager = new DeviceManager();
$webhookManager = new WebhookManager();

$error = '';
$success = '';

// Get user's devices
$devices = $deviceManager->getAllDevices($currentUser['id']);

// Handle webhook test
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'test':
                handleTestWebhook();
                break;
            case 'update':
                handleUpdateWebhook();
                break;
        }
    }
}

function handleTestWebhook()
{
    global $deviceManager, $webhookManager, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);
    $webhookUrl = cleanInput($_POST['webhook_url'] ?? '');

    // Find device
    $device = null;
    foreach ($GLOBALS['devices'] as $d) {
        if ($d['id'] == $deviceId) {
            $device = $d;
            break;
        }
    }

    if (!$device) {
        $error = 'Device not found.';
        return;
    }

    if (empty($webhookUrl)) {
        $error = 'Webhook URL is required.';
        return;
    }

    if (!isValidUrl($webhookUrl)) {
        $error = 'Invalid webhook URL format.';
        return;
    }

    // Create test webhook data
    $testData = [
        'event' => 'webhook_test',
        'device_id' => $device['id'],
        'device_name' => $device['device_name'],
        'session_id' => $device['session_id'],
        'message' => 'This is a test webhook from WhatsApp Bridge',
        'timestamp' => getCurrentTimestamp(),
        'test' => true
    ];

    // Send test webhook
    $result = $webhookManager->sendWebhook($device['id'], $webhookUrl, $testData, 'webhook_test');

    if ($result) {
        $success = 'Test webhook sent successfully to ' . $webhookUrl;
    } else {
        $error = 'Failed to send test webhook. Check the logs for details.';
    }
}

function handleUpdateWebhook()
{
    global $deviceManager, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);
    $webhookUrl = cleanInput($_POST['webhook_url'] ?? '');

    // Find device
    $device = null;
    foreach ($GLOBALS['devices'] as $d) {
        if ($d['id'] == $deviceId) {
            $device = $d;
            break;
        }
    }

    if (!$device) {
        $error = 'Device not found.';
        return;
    }

    // Validate URL if not empty
    if (!empty($webhookUrl) && !isValidUrl($webhookUrl)) {
        $error = 'Invalid webhook URL format.';
        return;
    }

    if ($deviceManager->updateWebhookUrl($deviceId, $webhookUrl)) {
        $success = 'Webhook URL updated successfully.';
        // Refresh devices list
        $GLOBALS['devices'] = $deviceManager->getAllDevices($currentUser['id']);
    } else {
        $error = 'Failed to update webhook URL.';
    }
}

// Get webhook statistics
$webhookStats = [];
foreach ($devices as $device) {
    $stats = $webhookManager->getWebhookStats($device['id']);
    $webhookStats[$device['id']] = $stats ?: [
        'total_calls' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'avg_execution_time' => 0
    ];
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooks - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f6fa;
            line-height: 1.6;
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 1.5rem;
        }

        .nav {
            background: #2c3e50;
            padding: 1rem 2rem;
        }

        .nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .nav a:hover,
        .nav a.active {
            background: #34495e;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #333;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        input,
        select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            margin-right: 0.5rem;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #393;
            border: 1px solid #cfc;
        }

        .devices-grid {
            display: grid;
            gap: 1.5rem;
        }

        .device-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            background: #f8f9fa;
        }

        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .device-name {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }

        .device-status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-connected {
            background: #d4edda;
            color: #155724;
        }

        .status-connecting {
            background: #fff3cd;
            color: #856404;
        }

        .status-disconnected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-banned {
            background: #f5c6cb;
            color: #721c24;
        }

        .webhook-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .webhook-url {
            font-family: monospace;
            background: white;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            word-break: break-all;
            font-size: 0.9rem;
        }

        .webhook-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .webhook-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 1rem 0;
        }

        .endpoint-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 4px;
            border-left: 4px solid #2196f3;
            margin-bottom: 1rem;
        }

        .endpoint-url {
            font-family: monospace;
            font-weight: bold;
            color: #1976d2;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .webhook-info {
                grid-template-columns: 1fr;
            }

            .webhook-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?> - Webhooks</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="devices.php">Devices</a></li>
            <li><a href="messages.php">Messages</a></li>
            <li><a href="webhooks.php" class="active">Webhooks</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Webhook Information -->
        <div class="card">
            <div class="card-header">Webhook Information</div>
            <div class="card-body">
                <div class="endpoint-info">
                    <strong>Webhook Receiver Endpoint:</strong><br>
                    <span class="endpoint-url"><?php echo APP_URL; ?>/webhooks/receiver.php</span>
                </div>

                <p>Configure your Node.js WhatsApp API to send webhooks to the endpoint above.
                    The bridge will automatically process incoming webhooks and forward them to your configured URLs.</p>

                <h4 style="margin: 1.5rem 0 1rem 0;">Supported Events:</h4>
                <ul style="margin-left: 2rem; color: #666;">
                    <li><strong>message</strong> - New message received</li>
                    <li><strong>connection_update</strong> - Device connection status changed</li>
                    <li><strong>qr_code</strong> - QR code generated for pairing</li>
                    <li><strong>auth_failure</strong> - Authentication failed or device banned</li>
                    <li><strong>contact_update</strong> - Contact information updated</li>
                    <li><strong>group_update</strong> - Group information updated</li>
                </ul>
            </div>
        </div>

        <!-- Test Webhook -->
        <div class="card">
            <div class="card-header">Test Webhook</div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="test">

                    <div class="webhook-info">
                        <div class="form-group">
                            <label for="test_device_id">Device</label>
                            <select id="test_device_id" name="device_id" required>
                                <option value="">Select Device</option>
                                <?php foreach ($devices as $device): ?>
                                    <option value="<?php echo $device['id']; ?>">
                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                        (<?php echo ucfirst($device['status']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="test_webhook_url">Webhook URL</label>
                            <input type="url" id="test_webhook_url" name="webhook_url" required
                                placeholder="https://yourapp.com/webhook">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning">Send Test Webhook</button>
                </form>

                <div class="code-block">
                    <strong>Test webhook payload example:</strong>
                    <pre>{
  "event": "webhook_test",
  "device_id": 1,
  "device_name": "My Device",
  "session_id": "session_123",
  "message": "This is a test webhook from WhatsApp Bridge",
  "timestamp": "2024-01-01 12:00:00",
  "test": true
}</pre>
                </div>
            </div>
        </div>

        <!-- Device Webhooks -->
        <div class="card">
            <div class="card-header">Device Webhooks (<?php echo count($devices); ?>)</div>
            <div class="card-body">
                <?php if (empty($devices)): ?>
                    <p style="color: #666; text-align: center; padding: 2rem;">
                        No devices found. <a href="devices.php">Create a device</a> first.
                    </p>
                <?php else: ?>
                    <div class="devices-grid">
                        <?php foreach ($devices as $device): ?>
                            <div class="device-card">
                                <div class="device-header">
                                    <div class="device-name">
                                        <?php echo htmlspecialchars($device['device_name']); ?>
                                    </div>
                                    <div class="device-status status-<?php echo $device['status']; ?>">
                                        <?php echo ucfirst($device['status']); ?>
                                    </div>
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <strong>Session ID:</strong>
                                    <code><?php echo htmlspecialchars($device['session_id']); ?></code>
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <strong>Webhook URL:</strong>
                                    <?php if ($device['webhook_url']): ?>
                                        <div class="webhook-url">
                                            <?php echo htmlspecialchars($device['webhook_url']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #999; font-style: italic;">Not configured</div>
                                    <?php endif; ?>
                                </div>

                                <!-- Webhook Statistics (Last 24 hours) -->
                                <div class="webhook-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $webhookStats[$device['id']]['total_calls']; ?></div>
                                        <div class="stat-label">Total Calls</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $webhookStats[$device['id']]['success_count']; ?></div>
                                        <div class="stat-label">Success</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $webhookStats[$device['id']]['failed_count']; ?></div>
                                        <div class="stat-label">Failed</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php echo number_format($webhookStats[$device['id']]['avg_execution_time'], 0); ?>ms
                                        </div>
                                        <div class="stat-label">Avg Time</div>
                                    </div>
                                </div>

                                <div class="webhook-actions">
                                    <button class="btn" onclick="updateWebhook(<?php echo $device['id']; ?>, '<?php echo htmlspecialchars($device['webhook_url']); ?>')">
                                        Update URL
                                    </button>

                                    <?php if ($device['webhook_url']): ?>
                                        <button class="btn btn-warning" onclick="testWebhook(<?php echo $device['id']; ?>, '<?php echo htmlspecialchars($device['webhook_url']); ?>')">
                                            Test Webhook
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn" onclick="viewLogs(<?php echo $device['id']; ?>)">
                                        View Logs
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Webhook Examples -->
        <div class="card">
            <div class="card-header">Webhook Payload Examples</div>
            <div class="card-body">
                <h4>Message Received Event:</h4>
                <div class="code-block">
                    <pre>{
  "event": "message_received",
  "device_id": 1,
  "device_name": "My Device",
  "session_id": "session_123",
  "message": {
    "id": "message_id_123",
    "type": "text",
    "from": "628123456789",
    "to": "628987654321",
    "content": "Hello World!",
    "timestamp": "2024-01-01 12:00:00"
  },
  "timestamp": "2024-01-01 12:00:00"
}</pre>
                </div>

                <h4>Connection Update Event:</h4>
                <div class="code-block">
                    <pre>{
  "event": "connection_update",
  "device_id": 1,
  "device_name": "My Device",
  "session_id": "session_123",
  "status": "connected",
  "node_status": "CONNECTED",
  "phone_number": "628987654321",
  "timestamp": "2024-01-01 12:00:00"
}</pre>
                </div>

                <h4>QR Code Event:</h4>
                <div class="code-block">
                    <pre>{
  "event": "qr_code",
  "device_id": 1,
  "device_name": "My Device",
  "session_id": "session_123",
  "qr_code": "base64_qr_code_string",
  "timestamp": "2024-01-01 12:00:00"
}</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Webhook Modal -->
    <div id="updateModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 5% auto; padding: 2rem; border-radius: 8px; width: 90%; max-width: 500px;">
            <h3>Update Webhook URL</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="update_device_id" name="device_id" value="">

                <div class="form-group">
                    <label for="update_webhook_url">Webhook URL</label>
                    <input type="url" id="update_webhook_url" name="webhook_url"
                        placeholder="https://yourapp.com/webhook">
                    <small style="color: #666;">Leave empty to remove webhook URL</small>
                </div>

                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-success">Update</button>
                    <button type="button" class="btn" onclick="closeUpdate()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateWebhook(deviceId, currentUrl) {
            document.getElementById('update_device_id').value = deviceId;
            document.getElementById('update_webhook_url').value = currentUrl;
            document.getElementById('updateModal').style.display = 'block';
        }

        function closeUpdate() {
            document.getElementById('updateModal').style.display = 'none';
        }

        function testWebhook(deviceId, webhookUrl) {
            document.getElementById('test_device_id').value = deviceId;
            document.getElementById('test_webhook_url').value = webhookUrl;
            document.getElementById('test_webhook_url').focus();
        }

        function viewLogs(deviceId) {
            // In a real implementation, this would open a logs modal or redirect to logs page
            alert('Webhook logs feature would be implemented here.\n\nFor now, check the webhook_logs table in database or logs/webhook_*.log files.');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>