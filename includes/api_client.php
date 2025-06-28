<?php

/**
 * WhatsApp Bridge - API Client Class
 */

require_once __DIR__ . '/../config/node_api.php';
require_once __DIR__ . '/functions.php';

class WhatsAppApiClient
{
    private $nodeApi;
    private $deviceManager;
    private $messageManager;

    public function __construct($apiKey = null)
    {
        $this->nodeApi = new NodeApiClient($apiKey);
        $this->deviceManager = new DeviceManager();
        $this->messageManager = new MessageManager();
    }

    // Create and connect session
    public function createSession($deviceName, $sessionId = null, $config = [])
    {
        try {
            // Generate session ID if not provided
            if (!$sessionId) {
                $sessionId = generateSessionId();
            }

            // Create device in database
            $device = $this->deviceManager->createDevice($deviceName, $sessionId);
            if (!$device) {
                throw new Exception("Failed to create device in database");
            }

            // Create session in Node.js API
            $response = $this->nodeApi->createSession($sessionId, $config);

            if ($response['status_code'] !== 200 || !$response['data']['success']) {
                // Cleanup device if Node.js session creation failed
                $this->deviceManager->deleteDevice($device['id']);
                throw new Exception($response['data']['error'] ?? 'Failed to create session');
            }

            // Update device status
            $this->deviceManager->updateDeviceStatus($device['id'], 'connecting');

            return [
                'success' => true,
                'device' => $device,
                'session_id' => $sessionId
            ];
        } catch (Exception $e) {
            logError("Error creating session", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Connect existing session
    public function connectSession($sessionId)
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            // Use device's API key for Node.js API
            $nodeApi = new NodeApiClient($device['api_key']);
            $response = $nodeApi->connectSession($sessionId);

            if ($response['status_code'] !== 200 || !$response['data']['success']) {
                throw new Exception($response['data']['error'] ?? 'Failed to connect session');
            }

            // Update device status
            $this->deviceManager->updateDeviceStatus($device['id'], 'connecting');

            return [
                'success' => true,
                'message' => 'Session connection initiated'
            ];
        } catch (Exception $e) {
            logError("Error connecting session", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Get session status
    public function getSessionStatus($sessionId)
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            $response = $this->nodeApi->getSessionStatus($sessionId);

            if ($response['status_code'] !== 200) {
                throw new Exception("Failed to get session status");
            }

            $nodeStatus = $response['data']['data']['state'] ?? 'DISCONNECTED';
            $mappedStatus = NODE_STATUS_MAP[$nodeStatus] ?? 'disconnected';

            // Update device status in database
            $phoneNumber = $response['data']['data']['phone'] ?? null;
            $this->deviceManager->updateDeviceStatus($device['id'], $mappedStatus, $phoneNumber);

            return [
                'success' => true,
                'status' => $mappedStatus,
                'node_status' => $nodeStatus,
                'phone' => $phoneNumber,
                'data' => $response['data']['data']
            ];
        } catch (Exception $e) {
            logError("Error getting session status", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Get QR Code
    public function getQRCode($sessionId)
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            $response = $this->nodeApi->getQRCode($sessionId);

            if ($response['status_code'] !== 200) {
                throw new Exception("Failed to get QR code");
            }

            return [
                'success' => true,
                'qr_code' => $response['data']['data']['qr'] ?? null,
                'qr_url' => $response['data']['data']['qrUrl'] ?? null
            ];
        } catch (Exception $e) {
            logError("Error getting QR code", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Disconnect session
    public function disconnectSession($sessionId)
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            $response = $this->nodeApi->disconnectSession($sessionId);

            if ($response['status_code'] !== 200) {
                throw new Exception("Failed to disconnect session");
            }

            // Update device status
            $this->deviceManager->updateDeviceStatus($device['id'], 'disconnected');

            return [
                'success' => true,
                'message' => 'Session disconnected'
            ];
        } catch (Exception $e) {
            logError("Error disconnecting session", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Send text message
    public function sendTextMessage($sessionId, $to, $text, $options = [])
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            // Validate and format phone numbers
            $recipients = is_array($to) ? $to : [$to];
            $formattedRecipients = [];

            foreach ($recipients as $phone) {
                $formatted = validatePhoneNumber($phone);
                if (!$formatted) {
                    throw new Exception("Invalid phone number: " . $phone);
                }
                $formattedRecipients[] = $formatted;
            }

            $response = $this->nodeApi->sendTextMessage($sessionId, $formattedRecipients, $text, $options);

            if ($response['status_code'] !== 200 || !$response['data']['success']) {
                throw new Exception($response['data']['error'] ?? 'Failed to send message');
            }

            // Save outgoing message to database
            foreach ($formattedRecipients as $recipient) {
                $this->messageManager->saveOutgoingMessage($device['id'], [
                    'sessionId' => $sessionId,
                    'type' => 'text',
                    'from' => $device['phone_number'] ?? '',
                    'to' => $recipient,
                    'content' => $text
                ]);
            }

            return [
                'success' => true,
                'data' => $response['data']['data'],
                'recipients' => $formattedRecipients
            ];
        } catch (Exception $e) {
            logError("Error sending text message", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Send media message
    public function sendMediaMessage($sessionId, $to, $mediaFile, $type, $caption = '', $options = [])
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            // Validate and format phone numbers
            $recipients = is_array($to) ? $to : [$to];
            $formattedRecipients = [];

            foreach ($recipients as $phone) {
                $formatted = validatePhoneNumber($phone);
                if (!$formatted) {
                    throw new Exception("Invalid phone number: " . $phone);
                }
                $formattedRecipients[] = $formatted;
            }

            // Read media file
            $mediaData = file_get_contents($mediaFile);
            if ($mediaData === false) {
                throw new Exception("Failed to read media file");
            }

            $mediaOptions = array_merge($options, [
                'caption' => $caption,
                'fileName' => basename($mediaFile)
            ]);

            $response = $this->nodeApi->sendMediaMessage($sessionId, $formattedRecipients, $mediaData, $type, $mediaOptions);

            if ($response['status_code'] !== 200 || !$response['data']['success']) {
                throw new Exception($response['data']['error'] ?? 'Failed to send media message');
            }

            // Save outgoing message to database
            foreach ($formattedRecipients as $recipient) {
                $this->messageManager->saveOutgoingMessage($device['id'], [
                    'sessionId' => $sessionId,
                    'type' => $type,
                    'from' => $device['phone_number'] ?? '',
                    'to' => $recipient,
                    'content' => $caption,
                    'mediaUrl' => basename($mediaFile)
                ]);
            }

            return [
                'success' => true,
                'data' => $response['data']['data'],
                'recipients' => $formattedRecipients
            ];
        } catch (Exception $e) {
            logError("Error sending media message", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Send location
    public function sendLocation($sessionId, $to, $latitude, $longitude, $name = '', $address = '')
    {
        try {
            $device = $this->deviceManager->getDeviceBySessionId($sessionId);
            if (!$device) {
                throw new Exception("Device not found");
            }

            $recipients = is_array($to) ? $to : [$to];
            $formattedRecipients = [];

            foreach ($recipients as $phone) {
                $formatted = validatePhoneNumber($phone);
                if (!$formatted) {
                    throw new Exception("Invalid phone number: " . $phone);
                }
                $formattedRecipients[] = $formatted;
            }

            $response = $this->nodeApi->sendLocation($sessionId, $formattedRecipients, $latitude, $longitude, $name, $address);

            if ($response['status_code'] !== 200 || !$response['data']['success']) {
                throw new Exception($response['data']['error'] ?? 'Failed to send location');
            }

            // Save outgoing message to database
            foreach ($formattedRecipients as $recipient) {
                $this->messageManager->saveOutgoingMessage($device['id'], [
                    'sessionId' => $sessionId,
                    'type' => 'location',
                    'from' => $device['phone_number'] ?? '',
                    'to' => $recipient,
                    'content' => json_encode([
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'name' => $name,
                        'address' => $address
                    ])
                ]);
            }

            return [
                'success' => true,
                'data' => $response['data']['data'],
                'recipients' => $formattedRecipients
            ];
        } catch (Exception $e) {
            logError("Error sending location", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Get all sessions
    public function getAllSessions()
    {
        try {
            $response = $this->nodeApi->getAllSessions();

            if ($response['status_code'] !== 200) {
                throw new Exception("Failed to get sessions");
            }

            return [
                'success' => true,
                'sessions' => $response['data']['data'] ?? []
            ];
        } catch (Exception $e) {
            logError("Error getting all sessions", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Health check
    public function healthCheck()
    {
        try {
            $response = $this->nodeApi->healthCheck();

            return [
                'success' => $response['status_code'] === 200,
                'data' => $response['data'] ?? null,
                'status_code' => $response['status_code']
            ];
        } catch (Exception $e) {
            logError("Error in health check", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
