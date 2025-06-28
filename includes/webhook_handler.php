<?php

/**
 * WhatsApp Bridge - Webhook Handler
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

class WebhookHandler
{
    private $db;
    private $deviceManager;
    private $messageManager;
    private $webhookManager;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->deviceManager = new DeviceManager();
        $this->messageManager = new MessageManager();
        $this->webhookManager = new WebhookManager();
    }

    // Handle incoming webhook from Node.js
    public function handleWebhook($data)
    {
        try {
            if (!isset($data['sessionId']) || !isset($data['event'])) {
                throw new Exception("Invalid webhook data: missing sessionId or event");
            }

            $sessionId = $data['sessionId'];
            $event = $data['event'];

            // Get device by session ID
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                logError("Webhook received for unknown session", ['session_id' => $sessionId]);
                return false;
            }

            logInfo("Webhook received", [
                'session_id' => $sessionId,
                'event' => $event,
                'device_id' => $device['id']
            ]);

            // Process different event types
            switch ($event) {
                case 'message':
                    return $this->handleMessageEvent($device, $data['data']);

                case 'connection_update':
                    return $this->handleConnectionUpdate($device, $data['data']);

                case 'qr_code':
                    return $this->handleQRCode($device, $data['data']);

                case 'auth_failure':
                    return $this->handleAuthFailure($device, $data['data']);

                case 'contact_update':
                    return $this->handleContactUpdate($device, $data['data']);

                case 'group_update':
                    return $this->handleGroupUpdate($device, $data['data']);

                default:
                    logInfo("Unknown webhook event", ['event' => $event]);
                    return true;
            }
        } catch (Exception $e) {
            logError("Error handling webhook", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Handle message events
    private function handleMessageEvent($device, $messageData)
    {
        try {
            // Extract message information
            $message = [
                'id' => $messageData['key']['id'] ?? '',
                'sessionId' => $device['session_id'],
                'type' => $this->getMessageType($messageData),
                'from' => $this->extractPhoneNumber($messageData['key']['remoteJid'] ?? ''),
                'to' => $device['phone_number'] ?? '',
                'groupId' => $this->isGroupMessage($messageData['key']['remoteJid'] ?? '') ?
                    $messageData['key']['remoteJid'] : null,
                'content' => $this->extractMessageContent($messageData),
                'mediaUrl' => $this->extractMediaUrl($messageData),
                'caption' => $messageData['message']['imageMessage']['caption'] ??
                    $messageData['message']['videoMessage']['caption'] ??
                    $messageData['message']['documentMessage']['caption'] ?? null,
                'quotedMessageId' => $messageData['message']['extendedTextMessage']['contextInfo']['quotedMessage']['key']['id'] ?? null
            ];

            // Save message to database
            $messageId = $this->messageManager->saveIncomingMessage($device['id'], $message);

            if (!$messageId) {
                throw new Exception("Failed to save message to database");
            }

            // Forward webhook to external URL if configured
            if (!empty($device['webhook_url'])) {
                $webhookData = [
                    'event' => 'message_received',
                    'device_id' => $device['id'],
                    'device_name' => $device['device_name'],
                    'session_id' => $device['session_id'],
                    'message' => $message,
                    'timestamp' => getCurrentTimestamp()
                ];

                $this->webhookManager->sendWebhook(
                    $device['id'],
                    $device['webhook_url'],
                    $webhookData,
                    'message_received'
                );
            }

            logInfo("Message processed", [
                'message_id' => $messageId,
                'device_id' => $device['id'],
                'from' => $message['from'],
                'type' => $message['type']
            ]);

            return true;
        } catch (Exception $e) {
            logError("Error handling message event", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Handle connection updates
    private function handleConnectionUpdate($device, $connectionData)
    {
        try {
            $state = $connectionData['state'] ?? 'DISCONNECTED';
            $mappedStatus = NODE_STATUS_MAP[$state] ?? 'disconnected';

            // Extract phone number if connected
            $phoneNumber = null;
            if ($state === 'CONNECTED' && isset($connectionData['user']['id'])) {
                $phoneNumber = $this->extractPhoneNumber($connectionData['user']['id']);
            }

            // Update device status
            $this->deviceManager->updateDeviceStatus($device['id'], $mappedStatus, $phoneNumber);

            // Forward webhook to external URL if configured
            if (!empty($device['webhook_url'])) {
                $webhookData = [
                    'event' => 'connection_update',
                    'device_id' => $device['id'],
                    'device_name' => $device['device_name'],
                    'session_id' => $device['session_id'],
                    'status' => $mappedStatus,
                    'node_status' => $state,
                    'phone_number' => $phoneNumber,
                    'timestamp' => getCurrentTimestamp()
                ];

                $this->webhookManager->sendWebhook(
                    $device['id'],
                    $device['webhook_url'],
                    $webhookData,
                    'connection_update'
                );
            }

            logInfo("Connection status updated", [
                'device_id' => $device['id'],
                'status' => $mappedStatus,
                'phone_number' => $phoneNumber
            ]);

            return true;
        } catch (Exception $e) {
            logError("Error handling connection update", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Handle QR code events
    private function handleQRCode($device, $qrData)
    {
        try {
            $qrCode = $qrData['qr'] ?? null;

            // Update device with QR code
            $this->deviceManager->updateDeviceStatus($device['id'], 'connecting', null, $qrCode);

            // Forward webhook to external URL if configured
            if (!empty($device['webhook_url'])) {
                $webhookData = [
                    'event' => 'qr_code',
                    'device_id' => $device['id'],
                    'device_name' => $device['device_name'],
                    'session_id' => $device['session_id'],
                    'qr_code' => $qrCode,
                    'timestamp' => getCurrentTimestamp()
                ];

                $this->webhookManager->sendWebhook(
                    $device['id'],
                    $device['webhook_url'],
                    $webhookData,
                    'qr_code'
                );
            }

            logInfo("QR code received", ['device_id' => $device['id']]);
            return true;
        } catch (Exception $e) {
            logError("Error handling QR code", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Handle authentication failure
    private function handleAuthFailure($device, $authData)
    {
        try {
            $reason = $authData['reason'] ?? 'unknown';

            // Update device status to banned if auth failed due to ban
            $status = ($reason === 'banned' || $reason === 'blocked') ? 'banned' : 'disconnected';
            $this->deviceManager->updateDeviceStatus($device['id'], $status);

            // Forward webhook to external URL if configured
            if (!empty($device['webhook_url'])) {
                $webhookData = [
                    'event' => 'auth_failure',
                    'device_id' => $device['id'],
                    'device_name' => $device['device_name'],
                    'session_id' => $device['session_id'],
                    'reason' => $reason,
                    'status' => $status,
                    'timestamp' => getCurrentTimestamp()
                ];

                $this->webhookManager->sendWebhook(
                    $device['id'],
                    $device['webhook_url'],
                    $webhookData,
                    'auth_failure'
                );
            }

            logInfo("Authentication failure", [
                'device_id' => $device['id'],
                'reason' => $reason,
                'status' => $status
            ]);

            return true;
        } catch (Exception $e) {
            logError("Error handling auth failure", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Handle contact updates
    private function handleContactUpdate($device, $contactData)
    {
        try {
            // Save or update contact in database
            $phoneNumber = $this->extractPhoneNumber($contactData['jid'] ?? '');

            $this->db->execute(
                "INSERT INTO contacts (device_id, phone_number, display_name, profile_name, profile_picture, last_seen, status_message) 
                 VALUES (?, ?, ?, ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 display_name = VALUES(display_name), 
                 profile_name = VALUES(profile_name), 
                 profile_picture = VALUES(profile_picture), 
                 last_seen = VALUES(last_seen), 
                 status_message = VALUES(status_message)",
                [
                    $device['id'],
                    $phoneNumber,
                    $contactData['name'] ?? null,
                    $contactData['notify'] ?? null,
                    $contactData['imgUrl'] ?? null,
                    isset($contactData['lastSeen']) ? date('Y-m-d H:i:s', $contactData['lastSeen']) : null,
                    $contactData['status'] ?? null
                ]
            );

            logInfo("Contact updated", [
                'device_id' => $device['id'],
                'phone_number' => $phoneNumber
            ]);

            return true;
        } catch (Exception $e) {
            logError("Error handling contact update", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Handle group updates
    private function handleGroupUpdate($device, $groupData)
    {
        try {
            $groupId = $groupData['jid'] ?? '';

            $this->db->execute(
                "INSERT INTO groups (device_id, group_id, group_name, group_description, group_picture, owner_number, participant_count) 
                 VALUES (?, ?, ?, ?, ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE 
                 group_name = VALUES(group_name), 
                 group_description = VALUES(group_description), 
                 group_picture = VALUES(group_picture), 
                 participant_count = VALUES(participant_count)",
                [
                    $device['id'],
                    $groupId,
                    $groupData['subject'] ?? '',
                    $groupData['desc'] ?? '',
                    $groupData['pictureUrl'] ?? null,
                    $this->extractPhoneNumber($groupData['owner'] ?? ''),
                    $groupData['size'] ?? 0
                ]
            );

            logInfo("Group updated", [
                'device_id' => $device['id'],
                'group_id' => $groupId
            ]);

            return true;
        } catch (Exception $e) {
            logError("Error handling group update", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Helper methods
    private function getMessageType($messageData)
    {
        if (isset($messageData['message']['conversation'])) return 'text';
        if (isset($messageData['message']['extendedTextMessage'])) return 'text';
        if (isset($messageData['message']['imageMessage'])) return 'image';
        if (isset($messageData['message']['videoMessage'])) return 'video';
        if (isset($messageData['message']['audioMessage'])) return 'audio';
        if (isset($messageData['message']['documentMessage'])) return 'document';
        if (isset($messageData['message']['contactMessage'])) return 'contact';
        if (isset($messageData['message']['locationMessage'])) return 'location';
        if (isset($messageData['message']['reactionMessage'])) return 'reaction';
        return 'unknown';
    }

    private function extractMessageContent($messageData)
    {
        if (isset($messageData['message']['conversation'])) {
            return $messageData['message']['conversation'];
        }
        if (isset($messageData['message']['extendedTextMessage']['text'])) {
            return $messageData['message']['extendedTextMessage']['text'];
        }
        if (isset($messageData['message']['imageMessage']['caption'])) {
            return $messageData['message']['imageMessage']['caption'];
        }
        if (isset($messageData['message']['videoMessage']['caption'])) {
            return $messageData['message']['videoMessage']['caption'];
        }
        if (isset($messageData['message']['documentMessage']['caption'])) {
            return $messageData['message']['documentMessage']['caption'];
        }
        if (isset($messageData['message']['contactMessage']['displayName'])) {
            return $messageData['message']['contactMessage']['displayName'];
        }
        if (isset($messageData['message']['locationMessage'])) {
            $loc = $messageData['message']['locationMessage'];
            return "Location: " . ($loc['name'] ?? 'Unknown') . " (" . $loc['degreesLatitude'] . ", " . $loc['degreesLongitude'] . ")";
        }
        return '';
    }

    private function extractMediaUrl($messageData)
    {
        if (isset($messageData['message']['imageMessage']['url'])) {
            return $messageData['message']['imageMessage']['url'];
        }
        if (isset($messageData['message']['videoMessage']['url'])) {
            return $messageData['message']['videoMessage']['url'];
        }
        if (isset($messageData['message']['audioMessage']['url'])) {
            return $messageData['message']['audioMessage']['url'];
        }
        if (isset($messageData['message']['documentMessage']['url'])) {
            return $messageData['message']['documentMessage']['url'];
        }
        return null;
    }

    private function extractPhoneNumber($jid)
    {
        return preg_replace('/@.*/', '', $jid);
    }

    private function isGroupMessage($jid)
    {
        return strpos($jid, '@g.us') !== false;
    }
}

// Process incoming webhook
function processWebhook()
{
    try {
        // Get raw POST data
        $input = file_get_contents('php://input');
        if (empty($input)) {
            throw new Exception("No webhook data received");
        }

        // Decode JSON
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON data");
        }

        // Log webhook receipt
        logInfo("Webhook received", ['data' => $data]);

        // Process webhook
        $handler = new WebhookHandler();
        $success = $handler->handleWebhook($data);

        if ($success) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Webhook processed']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process webhook']);
        }
    } catch (Exception $e) {
        logError("Webhook processing error", ['error' => $e->getMessage()]);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
