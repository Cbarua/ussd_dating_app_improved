<?php

require_once __DIR__ . "/config.php";

define('AUTH_INACTIVITY_TIMEOUT', 1800); // 30 minutes in seconds
define('AUTH_REMEMBER_ME_LIFETIME', 30 * 86400); // 30 days in seconds

/**
 * Configure and start session securely.
 *
 * @param bool $remember
 */
function start_session_secure($remember = false) {
    if (session_status() === PHP_SESSION_NONE) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        
        $lifetime = $remember ? AUTH_REMEMBER_ME_LIFETIME : 0;

        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        session_start();
    }
}

/**
 * Check if the user is currently authenticated and active.
 *
 * @return bool
 */
function is_logged_in() {
    start_session_secure();

    if (empty($_SESSION['logged_in_user'])) {
        return false;
    }

    // Inactivity timeout check (applicable if "Remember Me" was not selected)
    $is_remembered = !empty($_SESSION['remember_me']);
    if (!$is_remembered && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > AUTH_INACTIVITY_TIMEOUT) {
            logout();
            return false;
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Redirect to login page if unauthenticated.
 */
function require_auth() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Authenticate username and password.
 *
 * @param string $username
 * @param string $password
 * @param bool $remember
 * @return bool
 */
function authenticate($username, $password, $remember = false) {
    $expected_user = app['dash_user'];
    $expected_pass = app['dash_pass'];

    $user_valid = hash_equals((string)$expected_user, (string)$username);
    $pass_valid = hash_equals((string)$expected_pass, (string)$password);

    if (!$user_valid || !$pass_valid) {
        // Rate-limiting delay against brute-force attacks
        sleep(1);
        return false;
    }

    // Start session with appropriate lifetime
    start_session_secure($remember);
    session_regenerate_id(true);

    $_SESSION['logged_in_user'] = $username;
    $_SESSION['remember_me'] = $remember ? true : false;
    $_SESSION['last_activity'] = time();

    if ($remember && !headers_sent()) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => time() + AUTH_REMEMBER_ME_LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    return true;
}

/**
 * Log out user and destroy session completely.
 */
function logout() {
    start_session_secure();
    $_SESSION = [];

    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
