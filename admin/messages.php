<?php

/**
 * WhatsApp Bridge - Messages Management
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_client.php';

// Require login
requireLogin();

$currentUser = getCurrentUser();
$deviceManager = new DeviceManager();
$messageManager = new MessageManager();
$apiClient = new WhatsAppApiClient();

$error = '';
$success = '';

// Get user's devices
$devices = $deviceManager->getAllDevices($currentUser['id']);

// Handle send message form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        handleSendMessage();
    }
}

function handleSendMessage()
{
    global $devices, $apiClient, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);
    $to = cleanInput($_POST['to'] ?? '');
    $messageType = cleanInput($_POST['message_type'] ?? 'text');
    $text = cleanInput($_POST['text'] ?? '');

    // Find device
    $selectedDevice = null;
    foreach ($devices as $device) {
        if ($device['id'] == $deviceId) {
            $selectedDevice = $device;
            break;
        }
    }

    if (!$selectedDevice) {
        $error = 'Device not found.';
        return;
    }

    if (empty($to)) {
        $error = 'Phone number is required.';
        return;
    }

    // Validate phone number
    $formattedPhone = validatePhoneNumber($to);
    if (!$formattedPhone) {
        $error = 'Invalid phone number format.';
        return;
    }

    try {
        // Create API client with device API key
        $deviceApiClient = new WhatsAppApiClient($selectedDevice['api_key']);

        switch ($messageType) {
            case 'text':
                if (empty($text)) {
                    $error = 'Message text is required.';
                    return;
                }

                $result = $deviceApiClient->sendTextMessage($selectedDevice['session_id'], $formattedPhone, $text);
                break;

            case 'location':
                $latitude = (float)($_POST['latitude'] ?? 0);
                $longitude = (float)($_POST['longitude'] ?? 0);
                $locationName = cleanInput($_POST['location_name'] ?? '');
                $locationAddress = cleanInput($_POST['location_address'] ?? '');

                if (!$latitude || !$longitude) {
                    $error = 'Latitude and longitude are required.';
                    return;
                }

                $result = $deviceApiClient->sendLocation($selectedDevice['session_id'], $formattedPhone, $latitude, $longitude, $locationName, $locationAddress);
                break;

            default:
                $error = 'Invalid message type.';
                return;
        }

        if ($result['success']) {
            $success = 'Message sent successfully to ' . $formattedPhone;
        } else {
            $error = 'Failed to send message: ' . $result['error'];
        }
    } catch (Exception $e) {
        $error = 'Error sending message: ' . $e->getMessage();
    }
}

// Get messages for display
$selectedDeviceId = (int)($_GET['device_id'] ?? 0);
$direction = $_GET['direction'] ?? '';
$limit = min((int)($_GET['limit'] ?? 50), 100);
$offset = max((int)($_GET['offset'] ?? 0), 0);

$messages = [];
$totalMessages = 0;

if ($selectedDeviceId > 0) {
    // Check if user owns this device
    $selectedDevice = null;
    foreach ($devices as $device) {
        if ($device['id'] == $selectedDeviceId) {
            $selectedDevice = $device;
            break;
        }
    }

    if ($selectedDevice) {
        $messages = $messageManager->getMessages($selectedDeviceId, $limit, $offset, $direction ?: null);

        // Get total count for pagination
        $db = Database::getInstance();
        $totalQuery = "SELECT COUNT(*) as total FROM messages WHERE device_id = ?";
        $totalParams = [$selectedDeviceId];

        if ($direction) {
            $totalQuery .= " AND direction = ?";
            $totalParams[] = $direction;
        }

        $totalResult = $db->fetch($totalQuery, $totalParams);
        $totalMessages = $totalResult['total'] ?? 0;
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
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

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
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
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }

        input:focus,
        select:focus,
        textarea:focus {
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

        .filters {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .messages-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .message-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item.incoming {
            background: #f8f9fa;
        }

        .message-item.outgoing {
            background: #e3f2fd;
        }

        .message-content {
            flex: 1;
        }

        .message-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .message-direction {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .direction-incoming {
            background: #d4edda;
            color: #155724;
        }

        .direction-outgoing {
            background: #cce5ff;
            color: #004085;
        }

        .message-type {
            font-size: 0.8rem;
            color: #666;
            background: #e9ecef;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .message-text {
            color: #333;
            margin: 0.5rem 0;
            word-wrap: break-word;
        }

        .message-meta {
            font-size: 0.8rem;
            color: #666;
        }

        .message-time {
            text-align: right;
            font-size: 0.7rem;
            color: #999;
            white-space: nowrap;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .pagination-info {
            color: #666;
            font-size: 0.9rem;
        }

        .pagination-links {
            display: flex;
            gap: 0.5rem;
        }

        .pagination-links a {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .pagination-links a:hover {
            background: #e9ecef;
        }

        .pagination-links a.disabled {
            color: #999;
            pointer-events: none;
            background: #f8f9fa;
        }

        .message-type-tabs {
            display: flex;
            margin-bottom: 1rem;
        }

        .message-type-tabs button {
            padding: 0.5rem 1rem;
            border: none;
            background: #f8f9fa;
            color: #666;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .message-type-tabs button.active {
            background: white;
            color: #333;
            border-bottom-color: #3498db;
        }

        .location-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?> - Messages</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="devices.php">Devices</a></li>
            <li><a href="messages.php" class="active">Messages</a></li>
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

        <div class="content-grid">
            <!-- Send Message -->
            <div class="card">
                <div class="card-header">Send Message</div>
                <div class="card-body">
                    <?php if (empty($devices)): ?>
                        <p style="color: #666; text-align: center;">
                            No devices available. <a href="devices.php">Create a device</a> first.
                        </p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                            <div class="form-group">
                                <label for="device_id">Device</label>
                                <select id="device_id" name="device_id" required>
                                    <option value="">Select Device</option>
                                    <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo $device['id']; ?>"
                                            <?php echo $device['status'] !== 'connected' ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($device['device_name']); ?>
                                            (<?php echo ucfirst($device['status']); ?>)
                                            <?php if ($device['phone_number']): ?>
                                                - <?php echo htmlspecialchars($device['phone_number']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="to">To (Phone Number)</label>
                                <input type="text" id="to" name="to" required
                                    placeholder="628123456789 or +62 812 3456 789">
                            </div>

                            <div class="message-type-tabs">
                                <button type="button" class="active" onclick="showMessageType('text')">Text</button>
                                <button type="button" onclick="showMessageType('location')">Location</button>
                            </div>

                            <input type="hidden" id="message_type" name="message_type" value="text">

                            <div id="text-inputs">
                                <div class="form-group">
                                    <label for="text">Message</label>
                                    <textarea id="text" name="text" rows="4"
                                        placeholder="Type your message here..."></textarea>
                                </div>
                            </div>

                            <div id="location-inputs" style="display: none;">
                                <div class="location-inputs">
                                    <div class="form-group">
                                        <label for="latitude">Latitude</label>
                                        <input type="number" id="latitude" name="latitude" step="any"
                                            placeholder="-6.200000">
                                    </div>
                                    <div class="form-group">
                                        <label for="longitude">Longitude</label>
                                        <input type="number" id="longitude" name="longitude" step="any"
                                            placeholder="106.816666">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="location_name">Name (optional)</label>
                                    <input type="text" id="location_name" name="location_name"
                                        placeholder="Location name">
                                </div>
                                <div class="form-group">
                                    <label for="location_address">Address (optional)</label>
                                    <input type="text" id="location_address" name="location_address"
                                        placeholder="Full address">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success">Send Message</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages List -->
            <div class="card">
                <div class="card-header">Messages</div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="">
                        <div class="filters">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="filter_device_id">Device</label>
                                <select id="filter_device_id" name="device_id">
                                    <option value="">All Devices</option>
                                    <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo $device['id']; ?>"
                                            <?php echo $device['id'] == $selectedDeviceId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($device['device_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="filter_direction">Direction</label>
                                <select id="filter_direction" name="direction">
                                    <option value="">All Messages</option>
                                    <option value="incoming" <?php echo $direction === 'incoming' ? 'selected' : ''; ?>>
                                        Incoming
                                    </option>
                                    <option value="outgoing" <?php echo $direction === 'outgoing' ? 'selected' : ''; ?>>
                                        Outgoing
                                    </option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="filter_limit">Limit</label>
                                <select id="filter_limit" name="limit">
                                    <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>

                            <button type="submit" class="btn">Filter</button>
                        </div>
                    </form>

                    <!-- Messages -->
                    <?php if (empty($messages)): ?>
                        <p style="color: #666; text-align: center; padding: 2rem;">
                            <?php if ($selectedDeviceId): ?>
                                No messages found for selected device.
                            <?php else: ?>
                                Select a device to view messages.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <div class="messages-list">
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item <?php echo $message['direction']; ?>">
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="message-direction direction-<?php echo $message['direction']; ?>">
                                                <?php echo $message['direction'] === 'incoming' ? '📥 IN' : '📤 OUT'; ?>
                                            </span>
                                            <span class="message-type">
                                                <?php echo getFileIcon($message['message_type']); ?>
                                                <?php echo ucfirst($message['message_type']); ?>
                                            </span>
                                        </div>

                                        <div class="message-meta">
                                            <?php if ($message['direction'] === 'incoming'): ?>
                                                <strong>From:</strong> <?php echo htmlspecialchars($message['from_number']); ?>
                                            <?php else: ?>
                                                <strong>To:</strong> <?php echo htmlspecialchars($message['to_number']); ?>
                                            <?php endif; ?>

                                            <?php if ($message['group_id']): ?>
                                                <br><strong>Group:</strong> <?php echo htmlspecialchars($message['group_id']); ?>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($message['message_content'])): ?>
                                            <div class="message-text">
                                                <?php echo nl2br(htmlspecialchars($message['message_content'])); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($message['media_url']): ?>
                                            <div class="message-text">
                                                📎 <em>Media: <?php echo htmlspecialchars($message['media_url']); ?></em>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($message['caption']): ?>
                                            <div class="message-text">
                                                <strong>Caption:</strong> <?php echo nl2br(htmlspecialchars($message['caption'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="message-time">
                                        <?php echo date('M j, Y', strtotime($message['received_at'])); ?><br>
                                        <?php echo date('H:i:s', strtotime($message['received_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalMessages > $limit): ?>
                            <div class="pagination">
                                <div class="pagination-info">
                                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalMessages); ?>
                                    of <?php echo $totalMessages; ?> messages
                                </div>
                                <div class="pagination-links">
                                    <?php if ($offset > 0): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['offset' => max(0, $offset - $limit)])); ?>">
                                            « Previous
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($offset + $limit < $totalMessages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['offset' => $offset + $limit])); ?>">
                                            Next »
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showMessageType(type) {
            // Update active tab
            document.querySelectorAll('.message-type-tabs button').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Update hidden input
            document.getElementById('message_type').value = type;

            // Show/hide inputs
            document.getElementById('text-inputs').style.display = type === 'text' ? 'block' : 'none';
            document.getElementById('location-inputs').style.display = type === 'location' ? 'block' : 'none';

            // Update required fields
            document.getElementById('text').required = type === 'text';
            document.getElementById('latitude').required = type === 'location';
            document.getElementById('longitude').required = type === 'location';
        }

        // Auto refresh messages every 30 seconds
        setInterval(function() {
            if (new URLSearchParams(window.location.search).get('device_id')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>

</html>