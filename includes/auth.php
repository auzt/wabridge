<?php

/**
 * WhatsApp Bridge - Authentication Functions
 */

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Login user
    public function login($username, $password)
    {
        try {
            $user = $this->db->fetch(
                "SELECT * FROM users WHERE username = ? AND status = 'active'",
                [$username]
            );

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['login_time'] = time();

                logInfo("User logged in", ['username' => $username]);
                return true;
            }

            logInfo("Failed login attempt", ['username' => $username]);
            return false;
        } catch (Exception $e) {
            logError("Login error", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Logout user
    public function logout()
    {
        $username = $_SESSION['username'] ?? 'unknown';
        session_destroy();
        logInfo("User logged out", ['username' => $username]);
    }

    // Check if user is logged in
    public function isLoggedIn()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }

        // Check session timeout
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }

        return true;
    }

    // Get current user
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name']
        ];
    }

    // Require login (redirect if not logged in)
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            // Deteksi apakah sedang di subfolder admin atau bukan
            $currentPath = $_SERVER['REQUEST_URI'];

            if (strpos($currentPath, '/admin/') !== false) {
                // Jika sudah di folder admin, redirect ke login.php
                header('Location: ../login.php');
            } else {
                // Jika di root atau folder lain, redirect ke admin/login.php
                header('Location: admin/login.php');
            }
            exit;
        }
    }

    // API Key Authentication
    public function validateApiKey($apiKey)
    {
        try {
            if (empty($apiKey)) {
                return false;
            }

            $device = $this->db->fetch(
                "SELECT d.*, u.username FROM devices d 
                 JOIN users u ON d.created_by = u.id 
                 WHERE d.api_key = ? AND d.status != 'inactive'",
                [$apiKey]
            );

            if ($device) {
                // Update last activity
                $this->db->execute(
                    "UPDATE devices SET last_activity = NOW() WHERE id = ?",
                    [$device['id']]
                );

                return $device;
            }

            return false;
        } catch (Exception $e) {
            logError("API key validation error", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Device Token Authentication
    public function validateDeviceToken($token)
    {
        try {
            if (empty($token)) {
                return false;
            }

            $device = $this->db->fetch(
                "SELECT * FROM devices WHERE device_token = ? AND status != 'inactive'",
                [$token]
            );

            return $device ?: false;
        } catch (Exception $e) {
            logError("Device token validation error", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Generate CSRF token
    public function generateCsrfToken()
    {
        $token = generateToken(CSRF_TOKEN_LENGTH);
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    // Validate CSRF token
    public function validateCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // Rate limiting for API
    public function checkRateLimit($identifier, $limit = API_RATE_LIMIT, $window = 60)
    {
        $key = "rate_limit:" . $identifier;
        $file = LOGS_PATH . "/" . $key . ".json";
        $now = time();

        $data = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true) ?: [];
        }

        // Clean old entries
        $data = array_filter($data, function ($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });

        // Check limit
        if (count($data) >= $limit) {
            return false;
        }

        // Add current request
        $data[] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return true;
    }

    // Create default admin user if not exists
    public function createDefaultAdmin()
    {
        try {
            $existingAdmin = $this->db->fetch("SELECT id FROM users WHERE username = 'admin'");

            if (!$existingAdmin) {
                $hashedPassword = password_hash('password', PASSWORD_DEFAULT);

                $this->db->execute(
                    "INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)",
                    ['admin', $hashedPassword, 'Administrator']
                );

                logInfo("Default admin user created", ['username' => 'admin']);
                return true;
            }

            return false;
        } catch (Exception $e) {
            logError("Error creating default admin", ['error' => $e->getMessage()]);
            return false;
        }
    }
}

// Helper functions for backward compatibility
function login($username, $password)
{
    $auth = new Auth();
    return $auth->login($username, $password);
}

function logout()
{
    $auth = new Auth();
    return $auth->logout();
}

function isLoggedIn()
{
    $auth = new Auth();
    return $auth->isLoggedIn();
}

function getCurrentUser()
{
    $auth = new Auth();
    return $auth->getCurrentUser();
}

function requireLogin()
{
    $auth = new Auth();
    return $auth->requireLogin();
}

function validateApiKey($apiKey)
{
    $auth = new Auth();
    return $auth->validateApiKey($apiKey);
}

function validateDeviceToken($token)
{
    $auth = new Auth();
    return $auth->validateDeviceToken($token);
}

function generateCsrfToken()
{
    $auth = new Auth();
    return $auth->generateCsrfToken();
}

function validateCsrfToken($token)
{
    $auth = new Auth();
    return $auth->validateCsrfToken($token);
}

function checkRateLimit($identifier, $limit = API_RATE_LIMIT)
{
    $auth = new Auth();
    return $auth->checkRateLimit($identifier, $limit);
}

// Create default admin on first run
$auth = new Auth();
$auth->createDefaultAdmin();
