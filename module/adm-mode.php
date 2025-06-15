<?php
require_once __DIR__ . "/../config/header.php";
require_once __DIR__ . "/../config/dbop.php";
$title = "Choose Mode";

// Set hostname for operator mode
if (isset($_GET['mode']) && $_GET['mode'] === 'operator') {
    $db1 = new DbOp(1);

    // Set session variables
    $_SESSION['hostname'] = 'NBCP-TAB-001';
    $_SESSION['hostnameId'] = 1; // Assuming this is the ID for NBCP-TAB-001

    // Update IsLoggedIn status in database
    $updQry2 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $db1->execute($updQry2, [$_SESSION['hostnameId'], 1], 1);

    header('Location: dor-home.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Default Title'; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/master.css" rel="stylesheet">
</head>

<body>
    <div class="text-center mt-5">
        <h2 class="mb-4">Choose Mode</h2>
        <div class="d-grid gap-3 col-6 mx-auto">
            <a href="?mode=operator" class="btn btn-outline-primary btn-lg">Operator Mode</a>
            <a href="adm-dashboard.php" class="btn btn-outline-secondary btn-lg">Admin Dashboard</a>
        </div>
    </div>
</body>

</html>