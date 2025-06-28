<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>

    <!-- Include CSS -->
    <link rel="stylesheet" href="assets/devices.css">
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1><?php echo APP_NAME; ?> - Device Management</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            <a href="../logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="../dashboard.php">Dashboard</a></li>
            <li><a href="index.php" class="active">Devices</a></li>
            <li><a href="../messages.php">Messages</a></li>
            <li><a href="../webhooks.php">Webhooks</a></li>
        </ul>
    </nav>

    <div class="container">
        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Device Statistics -->
        <div class="stats-grid">
            <div class="stat-card stat-total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Devices</div>
            </div>
            <div class="stat-card stat-connected">
                <div class="stat-number"><?php echo $stats['connected']; ?></div>
                <div class="stat-label">Connected</div>
            </div>
            <div class="stat-card stat-connecting">
                <div class="stat-number"><?php echo $stats['connecting']; ?></div>
                <div class="stat-label">Connecting</div>
            </div>
            <div class="stat-card stat-disconnected">
                <div class="stat-number"><?php echo $stats['disconnected']; ?></div>
                <div class="stat-label">Disconnected</div>
            </div>
        </div>

        <!-- Create Device Form -->
        <div class="card">
            <div class="card-header">
                <span>Create New Device</span>
                <button type="button" class="btn btn-secondary btn-small" onclick="syncAllDevices()">
                    🔄 Sync All Status
                </button>
            </div>
            <div class="card-body">
                <form method="POST" action="" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="form_action" value="create">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="device_name">Device Name *</label>
                            <input type="text" id="device_name" name="device_name" required
                                placeholder="My WhatsApp Device" maxlength="100">
                            <small class="form-help">Choose a unique name for this device</small>
                        </div>

                        <div class="form-group">
                            <label for="webhook_url">Webhook URL (optional)</label>
                            <input type="url" id="webhook_url" name="webhook_url"
                                placeholder="https://yourapp.com/webhook">
                            <small class="form-help">URL to receive incoming messages</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="note">Note (optional)</label>
                        <textarea id="note" name="note" rows="3" maxlength="500"
                            placeholder="Add a note about this device (e.g., Customer Service Bot, Marketing Bot)"></textarea>
                        <small class="form-help">Maximum 500 characters</small>
                    </div>

                    <button type="submit" class="btn btn-success">Create Device</button>
                </form>
            </div>
        </div>

        <!-- Devices List -->
        <div class="card">
            <div class="card-header">
                <span>Your Devices (<?php echo count($devices); ?>)</span>
                <div>
                    <button type="button" class="btn btn-primary btn-small" onclick="location.reload()">
                        🔄 Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($devices)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📱</div>
                        <h3>No devices found</h3>
                        <p>Create your first WhatsApp device above to get started.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="devices-table">
                            <thead>
                                <tr>
                                    <th>Device Info</th>
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
                                            <div>
                                                <strong><?php echo htmlspecialchars($device['device_name']); ?></strong>
                                                <?php if ($device['webhook_url']): ?>
                                                    <br><small style="color: #28a745;">🔗 Webhook configured</small>
                                                <?php endif; ?>
                                                <?php if ($device['note']): ?>
                                                    <br><small style="color: #666;" title="<?php echo htmlspecialchars($device['note']); ?>">
                                                        📝 <?php echo htmlspecialchars(truncateText($device['note'], 30)); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <code style="font-size: 0.8rem;"><?php echo htmlspecialchars($device['session_id']); ?></code>
                                        </td>
                                        <td>
                                            <?php if ($device['phone_number']): ?>
                                                <span style="color: #28a745;">📱 <?php echo htmlspecialchars($device['phone_number']); ?></span>
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
                                            <div class="api-key-display" onclick="copyToClipboard('<?php echo $device['api_key']; ?>')"
                                                title="Click to copy: <?php echo $device['api_key']; ?>" style="cursor: pointer;">
                                                <?php echo substr($device['api_key'], 0, 12) . '...'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small><?php echo $device['last_activity'] ? timeAgo($device['last_activity']) : 'Never'; ?></small>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <!-- Connection Actions -->
                                                <?php if ($device['status'] === 'disconnected' || $device['status'] === 'inactive'): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return handleFormSubmit(this)">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="form_action" value="connect">
                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                        <button type="submit" class="btn btn-small btn-success" title="Connect Device">
                                                            🔌 Connect
                                                        </button>
                                                    </form>
                                                <?php elseif ($device['status'] === 'connected'): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return handleFormSubmit(this)">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                        <input type="hidden" name="form_action" value="disconnect">
                                                        <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                        <button type="submit" class="btn btn-small btn-warning" title="Disconnect Device">
                                                            🔌 Disconnect
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- QR Code button for non-connected devices -->
                                                <?php if ($device['status'] !== 'connected'): ?>
                                                    <button class="btn btn-small btn-primary"
                                                        onclick="showQR('<?php echo $device['session_id']; ?>', '<?php echo $device['api_key']; ?>')"
                                                        title="Show QR Code">
                                                        📱 QR Code
                                                    </button>
                                                <?php endif; ?>

                                                <!-- API Examples -->
                                                <button class="btn btn-small btn-secondary"
                                                    onclick="showApiExamples('<?php echo $device['api_key']; ?>', '<?php echo htmlspecialchars($device['device_name']); ?>')"
                                                    title="API Examples">
                                                    📚 API
                                                </button>

                                                <!-- Edit Device -->
                                                <button class="btn btn-small btn-secondary"
                                                    onclick="editDevice(<?php echo $device['id']; ?>, '<?php echo addslashes($device['webhook_url'] ?? ''); ?>', '<?php echo addslashes($device['note'] ?? ''); ?>')"
                                                    title="Edit Device">
                                                    ✏️ Edit
                                                </button>

                                                <!-- Delete Device -->
                                                <form method="POST" style="display: inline;"
                                                    onsubmit="return confirm('Are you sure you want to delete this device? This action cannot be undone.')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="form_action" value="delete">
                                                    <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                                    <button type="submit" class="btn btn-small btn-danger" title="Delete Device">
                                                        🗑️ Delete
                                                    </button>
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

    <!-- Include Modals -->
    <?php include __DIR__ . '/../includes/modals.php'; ?>

    <!-- Include JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js"></script>
    <script src="assets/devices.js"></script>
</body>

</html>