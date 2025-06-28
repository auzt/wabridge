<?php

/**
 * WhatsApp Bridge API - Device Management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_client.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp)) {
    jsonResponse(['success' => false, 'error' => 'Rate limit exceeded'], 429);
}

// API Key authentication
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$apiKey = str_replace('Bearer ', '', $apiKey);

if (empty($apiKey)) {
    jsonResponse(['success' => false, 'error' => 'API key required'], 401);
}

$device = validateApiKey($apiKey);
if (!$device) {
    jsonResponse(['success' => false, 'error' => 'Invalid API key'], 401);
}

// Log API request
$startTime = microtime(true);
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = '/api/devices';
$requestData = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $deviceManager = new DeviceManager();
    $apiClient = new WhatsAppApiClient();

    switch ($method) {
        case 'GET':
            handleGetDevices($deviceManager, $device);
            break;

        case 'POST':
            handleCreateDevice($deviceManager, $apiClient, $requestData, $device);
            break;

        case 'PUT':
            handleUpdateDevice($deviceManager, $requestData, $device);
            break;

        case 'DELETE':
            handleDeleteDevice($deviceManager, $apiClient, $requestData, $device);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError("API Error in devices.php", ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
} finally {
    // Log API call
    $executionTime = (microtime(true) - $startTime) * 1000;
    logApi($endpoint, $method, $requestData, [], http_response_code(), $executionTime);
}

// Get devices
function handleGetDevices($deviceManager, $authDevice)
{
    try {
        // Get device info if device_id parameter provided
        if (isset($_GET['device_id'])) {
            $deviceId = cleanInput($_GET['device_id']);
            $device = $deviceManager->getDevice($deviceId);

            if (!$device) {
                jsonResponse(['success' => false, 'error' => 'Device not found'], 404);
            }

            // Check if user owns this device
            if ($device['created_by'] !== $authDevice['created_by']) {
                jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
            }

            jsonResponse([
                'success' => true,
                'data' => $device
            ]);
        }

        // Get all devices for user
        $devices = $deviceManager->getAllDevices($authDevice['created_by']);

        jsonResponse([
            'success' => true,
            'data' => $devices,
            'total' => count($devices)
        ]);
    } catch (Exception $e) {
        logError("Error getting devices", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to get devices'], 500);
    }
}

// Create new device
function handleCreateDevice($deviceManager, $apiClient, $data, $authDevice)
{
    try {
        // Validate required fields
        if (empty($data['device_name'])) {
            jsonResponse(['success' => false, 'error' => 'device_name is required'], 400);
        }

        $deviceName = cleanInput($data['device_name']);
        $sessionId = cleanInput($data['session_id'] ?? generateSessionId());
        $webhookUrl = cleanInput($data['webhook_url'] ?? '');
        $config = $data['config'] ?? [];

        // Validate webhook URL if provided
        if (!empty($webhookUrl) && !isValidUrl($webhookUrl)) {
            jsonResponse(['success' => false, 'error' => 'Invalid webhook URL'], 400);
        }

        // Check if session ID already exists
        $existingDevice = $deviceManager->getDeviceBySessionId($sessionId);
        if ($existingDevice) {
            jsonResponse(['success' => false, 'error' => 'Session ID already exists'], 400);
        }

        // Create device in database
        $newDevice = $deviceManager->createDevice($deviceName, $sessionId, $webhookUrl, $authDevice['created_by']);
        if (!$newDevice) {
            jsonResponse(['success' => false, 'error' => 'Failed to create device'], 500);
        }

        // Create session in Node.js API
        $sessionResult = $apiClient->createSession($deviceName, $sessionId, $config);
        if (!$sessionResult['success']) {
            // Cleanup device if session creation failed
            $deviceManager->deleteDevice($newDevice['id']);
            jsonResponse([
                'success' => false,
                'error' => 'Failed to create session: ' . $sessionResult['error']
            ], 500);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Device created successfully',
            'data' => $newDevice
        ], 201);
    } catch (Exception $e) {
        logError("Error creating device", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to create device'], 500);
    }
}

// Update device
function handleUpdateDevice($deviceManager, $data, $authDevice)
{
    try {
        if (empty($data['device_id'])) {
            jsonResponse(['success' => false, 'error' => 'device_id is required'], 400);
        }

        $deviceId = cleanInput($data['device_id']);
        $device = $deviceManager->getDevice($deviceId);

        if (!$device) {
            jsonResponse(['success' => false, 'error' => 'Device not found'], 404);
        }

        // Check if user owns this device
        if ($device['created_by'] !== $authDevice['created_by']) {
            jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        // Update webhook URL if provided
        if (isset($data['webhook_url'])) {
            $webhookUrl = cleanInput($data['webhook_url']);

            if (!empty($webhookUrl) && !isValidUrl($webhookUrl)) {
                jsonResponse(['success' => false, 'error' => 'Invalid webhook URL'], 400);
            }

            $result = $deviceManager->updateWebhookUrl($deviceId, $webhookUrl);
            if (!$result) {
                jsonResponse(['success' => false, 'error' => 'Failed to update webhook URL'], 500);
            }
        }

        // Update device name if provided
        if (isset($data['device_name'])) {
            $deviceName = cleanInput($data['device_name']);
            if (empty($deviceName)) {
                jsonResponse(['success' => false, 'error' => 'Device name cannot be empty'], 400);
            }

            $db = Database::getInstance();
            $result = $db->execute(
                "UPDATE devices SET device_name = ? WHERE id = ?",
                [$deviceName, $deviceId]
            );

            if (!$result) {
                jsonResponse(['success' => false, 'error' => 'Failed to update device name'], 500);
            }
        }

        // Get updated device
        $updatedDevice = $deviceManager->getDevice($deviceId);

        jsonResponse([
            'success' => true,
            'message' => 'Device updated successfully',
            'data' => $updatedDevice
        ]);
    } catch (Exception $e) {
        logError("Error updating device", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to update device'], 500);
    }
}

// Delete device
function handleDeleteDevice($deviceManager, $apiClient, $data, $authDevice)
{
    try {
        if (empty($data['device_id'])) {
            jsonResponse(['success' => false, 'error' => 'device_id is required'], 400);
        }

        $deviceId = cleanInput($data['device_id']);
        $device = $deviceManager->getDevice($deviceId);

        if (!$device) {
            jsonResponse(['success' => false, 'error' => 'Device not found'], 404);
        }

        // Check if user owns this device
        if ($device['created_by'] !== $authDevice['created_by']) {
            jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        // Disconnect session in Node.js API first
        try {
            $apiClient->disconnectSession($device['session_id']);
        } catch (Exception $e) {
            logError("Failed to disconnect session before delete", ['error' => $e->getMessage()]);
            // Continue with deletion even if disconnect fails
        }

        // Delete device from database
        $result = $deviceManager->deleteDevice($deviceId);
        if (!$result) {
            jsonResponse(['success' => false, 'error' => 'Failed to delete device'], 500);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Device deleted successfully'
        ]);
    } catch (Exception $e) {
        logError("Error deleting device", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to delete device'], 500);
    }
}
