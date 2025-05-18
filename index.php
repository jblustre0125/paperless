<?php
require_once "config/dbop.php";

$db1 = new DbOp(1);
$clientIp = $_SERVER['REMOTE_ADDR'];

// Developer override via URL: index.php?dev
if (isset($_GET['dev'])) {
    header("Location: module/adm-mode.php");
    exit;
}

// Check if IP is registered and active (indicates tablet device)
$query = "SELECT TOP 1 HostnameId FROM GenHostname WHERE IpAddress = ? AND IsActive = 1";
$result = $db1->execute($query, [$clientIp], 1);

if ($result) {
    header("Location: module/dor-login.php");
    exit;
}

// Default: desktop/mobile/others
header("Location: module/adm-dashboard.php");
exit;
