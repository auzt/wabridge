<?php

/**
 * WhatsApp Bridge - Device Management
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_client.php';

// Require login
requireLogin();

$currentUser = getCurrentUser();
$deviceManager = new DeviceManager();
$apiClient = new WhatsAppApiClient();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        switch ($_POST['form_action']) {
            case 'create':
                handleCreateDevice();
                break;
            case 'update':
                handleUpdateDevice();
                break;
            case 'delete':
                handleDeleteDevice();
                break;
            case 'connect':
                handleConnectDevice();
                break;
            case 'disconnect':
                handleDisconnectDevice();
                break;
        }
    }
}

// Get devices
$devices = $deviceManager->getAllDevices($currentUser['id']);

// Handle different actions
function handleCreateDevice()
{
    global $deviceManager, $apiClient, $currentUser, $success, $error;

    $deviceName = cleanInput($_POST['device_name'] ?? '');
    $sessionId = cleanInput($_POST['session_id'] ?? '');
    $webhookUrl = cleanInput($_POST['webhook_url'] ?? '');
    $note = cleanInput($_POST['note'] ?? '');

    if (empty($deviceName)) {
        $error = 'Device name is required.';
        return;
    }

    // Generate session ID if not provided
    if (empty($sessionId)) {
        $sessionId = generateSessionId();
    }

    // Check if session ID already exists
    $existingDevice = $deviceManager->getDeviceBySessionId($sessionId);
    if ($existingDevice) {
        $error = 'Session ID already exists. Please use a different one.';
        return;
    }

    // Validate webhook URL if provided
    if (!empty($webhookUrl) && !isValidUrl($webhookUrl)) {
        $error = 'Invalid webhook URL format.';
        return;
    }

    // Create device with note
    $device = $deviceManager->createDevice($deviceName, $sessionId, $webhookUrl, $currentUser['id'], $note);
    if ($device) {
        $success = 'Device created successfully! API Key: ' . maskApiKey($device['api_key']);
    } else {
        $error = 'Failed to create device.';
    }
}

function handleUpdateDevice()
{
    global $deviceManager, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);
    $webhookUrl = cleanInput($_POST['webhook_url'] ?? '');

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    // Validate webhook URL if provided
    if (!empty($webhookUrl) && !isValidUrl($webhookUrl)) {
        $error = 'Invalid webhook URL format.';
        return;
    }

    if ($deviceManager->updateWebhookUrl($deviceId, $webhookUrl)) {
        $success = 'Device updated successfully.';
    } else {
        $error = 'Failed to update device.';
    }
}

function handleDeleteDevice()
{
    global $deviceManager, $apiClient, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    // Try to disconnect session first
    try {
        $apiClient->disconnectSession($device['session_id']);
    } catch (Exception $e) {
        // Continue even if disconnect fails
    }

    if ($deviceManager->deleteDevice($deviceId)) {
        $success = 'Device deleted successfully.';
    } else {
        $error = 'Failed to delete device.';
    }
}

function handleConnectDevice()
{
    global $deviceManager, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    try {
        // Test Node.js API health first
        $nodeApi = new NodeApiClient('your_secure_api_key_here_change_this');
        $healthCheck = $nodeApi->healthCheck();

        if ($healthCheck['status_code'] !== 200 || !isset($healthCheck['data']['success']) || !$healthCheck['data']['success']) {
            $error = 'Node.js API is not responding properly. Status: ' . ($healthCheck['status_code'] ?? 'unknown');
            return;
        }

        // Try to connect session
        $response = $nodeApi->connectSession($device['session_id']);

        if ($response['status_code'] === 200 && isset($response['data']['success']) && $response['data']['success']) {
            $deviceManager->updateDeviceStatus($device['id'], 'connecting');
            $success = 'Connection initiated. Check QR code if needed.';
        } else {
            $error = 'Failed to connect: ' . ($response['data']['error'] ?? 'Node.js API error');
        }
    } catch (Exception $e) {
        $error = 'Connection error: Make sure Node.js WhatsApp API is running on ' . NODE_API_URL;
    }
}

function handleDisconnectDevice()
{
    global $deviceManager, $apiClient, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    $result = $apiClient->disconnectSession($device['session_id']);
    if ($result['success']) {
        $success = 'Device disconnected successfully.';
    } else {
        $error = 'Failed to disconnect: ' . $result['error'];
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - <?php echo APP_NAME; ?></title>
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
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
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

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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

        input[type="text"],
        input[type="url"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }

        input[type="text"]:focus,
        input[type="url"]:focus {
            outline: none;
            border-color: #3498db;
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

        .devices-table {
            width: 100%;
            border-collapse: collapse;
        }

        .devices-table th,
        .devices-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .devices-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
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

        .status-inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .qr-code {
            text-align: center;
            padding: 1rem;
        }

        .qr-code img {
            max-width: 300px;
            width: 100%;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .devices-table {
                font-size: 0.8rem;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?> - Device Management</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="devices.php" class="active">Devices</a></li>
            <li><a href="messages.php">Messages</a></li>
            <li><a href="webhooks.php">Webhooks</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Create Device Form -->
        <div class="card">
            <div class="card-header">
                Create New Device
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="form_action" value="create">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="device_name">Device Name *</label>
                            <input type="text" id="device_name" name="device_name" required
                                placeholder="My WhatsApp Device">
                        </div>

                        <div class="form-group">
                            <label for="session_id">Session ID (optional)</label>
                            <input type="text" id="session_id" name="session_id"
                                placeholder="Leave empty to auto-generate">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="webhook_url">Webhook URL (optional)</label>
                        <input type="url" id="webhook_url" name="webhook_url"
                            placeholder="https://yourapp.com/webhook">
                    </div>

                    <div class="form-group">
                        <label for="note">Note (optional)</label>
                        <textarea id="note" name="note" rows="3"
                            placeholder="Catatan untuk device ini, misalnya: Device untuk customer service, Device untuk marketing, dll"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">Create Device</button>
                </form>
            </div>
        </div>

        <!-- Devices List -->
        <div class="card">
            <div class="card-header">
                Your Devices (<?php echo count($devices); ?>)
            </div>
            <div class="card-body">
                <?php if (empty($devices)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">
                        No devices found. Create your first device above.
                    </p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="devices-table">
                            <thead>
                                <tr>
                                    <th>Device Name</th>
                                    <th>Session ID</th>
                                    <th>Phone Number</th>
                                    <th>Status</th>
                                    <th>API Key</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devices as $device): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($device['device_name']); ?></strong>
                                            <?php if ($device['webhook_url']): ?>
                                                <br><small>🔗 Webhook configured</small>
                                            <?php endif; ?>
                                            <?php if ($device['note']): ?>
                                                <br><small style="color: #666;">📝 <?php echo htmlspecialchars(truncateText($device['note'], 50)); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($device['session_id']); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($device['phone_number']): ?>
                                                📱 <?php echo htmlspecialchars($device['phone_number']); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Not connected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $device['status']; ?>">
                                                <?php echo ucfirst($device['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo maskApiKey($device['api_key']); ?></code>
                                            <button class="btn btn-small" onclick="copyToClipboard('<?php echo $device['api_key']; ?>')">
                                                Copy
                                            </button>
                                        </td>
                                        <td>
                                            <?php echo $device['last_activity'] ? timeAgo($device['last_activity']) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <?php if ($device['status'] === 'disconnected' || $device['status'] === 'inactive'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="form_action" value="connect">
                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                        <button type="submit" class="btn btn-small btn-success">Connect</button>
                                                    </form>
                                                <?php elseif ($device['status'] === 'connected'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="form_action" value="disconnect">
                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                        <button type="submit" class="btn btn-small btn-warning">Disconnect</button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- QR Code button untuk semua status kecuali connected -->
                                                <?php if ($device['status'] !== 'connected'): ?>
                                                    <button class="btn btn-small" onclick="showQR('<?php echo $device['session_id']; ?>', '<?php echo $device['api_key']; ?>')">
                                                        QR Code
                                                    </button>
                                                <?php endif; ?>

                                                <button class="btn btn-small" onclick="showApiExamples('<?php echo $device['api_key']; ?>', '<?php echo htmlspecialchars($device['device_name']); ?>')">
                                                    API Examples
                                                </button>

                                                <button class="btn btn-small" onclick="editDevice(<?php echo $device['id']; ?>, '<?php echo addslashes($device['webhook_url'] ?? ''); ?>', '<?php echo addslashes($device['note'] ?? ''); ?>')">
                                                    Edit
                                                </button>

                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this device?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeQR()">&times;</span>
            <h3>QR Code Scanner</h3>

            <!-- Tab Navigation -->
            <div style="display: flex; margin-bottom: 1rem; border-bottom: 1px solid #ddd;">
                <button id="showQRTab" onclick="switchQRTab('show')" style="padding: 0.5rem 1rem; border: none; background: #3498db; color: white; cursor: pointer;">Show QR</button>
                <button id="scanQRTab" onclick="switchQRTab('scan')" style="padding: 0.5rem 1rem; border: none; background: #f8f9fa; color: #333; cursor: pointer;">Scan QR</button>
            </div>

            <!-- Show QR Content -->
            <div id="showQRContent" class="qr-content">
                <div id="qrContent">Loading QR code...</div>
            </div>

            <!-- Scan QR Content -->
            <div id="scanQRContent" class="qr-content" style="display: none;">
                <div style="text-align: center;">
                    <video id="qrVideo" width="300" height="300" style="border: 1px solid #ddd; margin-bottom: 1rem;"></video>
                    <canvas id="qrCanvas" style="display: none;"></canvas>
                    <br>
                    <button id="startScanBtn" onclick="startQRScan()" class="btn btn-success">Start Camera</button>
                    <button id="stopScanBtn" onclick="stopQRScan()" class="btn btn-danger" style="display: none;">Stop Camera</button>
                    <div id="scanResult" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px; display: none;">
                        <strong>Scanned QR Code:</strong><br>
                        <textarea id="scannedText" rows="10" style="width: 100%; margin-top: 0.5rem;" readonly></textarea>
                        <br><br>
                        <button onclick="sendQRToWhatsApp()" class="btn btn-success">Send to WhatsApp</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Device Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEdit()">&times;</span>
            <h3>Edit Device</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="form_action" value="update">
                <input type="hidden" id="edit_device_id" name="device_id" value="">

                <div class="form-group">
                    <label for="edit_webhook_url">Webhook URL</label>
                    <input type="url" id="edit_webhook_url" name="webhook_url"
                        placeholder="https://yourapp.com/webhook">
                </div>

                <button type="submit" class="btn btn-success">Update Device</button>
                <button type="button" class="btn" onclick="closeEdit()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- API Examples Modal -->
    <div id="apiModal" class="modal">
        <div class="modal-content" style="max-width: 900px; width: 95%;">
            <span class="close" onclick="closeApi()">&times;</span>
            <h3>API Usage Examples</h3>
            <div id="apiContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied to clipboard!');
            });
        }

        function showQR(sessionId, apiKey) {
            document.getElementById('qrModal').style.display = 'block';
            document.getElementById('qrContent').innerHTML = '<p>Loading QR code...</p>';

            // Fetch QR code
            fetch(`../api/auth.php?action=qr`, {
                    headers: {
                        'X-API-Key': apiKey
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.qr_code) {
                        document.getElementById('qrContent').innerHTML =
                            `<img src="data:image/png;base64,${data.data.qr_code}" alt="QR Code" style="max-width: 300px; width: 100%;">
                         <p>Scan this QR code with WhatsApp</p>
                         <button class="btn" onclick="refreshQR('${sessionId}', '${apiKey}')">Refresh QR</button>`;
                    } else {
                        document.getElementById('qrContent').innerHTML =
                            '<p>QR code not available. Try connecting the device first.</p>';
                    }
                })
                .catch(error => {
                    document.getElementById('qrContent').innerHTML =
                        '<p>Error loading QR code: ' + error.message + '</p>';
                });
        }

        function refreshQR(sessionId, apiKey) {
            showQR(sessionId, apiKey);
        }

        function closeQR() {
            document.getElementById('qrModal').style.display = 'none';
        }

        function showApiExamples(apiKey, deviceName) {
            document.getElementById('apiModal').style.display = 'block';

            const baseUrl = window.location.origin + window.location.pathname.replace('/admin/devices.php', '');

            document.getElementById('apiContent').innerHTML = `
                <div style="max-height: 70vh; overflow-y: auto;">
                    <div style="background: #e3f2fd; padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                        <strong>Device:</strong> ${deviceName}<br>
                        <strong>API Key:</strong> <code>${apiKey}</code>
                        <button onclick="copyToClipboard('${apiKey}')" style="margin-left: 10px; padding: 2px 8px; font-size: 0.8rem;">Copy</button>
                    </div>
                    
                    <h4>1. Send Text Message</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_text",
    "to": "628123456789",
    "text": "Hello from ${deviceName}!"
  }'</code></pre>
                    
                    <h4>2. Send to Multiple Numbers</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_text",
    "to": ["628123456789", "628987654321"],
    "text": "Broadcast message from ${deviceName}"
  }'</code></pre>
                    
                    <h4>3. Send to Group</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_text",
    "to": "120363123456789012@g.us",
    "text": "Hello Group! Message from ${deviceName}"
  }'</code></pre>
                    
                    <h4>4. Send Image</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_media",
    "to": "628123456789",
    "media_url": "https://example.com/image.jpg",
    "type": "image",
    "caption": "Check this image!"
  }'</code></pre>
                    
                    <h4>6. Send Location</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X POST ${baseUrl}/api/messages \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "send_location",
    "to": "628123456789",
    "latitude": -6.200000,
    "longitude": 106.816666,
    "name": "Jakarta",
    "address": "Jakarta, Indonesia"
  }'</code></pre>
                    
                    <h4>7. Get Session Status</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X GET "${baseUrl}/api/auth?action=status" \\
  -H "X-API-Key: ${apiKey}"</code></pre>
                    
                    <h4>6. Get Messages</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X GET "${baseUrl}/api/messages?limit=50" \\
  -H "X-API-Key: ${apiKey}"</code></pre>
                    
                    <h4>9. Connect/Disconnect</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code># Connect
curl -X POST ${baseUrl}/api/auth \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{"action": "connect"}'

# Disconnect  
curl -X POST ${baseUrl}/api/auth \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{"action": "disconnect"}'</code></pre>
                    
                    <h4>10. Test Webhook</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>curl -X POST ${baseUrl}/api/webhook \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: ${apiKey}" \\
  -d '{
    "action": "test",
    "webhook_url": "https://yourapp.com/webhook"
  }'</code></pre>
                    
                    <h4>Response Format</h4>
                    <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>{
  "success": true,
  "message": "Text message sent successfully",
  "data": {
    "results": [
      {
        "to": "628123456789",
        "success": true,
        "messageId": "msg_abc123",
        "timestamp": 1640995200
      }
    ]
  }
}</code></pre>
                    
                    <div style="background: #fff3cd; padding: 1rem; border-radius: 4px; margin-top: 1rem;">
                        <strong>📝 Notes:</strong><br>
                        • Replace <code>628123456789</code> with actual phone numbers<br>
                        • Phone numbers must include country code (62 for Indonesia)<br>
                        • Group ID format: <code>120363123456789012@g.us</code><br>
                        • All endpoints return JSON responses<br>
                        • Check device status is "connected" before sending messages<br>
                        • Use webhook URL to receive incoming messages
                    </div>
                </div>
            `;
        }

        function closeApi() {
            document.getElementById('apiModal').style.display = 'none';
        }

        function editDevice(deviceId, webhookUrl, note) {
            document.getElementById('edit_device_id').value = deviceId;
            document.getElementById('edit_webhook_url').value = webhookUrl || '';
            document.getElementById('edit_note').value = note || '';
            document.getElementById('editModal').style.display = 'block';

            // Debug log
            console.log('Edit device called:', {
                deviceId,
                webhookUrl,
                note
            });
        }

        function closeEdit() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const qrModal = document.getElementById('qrModal');
            const editModal = document.getElementById('editModal');
            const apiModal = document.getElementById('apiModal');

            if (event.target == qrModal) {
                qrModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == apiModal) {
                apiModal.style.display = 'none';
            }
        }

        // Auto refresh status every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>

</html>