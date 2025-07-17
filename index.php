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
    $clientIp = '192.168.21.145';
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

if (empty($res) || !is_array($res) || !isset($res[0]) || !isset($res[0]["HostnameId"])) {
    error_log("IP not registered in database or HostnameId missing: $clientIp");
    echo '<h2>Device not registered or HostnameId missing. Please contact admin.</h2>';
    header("Location: module/adm-dashboard.php");
    exit;
}

$row = $res[0];
error_log("IP registered. HostnameId: {$row['HostnameId']}, Hostname: {$row['Hostname']}, ProcessId: {$row['ProcessId']}, IpAddress: {$row['IpAddress']}");

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
        $query6 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
        if (isset($row5["HostnameId"])) {
            $res6 = $db1->execute($query6, [$row5["HostnameId"], 1], 1);
        }
    } else {
        error_log("res5 is not an array or does not contain expected data.");
    }
}


// IP is registered - check if it's a leader device
// $query2 = "EXEC RdGenHostname @IpAddress=?, @IsActive=?, @IsLoggedIn=?, @IsLeader=?";
// $res2 = $db1->execute($query2, [$clientIp, 1, 0, 1], 1);

// if (!empty($res2)) {
//     // Is leader device - redirect to leader login
//     $row2 = $res2[0];
//     $_SESSION['hostnameId'] = $row2["HostnameId"];
//     $_SESSION['hostname'] = $row2["Hostname"];
//     $_SESSION['processId'] = $row2['ProcessId'];
//     $_SESSION['ipAddress'] = $row2["IpAddress"];

//     $query3 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
//     $res3 = $db1->execute($query3, [$row2["HostnameId"], 1], 1);

//     $query4 = "EXEC RdGenLine @HostnameId=?, @IsActive=?, @IsLoggedIn=?";
//     $res4 = $db1->execute($query3, [$row2["HostnameId"], 1, 0], 1);

//     if (!empty($res4)) {
//         $row4 = $res4[0];
//         $_SESSION['lineId'] = $row3["LineId"];
//         $_SESSION['lineNumber'] = $row3["LineNumber"];

//         $query4 = "EXEC UpdGenLine @LineId=?, @IsLoggedIn =?";
//         $res4 = $db1->execute($query4, [$row3["LineId"], 1], 1);

//         header("Location: leader/module/dor-leader-login.php");
//         exit;
//     }
// } else {
//     // Not leader device - redirect to regular DOR home
//     $query5 = "EXEC RdGenHostname @IpAddress=?, @IsLoggedIn=?";
//     $res5 = $db1->execute($query5, [$clientIp, 0]);

//     if (!empty($res5)) {
//         $row5 = $res5[0];
//         $_SESSION['hostnameId'] = $row5["HostnameId"];
//         $_SESSION['hostname'] = $row5["Hostname"];
//         $_SESSION['processId'] = $row5['ProcessId'];
//         $_SESSION['ipAddress'] = $row5["IpAddress"];

//         $query6 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
//         $res6 = $db1->execute($query6, [$row5["HostnameId"], 1], 1);
//     }

//     // $query5 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
//     // $res5 = $db1->execute($query5, [$row["HostnameId"], 1], 1);

//     // $query6 = "EXEC RdGenLine @HostnameId=?, @IsActive=?, @IsLoggedIn=?";
//     // $res6 = $db1->execute($query6, [$row["HostnameId"], 1, 0], 1);

//     // if (!empty($res6)) {
//     //     $row6 = $res6[0];
//     //     $_SESSION['lineId'] = $row6["LineId"];
//     //     $_SESSION['lineNumber'] = $row6["LineNumber"];

//     //     $query7 = "EXEC UpdGenLine @LineId=?, @IsLoggedIn=?";
//     //     $res7 = $db1->execute($query7, [$row6["LineId"], 1], 1);

//     //     header("Location: module/dor-home.php");
//     //     exit;
//     // }
// }

function getClientIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'];
}
