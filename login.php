<?php

require_once __DIR__ . "/app/auth.php";

// If already logged in, redirect directly to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$logged_out_msg = '';

if (isset($_GET['logged_out'])) {
    $logged_out_msg = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $remember = !empty($_POST['remember']);

    if (authenticate($username, $password, $remember)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(app['app_name']); ?> - Login</title>
    <style>
        body {
            font-family: monospace;
            line-height: 1.5;
            background-color: #f8f8f8;
            margin: 0;
            padding: 40px 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
        }

        .login-box {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 25px 30px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4rem;
            text-align: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 12px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            font-family: monospace;
            font-size: 0.95rem;
            background: #fff;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #333;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            font-size: 0.85rem;
        }

        .checkbox-group input {
            margin: 0;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #333;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: monospace;
            font-size: 1rem;
            font-weight: bold;
        }

        button:hover {
            background-color: #555;
        }

        .alert {
            padding: 8px 12px;
            margin-bottom: 15px;
            border: 1px solid;
            font-size: 0.85rem;
        }

        .alert-error {
            background-color: #ffebee;
            border-color: #ffcdd2;
            color: #c62828;
        }

        .alert-info {
            background-color: #e8f5e9;
            border-color: #c8e6c9;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2><?php echo ucfirst(htmlspecialchars(app['app_name'])); ?> Dashboard</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($logged_out_msg)): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($logged_out_msg); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember" style="font-weight: normal; margin-bottom: 0;">Remember me for 30 days</label>
            </div>

            <button type="submit">Log In</button>
        </form>
    </div>
</body>
</html>
