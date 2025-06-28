<?php

/**
 * WhatsApp Bridge - Webhook Receiver
 * Receives webhooks from Node.js WhatsApp API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Event-Type, User-Agent');

require_once __DIR__ . '/../includes/webhook_handler.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Process webhook
processWebhook();
