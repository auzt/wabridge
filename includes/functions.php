<?php

/**
 * WhatsApp Bridge - Utility Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';


/**
 * WhatsApp Bridge - Complete DeviceManager Class
 * Ganti isi class DeviceManager di includes/functions.php dengan yang ini
 */

class DeviceManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new device - FIXED VERSION
     */
    public function createDevice($data)
    {
        try {
            // Handle array parameter
            if (is_array($data)) {
                $deviceName = $data['device_name'];
                $webhookUrl = $data['webhook_url'] ?? null;
                $note = $data['note'] ?? null;
                $userId = $data['created_by'];
            } else {
                // Handle old parameter format for backward compatibility
                $deviceName = $data;
                $webhookUrl = func_num_args() > 2 ? func_get_arg(2) : null;
                $userId = func_num_args() > 3 ? func_get_arg(3) : null;
                $note = func_num_args() > 4 ? func_get_arg(4) : null;
            }

            $sessionId = generateSessionId();
            $apiKey = generateApiKey();
            $deviceToken = generateDeviceToken();

            $result = $this->db->execute(
                "INSERT INTO devices (device_name, session_id, device_token, api_key, webhook_url, note, created_by, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'disconnected')",
                [$deviceName, $sessionId, $deviceToken, $apiKey, $webhookUrl, $note, $userId]
            );

            if ($result) {
                $deviceId = $this->db->lastInsertId();

                logInfo("Device created", [
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'session_id' => $sessionId
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'device_id' => $deviceId,
                        'id' => $deviceId,
                        'device_name' => $deviceName,
                        'session_id' => $sessionId,
                        'device_token' => $deviceToken,
                        'api_key' => $apiKey,
                        'webhook_url' => $webhookUrl,
                        'note' => $note,
                        'status' => 'disconnected'
                    ]
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to insert device into database'
            ];
        } catch (Exception $e) {
            logError("Error creating device", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if device name exists for user
     */
    public function getDeviceByName($deviceName, $userId)
    {
        try {
            return $this->db->fetch(
                "SELECT * FROM devices WHERE device_name = ? AND created_by = ? AND status != 'inactive'",
                [$deviceName, $userId]
            );
        } catch (Exception $e) {
            logError("Error checking device name", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get device by ID
     */
    public function getDevice($deviceId)
    {
        try {
            return $this->db->fetch(
                "SELECT d.*, u.username as created_by_username FROM devices d 
                 JOIN users u ON d.created_by = u.id 
                 WHERE d.id = ?",
                [$deviceId]
            );
        } catch (Exception $e) {
            logError("Error getting device", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get device by session ID
     */
    public function getDeviceBySessionId($sessionId)
    {
        try {
            return $this->db->fetch(
                "SELECT * FROM devices WHERE session_id = ? AND status != 'inactive'",
                [$sessionId]
            );
        } catch (Exception $e) {
            logError("Error getting device by session", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get device by API key
     */
    public function getDeviceByApiKey($apiKey)
    {
        try {
            return $this->db->fetch(
                "SELECT * FROM devices WHERE api_key = ? AND status != 'inactive'",
                [$apiKey]
            );
        } catch (Exception $e) {
            logError("Error getting device by API key", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Update device
     */
    public function updateDevice($deviceId, $data)
    {
        try {
            $setParts = [];
            $params = [];

            if (isset($data['webhook_url'])) {
                $setParts[] = "webhook_url = ?";
                $params[] = $data['webhook_url'];
            }

            if (isset($data['note'])) {
                $setParts[] = "note = ?";
                $params[] = $data['note'];
            }

            if (isset($data['device_name'])) {
                $setParts[] = "device_name = ?";
                $params[] = $data['device_name'];
            }

            if (empty($setParts)) {
                return [
                    'success' => false,
                    'error' => 'No data to update'
                ];
            }

            $setParts[] = "updated_at = NOW()";
            $params[] = $deviceId;

            $sql = "UPDATE devices SET " . implode(', ', $setParts) . " WHERE id = ?";
            $result = $this->db->execute($sql, $params);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Device updated successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to update device'
            ];
        } catch (Exception $e) {
            logError("Error updating device", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update device status
     */
    public function updateDeviceStatus($deviceId, $status, $phoneNumber = null, $qrCode = null)
    {
        try {
            $sql = "UPDATE devices SET status = ?, last_activity = NOW()";
            $params = [$status];

            if ($phoneNumber !== null) {
                $sql .= ", phone_number = ?";
                $params[] = $phoneNumber;
            }

            if ($qrCode !== null) {
                $sql .= ", qr_code = ?";
                $params[] = $qrCode;
            }

            $sql .= " WHERE id = ?";
            $params[] = $deviceId;

            $result = $this->db->execute($sql, $params);

            if ($result) {
                logInfo("Device status updated", [
                    'device_id' => $deviceId,
                    'status' => $status,
                    'phone_number' => $phoneNumber
                ]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            logError("Error updating device status", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Update webhook URL
     */
    public function updateWebhookUrl($deviceId, $webhookUrl)
    {
        try {
            $result = $this->db->execute(
                "UPDATE devices SET webhook_url = ?, updated_at = NOW() WHERE id = ?",
                [$webhookUrl, $deviceId]
            );

            return $result > 0;
        } catch (Exception $e) {
            logError("Error updating webhook URL", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get all devices
     */
    public function getAllDevices($userId = null)
    {
        try {
            $sql = "SELECT d.*, u.username as created_by_username FROM devices d 
                    JOIN users u ON d.created_by = u.id 
                    WHERE d.status != 'inactive'";
            $params = [];

            if ($userId) {
                $sql .= " AND d.created_by = ?";
                $params[] = $userId;
            }

            $sql .= " ORDER BY d.created_at DESC";

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            logError("Error getting all devices", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get user devices with statistics
     */
    public function getUserDevices($userId)
    {
        try {
            $devices = $this->getAllDevices($userId);

            return [
                'success' => true,
                'data' => [
                    'devices' => $devices,
                    'total' => count($devices)
                ]
            ];
        } catch (Exception $e) {
            logError("Error getting user devices", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => ['devices' => [], 'total' => 0]
            ];
        }
    }

    /**
     * Get device statistics
     */
    public function getDeviceStats($userId)
    {
        try {
            $devices = $this->getAllDevices($userId);

            $stats = [
                'total' => 0,
                'connected' => 0,
                'connecting' => 0,
                'disconnected' => 0,
                'inactive' => 0
            ];

            foreach ($devices as $device) {
                $stats['total']++;
                $status = $device['status'] ?? 'disconnected';
                if (isset($stats[$status])) {
                    $stats[$status]++;
                }
            }

            return [
                'success' => true,
                'data' => $stats
            ];
        } catch (Exception $e) {
            logError("Error getting device stats", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => ['total' => 0, 'connected' => 0, 'connecting' => 0, 'disconnected' => 0, 'inactive' => 0]
            ];
        }
    }

    /**
     * Delete device
     */
    public function deleteDevice($deviceId)
    {
        try {
            $device = $this->getDevice($deviceId);
            if (!$device) {
                return false;
            }

            // Soft delete - mark as inactive instead of hard delete
            $result = $this->db->execute(
                "UPDATE devices SET status = 'inactive', updated_at = NOW() WHERE id = ?",
                [$deviceId]
            );

            // Or hard delete if preferred:
            // $result = $this->db->execute("DELETE FROM devices WHERE id = ?", [$deviceId]);

            if ($result) {
                logInfo("Device deleted", [
                    'device_id' => $deviceId,
                    'device_name' => $device['device_name']
                ]);
                return true;
            }

            return false;
        } catch (Exception $e) {
            logError("Error deleting device", ['error' => $e->getMessage()]);
            return false;
        }
    }
}

class MessageManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Save incoming message
    public function saveIncomingMessage($deviceId, $messageData)
    {
        try {
            $result = $this->db->execute(
                "INSERT INTO messages (device_id, message_id, session_id, direction, message_type, 
                 from_number, to_number, group_id, message_content, media_url, caption, 
                 quoted_message_id, status, received_at) 
                 VALUES (?, ?, ?, 'incoming', ?, ?, ?, ?, ?, ?, ?, ?, 'delivered', NOW())",
                [
                    $deviceId,
                    $messageData['id'] ?? '',
                    $messageData['sessionId'] ?? '',
                    $messageData['type'] ?? 'text',
                    $messageData['from'] ?? '',
                    $messageData['to'] ?? '',
                    $messageData['groupId'] ?? null,
                    $messageData['content'] ?? '',
                    $messageData['mediaUrl'] ?? null,
                    $messageData['caption'] ?? null,
                    $messageData['quotedMessageId'] ?? null
                ]
            );

            if ($result) {
                $messageId = $this->db->lastInsertId();
                logInfo("Incoming message saved", [
                    'message_id' => $messageId,
                    'device_id' => $deviceId,
                    'from' => $messageData['from'] ?? ''
                ]);
                return $messageId;
            }

            return false;
        } catch (Exception $e) {
            logError("Error saving incoming message", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Save outgoing message
    public function saveOutgoingMessage($deviceId, $messageData)
    {
        try {
            $result = $this->db->execute(
                "INSERT INTO messages (device_id, message_id, session_id, direction, message_type, 
                 from_number, to_number, group_id, message_content, media_url, caption, 
                 status, sent_at) 
                 VALUES (?, ?, ?, 'outgoing', ?, ?, ?, ?, ?, ?, ?, 'sent', NOW())",
                [
                    $deviceId,
                    $messageData['id'] ?? '',
                    $messageData['sessionId'] ?? '',
                    $messageData['type'] ?? 'text',
                    $messageData['from'] ?? '',
                    $messageData['to'] ?? '',
                    $messageData['groupId'] ?? null,
                    $messageData['content'] ?? '',
                    $messageData['mediaUrl'] ?? null,
                    $messageData['caption'] ?? null
                ]
            );

            if ($result) {
                $messageId = $this->db->lastInsertId();
                logInfo("Outgoing message saved", [
                    'message_id' => $messageId,
                    'device_id' => $deviceId,
                    'to' => $messageData['to'] ?? ''
                ]);
                return $messageId;
            }

            return false;
        } catch (Exception $e) {
            logError("Error saving outgoing message", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Get messages for device
    public function getMessages($deviceId, $limit = 50, $offset = 0, $direction = null)
    {
        $sql = "SELECT * FROM messages WHERE device_id = ?";
        $params = [$deviceId];

        if ($direction) {
            $sql .= " AND direction = ?";
            $params[] = $direction;
        }

        $sql .= " ORDER BY received_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    // Update message status
    public function updateMessageStatus($messageId, $status)
    {
        try {
            $column = '';
            switch ($status) {
                case 'delivered':
                    $column = 'delivered_at';
                    break;
                case 'read':
                    $column = 'read_at';
                    break;
                default:
                    $column = null;
            }

            $sql = "UPDATE messages SET status = ?";
            $params = [$status];

            if ($column) {
                $sql .= ", {$column} = NOW()";
            }

            $sql .= " WHERE id = ?";
            $params[] = $messageId;

            return $this->db->execute($sql, $params) > 0;
        } catch (Exception $e) {
            logError("Error updating message status", ['error' => $e->getMessage()]);
            return false;
        }
    }
}

class WebhookManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Send webhook
    public function sendWebhook($deviceId, $webhookUrl, $data, $eventType = 'message')
    {
        try {
            if (empty($webhookUrl)) {
                return false;
            }

            $payload = json_encode($data);
            $startTime = microtime(true);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $webhookUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Event-Type: ' . $eventType,
                    'User-Agent: WhatsApp-Bridge/1.0'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => WEBHOOK_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $executionTime = (microtime(true) - $startTime) * 1000; // ms
            $status = $error ? 'failed' : ($httpCode >= 200 && $httpCode < 300 ? 'success' : 'failed');

            // Log webhook call
            $this->logWebhookCall($deviceId, $eventType, $payload, $httpCode, $response, $executionTime, $status, $error);

            return $status === 'success';
        } catch (Exception $e) {
            logError("Error sending webhook", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Log webhook call
    private function logWebhookCall($deviceId, $eventType, $payload, $responseCode, $responseBody, $executionTime, $status, $error = null)
    {
        try {
            $this->db->execute(
                "INSERT INTO webhook_logs (device_id, webhook_id, event_type, payload, response_code, 
                 response_body, execution_time, status, error_message) 
                 VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $deviceId,
                    $eventType,
                    $payload,
                    $responseCode ?: 0,
                    $responseBody ?: '',
                    $executionTime,
                    $status,
                    $error
                ]
            );
        } catch (Exception $e) {
            logError("Error logging webhook call", ['error' => $e->getMessage()]);
        }
    }

    // Get webhook stats
    public function getWebhookStats($deviceId)
    {
        try {
            return $this->db->fetch(
                "SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    AVG(execution_time) as avg_execution_time
                 FROM webhook_logs 
                 WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$deviceId]
            );
        } catch (Exception $e) {
            logError("Error getting webhook stats", ['error' => $e->getMessage()]);
            return null;
        }
    }
}

// Utility functions
function formatBytes($size, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time / 60) . ' minutes ago';
    if ($time < 86400) return floor($time / 3600) . ' hours ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';

    return date('M j, Y', strtotime($datetime));
}

function truncateText($text, $length = 100)
{
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function getFileIcon($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📽️',
        'pptx' => '📽️',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'mp3' => '🎵',
        'wav' => '🎵',
        'mp4' => '🎬',
        'avi' => '🎬',
        'mov' => '🎬',
        'zip' => '📦',
        'rar' => '📦',
        'txt' => '📄'
    ];

    return $icons[$ext] ?? '📎';
}

function isValidJson($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

function maskApiKey($apiKey)
{
    if (strlen($apiKey) < 8) return $apiKey;
    return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
}

function generateSessionId($prefix = 'wa')
{
    return $prefix . '_' . time() . '_' . mt_rand(1000, 9999);
}
