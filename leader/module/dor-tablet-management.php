<?php
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();

    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../config/header.php";
require_once __DIR__ . "/../../config/dbop.php";
require_once "../controller/dor-leader-method.php";

$title = "Tablet Management";
$method = new Method(1);

// Check authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    header('Location: dor-leader-login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="../../img/dor-1024.png">
    <link href="../css/leader-dashboard.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
        <div class="container-lg">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link fs-5" href="dor-leader-dashboard.php">DOR System</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active fs-5" href="#" id="masterDataDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Master Data
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../leader/module/dor-model.php">
                                    <i class="bi bi-diagram-3"></i> Model
                                </a></li>
                            <li><a class="dropdown-item" href="../../leader/module/dor-user.php">
                                    <i class="bi bi-person"></i> User
                                </a></li>
                            <li><a class="dropdown-item" href="../../leader/module/dor-line.php">
                                    <i class="bi bi-tablet"></i> Line
                                </a></li>
                            <li><a class="dropdown-item active" href="../../leader/module/dor-tablet-management.php">
                                    <i class="bi bi-tablet"></i> Tablet
                                </a></li>
                        </ul>
                    </li>
                </ul>

                <!-- Device Name Display -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <?php
                        // Get current tablet info
                        $currentTablet = isset($_SESSION['hostnameId']) ? $method->getCurrentTablet($_SESSION['hostnameId']) : null;
                        $tabletName = $currentTablet ? htmlspecialchars($currentTablet['Hostname']) : 'Tablet Name';
                        ?>
                        <a class="nav-link dropdown-toggle fw-bold" href="#" id="deviceDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-tablet"></i> <?= $tabletName ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php" onclick="exitApplication(event)">
                                    <i class="bi bi-box-arrow-right"></i> Exit Application
                                </a>
                            </li>
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Log Out
                                </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h4><i class="bi bi-tablet"></i> Tablet Management</h4>
        <p>Tablet management functionality will be implemented here.</p>
    </div>

    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script>
        function exitApplication(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to exit the application?')) {
                window.location.href = '../controller/dor-leader-logout.php?exit=1';
            }
        }
    </script>
</body>

</html>