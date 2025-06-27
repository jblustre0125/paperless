<?php
require_once '../../config/dbop.php';
require_once '../controller/dor-leader-method.php';

session_start();

// Update tablet status if set
if (isset($_SESSION['hostnameId'])) {
    try {
        $method = new Method(1);
        $method->updateTabletStatus($_SESSION['hostnameId'], 0); // Mark as logged out
    } catch (Exception $e) {
        error_log('Tablet status update failed: ' . $e->getMessage());
    }
}

// Destroy session securely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login page
header('Location: dor-leader-login.php');
exit;
