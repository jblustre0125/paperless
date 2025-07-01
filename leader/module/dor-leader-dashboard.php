    <?php
    ob_start();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past


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

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }

    require_once __DIR__ . "/../../config/header.php";
    require_once __DIR__ . "/../../config/dbop.php";
    require_once "../controller/dor-leader-method.php";



    $title = "Leader Dashboard";
    $method = new Method(1);

    // Get current user's tablet ID from session
    $currentTabletId = $_SESSION['hostnameId'] ?? null;

    $hostnames = $method->getOnlineTablets($currentTabletId);

    $productionCode = $_SESSION['production_code'] ?? null;


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
                            <a class="nav-link active fs-5" href="dor-home.php">DOR System</a>
                        </li>
                    </ul>

                    <!-- Device Name Display -->
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <?php
                            // Get current tablet info
                            $currentTablet = isset($_SESSION['hostnameId']) ? $method->getCurrentTablet($_SESSION['hostnameId']) : null;
                            $tabletName = $currentTablet ? htmlspecialchars($currentTablet['Hostname']) : 'Tablet Name';
                            $isActive = $currentTablet ? $currentTablet['IsActive'] : false;
                            $statusClass = $isActive ? 'text-success' : 'text-warning';
                            ?>
                            <a class="nav-link dropdown-toggle <?= $statusClass ?> fw-bold" href="#" id="deviceDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-tablet"></i> <?= $tabletName ?>
                                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> ms-2">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item text-danger fw-bold" href="dor-leader-login.php">
                                        <i class="bi bi-box-arrow-right"></i> Exit Application
                                    </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <script src="../../js/bootstrap.bundle.min.js"></script>
        <div class="container mt-5">
            <!-- Current User Tablet (Displayed separately) -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-tablet-fill fs-1 me-3"></i>
                                <div>
                                    <h5 class="card-title mb-1">Your Tablet</h5>
                                    <p class="card-text mb-0">
                                        <?php if (isset($_SESSION['is_leader']) || isset($_SESSION['is_sr_leader'])): ?>
                                            <span class="badge bg-light text-dark me-2">
                                                <?= $_SESSION['is_sr_leader'] ? 'SR Leader' : 'Leader' ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-success">
                                            <?= isset($currentTablet['Hostname']) ? htmlspecialchars($currentTablet['Hostname']) : 'Unknown Tablet' ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other Online Tablets -->
            <h4 class="mb-3">Operator Tablets</h4>
            <div id="tablet-list">
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
                    <?php if (!empty($hostnames)): ?>
                        <?php foreach ($hostnames as $row): ?>
                            <div class="col">
                                <div class="card text-center shadow-sm border-success">
                                    <div class="card-body py-3 cursor-pointer"
                                        onclick="window.location.href='dor-tablet.php?hostname_id=<?= $row['HostnameId'] ?>'"
                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                        data-hostname="<?= htmlspecialchars($row['Hostname']) ?>"
                                        data-record-id="<?= $row['RecordId'] ?? 'new' ?>">
                                        <i class="bi bi-tablet text-success fs-3 mb-2"></i>
                                        <h6 class="card-title mb-1"><?= htmlspecialchars($row['Hostname']) ?></h6>
                                        <span class="badge bg-secondary">
                                            <?= $row['IsActive'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i> No other tablets are currently online
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </div>

        <script>
            function loadTabletList(){
                fetch('../ajax/dor-load-tablet.php')
                .then(response => {
                    if(!response.ok) throw new Error('Network Error.');
                    return response.text();
                })
                .then(html => {
                    document.getElementById('tablet-list').innerHTML = html;
                })
                .catch(err => {
                    console.error("Failed to lo;ad tablets:", err)
                });
            }

            loadTabletList();

            setInterval(loadTabletList, 3000);
        </script>
    </body>

    </html>