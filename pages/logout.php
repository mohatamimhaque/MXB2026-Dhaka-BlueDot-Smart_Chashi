<?php
require_once __DIR__ . '/../config/config.php';

// Clear remember-me cookie and DB token
if (!empty($_COOKIE['sc_remember'])) {
    $parts = explode(':', $_COOKIE['sc_remember'], 2);
    if (count($parts) === 2 && isset($_SESSION['user_id'])) {
        try {
            $db = new Database();
            $db->query("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE user_id = ?")
               ->bind(1, (int)$_SESSION['user_id'])->execute();
        } catch (Exception $e) {
            error_log('Logout clear remember token: ' . $e->getMessage());
        }
    }
    setcookie('sc_remember', '', time() - 3600, APP_COOKIE_PATH);
}

// Clear all session variables
$_SESSION = array();

// Clear session cookie
if (ini_get("session.use_cookies")) {
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

// Destroy session
session_destroy();

// Redirect to home
header('Location: ' . $base_url);
exit;
?>
