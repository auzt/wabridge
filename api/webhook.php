<?php

/**
 * WhatsApp Bridge API - Webhook Management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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
$endpoint = '/api/webhook';
$requestData = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $webhookManager = new WebhookManager();
    $deviceManager = new DeviceManager();

    switch ($method) {
        case 'GET':
            handleGetWebhookStats($webhookManager, $device);
            break;

        case 'POST':
            handleWebhookAction($webhookManager, $deviceManager, $requestData, $device);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError("API Error in webhook.php", ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
} finally {
    // Log API call
    $executionTime = (microtime(true) - $startTime) * 1000;
    logApi($endpoint, $method, $requestData, [], http_response_code(), $executionTime);
}

// Get webhook statistics
function handleGetWebhookStats($webhookManager, $authDevice)
{
    try {
        $deviceId = $authDevice['id'];
        $stats = $webhookManager->getWebhookStats($deviceId);

        if (!$stats) {
            $stats = [
                'total_calls' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'avg_execution_time' => 0
            ];
        }

        // Get recent webhook logs
        $db = Database::getInstance();
        $recentLogs = $db->fetchAll(
            "SELECT event_type, response_code, execution_time, status, created_at, error_message 
             FROM webhook_logs 
             WHERE device_id = ? 
             ORDER BY created_at DESC 
             LIMIT 10",
            [$deviceId]
        );

        jsonResponse([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_logs' => $recentLogs,
                'webhook_url' => $authDevice['webhook_url']
            ]
        ]);
    } catch (Exception $e) {
        logError("Error getting webhook stats", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to get webhook stats'], 500);
    }
}

// Handle webhook actions
function handleWebhookAction($webhookManager, $deviceManager, $data, $authDevice)
{
    try {
        $action = $data['action'] ?? 'test';

        switch ($action) {
            case 'test':
                handleTestWebhook($webhookManager, $data, $authDevice);
                break;

            case 'update_url':
                handleUpdateWebhookUrl($deviceManager, $data, $authDevice);
                break;

            case 'send_custom':
                handleSendCustomWebhook($webhookManager, $data, $authDevice);
                break;

            default:
                jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        logError("Error handling webhook action", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to process webhook action'], 500);
    }
}

// Test webhook
function handleTestWebhook($webhookManager, $data, $authDevice)
{
    $webhookUrl = cleanInput($data['webhook_url'] ?? $authDevice['webhook_url']);

    if (empty($webhookUrl)) {
        jsonResponse(['success' => false, 'error' => 'webhook_url is required'], 400);
    }

    if (!isValidUrl($webhookUrl)) {
        jsonResponse(['success' => false, 'error' => 'Invalid webhook URL'], 400);
    }

    // Create test webhook data
    $testData = [
        'event' => 'webhook_test',
        'device_id' => $authDevice['id'],
        'device_name' => $authDevice['device_name'],
        'session_id' => $authDevice['session_id'],
        'message' => 'This is a test webhook from WhatsApp Bridge',
        'timestamp' => getCurrentTimestamp(),
        'test' => true
    ];

    // Send test webhook
    $success = $webhookManager->sendWebhook($authDevice['id'], $webhookUrl, $testData, 'webhook_test');

    if ($success) {
        jsonResponse([
            'success' => true,
            'message' => 'Test webhook sent successfully',
            'webhook_url' => $webhookUrl
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'error' => 'Failed to send test webhook'
        ], 500);
    }
}

// Update webhook URL
function handleUpdateWebhookUrl($deviceManager, $data, $authDevice)
{
    if (!isset($data['webhook_url'])) {
        jsonResponse(['success' => false, 'error' => 'webhook_url is required'], 400);
    }

    $webhookUrl = cleanInput($data['webhook_url']);

    // Validate URL if not empty
    if (!empty($webhookUrl) && !isValidUrl($webhookUrl)) {
        jsonResponse(['success' => false, 'error' => 'Invalid webhook URL'], 400);
    }

    // Update webhook URL in database
    $success = $deviceManager->updateWebhookUrl($authDevice['id'], $webhookUrl);

    if ($success) {
        jsonResponse([
            'success' => true,
            'message' => 'Webhook URL updated successfully',
            'webhook_url' => $webhookUrl
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'error' => 'Failed to update webhook URL'
        ], 500);
    }
}

// Send custom webhook
function handleSendCustomWebhook($webhookManager, $data, $authDevice)
{
    if (empty($data['event_type']) || empty($data['payload'])) {
        jsonResponse(['success' => false, 'error' => 'event_type and payload are required'], 400);
    }

    $eventType = cleanInput($data['event_type']);
    $payload = $data['payload'];
    $webhookUrl = cleanInput($data['webhook_url'] ?? $authDevice['webhook_url']);

    if (empty($webhookUrl)) {
        jsonResponse(['success' => false, 'error' => 'No webhook URL configured'], 400);
    }

    // Add device info to payload
    $webhookData = array_merge($payload, [
        'device_id' => $authDevice['id'],
        'device_name' => $authDevice['device_name'],
        'session_id' => $authDevice['session_id'],
        'timestamp' => getCurrentTimestamp()
    ]);

    // Send custom webhook
    $success = $webhookManager->sendWebhook($authDevice['id'], $webhookUrl, $webhookData, $eventType);

    if ($success) {
        jsonResponse([
            'success' => true,
            'message' => 'Custom webhook sent successfully',
            'event_type' => $eventType
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'error' => 'Failed to send custom webhook'
        ], 500);
    }
}
