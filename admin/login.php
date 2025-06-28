<?php

/**
 * WhatsApp Bridge - Login Page
 */

require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } elseif (login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

// Generate CSRF token
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .logo p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
        }

        .alert {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #393;
            border: 1px solid #cfc;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            color: #666;
            font-size: 0.8rem;
        }

        .default-credentials {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
        }

        .default-credentials h4 {
            color: #17a2b8;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .default-credentials p {
            font-size: 0.8rem;
            color: #666;
            margin: 0.25rem 0;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <h1><?php echo APP_NAME; ?></h1>
            <p>WhatsApp API Bridge Administration</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="default-credentials">
            <h4>Default Credentials</h4>
            <p><strong>Username:</strong> admin</p>
            <p><strong>Password:</strong> password</p>
            <p style="color: #e74c3c; font-weight: bold;">⚠️ Please change default password after first login!</p>
        </div>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></p>
            <p>Powered by Node.js WhatsApp API</p>
        </div>
    </div>
</body>

</html>