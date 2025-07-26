<?php
// Allow direct access to manifest without PHP processing
if (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], 'manifest.webmanifest')) {
    exit;
}

require_once __DIR__ . "/config/dbop.php";
require_once __DIR__ . "/config/header.php";

$db1 = new DbOp(1);

$clientIp = getClientIp();
error_log("Detected client IP: $clientIp");
if ($clientIp == "::1") {
    $clientIp = '192.168.21.144';
    error_log("Localhost IP mapped to: $clientIp");
}

// Developer override via URL: index.php?dev
if (isset($_GET['dev'])) {
    error_log("Developer override detected, redirecting to adm-mode.php");
    header("Location: module/adm-mode.php");
    exit;
}

// Check if IP is registered in database
$query = "EXEC RdGenHostname @IpAddress=?, @IsLoggedIn=?, @IsActive=?";
$res = $db1->execute($query, [$clientIp, 0, 1], 1);
$row = $res[0];

if (empty($res) || !is_array($res) || !isset($res[0]) || !isset($res[0]["HostnameId"])) {
    error_log("IP not registered in database or HostnameId missing: $clientIp");
    echo '<h2>Device not registered or HostnameId missing. Please contact admin.</h2>';
    header("Location: module/adm-dashboard.php");
    exit;
}

// Fetch from GenHostname: IsLeader=1 for this IP (regardless of IsLoggedIn)
$leaderQuery = "SELECT TOP 1 * FROM GenHostname WHERE IpAddress = ? AND IsLeader = 1";
$leaderRes = $db1->execute($leaderQuery, [$clientIp], 1);
error_log('Leader query executed: ' . $leaderQuery . ' with IP=' . $clientIp);
error_log('Leader query result: ' . print_r($leaderRes, true));
if (is_array($leaderRes)) {
    error_log('Leader query row count: ' . count($leaderRes));
}

if (!empty($leaderRes) && is_array($leaderRes) && isset($leaderRes[0])) {
    $leaderRow = $leaderRes[0];
    $_SESSION['hostnameId'] = $leaderRow['HostnameId'];
    $_SESSION['hostname'] = $leaderRow['Hostname'];
    $_SESSION['processId'] = $leaderRow['ProcessId'];
    $_SESSION['ipAddress'] = $leaderRow['IpAddress'];

    $updLdQry = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $resldQry = $db1->execute($updLdQry, [$_SESSION['hostnameId'], 1], 1);

    error_log("Leader device detected from direct query. Redirecting to dor-leader-login.php. HostnameId: {$leaderRow['HostnameId']}, Hostname: {$leaderRow['Hostname']}");
    header("Location: leader/module/dor-leader-login.php");
    exit;
}

// IP is registered - check if its deployed to line
$query2 = "EXEC RdGenLine @HostnameId=?, @IsLoggedIn=?, @IsActive=?";
$res2 = $db1->execute($query2, [$row["HostnameId"], 0, 1], 1);

if (!empty($res2) && is_array($res2) && isset($res2[0]) && is_array($res2[0])) {
    $row2 = $res2[0];
    error_log("row2 contents: " . print_r($row2, true));
    $hostnameId = isset($row2['HostnameId']) ? $row2['HostnameId'] : $row['HostnameId'];
    $_SESSION['hostnameId'] = $hostnameId;
    $_SESSION['hostname'] = isset($row2["Hostname"]) ? $row2["Hostname"] : (isset($row["Hostname"]) ? $row["Hostname"] : null);
    $_SESSION['processId'] = isset($row2['ProcessId']) ? $row2['ProcessId'] : (isset($row['ProcessId']) ? $row['ProcessId'] : null);
    $_SESSION['ipAddress'] = isset($row2["IpAddress"]) ? $row2["IpAddress"] : (isset($row["IpAddress"]) ? $row["IpAddress"] : null);
    $_SESSION['lineId'] = isset($row2["LineId"]) ? $row2["LineId"] : null;
    $_SESSION['lineNumber'] = isset($row2["LineNumber"]) ? $row2["LineNumber"] : null;
    $_SESSION['dorTypeId'] = isset($row2["DorTypeId"]) ? $row2["DorTypeId"] : null;
    // Update isLoggedIn in GenLine and GenHostname
    if (isset($row2['LineId'])) {
        // Directly update GenLine.IsLoggedIn without stored procedure
        $query3 = "UPDATE GenLine SET IsLoggedIn = 1 WHERE LineId = ?";
        $res3 = $db1->execute($query3, [$row2['LineId']], 1);
        error_log('GenLine updated directly for LineId=' . $row2['LineId'] . ', IsLoggedIn=1, result: ' . print_r($res3, true));
    }
    $query4 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $res4 = $db1->execute($query4, [$hostnameId, 1], 1);
    error_log("Session set: hostnameId={$_SESSION['hostnameId']}, hostname={$_SESSION['hostname']}, processId={$_SESSION['processId']}, ipAddress={$_SESSION['ipAddress']}, lineId={$_SESSION['lineId']}, lineNumber={$_SESSION['lineNumber']}");
    header("Location: module/dor-home.php");
    exit;
} else {
    error_log("HostnameId {$row['HostnameId']} not deployed to line or IP not registered.");
    // Fallback: try to get hostname and hostnameId from RdGenHostname for leader or other device
    $query5 = "EXEC RdGenHostname @IpAddress=?, @IsLoggedIn=?";
    $res5 = $db1->execute($query5, [$clientIp, 0]);

    if (!empty($res5) && is_array($res5) && isset($res5[0]) && is_array($res5[0])) {
        $row5 = $res5[0];
        $_SESSION['hostnameId'] = isset($row5["HostnameId"]) ? $row5["HostnameId"] : null;
        $_SESSION['hostname'] = isset($row5["Hostname"]) ? $row5["Hostname"] : null;
        $_SESSION['processId'] = isset($row5['ProcessId']) ? $row5['ProcessId'] : null;
        $_SESSION['ipAddress'] = isset($row5["IpAddress"]) ? $row5["IpAddress"] : null;
        error_log("Fallback session set: hostnameId={$_SESSION['hostnameId']}, hostname={$_SESSION['hostname']}, processId={$_SESSION['processId']}, ipAddress={$_SESSION['ipAddress']}");
    } else {
        error_log("res5 is not an array or does not contain expected data.");
    }
}

function getClientIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'];
}
