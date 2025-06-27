<?php

require_once '../../config/dbop.php';
require_once '../controller/dor-leader-method.php';

// Secure session start
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

// Disable caching
header_remove('Pragma');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Expires: 0");

// Store hostnameId before destroying session
$hostnameId = $_SESSION['hostnameId'] ?? null;

// Destroy session securely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// 🛠️ Update tablet status after session destruction (but using stored value)
$updateSuccess = false;
if ($hostnameId !== null) {
    try {
        $method = new Method(1);
        $updateSuccess = $method->updateTabletStatus($hostnameId, 0);
        if (!$updateSuccess) {
            error_log("Failed to update tablet status for HostnameId = $hostnameId");
        }
    } catch (Exception $e) {
        error_log("Tablet status update error: " . $e->getMessage());
    }
} else {
    error_log("HostnameId missing during logout");
}

// 🚪 Logout redirect page
?>
<!DOCTYPE html>
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

</html>