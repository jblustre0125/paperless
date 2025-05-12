<?php
require_once "../config/header.php";
$title = "Choose Mode";
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
            <a href="dor-login.php" class="btn btn-outline-primary btn-lg">Operator Mode</a>
            <a href="adm-dashboard.php" class="btn btn-outline-secondary btn-lg">Admin Dashboard</a>
        </div>
    </div>
</body>

</html>