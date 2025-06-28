<?php

/**
 * WhatsApp Bridge - Main Configuration
 */

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Application Configuration
define('APP_NAME', 'WhatsApp Bridge');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/whatsapp-php-bridge');

// Security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_LENGTH', 32);

// Directories
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Create directories if not exist
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(LOGS_PATH)) {
    mkdir(LOGS_PATH, 0755, true);
}

// File upload limits
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp3', 'mp4', 'avi']);

// API Configuration
define('API_RATE_LIMIT', 100); // requests per minute
define('API_TIMEOUT', 30); // seconds

// Webhook Configuration
define('WEBHOOK_TIMEOUT', 30); // seconds
define('WEBHOOK_MAX_RETRIES', 3);

// Logging
define('LOG_LEVEL', 'INFO'); // ERROR, WARN, INFO, DEBUG
define('LOG_MAX_FILES', 30); // Keep logs for 30 days

// Status mapping from Node.js API
define('NODE_STATUS_MAP', [
    'CONNECTING' => 'connecting',
    'CONNECTED' => 'connected',
    'DISCONNECTED' => 'disconnected',
    'BANNED' => 'banned',
    'QR_GENERATED' => 'connecting'
]);

// Error codes
define('ERROR_CODES', [
    'INVALID_REQUEST' => 400,
    'UNAUTHORIZED' => 401,
    'FORBIDDEN' => 403,
    'NOT_FOUND' => 404,
    'METHOD_NOT_ALLOWED' => 405,
    'VALIDATION_ERROR' => 422,
    'RATE_LIMITED' => 429,
    'INTERNAL_ERROR' => 500,
    'SERVICE_UNAVAILABLE' => 503
]);

// Helper Functions
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

function generateApiKey()
{
    return 'wa_' . generateToken(20);
}

function generateDeviceToken()
{
    return 'dev_' . generateToken(16);
}

function getCurrentTimestamp()
{
    return date('Y-m-d H:i:s');
}

function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function logError($message, $context = [])
{
    $logFile = LOGS_PATH . '/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = $context ? ' - Context: ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] ERROR: {$message}{$contextStr}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function logInfo($message, $context = [])
{
    $logFile = LOGS_PATH . '/info_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = $context ? ' - Context: ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] INFO: {$message}{$contextStr}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function logApi($endpoint, $method, $data = [], $response = [], $responseCode = 200, $executionTime = 0)
{
    $logFile = LOGS_PATH . '/api_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logData = [
        'timestamp' => $timestamp,
        'ip' => $ip,
        'method' => $method,
        'endpoint' => $endpoint,
        'request_data' => $data,
        'response_code' => $responseCode,
        'execution_time' => round($executionTime, 3),
        'user_agent' => $userAgent
    ];

    $logMessage = json_encode($logData) . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function validatePhoneNumber($phone)
{
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Must be at least 8 digits
    if (strlen($phone) < 8) {
        return false;
    }

    // If starts with 0, remove it and add country code
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }

    // If doesn't start with country code, add default (62 for Indonesia)
    if (!preg_match('/^[1-9]/', $phone)) {
        $phone = '62' . $phone;
    }

    return $phone;
}

function formatPhoneNumber($phone)
{
    $cleaned = validatePhoneNumber($phone);
    if (!$cleaned) {
        return false;
    }
    return $cleaned . '@s.whatsapp.net';
}

function cleanInput($data)
{
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}
