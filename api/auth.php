<?php

/**
 * WhatsApp Bridge API - Authentication & QR Code
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_client.php';

try {
    // Ambil action dari parameter
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if (empty($action)) {
        jsonResponse([
            'success' => false,
            'error' => 'Action parameter required'
        ], 400);
    }

    // Validasi API Key
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

    if (empty($apiKey)) {
        jsonResponse([
            'success' => false,
            'error' => 'API key required'
        ], 401);
    }

    // Inisialisasi database dan validasi device
    $db = new Database();

    $device = $db->fetch(
        "SELECT d.*, u.username FROM devices d 
         JOIN users u ON d.created_by = u.id 
         WHERE d.api_key = ? AND d.status != 'inactive'",
        [$apiKey]
    );

    if (!$device) {
        jsonResponse([
            'success' => false,
            'error' => 'Invalid API key or device inactive'
        ], 401);
    }

    // Update last activity
    $db->execute(
        "UPDATE devices SET last_activity = NOW() WHERE id = ?",
        [$device['id']]
    );

    // Inisialisasi API Client untuk Node.js
    $apiClient = new ApiClient();

    switch ($action) {
        case 'qr':
            handleQRCode($apiClient, $device);
            break;

        case 'status':
            handleStatus($apiClient, $device);
            break;

        case 'logout':
            handleLogout($apiClient, $device, $db);
            break;

        default:
            jsonResponse([
                'success' => false,
                'error' => 'Invalid action'
            ], 400);
    }
} catch (Exception $e) {
    logError("Auth API Error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    jsonResponse([
        'success' => false,
        'error' => 'Internal server error'
    ], 500);
}

function handleQRCode($apiClient, $device)
{
    try {
        // Cek status device terlebih dahulu
        $statusResult = $apiClient->getSessionStatus($device['session_id']);

        if (!$statusResult['success']) {
            // Jika session belum ada, buat session baru
            $createResult = $apiClient->createSession($device['session_id']);
            if (!$createResult['success']) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Failed to create session: ' . $createResult['error']
                ], 500);
            }
        }

        // Ambil QR code
        $qrResult = $apiClient->getQRCode($device['session_id']);

        if ($qrResult['success'] && isset($qrResult['data']['qr_code'])) {
            jsonResponse([
                'success' => true,
                'data' => [
                    'qr_code' => $qrResult['data']['qr_code'],
                    'session_id' => $device['session_id'],
                    'status' => $statusResult['data']['status'] ?? 'connecting'
                ]
            ]);
        } else {
            // Jika QR tidak tersedia, mungkin sudah connected
            $currentStatus = $statusResult['data']['status'] ?? 'unknown';

            if ($currentStatus === 'CONNECTED') {
                jsonResponse([
                    'success' => false,
                    'error' => 'Device already connected',
                    'data' => [
                        'status' => 'connected',
                        'phone_number' => $statusResult['data']['phone_number'] ?? null
                    ]
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'error' => 'QR code not available. ' . ($qrResult['error'] ?? 'Please try again.')
                ]);
            }
        }
    } catch (Exception $e) {
        logError("QR Code Error", [
            'device_id' => $device['id'],
            'session_id' => $device['session_id'],
            'error' => $e->getMessage()
        ]);

        jsonResponse([
            'success' => false,
            'error' => 'Failed to get QR code: ' . $e->getMessage()
        ], 500);
    }
}

function handleStatus($apiClient, $device)
{
    try {
        $result = $apiClient->getSessionStatus($device['session_id']);

        if ($result['success']) {
            $status = $result['data']['status'] ?? 'unknown';
            $phoneNumber = $result['data']['phone_number'] ?? null;

            // Map status dari Node.js ke format PHP
            $mappedStatus = mapNodeStatus($status);

            // Update status di database jika berbeda
            global $db;
            $currentDevice = $db->fetch("SELECT status, phone_number FROM devices WHERE id = ?", [$device['id']]);

            if ($currentDevice['status'] !== $mappedStatus || $currentDevice['phone_number'] !== $phoneNumber) {
                $db->execute(
                    "UPDATE devices SET status = ?, phone_number = ?, last_activity = NOW() WHERE id = ?",
                    [$mappedStatus, $phoneNumber, $device['id']]
                );
            }

            jsonResponse([
                'success' => true,
                'data' => [
                    'status' => $mappedStatus,
                    'phone_number' => $phoneNumber,
                    'session_id' => $device['session_id'],
                    'last_activity' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => 'Failed to get status: ' . $result['error']
            ], 500);
        }
    } catch (Exception $e) {
        logError("Status Check Error", [
            'device_id' => $device['id'],
            'session_id' => $device['session_id'],
            'error' => $e->getMessage()
        ]);

        jsonResponse([
            'success' => false,
            'error' => 'Failed to check status: ' . $e->getMessage()
        ], 500);
    }
}

function handleLogout($apiClient, $device, $db)
{
    try {
        $result = $apiClient->disconnectSession($device['session_id']);

        // Update status di database menjadi disconnected
        $db->execute(
            "UPDATE devices SET status = 'disconnected', phone_number = NULL, last_activity = NOW() WHERE id = ?",
            [$device['id']]
        );

        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Device logged out successfully'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'error' => 'Failed to logout: ' . $result['error']
            ], 500);
        }
    } catch (Exception $e) {
        logError("Logout Error", [
            'device_id' => $device['id'],
            'session_id' => $device['session_id'],
            'error' => $e->getMessage()
        ]);

        jsonResponse([
            'success' => false,
            'error' => 'Failed to logout: ' . $e->getMessage()
        ], 500);
    }
}

function mapNodeStatus($nodeStatus)
{
    $statusMap = [
        'CONNECTING' => 'connecting',
        'CONNECTED' => 'connected',
        'DISCONNECTED' => 'disconnected',
        'BANNED' => 'banned',
        'QR_GENERATED' => 'connecting',
        'TIMEOUT' => 'disconnected'
    ];

    return $statusMap[$nodeStatus] ?? 'unknown';
}

// Helper function untuk validasi dan log
function logApiRequest($device, $action, $result)
{
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'device_id' => $device['id'],
        'session_id' => $device['session_id'],
        'action' => $action,
        'success' => $result['success'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    logInfo("Auth API Request", $logData);
}
