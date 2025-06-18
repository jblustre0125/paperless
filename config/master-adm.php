<?php
require_once "../config/header.php";
$db1 = new DbOp(1);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Admin Dashboard'; ?></title>
    <!-- <link href="../css/bootstrap.min.css" rel="stylesheet"> -->
     <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>    
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
                    <li class="nav-item"><a class="nav-link fs-5" href="dor-dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link fs-5" href="admin-lines.php">Line Management</a></li>
                    <li class="nav-item"><a class="nav-link fs-5" href="admin-checkpoints.php">Checkpoint Setup</a></li>
                    <li class="nav-item"><a class="nav-link fs-5" href="admin-users.php">User Access</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fs-5" href="#" id="reportDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reports</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="report-downtime.php">Downtime Report</a></li>
                            <li><a class="dropdown-item" href="report-ng.php">NG Summary</a></li>
                            <li><a class="dropdown-item" href="report-output.php">Production Output</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-lg mt-4">
        <?php echo $content ?? ''; ?>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php require_once '../config/footer.php'; ?>