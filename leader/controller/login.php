<?php 
ob_start();
session_start();
require_once '../../config/dbop.php';


//get user ip
function getUserIP(){
        if(!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
        else return $_SERVER['REMOTE_ADDR'];
}

$ip = getUserIP();

$employeeCode = $_POST['employee_code'] ?? '';

if(isset($_POST['btnLogin'])){
    $db = new DbOp(1);

    $hostQuery = "SELECT Hostname, IsActive FROM GenHostname WHERE IPAddress = ?";
    $hostData = $db->execute($hostQuery, [$ip]);

    if(!$hostData || $hostData[0]['IsActive'] != 1){
        header('Location: ../../module/dor-login.php');
    }

//selecting leader and sr leader from the GenOperator
$userQuery = "SELECT OperatorId, EmployeeCode, EmployeeName, IsLeader, IsSrLeader, IsActive FROM GenOperator WHERE LTRIM(RTRIM(EmployeeCode)) = ?";

$userData = $db->execute($userQuery, [$employeeCode]);

if($userData && count($userData) > 0){
    $user = $userData[0];

    if($user['IsActive'] != 1){
        $errorPrompt = "Your account is inactive.";
    }elseif($user['IsLeader'] == 1 || $user['IsSrLeader'] == 1){
        $_SESSION['employee_code'] = $user['EmployeeCode'];
        $_SESSION['is_leader'] = $user['IsLeader'];
        $_SESSION['is_sr_leader'] = $user['IsSrLeader'];

        header('Location: ../../leader/module/dor-leader-dashboard.php');
        exit();
    }else{
        header('Location: ../../module/dor-login.php');
    }
    }else{
    echo "Employee ID [$employeeCode] is not found.";
    }
}
?>