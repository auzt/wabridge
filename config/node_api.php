<?php

/**
 * WhatsApp Bridge - Node.js API Configuration
 */

// Node.js API Configuration
define('NODE_API_URL', 'http://localhost:3000/api');
define('NODE_API_TIMEOUT', 30);

class NodeApiClient
{
    private $baseUrl;
    private $timeout;
    private $apiKey;

    public function __construct($apiKey =  'your_secure_api_key_here_change_this')
    {
        $this->baseUrl = NODE_API_URL;
        $this->timeout = NODE_API_TIMEOUT;
        $this->apiKey = $apiKey;
    }

    private function makeRequest($method, $endpoint, $data = null, $headers = [])
    {
        $url = $this->baseUrl . $endpoint;

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($this->apiKey) {
            $defaultHeaders[] = 'x-api-key: ' . $this->apiKey;
        }

        $headers = array_merge($defaultHeaders, $headers);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        $decodedResponse = json_decode($response, true);

        return [
            'status_code' => $httpCode,
            'data' => $decodedResponse,
            'raw' => $response
        ];
    }

    // Authentication endpoints
    public function createSession($sessionId, $config = [])
    {
        return $this->makeRequest('POST', '/auth/create-session', [
            'sessionId' => $sessionId,
            'config' => $config
        ]);
    }

    public function connectSession($sessionId)
    {
        return $this->makeRequest('POST', '/auth/connect', [
            'sessionId' => $sessionId
        ]);
    }

    public function getSessionStatus($sessionId)
    {
        return $this->makeRequest('GET', '/auth/status/' . $sessionId);
    }

    public function getQRCode($sessionId)
    {
        return $this->makeRequest('GET', '/auth/qr/' . $sessionId);
    }

    public function disconnectSession($sessionId)
    {
        return $this->makeRequest('POST', '/auth/disconnect', [
            'sessionId' => $sessionId
        ]);
    }

    public function logoutSession($sessionId)
    {
        return $this->makeRequest('POST', '/auth/logout', [
            'sessionId' => $sessionId
        ]);
    }

    public function getAllSessions()
    {
        return $this->makeRequest('GET', '/auth/sessions');
    }

    // Message endpoints
    public function sendTextMessage($sessionId, $to, $text, $options = [])
    {
        return $this->makeRequest('POST', '/message/send-text', [
            'sessionId' => $sessionId,
            'to' => $to,
            'text' => $text,
            'options' => $options
        ]);
    }

    public function sendMediaMessage($sessionId, $to, $media, $type, $options = [])
    {
        // For file uploads, we need to handle multipart/form-data
        // This is a simplified version - in production you might want to use a proper multipart implementation
        return $this->makeRequest('POST', '/message/send-media', [
            'sessionId' => $sessionId,
            'to' => $to,
            'type' => $type,
            'media' => base64_encode($media),
            'options' => $options
        ]);
    }

    public function sendLocation($sessionId, $to, $latitude, $longitude, $name = '', $address = '')
    {
        return $this->makeRequest('POST', '/message/send-location', [
            'sessionId' => $sessionId,
            'to' => $to,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address
        ]);
    }

    public function sendContact($sessionId, $to, $contacts)
    {
        return $this->makeRequest('POST', '/message/send-contact', [
            'sessionId' => $sessionId,
            'to' => $to,
            'contacts' => $contacts
        ]);
    }

    public function sendReaction($sessionId, $to, $messageId, $reaction)
    {
        return $this->makeRequest('POST', '/message/send-reaction', [
            'sessionId' => $sessionId,
            'to' => $to,
            'messageId' => $messageId,
            'reaction' => $reaction
        ]);
    }

    public function deleteMessage($sessionId, $to, $messageId)
    {
        return $this->makeRequest('POST', '/message/delete', [
            'sessionId' => $sessionId,
            'to' => $to,
            'messageId' => $messageId
        ]);
    }

    public function getMessageHistory($sessionId, $jid, $limit = 50)
    {
        return $this->makeRequest('GET', "/message/history/{$sessionId}/{$jid}?limit={$limit}");
    }

    // Contact endpoints
    public function getContacts($sessionId)
    {
        return $this->makeRequest('GET', '/contact/list/' . $sessionId);
    }

    public function getContactProfile($sessionId, $jid)
    {
        return $this->makeRequest('GET', "/contact/profile/{$sessionId}/{$jid}");
    }

    public function blockContact($sessionId, $jid)
    {
        return $this->makeRequest('POST', '/contact/block', [
            'sessionId' => $sessionId,
            'jid' => $jid
        ]);
    }

    public function unblockContact($sessionId, $jid)
    {
        return $this->makeRequest('POST', '/contact/unblock', [
            'sessionId' => $sessionId,
            'jid' => $jid
        ]);
    }

    // Group endpoints
    public function createGroup($sessionId, $subject, $participants)
    {
        return $this->makeRequest('POST', '/group/create', [
            'sessionId' => $sessionId,
            'subject' => $subject,
            'participants' => $participants
        ]);
    }

    public function getGroupInfo($sessionId, $groupId)
    {
        return $this->makeRequest('GET', "/group/info/{$sessionId}/{$groupId}");
    }

    public function addGroupParticipant($sessionId, $groupId, $participants)
    {
        return $this->makeRequest('POST', '/group/add-participant', [
            'sessionId' => $sessionId,
            'groupId' => $groupId,
            'participants' => $participants
        ]);
    }

    public function removeGroupParticipant($sessionId, $groupId, $participants)
    {
        return $this->makeRequest('POST', '/group/remove-participant', [
            'sessionId' => $sessionId,
            'groupId' => $groupId,
            'participants' => $participants
        ]);
    }

    public function leaveGroup($sessionId, $groupId)
    {
        return $this->makeRequest('POST', '/group/leave', [
            'sessionId' => $sessionId,
            'groupId' => $groupId
        ]);
    }

    // Webhook endpoints
    public function testWebhook($sessionId, $webhookUrl)
    {
        return $this->makeRequest('POST', '/webhook/test', [
            'sessionId' => $sessionId,
            'webhookUrl' => $webhookUrl
        ]);
    }

    public function getWebhookStats($sessionId)
    {
        return $this->makeRequest('GET', '/webhook/stats/' . $sessionId);
    }

    // Health check
    public function healthCheck()
    {
        return $this->makeRequest('GET', '/health');
    }

    public function getInfo()
    {
        return $this->makeRequest('GET', '/info');
    }
}
