<?php
ob_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Secure session start
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require_once '../../config/dbop.php';

$db = new DbOp(1);

// Check if this is an exit application request
if (isset($_GET['exit'])) {
    // Update the tablet's logged in status to 0
    if (isset($_SESSION['hostnameId']) && !empty($_SESSION['hostnameId'])) {
        $updateQuery = "UPDATE GenHostname SET IsLoggedIn = 0 WHERE HostnameId = ?";
        $db->execute($updateQuery, [$_SESSION['hostnameId']]);
    }

    // Update the operator's logged in status to 0
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        $updateQuery = "UPDATE GenOperator SET IsLoggedIn = 0 WHERE OperatorId = ?";
        $db->execute($updateQuery, [$_SESSION['user_id']]);
    }

    // Clear all session data
    session_unset();
    session_destroy();

    // Send HTML response to close the window
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Closing Application</title>
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f8f9fa;
                font-family: Arial, sans-serif;
            }
            .message {
                text-align: center;
                padding: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>Application Closed</h2>
            <p>Please close this window manually.</p>
        </div>
        <script>
            try {
                if (window.AndroidApp && AndroidApp.exitApp) {
                    AndroidApp.exitApp(); // This will call the Android code
                } else {
                    alert("Please close this window manually.");
                }
            } catch (e) {
                alert("Please close this window manually.");
            }
        </script>
    </body>
    </html>';
    exit;
}

// Check if user is logged in and has hostnameId
if (isset($_SESSION['hostnameId']) && !empty($_SESSION['hostnameId'])) {
    // Update GenHostname table to set IsLoggedIn = 0
    $updateQuery = "UPDATE GenHostname SET IsLoggedIn = 0 WHERE HostnameId = ?";
    $db->execute($updateQuery, [$_SESSION['hostnameId']]);
}

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $updateQuery = "UPDATE GenOperator SET IsLoggedIn = 0 WHERE OperatorId = ?";
    $db->execute($updateQuery, [$_SESSION['user_id']]);
}

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page
header('Location: ../module/dor-leader-login.php');
exit();
