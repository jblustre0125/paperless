<?php
// Allow direct access to manifest without PHP processing
if (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], 'manifest.webmanifest')) {
    exit;
}

require_once __DIR__ . "/config/dbop.php";
require_once __DIR__ . "/config/header.php";

$db1 = new DbOp(1);

$clientIp = $_SERVER['REMOTE_ADDR'];

// Developer override via URL: index.php?dev
if (isset($_GET['dev'])) {
    header("Location: module/adm-mode.php");
    exit;
}

// Check if IP is registered and active (indicates tablet device)
$query = "EXEC RdGenHostname @IpAddress=?, @IsActive=?, @IsLoggedIn=?";
$res = $db1->execute($query, [$clientIp, 1, 0], 1);

if (!empty($res)) {
    // Get the first row from the results array
    $row = $res[0];

    $_SESSION['hostnameId'] = $row["HostnameId"];
    $_SESSION['hostname'] = $row["Hostname"];
    $_SESSION['processId'] = $row['ProcessId'];
    $_SESSION['ipAddress'] = $row["IpAddress"];

    $updQry2 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $db1->execute($updQry2, [$row["HostnameId"], 1], 1);

    header("Location: module/dor-home.php");
    exit;
}

// Default: desktop/mobile/others
header("Location: module/adm-dashboard.php");
exit;
