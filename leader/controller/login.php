<?php 
ob_start();
session_start();
require_once '../../config/dbop.php';

// Get user IP
function getUserIP(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    else return $_SERVER['REMOTE_ADDR'];
}

$ip = getUserIP();
$employeeCode = isset($_POST['employee_code']) ? trim($_POST['employee_code']) : '';

if(isset($_POST['btnLogin'])){
    $db = new DbOp(1);
    $error = '';

    // 1. First check tablet/hostname status
    $hostQuery = "SELECT HostnameId, Hostname, IsLoggedin, IsActive FROM GenHostname WHERE IPAddress = ?";
    $hostData = $db->execute($hostQuery, [$ip]);

    if(empty($hostData)){
        $error = "Tablet not registered with IP: $ip";
    } 
    elseif($hostData[0]['IsActive'] != 1){
        $error = "Tablet is inactive";
    }
    elseif($hostData[0]['IsLogin'] == 1){
        $error = "Tablet is already logged in";
    }

    // Only proceed with user auth if tablet checks pass
    if(empty($error)){
        $userQuery = "SELECT OperatorId, EmployeeCode, EmployeeName, IsLeader, IsSrLeader, IsActive 
                     FROM GenOperator 
                     WHERE LTRIM(RTRIM(EmployeeCode)) = ?";
        $userData = $db->execute($userQuery, [$employeeCode]);

        if(empty($userData)){
            $error = "Employee ID [$employeeCode] not found";
        }
        elseif($userData[0]['IsActive'] != 1){
            $error = "Your account is inactive";
        }
        elseif($userData[0]['IsLeader'] == 1 || $userData[0]['IsSrLeader'] == 1){
            // All checks passed - log them in
            $_SESSION['employee_code'] = $userData[0]['EmployeeCode'];
            $_SESSION['employee_name'] = $userData[0]['EmployeeName'];
            $_SESSION['is_leader'] = $userData[0]['IsLeader'];
            $_SESSION['is_sr_leader'] = $userData[0]['IsSrLeader'];
            $_SESSION['hostnameId'] = $hostData[0]['HostnameId'];
            $_SESSION['hostname'] = $hostData[0]['Hostname'];
            
            // Update tablet login status
            $updateQuery = "UPDATE GenHostname SET IsLogin = 1 WHERE HostnameId = ?";
            $db->execute($updateQuery, [$hostData[0]['HostnameId']]);
            
            header('Location: ../../leader/module/dor-leader-dashboard.php');
            exit();
        }
        else {
            $error = "You don't have leader privileges";
        }
    }

    // If we get here, there was an error
    $_SESSION['login_error'] = $error;
    header('Location: ../../module/dor-login.php');
    exit();
}