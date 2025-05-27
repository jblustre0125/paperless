<?php
session_status() === PHP_SESSION_ACTIVE ?: session_start();
require_once "../config/method.php";

$db1 = new DbOp(1);

// Resolve hostnameId and hostname if missing
if (!isset($_SESSION['hostnameId'])) {
    $clientIp = $_SERVER['REMOTE_ADDR'];
    $res = $db1->execute("SELECT TOP 1 HostnameId FROM GenHostname WHERE IpAddress = ? AND IsActive = 1", [$clientIp], 1);
    $_SESSION['hostnameId'] = $res[0]['HostnameId'] ?? 0;
    $_SESSION['hostname'] = $res[0]['Hostname'] ?? '-';
}

// Open-access pages
$openPages = ['adm-mode.php', 'adm-dashboard.php', 'dor-login.php'];
$currentFile = basename($_SERVER['SCRIPT_NAME']);

// Restrict other pages
if (!in_array($currentFile, $openPages)) {
    if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
        header("Location: ../index.php");
        exit;
    }
}
