<?php
ob_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Start session
session_start();

require_once '../../config/dbop.php';

// Get client IP
function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'];
}

// Ensure POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['btnLogin'])) {
    header('Location: ../module/dor-leader-login.php');
    exit;
}

$db = new DbOp(1);
$ip = getUserIP();

$productionCode = isset($_POST['production_code']) ? strtoupper(trim($_POST['production_code'])) : '';
$error = '';

// Check IP first in database
$hostQuery2 = "SELECT HostnameId, Hostname, IsLoggedIn, IsActive, IsLeader FROM GenHostname WHERE IpAddress = ?";
$hostData2 = $db->execute($hostQuery2, [$ip], 1);

// If IP not found in database, use gethostname() for dev workstation
if (empty($hostData2)) {
    $currentHostname = gethostname();
    if ($currentHostname === 'NBCP-LT-144') {
        $hostQuery = "SELECT HostnameId, Hostname, IsLoggedIn, IsActive, IsLeader FROM GenHostname WHERE Hostname = 'NBCP-TAB-005'";
        $hostData = $db->execute($hostQuery);
    } elseif ($currentHostname === 'NBCP-LT-145') {
        $hostQuery = "SELECT HostnameId, Hostname, IsLoggedIn, IsActive, IsLeader FROM GenHostname WHERE Hostname = 'NBCP-TAB-006'";
        $hostData = $db->execute($hostQuery);
    } else {
        // Hostname is neither NBCP-LT-144 nor NBCP-LT-145 - throw error
        $error = "Unauthorized device: " . htmlspecialchars($currentHostname);
    }
} else {
    $hostQuery = "SELECT HostnameId, Hostname, IsLoggedIn, IsActive, IsLeader FROM GenHostname WHERE IpAddress = ?";
    $hostData = $db->execute($hostQuery, [$ip], 1);
}
$hostQuery = "SELECT HostnameId, Hostname, IsLoggedIn, IsActive FROM GenHostname WHERE IPAddress = ?";
    $hostData = $db->execute($hostQuery, [$ip]);
//Validate tablet data
if (empty($hostData)) {
    $error = "Tablet not registered with IP: " . htmlspecialchars($ip);
} elseif ((int)$hostData[0]['IsActive'] !== 1) {
    $error = "Tablet is inactive.";
}

//Authenticate leader (only if tablet is valid)
if (empty($error)) {
    $userQuery = "
        SELECT OperatorId, ProductionCode, EmployeeCode, EmployeeName, IsLeader, IsSrLeader, IsActive 
        FROM GenOperator 
        WHERE ProductionCode = ? AND (IsLeader = 1 OR IsSrLeader = 1)
    ";
    $userData = $db->execute($userQuery, [$productionCode], 1);

    if (empty($userData)) {
        $error = "Invalid production code.";
    } elseif ((int)$userData[0]['IsActive'] !== 1) {
        $error = "Account is deactivated. Please contact IT or Production Supervisor.";
    } else {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userData[0]['OperatorId'];
        $_SESSION['production_code'] = $userData[0]['ProductionCode'];
        $_SESSION['employee_code'] = $userData[0]['EmployeeCode'];
        $_SESSION['employee_name'] = $userData[0]['EmployeeName'];
        $_SESSION['is_leader'] = $userData[0]['IsLeader'];
        $_SESSION['is_sr_leader'] = $userData[0]['IsSrLeader'];
        $_SESSION['hostnameId'] = $hostData[0]['HostnameId'];
        $_SESSION['hostname'] = $hostData[0]['Hostname'];

        // Mark tablet as logged in
        $updateQuery = "UPDATE GenHostname SET IsLoggedIn = 1 WHERE HostnameId = ?";
        $db->execute($updateQuery, [$hostData[0]['HostnameId']]);

        // Update operator login status
        $updateOperatorQuery = "UPDATE GenOperator SET IsLoggedIn = 1 WHERE OperatorId = ?";
        $db->execute($updateOperatorQuery, [$userData[0]['OperatorId']]);

        // Clear any output buffer before redirect
        ob_end_clean();
        header('Location: ../module/dor-leader-dashboard.php');
        exit();
    }
}

//Login failed
session_unset();
session_destroy();
session_start();
$_SESSION['login_error'] = $error;
header('Location: ../module/dor-leader-login.php');
exit();
