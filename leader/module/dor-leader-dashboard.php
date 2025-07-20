    <?php
    ob_start();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

    // Simple session start - same as login controller
    session_start();

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
                            <a class="nav-link active fs-5" href="../../leader/module/dor-leader-dashboard.php">DOR System</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fs-5" href="#" id="masterDataDropdown" role="button"
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
                                <li><a class="dropdown-item" href="../../leader/module/dor-tablet-management.php">
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
        <script src="../../js/bootstrap.bundle.min.js"></script>
        <div class="container mt-5">
            <!-- Running Lines -->
            <h4 class="mb-3">Running Lines</h4>
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
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i> No running lines
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </div>

        <script>
            function loadTabletList() {
                fetch('../ajax/dor-load-tablet.php')
                    .then(response => {
                        if (!response.ok) throw new Error('Network Error.');
                        return response.text();
                    })
                    .then(html => {
                        document.getElementById('tablet-list').innerHTML = html;
                    })
                    .catch(err => {
                        console.error("Failed to load tablets:", err)
                    });
            }

            loadTabletList();

            setInterval(loadTabletList, 3000);

            function exitApplication(event) {
                event.preventDefault();

                // Show confirmation dialog
                if (confirm('Are you sure you want to exit the application?')) {
                    // Update database logout status
                    fetch('../controller/dor-leader-logout.php?exit=1')
                        .then(response => {
                            // Try to exit the Android app
                            try {
                                if (window.AndroidApp && typeof window.AndroidApp.exitApp === 'function') {
                                    window.AndroidApp.exitApp();
                                } else if (window.Android && typeof window.Android.exitApp === 'function') {
                                    window.Android.exitApp();
                                } else {
                                    // Fallback: close window or show manual close message
                                    window.close();
                                    if (!window.closed) {
                                        alert('Please close this application manually.');
                                    }
                                }
                            } catch (e) {
                                console.error('Error exiting app:', e);
                                alert('Please close this application manually.');
                            }
                        })
                        .catch(error => {
                            console.error('Error updating logout status:', error);
                            // Still try to exit the app even if database update fails
                            try {
                                if (window.AndroidApp && typeof window.AndroidApp.exitApp === 'function') {
                                    window.AndroidApp.exitApp();
                                } else if (window.Android && typeof window.Android.exitApp === 'function') {
                                    window.Android.exitApp();
                                } else {
                                    window.close();
                                    if (!window.closed) {
                                        alert('Please close this application manually.');
                                    }
                                }
                            } catch (e) {
                                alert('Please close this application manually.');
                            }
                        });
                }
            }
        </script>
    </body>

    </html>