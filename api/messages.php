<?php

/**
 * WhatsApp Bridge API - Message Management
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
$endpoint = '/api/messages';
$requestData = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    $deviceManager = new DeviceManager();
    $messageManager = new MessageManager();
    $apiClient = new WhatsAppApiClient($apiKey);

    switch ($method) {
        case 'GET':
            handleGetMessages($messageManager, $device);
            break;

        case 'POST':
            handleSendMessage($apiClient, $requestData, $device);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError("API Error in messages.php", ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
} finally {
    // Log API call
    $executionTime = (microtime(true) - $startTime) * 1000;
    logApi($endpoint, $method, $requestData, [], http_response_code(), $executionTime);
}

// Get messages
function handleGetMessages($messageManager, $authDevice)
{
    try {
        $deviceId = $authDevice['id'];
        $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 messages
        $offset = max((int)($_GET['offset'] ?? 0), 0);
        $direction = cleanInput($_GET['direction'] ?? '');

        // Validate direction parameter
        if (!empty($direction) && !in_array($direction, ['incoming', 'outgoing'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid direction parameter'], 400);
        }

        $messages = $messageManager->getMessages($deviceId, $limit, $offset, $direction ?: null);

        jsonResponse([
            'success' => true,
            'data' => $messages,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => count($messages)
            ]
        ]);
    } catch (Exception $e) {
        logError("Error getting messages", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to get messages'], 500);
    }
}

// Send message
function handleSendMessage($apiClient, $data, $authDevice)
{
    try {
        // Get action type
        $action = $data['action'] ?? 'send_text';

        switch ($action) {
            case 'send_text':
                handleSendTextMessage($apiClient, $data, $authDevice);
                break;

            case 'send_media':
                handleSendMediaMessage($apiClient, $data, $authDevice);
                break;

            case 'send_location':
                handleSendLocationMessage($apiClient, $data, $authDevice);
                break;

            case 'send_contact':
                handleSendContactMessage($apiClient, $data, $authDevice);
                break;

            default:
                jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
    } catch (Exception $e) {
        logError("Error sending message", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to send message'], 500);
    }
}

// Send text message
function handleSendTextMessage($apiClient, $data, $authDevice)
{
    // Validate required fields
    if (empty($data['to']) || empty($data['text'])) {
        jsonResponse(['success' => false, 'error' => 'to and text are required'], 400);
    }

    $to = $data['to'];
    $text = cleanInput($data['text']);
    $options = $data['options'] ?? [];

    // Validate phone numbers
    $recipients = is_array($to) ? $to : [$to];
    foreach ($recipients as $phone) {
        if (!validatePhoneNumber($phone)) {
            jsonResponse(['success' => false, 'error' => 'Invalid phone number: ' . $phone], 400);
        }
    }

    // Send message via Node.js API
    $result = $apiClient->sendTextMessage($authDevice['session_id'], $recipients, $text, $options);

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Text message sent successfully',
        'data' => $result['data'],
        'recipients' => $result['recipients']
    ]);
}

// Send media message
function handleSendMediaMessage($apiClient, $data, $authDevice)
{
    // Validate required fields
    if (empty($data['to']) || empty($data['media_url']) || empty($data['type'])) {
        jsonResponse(['success' => false, 'error' => 'to, media_url, and type are required'], 400);
    }

    $to = $data['to'];
    $mediaUrl = cleanInput($data['media_url']);
    $type = cleanInput($data['type']);
    $caption = cleanInput($data['caption'] ?? '');
    $options = $data['options'] ?? [];

    // Validate media type
    $allowedTypes = ['image', 'video', 'audio', 'document'];
    if (!in_array($type, $allowedTypes)) {
        jsonResponse(['success' => false, 'error' => 'Invalid media type. Allowed: ' . implode(', ', $allowedTypes)], 400);
    }

    // Validate phone numbers
    $recipients = is_array($to) ? $to : [$to];
    foreach ($recipients as $phone) {
        if (!validatePhoneNumber($phone)) {
            jsonResponse(['success' => false, 'error' => 'Invalid phone number: ' . $phone], 400);
        }
    }

    // Download media file if URL provided
    $mediaFile = null;
    if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
        // Download from URL
        $mediaContent = file_get_contents($mediaUrl);
        if ($mediaContent === false) {
            jsonResponse(['success' => false, 'error' => 'Failed to download media from URL'], 400);
        }

        // Save temporary file
        $tempFile = UPLOAD_PATH . '/temp_' . time() . '_' . mt_rand(1000, 9999);
        file_put_contents($tempFile, $mediaContent);
        $mediaFile = $tempFile;
    } else {
        // Assume it's a local file path
        $mediaFile = UPLOAD_PATH . '/' . basename($mediaUrl);
        if (!file_exists($mediaFile)) {
            jsonResponse(['success' => false, 'error' => 'Media file not found'], 400);
        }
    }

    // Send media message
    $result = $apiClient->sendMediaMessage($authDevice['session_id'], $recipients, $mediaFile, $type, $caption, $options);

    // Clean up temporary file
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Media message sent successfully',
        'data' => $result['data'],
        'recipients' => $result['recipients']
    ]);
}

// Send location message
function handleSendLocationMessage($apiClient, $data, $authDevice)
{
    // Validate required fields
    if (empty($data['to']) || !isset($data['latitude']) || !isset($data['longitude'])) {
        jsonResponse(['success' => false, 'error' => 'to, latitude, and longitude are required'], 400);
    }

    $to = $data['to'];
    $latitude = (float)$data['latitude'];
    $longitude = (float)$data['longitude'];
    $name = cleanInput($data['name'] ?? '');
    $address = cleanInput($data['address'] ?? '');

    // Validate coordinates
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        jsonResponse(['success' => false, 'error' => 'Invalid coordinates'], 400);
    }

    // Validate phone numbers
    $recipients = is_array($to) ? $to : [$to];
    foreach ($recipients as $phone) {
        if (!validatePhoneNumber($phone)) {
            jsonResponse(['success' => false, 'error' => 'Invalid phone number: ' . $phone], 400);
        }
    }

    // Send location message
    $result = $apiClient->sendLocation($authDevice['session_id'], $recipients, $latitude, $longitude, $name, $address);

    if (!$result['success']) {
        jsonResponse(['success' => false, 'error' => $result['error']], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Location message sent successfully',
        'data' => $result['data'],
        'recipients' => $result['recipients']
    ]);
}

// Send contact message
function handleSendContactMessage($apiClient, $data, $authDevice)
{
    // Validate required fields
    if (empty($data['to']) || empty($data['contacts'])) {
        jsonResponse(['success' => false, 'error' => 'to and contacts are required'], 400);
    }

    $to = $data['to'];
    $contacts = $data['contacts'];

    // Validate contacts format
    if (!is_array($contacts)) {
        jsonResponse(['success' => false, 'error' => 'contacts must be an array'], 400);
    }

    foreach ($contacts as $contact) {
        if (!isset($contact['name']) || !isset($contact['phone'])) {
            jsonResponse(['success' => false, 'error' => 'Each contact must have name and phone'], 400);
        }
    }

    // Validate phone numbers
    $recipients = is_array($to) ? $to : [$to];
    foreach ($recipients as $phone) {
        if (!validatePhoneNumber($phone)) {
            jsonResponse(['success' => false, 'error' => 'Invalid phone number: ' . $phone], 400);
        }
    }

    // Create Node.js API client and send contact
    $nodeApi = new NodeApiClient($authDevice['api_key']);
    $response = $nodeApi->sendContact($authDevice['session_id'], $recipients, $contacts);

    if ($response['status_code'] !== 200 || !$response['data']['success']) {
        jsonResponse(['success' => false, 'error' => $response['data']['error'] ?? 'Failed to send contact'], 500);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Contact message sent successfully',
        'data' => $response['data']['data'],
        'recipients' => $recipients
    ]);
}
