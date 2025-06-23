<?php

require_once '../../config/dbop.php';
require_once '../controller/method.php';

// Secure session start with headers
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Immediately set anti-caching headers
header_remove('Pragma');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Expires: 0");


// Tablet status update with error handling
$updateSuccess = false;
if (isset($_SESSION['hostnameId'])) {
    try {
        $method = new Method(1);
        if ($method->updateTabletStatus($_SESSION['hostnameId'], 0)) {
            $updateSuccess = true;
        }
    } catch (Exception $e) {
        error_log('Tablet status update error: ' . $e->getMessage());
    }
}

// Complete session destruction
$_SESSION = [];
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
session_destroy();

// JavaScript-assisted redirect for better back-button prevention
echo '<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
    <script>
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        window.location.replace("../../module/dor-login.php");
    </script>
    <noscript>
        <meta http-equiv="refresh" content="0;url=../../module/dor-login.php">
    </noscript>
</head>
<body>
    <p>Logging out... Please wait.</p>
    <p>If not redirected, <a href="../../module/dor-login.php">click here</a>.</p>
</body>
</html>';

exit();