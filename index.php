<?php
// Allow direct access to manifest without PHP processing
if (isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], 'manifest.webmanifest')) {
    exit;
}

require_once __DIR__ . "/config/dbop.php";
require_once __DIR__ . "/config/header.php";

$db1 = new DbOp(1);

$clientIp = getClientIp();

if ($clientIp == "::1") {
    $clientIp = '192.168.21.144';
}

// Developer override via URL: index.php?dev
if (isset($_GET['dev'])) {
    header("Location: module/adm-mode.php");
    exit;
}

// Check if IP is registered in database
$query = "EXEC RdGenHostname @IpAddress=?, @IsLoggedIn=?, @IsActive=?";
$res = $db1->execute($query, [$clientIp, 0, 1], 1);

if (empty($res)) {
    // IP not registered - redirect to admin dashboard
    header("Location: module/adm-dashboard.php");
    exit;
}

$row = $res[0];

// IP is registered - check if its deployed to line
$query2 = "EXEC RdGenLine @HostnameId=?, @IsLoggedIn=?, @IsActive=?";
$res2 = $db1->execute($query2, [$res["HostnameId"], 0, 1], 1);

if (!empty($res2)) {
    $row2 = $res2[0];

    $query3 = "EXEC UpdGenLine @HostnameId=?, @IsLoggedIn=?";
    $res3 = $db1->execute($query3, [$res["HostnameId"], 1]);

    $query4 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $res4 = $db1->execute($query4, [$res["HostnameId"], 1]);

    $_SESSION['hostnameId'] = $row2["HostnameId"];
    $_SESSION['hostname'] = $row2["Hostname"];
    $_SESSION['processId'] = $row2['ProcessId'];
    $_SESSION['ipAddress'] = $row2["IpAddress"];

    $_SESSION['lineId'] = $row2["LineId"];
    $_SESSION['lineNumber'] = $row2["LineNumber"];

    header("Location: module/dor-home.php");
    exit;
} else {
    // Either not deployed to line or IP not registered, check if leader tablet
    // $query5 = "EXEC RdGenHostname @IpAddress=?, @IsLoggedIn=?, @IsActive=?, @IsLeader=?";
    // $res5 = $db1->execute($query5, [$clientIp, 0, 1, 1], 1);

    // if (!empty($res5)) {
    //     $row5 = $res5[0];

    //     $query6 = "EXEC UpdGenLine @HostnameId=?, @IsLoggedIn=?";
    //     $res6 = $db1->execute($query6, [$row5["HostnameId"], 1], 1);

    //     $query7 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    //     $res7 = $db1->execute($query7, [$row5["HostnameId"], 1], 1);

    //     $_SESSION['hostnameId'] = $row2["HostnameId"];
    //     $_SESSION['hostname'] = $row2["Hostname"];
    //     $_SESSION['processId'] = $row2['ProcessId'];
    //     $_SESSION['ipAddress'] = $row2["IpAddress"];

    //     $_SESSION['lineId'] = $row2["LineId"];
    //     $_SESSION['lineNumber'] = $row2["LineNumber"];

    //     header("Location: module/dor-home.php");
    //     exit;
    // } else {
    //     header("Location: module/adm-dashboard.php");
    //     exit;
    // }
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
