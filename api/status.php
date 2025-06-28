<?php
/**
 * WhatsApp Bridge API - Status & Health Check
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_client.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// For health check, no auth required
if ($_GET['action'] === 'health') {
    handleHealthCheck();
    exit;
}

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp)) {
    jsonResponse(['success' => false, 'error' => 'Rate limit exceeded'], 429);
}

// API Key authentication for other endpoints
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
$endpoint = '/api/status';
$requestData = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    switch ($method) {
        case 'GET':
            handleGetStatus($device);
            break;
        
        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    logError("API Error in status.php", ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
} finally {
    // Log API call
    $executionTime = (microtime(true) - $startTime) * 1000;
    logApi($endpoint, $method, $requestData, [], http_response_code(), $executionTime);
}

// Health check (no auth required)
function handleHealthCheck() {
    try {
        $db = Database::getInstance();
        
        // Test database connection
        $dbTest = $db->fetch("SELECT 1 as test");
        $dbHealthy = $dbTest && $dbTest['test'] == 1;
        
        // Test Node.js API connection
        $apiClient = new WhatsAppApiClient();
        $nodeHealth = $apiClient->healthCheck();
        
        // Get system info
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'server_time' => getCurrentTimestamp(),
            'memory_usage' => formatBytes(memory_get_usage(true)),
            'memory_peak' => formatBytes(memory_get_peak_usage(true)),
            'disk_free' => formatBytes(disk_free_space('.')),
            'uptime' => function_exists('sys_getloadavg') ? sys_getloadavg() : 'N/A'
        ];
        
// Health check (no auth required)
function handleHealthCheck() {
    try {
        $db = Database::getInstance();
        
        // Test database connection
        $dbTest = $db->fetch("SELECT 1 as test");
        $dbHealthy = $dbTest && $dbTest['test'] == 1;
        
        // Test Node.js API connection
        $apiClient = new WhatsAppApiClient();
        $nodeHealth = $apiClient->healthCheck();
        
        // Get system info
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'server_time' => getCurrentTimestamp(),
            'memory_usage' => formatBytes(memory_get_usage(true)),
            'memory_peak' => formatBytes(memory_get_peak_usage(true)),
            'disk_free' => formatBytes(disk_free_space('.')),
            'uptime' => function_exists('sys_getloadavg') ? sys_getloadavg() : 'N/A'
        ];
        
        // Get counts
        $deviceCount = $db->fetch("SELECT COUNT(*) as count FROM devices WHERE status != 'inactive'")['count'] ?? 0;
        $activeDevices = $db->fetch("SELECT COUNT(*) as count FROM devices WHERE status IN ('connected', 'connecting')")['count'] ?? 0;
        $messageCount = $db->fetch("SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
        
        $status = $dbHealthy && $nodeHealth['success'] ? 'healthy' : 'degraded';
        $httpCode = $status === 'healthy' ? 200 : 503;
        
        jsonResponse([
            'success' => true,
            'status' => $status,
            'timestamp' => getCurrentTimestamp(),
            'version' => APP_VERSION,
            'database' => [
                'status' => $dbHealthy ? 'connected' : 'error',
                'total_devices' => $deviceCount,
                'active_devices' => $activeDevices,
                'messages_today' => $messageCount
            ],
            'node_api' => [
                'status' => $nodeHealth['success'] ? 'connected' : 'error',
                'data' => $nodeHealth['data'] ?? null
            ],
            'system' => $systemInfo
        ], $httpCode);
        
    } catch (Exception $e) {
        logError("Health check error", ['error' => $e->getMessage()]);
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'error' => $e->getMessage(),
            'timestamp' => getCurrentTimestamp()
        ], 503);
    }
}

// Get status information
function handleGetStatus($authDevice) {
    try {
        $action = $_GET['action'] ?? 'device';
        
        switch ($action) {
            case 'device':
                handleDeviceStatus($authDevice);
                break;
            
            case 'messages':
                handleMessageStats($authDevice);
                break;
            
            case 'webhooks':
                handleWebhookStats($authDevice);
                break;
            
            case 'api':
                handleApiStats($authDevice);
                break;
            
            default:
                jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        }
        
    } catch (Exception $e) {
        logError("Error getting status", ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => 'Failed to get status'], 500);
    }
}

// Get device status
function handleDeviceStatus($authDevice) {
    $db = Database::getInstance();
    $apiClient = new WhatsAppApiClient($authDevice['api_key']);
    
    // Get session status from Node.js
    $sessionStatus = $apiClient->getSessionStatus($authDevice['session_id']);
    
    // Get device stats
    $messageStats = $db->fetch(
        "SELECT 
            COUNT(*) as total_messages,
            SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming_count,
            SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing_count,
            MAX(received_at) as last_message_at
         FROM messages 
         WHERE device_id = ?",
        [$authDevice['id']]
    );
    
    jsonResponse([
        'success' => true,
        'data' => [
            'device' => [
                'id' => $authDevice['id'],
                'name' => $authDevice['device_name'],
                'session_id' => $authDevice['session_id'],
                'status' => $authDevice['status'],
                'phone_number' => $authDevice['phone_number'],
                'webhook_url' => $authDevice['webhook_url'],
                'last_activity' => $authDevice['last_activity'],
                'created_at' => $authDevice['created_at']
            ],
            'session' => $sessionStatus['success'] ? [
                'status' => $sessionStatus['status'],
                'node_status' => $sessionStatus['node_status'],
                'phone' => $sessionStatus['phone']
            ] : [
                'status' => 'error',
                'error' => $sessionStatus['error'] ?? 'Unknown error'
            ],
            'statistics' => $messageStats ?: [
                'total_messages' => 0,
                'incoming_count' => 0,
                'outgoing_count' => 0,
                'last_message_at' => null
            ]
        ]
    ]);
}

// Get message statistics
function handleMessageStats($authDevice) {
    $db = Database::getInstance();
    $days = min((int)($_GET['days'] ?? 7), 30); // Max 30 days
    
    // Daily message counts
    $dailyStats = $db->fetchAll(
        "SELECT 
            DATE(received_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming,
            SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing
         FROM messages 
         WHERE device_id = ? AND received_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(received_at)
         ORDER BY date DESC",
        [$authDevice['id'], $days]
    );
    
    // Message type distribution
    $typeStats = $db->fetchAll(
        "SELECT 
            message_type,
            COUNT(*) as count
         FROM messages 
         WHERE device_id = ? AND received_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY message_type
         ORDER BY count DESC",
        [$authDevice['id'], $days]
    );
    
    // Recent messages
    $recentMessages = $db->fetchAll(
        "SELECT 
            direction, message_type, from_number, to_number, 
            LEFT(message_content, 100) as preview,
            received_at, status
         FROM messages 
         WHERE device_id = ?
         ORDER BY received_at DESC 
         LIMIT 10",
        [$authDevice['id']]
    );
    
    jsonResponse([
        'success' => true,
        'data' => [
            'daily_stats' => $dailyStats,
            'type_distribution' => $typeStats,
            'recent_messages' => $recentMessages,
            'period_days' => $days
        ]
    ]);
}

// Get webhook statistics
function handleWebhookStats($authDevice) {
    $db = Database::getInstance();
    $hours = min((int)($_GET['hours'] ?? 24), 168); // Max 7 days
    
    // Webhook stats
    $stats = $db->fetch(
        "SELECT 
            COUNT(*) as total_calls,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
            AVG(execution_time) as avg_execution_time,
            MIN(execution_time) as min_execution_time,
            MAX(execution_time) as max_execution_time
         FROM webhook_logs 
         WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$authDevice['id'], $hours]
    );
    
    // Recent webhook calls
    $recentCalls = $db->fetchAll(
        "SELECT 
            event_type, response_code, execution_time, status, 
            created_at, error_message
         FROM webhook_logs 
         WHERE device_id = ?
         ORDER BY created_at DESC 
         LIMIT 20",
        [$authDevice['id']]
    );
    
    // Event type distribution
    $eventStats = $db->fetchAll(
        "SELECT 
            event_type,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
         FROM webhook_logs 
         WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
         GROUP BY event_type
         ORDER BY count DESC",
        [$authDevice['id'], $hours]
    );
    
    jsonResponse([
        'success' => true,
        'data' => [
            'webhook_url' => $authDevice['webhook_url'],
            'statistics' => $stats ?: [
                'total_calls' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'avg_execution_time' => 0,
                'min_execution_time' => 0,
                'max_execution_time' => 0
            ],
            'recent_calls' => $recentCalls,
            'event_distribution' => $eventStats,
            'period_hours' => $hours
        ]
    ]);
}

// Get API usage statistics
function handleApiStats($authDevice) {
    $db = Database::getInstance();
    $hours = min((int)($_GET['hours'] ?? 24), 168); // Max 7 days
    
    // API usage stats
    $stats = $db->fetch(
        "SELECT 
            COUNT(*) as total_requests,
            AVG(execution_time) as avg_response_time,
            SUM(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as error_count
         FROM api_logs 
         WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$authDevice['id'], $hours]
    );
    
    // Endpoint usage
    $endpointStats = $db->fetchAll(
        "SELECT 
            endpoint,
            COUNT(*) as count,
            AVG(execution_time) as avg_time
         FROM api_logs 
         WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
         GROUP BY endpoint
         ORDER BY count DESC
         LIMIT 10",
        [$authDevice['id'], $hours]
    );
    
    // Response code distribution
    $responseStats = $db->fetchAll(
        "SELECT 
            response_code,
            COUNT(*) as count
         FROM api_logs 
         WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
         GROUP BY response_code
         ORDER BY count DESC",
        [$authDevice['id'], $hours]
    );
    
    jsonResponse([
        'success' => true,
        'data' => [
            'statistics' => $stats ?: [
                'total_requests' => 0,
                'avg_response_time' => 0,
                'success_count' => 0,
                'error_count' => 0
            ],
            'endpoint_usage' => $endpointStats,
            'response_codes' => $responseStats,
            'period_hours' => $hours
        ]
    ]);
}
?>