<?php
// Allow direct access to manifest without PHP processing
if (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], 'manifest.webmanifest')) {
    exit;
}

require_once __DIR__ . "/config/dbop.php";
require_once __DIR__ . "/config/header.php";

$db1 = new DbOp(1);

$clientIp = getClientIp();

// Developer override via URL: index.php?dev
if (isset($_GET['dev'])) {
    header("Location: module/adm-mode.php");
    exit;
}

// Check if IP is registered in database
$query = "EXEC RdGenHostname @IpAddress=?, @IsActive=?, @IsLoggedIn=?";
$res = $db1->execute($query, [$clientIp, 1, 0], 1);

if (empty($res)) {
    // IP not registered - redirect to admin dashboard
    header("Location: module/adm-dashboard.php");
    exit;
}

// IP is registered - check if it's a leader device
$query2 = "EXEC RdGenHostname @IpAddress=?, @IsActive=?, @IsLoggedIn=?, @IsLeader=?";
$res2 = $db1->execute($query2, [$clientIp, 1, 0, 1], 1);

if (!empty($res2)) {
    // Is leader device - redirect to leader login
    $row2 = $res2[0];

    $_SESSION['hostnameId'] = $row2["HostnameId"];
    $_SESSION['hostname'] = $row2["Hostname"];
    $_SESSION['processId'] = $row2['ProcessId'];
    $_SESSION['ipAddress'] = $row2["IpAddress"];

    header("Location: leader/module/dor-leader-login.php");
    exit;
} else {
    // Not leader device - redirect to regular DOR home
    $row = $res[0];

    $_SESSION['hostnameId'] = $row["HostnameId"];
    $_SESSION['hostname'] = $row["Hostname"];
    $_SESSION['processId'] = $row['ProcessId'];
    $_SESSION['ipAddress'] = $row["IpAddress"];

    $updQry3 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $db1->execute($updQry3, [$row["HostnameId"], 1], 1);

    header("Location: module/dor-home.php");
    exit;
}

function getClientIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'];
}
