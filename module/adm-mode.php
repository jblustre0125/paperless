<?php
require_once __DIR__ . "/../config/header.php";
require_once __DIR__ . "/../config/dbop.php";
$title = "Choose Mode";

$clientIp = getClientIp();
if ($clientIp == "::1") {
    $clientIp = '192.168.21.144';
    $_SESSION['ipAddress'] = $clientIp;
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {
    $mode = $_POST['mode'];
    $db1 = new DbOp(1);
    $leaderQuery = "SELECT TOP 1 * FROM GenHostname WHERE IpAddress = ? AND IsLeader = 1";
    $leaderRes = $db1->execute($leaderQuery, [$clientIp], 1);
    $isLeader = (!empty($leaderRes) && is_array($leaderRes) && isset($leaderRes[0]));

    if ($mode === 'operator') {
        if ($isLeader) {
            $errorMsg = 'isLeader is set to 1.';
        } else {
            $_SESSION['hostnameId'] = 150;
            $_SESSION['hostname'] = 'NBCP-TAB-150';
            $_SESSION['processId'] = 'NULL';
            $_SESSION['ipAddress'] = '192.168.21.144';
            $_SESSION['lineId'] = 130;
            $_SESSION['lineNumber'] = 'ATO15';
            $_SESSION['dorTypeId'] = 3;

            $updDevQry = "EXEC UpdGenLine @LineId=?, @IsLoggedIn=?";
            $resDevQry = $db1->execute($updDevQry, [$_SESSION['lineId'], 1], 1);

            $updDevQry2 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
            $resDevQry2 = $db1->execute($updDevQry2, [$_SESSION['hostnameId'], 1], 1);

            header("Location: dor-home.php");
            exit;
        }
    } elseif ($mode === 'leader') {
        if (!$isLeader) {
            $errorMsg = 'isLeader is set to 0.';
        } else {
            header("Location: ../leader/module/dor-leader-login.php");
            exit;
        }
    } elseif ($mode === 'common') {
        header("Location: adm-dashboard.php");
        exit;
    }
}

function getClientIp()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Default Title'; ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../css/index.css" />
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/master.css" rel="stylesheet">
    <script src="../js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="text-center mt-5">
        <h2 class="mb-4">Choose Mode</h2>
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger col-6 mx-auto" role="alert">
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>
        <form method="post" class="d-grid gap-3 col-6 mx-auto">
            <button type="submit" name="mode" value="operator" class="btn btn-primary btn-lg">Operator Mode</button>
            <button type="submit" name="mode" value="leader" class="btn btn-warning btn-lg">Leader Dashboard</button>
            <button type="submit" name="mode" value="common" class="btn btn-secondary btn-lg">Common Dashboard</button>
        </form>
    </div>
</body>

</html>
