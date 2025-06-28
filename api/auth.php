<?php

/**
 * WhatsApp Bridge API - Authentication & Session Management
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
$endpoint = '/api/auth';
$requestData = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $apiClient = new WhatsAppApiClient($apiKey);
    $deviceManager = new DeviceManager();

    switch ($method) {
        case 'GET':
            handleGetAuth($apiClient, $device);
            break;

        case 'POST':
            handleAuthAction($apiClient, $deviceManager, $requestData, $device);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError("API Error in auth.php", ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
} finally {
    // Log API call
    $executionTime = (microtime(true) - $startTime) * 1000;
    logApi($endpoint, $method, $requestData, [], http_response_code(), $executionTime);
}

// Get authentication status
function handleGetAuth($apiClient, $authDevice)
{
    try {
        // Get action parameter
        $action = $_GET['action'] ?? 'status';

        switch ($action) {
            case 'status':
                handleGetStatus($apiClient, $authDevice);
                break;

            case 'qr':
                handleGetQRCode($apiClient, $authDevice);
                break;

            case 'sessions':
                handleGetSessions($apiClient, $authDevice);
                break;

            default:
                jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        logError("Error handling GET auth", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to process request'], 500);
    }
}

// Get session status
function handleGetStatus($apiClient, $authDevice)
{
    $result = $apiClient->getSessionStatus($authDevice['session_id']);

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'session_id' => $authDevice['session_id'],
            'device_name' => $authDevice['device_name'],
            'status' => $result['status'],
            'node_status' => $result['node_status'],
            'phone_number' => $result['phone'],
            'last_activity' => $authDevice['last_activity']
        ]
    ]);
}

// Get QR Code
function handleGetQRCode($apiClient, $authDevice)
{
    $result = $apiClient->getQRCode($authDevice['session_id']);

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'session_id' => $authDevice['session_id'],
            'qr_code' => $result['qr_code'],
            'qr_url' => $result['qr_url']
        ]
    ]);
}

// Get all sessions
function handleGetSessions($apiClient, $authDevice)
{
    $result = $apiClient->getAllSessions();

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'data' => $result['sessions']
    ]);
}

// Handle authentication actions
function handleAuthAction($apiClient, $deviceManager, $data, $authDevice)
{
    try {
        $action = $data['action'] ?? 'connect';

        switch ($action) {
            case 'connect':
                handleConnect($apiClient, $authDevice);
                break;

            case 'disconnect':
                handleDisconnect($apiClient, $deviceManager, $authDevice);
                break;

            case 'restart':
                handleRestart($apiClient, $deviceManager, $authDevice);
                break;

            case 'logout':
                handleLogout($apiClient, $deviceManager, $authDevice);
                break;

            default:
                jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        logError("Error handling auth action", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to process action'], 500);
    }
}

// Connect session
function handleConnect($apiClient, $authDevice)
{
    $result = $apiClient->connectSession($authDevice['session_id']);

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Connection initiated',
        'data' => [
            'session_id' => $authDevice['session_id'],
            'status' => 'connecting'
        ]
    ]);
}

// Disconnect session
function handleDisconnect($apiClient, $deviceManager, $authDevice)
{
    $result = $apiClient->disconnectSession($authDevice['session_id']);

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    // Update device status in database
    $deviceManager->updateDeviceStatus($authDevice['id'], 'disconnected');

    jsonResponse([
        'success' => true,
        'message' => 'Session disconnected',
        'data' => [
            'session_id' => $authDevice['session_id'],
            'status' => 'disconnected'
        ]
    ]);
}

// Restart session
function handleRestart($apiClient, $deviceManager, $authDevice)
{
    // First disconnect
    $disconnectResult = $apiClient->disconnectSession($authDevice['session_id']);

    // Wait a moment
    sleep(2);

    // Then reconnect
    $connectResult = $apiClient->connectSession($authDevice['session_id']);

    if (!$connectResult['success']) {
        jsonResponse(['success' => false, 'error' => $connectResult['error']], 500);
    }

    // Update device status in database
    $deviceManager->updateDeviceStatus($authDevice['id'], 'connecting');

    jsonResponse([
        'success' => true,
        'message' => 'Session restarted',
        'data' => [
            'session_id' => $authDevice['session_id'],
            'status' => 'connecting'
        ]
    ]);
}

// Logout session (disconnect and clear data)
function handleLogout($apiClient, $deviceManager, $authDevice)
{
    // Create Node.js API client
    $nodeApi = new NodeApiClient($authDevice['api_key']);

    // Logout from Node.js API
    $response = $nodeApi->logoutSession($authDevice['session_id']);

    // Update device status regardless of Node.js response
    $deviceManager->updateDeviceStatus($authDevice['id'], 'disconnected', null, null);

    jsonResponse([
        'success' => true,
        'message' => 'Session logged out and data cleared',
        'data' => [
            'session_id' => $authDevice['session_id'],
            'status' => 'disconnected'
        ]
    ]);
}
