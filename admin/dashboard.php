<?php

/**
 * WhatsApp Bridge - Dashboard
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

// Get statistics
try {
    $db = Database::getInstance();

    // Get device statistics
    $deviceStats = $db->fetch(
        "SELECT 
            COUNT(*) as total_devices,
            SUM(CASE WHEN d.status = 'connected' THEN 1 ELSE 0 END) as connected_devices,
            SUM(CASE WHEN d.status = 'connecting' THEN 1 ELSE 0 END) as connecting_devices,
            SUM(CASE WHEN d.status = 'disconnected' THEN 1 ELSE 0 END) as disconnected_devices,
            SUM(CASE WHEN d.status = 'banned' THEN 1 ELSE 0 END) as banned_devices
         FROM devices d
         WHERE d.created_by = ?",
        [$currentUser['id']]
    );

    // Get message statistics (today)
    $messageStats = $db->fetch(
        "SELECT 
            COUNT(*) as total_messages_today,
            SUM(CASE WHEN m.direction = 'incoming' THEN 1 ELSE 0 END) as incoming_today,
            SUM(CASE WHEN m.direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing_today
         FROM messages m
         JOIN devices d ON m.device_id = d.id
         WHERE d.created_by = ? AND DATE(m.received_at) = CURDATE()",
        [$currentUser['id']]
    );

    // Get webhook statistics (last 24 hours)
    $webhookStats = $db->fetch(
        "SELECT 
            COUNT(*) as total_webhooks,
            SUM(CASE WHEN wl.status = 'success' THEN 1 ELSE 0 END) as successful_webhooks,
            SUM(CASE WHEN wl.status = 'failed' THEN 1 ELSE 0 END) as failed_webhooks
         FROM webhook_logs wl
         JOIN devices d ON wl.device_id = d.id
         WHERE d.created_by = ? AND wl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        [$currentUser['id']]
    );

    // Get recent devices
    $recentDevices = $deviceManager->getAllDevices($currentUser['id']);
    $recentDevices = array_slice($recentDevices, 0, 5); // Limit to 5

    // Get recent messages
    $recentMessages = $db->fetchAll(
        "SELECT m.*, d.device_name 
         FROM messages m
         JOIN devices d ON m.device_id = d.id
         WHERE d.created_by = ?
         ORDER BY m.received_at DESC 
         LIMIT 10",
        [$currentUser['id']]
    );

    // Test Node.js API health - initialize with default values
    $nodeHealth = ['success' => false, 'data' => null];
    try {
        $nodeHealth = $apiClient->healthCheck();
    } catch (Exception $e) {
        // Keep default values if health check fails
        logError("Node.js health check failed", ['error' => $e->getMessage()]);
    }
} catch (Exception $e) {
    logError("Dashboard error", ['error' => $e->getMessage()]);
    $error = "Error loading dashboard data: " . $e->getMessage();

    // Initialize default values to prevent undefined variable errors
    $deviceStats = [
        'total_devices' => 0,
        'connected_devices' => 0,
        'connecting_devices' => 0,
        'disconnected_devices' => 0,
        'banned_devices' => 0
    ];
    $messageStats = [
        'total_messages_today' => 0,
        'incoming_today' => 0,
        'outgoing_today' => 0
    ];
    $webhookStats = [
        'total_webhooks' => 0,
        'successful_webhooks' => 0,
        'failed_webhooks' => 0
    ];
    $recentDevices = [];
    $recentMessages = [];
    $nodeHealth = ['success' => false, 'data' => null];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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

        .header .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3498db;
        }

        .stat-card.success {
            border-left-color: #27ae60;
        }

        .stat-card.warning {
            border-left-color: #f39c12;
        }

        .stat-card.danger {
            border-left-color: #e74c3c;
        }

        .stat-card.info {
            border-left-color: #9b59b6;
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-card .description {
            color: #666;
            font-size: 0.8rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
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

        .device-list,
        .message-list {
            list-style: none;
        }

        .device-item,
        .message-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .device-item:last-child,
        .message-item:last-child {
            border-bottom: none;
        }

        .device-name {
            font-weight: 500;
            color: #333;
        }

        .device-session {
            font-size: 0.8rem;
            color: #666;
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

        .message-info {
            flex: 1;
        }

        .message-from {
            font-weight: 500;
            color: #333;
        }

        .message-preview {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .message-time {
            font-size: 0.7rem;
            color: #999;
        }

        .health-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .health-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .health-healthy {
            background: #27ae60;
        }

        .health-error {
            background: #e74c3c;
        }

        .health-warning {
            background: #f39c12;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9rem;
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

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .nav ul {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h1><?php echo APP_NAME; ?> Dashboard</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>

    <nav class="nav">
        <ul>
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="devices.php">Devices</a></li>
            <li><a href="messages.php">Messages</a></li>
            <li><a href="webhooks.php">Webhooks</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card info">
                <h3>Total Devices</h3>
                <div class="value"><?php echo $deviceStats['total_devices'] ?? 0; ?></div>
                <div class="description">Registered WhatsApp devices</div>
            </div>

            <div class="stat-card success">
                <h3>Connected</h3>
                <div class="value"><?php echo $deviceStats['connected_devices'] ?? 0; ?></div>
                <div class="description">Devices currently online</div>
            </div>

            <div class="stat-card warning">
                <h3>Messages Today</h3>
                <div class="value"><?php echo $messageStats['total_messages_today'] ?? 0; ?></div>
                <div class="description">
                    <?php echo ($messageStats['incoming_today'] ?? 0); ?> in,
                    <?php echo ($messageStats['outgoing_today'] ?? 0); ?> out
                </div>
            </div>

            <div class="stat-card danger">
                <h3>Webhooks (24h)</h3>
                <div class="value"><?php echo $webhookStats['total_webhooks'] ?? 0; ?></div>
                <div class="description">
                    <?php echo ($webhookStats['successful_webhooks'] ?? 0); ?> success,
                    <?php echo ($webhookStats['failed_webhooks'] ?? 0); ?> failed
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">System Health</div>
            <div class="card-body">
                <div class="health-status">
                    <div class="health-indicator <?php echo $nodeHealth['success'] ? 'health-healthy' : 'health-error'; ?>"></div>
                    <span>Node.js API: <?php echo $nodeHealth['success'] ? 'Connected' : 'Error'; ?></span>
                </div>

                <div class="health-status">
                    <div class="health-indicator health-healthy"></div>
                    <span>Database: Connected</span>
                </div>

                <div class="health-status">
                    <div class="health-indicator <?php
                                                    $connectedDevices = $deviceStats['connected_devices'] ?? 0;
                                                    $totalDevices = $deviceStats['total_devices'] ?? 0;
                                                    if ($totalDevices == 0) echo 'health-warning';
                                                    elseif ($connectedDevices > 0) echo 'health-healthy';
                                                    else echo 'health-error';
                                                    ?>"></div>
                    <span>WhatsApp Sessions:
                        <?php echo $connectedDevices; ?>/<?php echo $totalDevices; ?> connected
                    </span>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Devices -->
            <div class="card">
                <div class="card-header">
                    Recent Devices
                    <a href="devices.php" class="btn" style="float: right; font-size: 0.8rem; padding: 0.25rem 0.5rem;">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentDevices)): ?>
                        <p style="color: #666; text-align: center; padding: 2rem;">
                            No devices found. <a href="devices.php">Create your first device</a>
                        </p>
                    <?php else: ?>
                        <ul class="device-list">
                            <?php foreach ($recentDevices as $device): ?>
                                <li class="device-item">
                                    <div>
                                        <div class="device-name"><?php echo htmlspecialchars($device['device_name']); ?></div>
                                        <div class="device-session"><?php echo htmlspecialchars($device['session_id']); ?></div>
                                        <?php if ($device['phone_number']): ?>
                                            <div class="device-session">📱 <?php echo htmlspecialchars($device['phone_number']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?php echo $device['status']; ?>">
                                            <?php echo ucfirst($device['status']); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="card">
                <div class="card-header">
                    Recent Messages
                    <a href="messages.php" class="btn" style="float: right; font-size: 0.8rem; padding: 0.25rem 0.5rem;">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentMessages)): ?>
                        <p style="color: #666; text-align: center; padding: 2rem;">
                            No messages found yet.
                        </p>
                    <?php else: ?>
                        <ul class="message-list">
                            <?php foreach ($recentMessages as $message): ?>
                                <li class="message-item">
                                    <div class="message-info">
                                        <div class="message-from">
                                            <?php if ($message['direction'] === 'incoming'): ?>
                                                📥 From: <?php echo htmlspecialchars($message['from_number']); ?>
                                            <?php else: ?>
                                                📤 To: <?php echo htmlspecialchars($message['to_number']); ?>
                                            <?php endif; ?>
                                            <span style="font-weight: normal; color: #666;">
                                                (<?php echo htmlspecialchars($message['device_name']); ?>)
                                            </span>
                                        </div>
                                        <div class="message-preview">
                                            <?php
                                            $content = $message['message_content'];
                                            if ($message['message_type'] !== 'text') {
                                                $content = ucfirst($message['message_type']) . ' message';
                                            }
                                            echo htmlspecialchars(truncateText($content, 60));
                                            ?>
                                        </div>
                                    </div>
                                    <div class="message-time">
                                        <?php echo timeAgo($message['received_at']); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">Quick Actions</div>
            <div class="card-body">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="devices.php?action=create" class="btn btn-success">+ Create New Device</a>
                    <a href="messages.php?action=send" class="btn">📱 Send Message</a>
                    <a href="webhooks.php?action=test" class="btn btn-warning">🔗 Test Webhook</a>
                    <a href="../api/status.php?action=health" class="btn" target="_blank">❤️ API Health Check</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto refresh dashboard every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);

        // Simple notification for connection status
        <?php if (isset($nodeHealth) && !$nodeHealth['success']): ?>
            console.warn('Node.js API connection error');
        <?php endif; ?>
    </script>
</body>

</html>