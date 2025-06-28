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

    public function __construct($apiKey = 'your_secure_api_key_here_change_this')
    {
        $this->baseUrl = NODE_API_URL;
        $this->timeout = NODE_API_TIMEOUT;
        $this->apiKey = $apiKey;
    }

    // PERBAIKAN: Method harus PUBLIC agar bisa diakses dari WhatsAppApiClient
    public function makeRequest($method, $endpoint, $data = null, $headers = array())
    {
        $url = $this->baseUrl . $endpoint;

        $defaultHeaders = array(
            'Content-Type: application/json',
            'Accept: application/json'
        );

        if ($this->apiKey) {
            $defaultHeaders[] = 'x-api-key: ' . $this->apiKey;
        }

        $headers = array_merge($defaultHeaders, $headers);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ));

        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
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

        return array(
            'status_code' => $httpCode,
            'data' => $decodedResponse,
            'raw' => $response
        );
    }

    // Authentication endpoints
    public function createSession($sessionId, $config = array())
    {
        return $this->makeRequest('POST', '/auth/create-session', array(
            'sessionId' => $sessionId,
            'config' => $config
        ));
    }

    public function connectSession($sessionId)
    {
        return $this->makeRequest('POST', '/auth/connect', array(
            'sessionId' => $sessionId
        ));
    }

    public function getSessionStatus($sessionId)
    {
        return $this->makeRequest('GET', '/auth/status/' . $sessionId);
    }

    public function getQRCode($sessionId)
    {
        return $this->makeRequest('GET', '/auth/qr/' . $sessionId);
    }

    // PERBAIKAN: Tambahkan method disconnect
    public function disconnectSession($sessionId)
    {
        return $this->makeRequest('POST', '/auth/disconnect', array(
            'sessionId' => $sessionId
        ));
    }

    // Logout session (delete)
    public function logoutSession($sessionId)
    {
        return $this->makeRequest('POST', '/auth/logout', array(
            'sessionId' => $sessionId
        ));
    }

    // Get all sessions
    public function getAllSessions()
    {
        return $this->makeRequest('GET', '/auth/sessions');
    }

    // Message endpoints
    public function sendTextMessage($sessionId, $to, $text, $options = array())
    {
        return $this->makeRequest('POST', '/message/text', array(
            'sessionId' => $sessionId,
            'to' => $to,
            'text' => $text,
            'options' => $options
        ));
    }

    public function sendMediaMessage($sessionId, $to, $media, $caption = '', $type = 'image')
    {
        return $this->makeRequest('POST', '/message/media', array(
            'sessionId' => $sessionId,
            'to' => $to,
            'media' => $media,
            'caption' => $caption,
            'type' => $type
        ));
    }

    public function sendLocationMessage($sessionId, $to, $latitude, $longitude, $name = '', $address = '')
    {
        return $this->makeRequest('POST', '/message/location', array(
            'sessionId' => $sessionId,
            'to' => $to,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address
        ));
    }

    public function sendContactMessage($sessionId, $to, $contact)
    {
        return $this->makeRequest('POST', '/message/contact', array(
            'sessionId' => $sessionId,
            'to' => $to,
            'contact' => $contact
        ));
    }

    // Health check
    public function healthCheck()
    {
        return $this->makeRequest('GET', '/status');
    }
}
