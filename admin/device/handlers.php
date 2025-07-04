<?php

/**
 * WhatsApp Bridge - Device Management Form Handlers
 * File: admin/device/handlers.php
 * Complete rewrite - no duplicates
 */

// Handle CSRF token refresh endpoint
if (isset($_GET['action']) && $_GET['action'] === 'refresh_csrf' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Clear existing token to force new generation
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);

    $newToken = generateCsrfToken();

    echo json_encode([
        'success' => true,
        'csrf_token' => $newToken
    ]);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF token first
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please refresh the page and try again.';

        // Log CSRF failure
        logError("CSRF Token Validation Failed", [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $currentUser['id'] ?? 'unknown',
            'action' => $formAction
        ]);

        // Regenerate token for security
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    } else {
        // Process form actions
        switch ($formAction) {
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

            default:
                $error = 'Invalid form action.';
        }
    }
}

/**
 * Handle device creation
 */
function handleCreateDevice()
{
    global $deviceManager, $currentUser, $success, $error;

    $deviceName = trim($_POST['device_name'] ?? '');
    $webhookUrl = trim($_POST['webhook_url'] ?? '');
    $note = trim($_POST['note'] ?? '');

    // Validation
    if (empty($deviceName)) {
        $error = 'Device name is required.';
        return;
    }

    if (strlen($deviceName) < 3) {
        $error = 'Device name must be at least 3 characters long.';
        return;
    }

    if (strlen($deviceName) > 100) {
        $error = 'Device name must not exceed 100 characters.';
        return;
    }

    // Validate webhook URL if provided
    if (!empty($webhookUrl)) {
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid webhook URL.';
            return;
        }

        $parsedUrl = parse_url($webhookUrl);
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            $error = 'Webhook URL must use HTTP or HTTPS protocol.';
            return;
        }
    }

    // Check for duplicate device names
    $existingDevice = $deviceManager->getDeviceByName($deviceName, $currentUser['id']);
    if ($existingDevice) {
        $error = 'Device name already exists. Please choose a different name.';
        return;
    }

    try {
        $result = $deviceManager->createDevice([
            'device_name' => $deviceName,
            'webhook_url' => $webhookUrl ?: null,
            'note' => $note ?: null,
            'created_by' => $currentUser['id']
        ]);

        if ($result['success']) {
            $success = 'Device created successfully! API Key: ' . $result['data']['api_key'];

            logInfo("Device Created", [
                'device_id' => $result['data']['device_id'],
                'device_name' => $deviceName,
                'user_id' => $currentUser['id'],
                'username' => $currentUser['username']
            ]);
        } else {
            $error = 'Failed to create device: ' . $result['error'];
        }
    } catch (Exception $e) {
        logError("Device Creation Error", [
            'error' => $e->getMessage(),
            'user_id' => $currentUser['id'],
            'device_name' => $deviceName
        ]);
        $error = 'An error occurred while creating the device. Please try again.';
    }
}

/**
 * Handle device update
 */
function handleUpdateDevice()
{
    global $deviceManager, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);
    $webhookUrl = trim($_POST['webhook_url'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($deviceId <= 0) {
        $error = 'Invalid device ID.';
        return;
    }

    // Check device ownership
    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    // Validate webhook URL if provided
    if (!empty($webhookUrl) && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid webhook URL.';
        return;
    }

    try {
        $result = $deviceManager->updateDevice($deviceId, [
            'webhook_url' => $webhookUrl ?: null,
            'note' => $note ?: null
        ]);

        if ($result['success']) {
            $success = 'Device updated successfully.';

            logInfo("Device Updated", [
                'device_id' => $deviceId,
                'user_id' => $currentUser['id']
            ]);
        } else {
            $error = 'Failed to update device: ' . $result['error'];
        }
    } catch (Exception $e) {
        logError("Device Update Error", [
            'device_id' => $deviceId,
            'error' => $e->getMessage(),
            'user_id' => $currentUser['id']
        ]);
        $error = 'An error occurred while updating the device. Please try again.';
    }
}

/**
 * Handle device connection
 */
function handleConnectDevice()
{
    global $deviceManager, $apiClient, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);

    if ($deviceId <= 0) {
        $error = 'Invalid device ID.';
        return;
    }

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    try {
        $result = $apiClient->connectSession($device['session_id']);

        if ($result['success']) {
            $deviceManager->updateDeviceStatus($device['id'], 'connecting');
            $success = 'Device connection initiated successfully.';

            logInfo("Device Connect Initiated", [
                'device_id' => $device['id'],
                'session_id' => $device['session_id'],
                'user_id' => $currentUser['id']
            ]);
        } else {
            $error = 'Failed to connect device: ' . ($result['error'] ?? 'Unknown error from Node.js API');
        }
    } catch (Exception $e) {
        logError("Device Connection Exception", [
            'device_id' => $device['id'],
            'error' => $e->getMessage()
        ]);
        $error = 'Connection error: Make sure Node.js WhatsApp API is running';
    }
}

/**
 * Handle device disconnection
 */
function handleDisconnectDevice()
{
    global $deviceManager, $apiClient, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);

    if ($deviceId <= 0) {
        $error = 'Invalid device ID.';
        return;
    }

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    try {
        $result = $apiClient->disconnectSession($device['session_id']);

        // Always update status in database
        $deviceManager->updateDeviceStatus($device['id'], 'disconnected');

        if ($result['success']) {
            $success = 'Device disconnected successfully.';

            logInfo("Device Disconnected", [
                'device_id' => $device['id'],
                'session_id' => $device['session_id'],
                'user_id' => $currentUser['id']
            ]);
        } else {
            $error = 'Device status updated, but API disconnect failed: ' . ($result['error'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        // Update status even if exception occurs
        $deviceManager->updateDeviceStatus($device['id'], 'disconnected');

        logError("Device Disconnect Exception", [
            'device_id' => $device['id'],
            'error' => $e->getMessage()
        ]);
        $error = 'Device marked as disconnected, but API call failed: ' . $e->getMessage();
    }
}

/**
 * Handle device deletion
 */
function handleDeleteDevice()
{
    global $deviceManager, $apiClient, $currentUser, $success, $error;

    $deviceId = (int)($_POST['device_id'] ?? 0);

    if ($deviceId <= 0) {
        $error = 'Invalid device ID.';
        return;
    }

    $device = $deviceManager->getDevice($deviceId);
    if (!$device || $device['created_by'] !== $currentUser['id']) {
        $error = 'Device not found or access denied.';
        return;
    }

    try {
        // Try to disconnect first if active
        if (isset($device['status']) && ($device['status'] === 'connected' || $device['status'] === 'connecting')) {
            try {
                $apiClient->disconnectSession($device['session_id']);
            } catch (Exception $e) {
                // Log but continue with deletion
                logError("Failed to disconnect session before delete", [
                    'device_id' => $deviceId,
                    'session_id' => $device['session_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Delete the device
        $result = $deviceManager->deleteDevice($deviceId);

        // Handle different return types from deleteDevice()
        if ($result === true || $result > 0) {
            // Success - deleteDevice returned true or affected rows > 0
            $success = 'Device deleted successfully.';

            logInfo("Device Deleted", [
                'device_id' => $deviceId,
                'device_name' => $device['device_name'] ?? 'unknown',
                'session_id' => $device['session_id'] ?? 'unknown',
                'user_id' => $currentUser['id']
            ]);
        } elseif (is_array($result)) {
            // If returns array with format ['success' => bool, 'error' => string]
            if (isset($result['success']) && $result['success']) {
                $success = 'Device deleted successfully.';

                logInfo("Device Deleted", [
                    'device_id' => $deviceId,
                    'device_name' => $device['device_name'] ?? 'unknown',
                    'session_id' => $device['session_id'] ?? 'unknown',
                    'user_id' => $currentUser['id']
                ]);
            } else {
                $error = 'Failed to delete device: ' . ($result['error'] ?? 'Unknown error');
            }
        } else {
            // Failed - deleteDevice returned false, 0, or null
            $error = 'Failed to delete device. Please try again.';

            logError("Device Deletion Failed", [
                'device_id' => $deviceId,
                'result_type' => gettype($result),
                'result_value' => var_export($result, true),
                'user_id' => $currentUser['id']
            ]);
        }
    } catch (Exception $e) {
        logError("Device Deletion Error", [
            'device_id' => $deviceId,
            'error' => $e->getMessage(),
            'user_id' => $currentUser['id']
        ]);
        $error = 'An error occurred while deleting the device. Please try again.';
    }
}

// End of file - no additional functions or duplicate code