<?php
require_once "../config/header.php";
$db1 = new DbOp(1);

if (isset($_GET['logOut'])) {
    echo $_SESSION['operatorId'];
    $updQry = "EXEC UpdGenOperator @OperatorId=?, @IsLoggedIn=?";
    $db1->execute($updQry, [$_SESSION['operatorId'], 0], 1);
    header('Location: ../logout.php');
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-lg">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active fs-5" href="dor-home.php">Daily Operation Record</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fs-5" href="#" id="reportDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Reports
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Downtime Report</a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Device Name Styled & Aligned -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link text-info fw-bold">
                            <?= isset($_SESSION['deviceName']) ? testInput($_SESSION['deviceName']) : '—'; ?>
                        </span>
                    </li>

                    <!-- Employee Dropdown (Styled & Aligned) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fs-5" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <?= isset($_SESSION['employeeName']) ? testInput($_SESSION['employeeName']) : 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger fw-bold" href="?logOut">Log Out</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-lg mt-4">
        <!-- Main content will be added here -->
        <?php echo $content ?? ''; ?>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php require_once '../config/footer.php'; ?>