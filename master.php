<?php
require('header.php');

if (isset($_GET['logout'])) {
    logout();
}

function logout()
{
    $updQry = "EXEC UpdGenOperator @OperatorId=?, @IsLoggedIn=?";
    $prms = [$_SESSION['operatorId'], 0];
    execQuery(1, 2, $updQry, $prms);
    header('Location: logout.php');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Default Title'; ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="home.php">
                <img src="img/nbc.jpg" alt="nbc-logo" width="70" height="35">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="home.php">Daily Operation Report</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="home.php">Item 2</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown-center">
                        <button class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= testInput($_SESSION['employeeName']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-lg-end">
                            <!-- https://stackoverflow.com/questions/8662535/trigger-php-function-by-clicking-html-link -->
                            <li><a class="dropdown-item" href="?logout">Log Out</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- main content will be added where the master page is included -->
        <?php echo $content ?? ''; ?>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php require_once 'config/footer.php'; ?>