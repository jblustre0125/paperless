<?php
/**
 * DOR Leader Dashboard
 * 
 * This is the main dashboard interface for DOR (Defect Occurrence Rate) leaders.
 * It displays all running production lines and provides access to various system functions.
 */

// Start output buffering to prevent header issues
ob_start();

// Set strict caching headers to prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Start session management
session_start();

// Include required configuration files
require_once __DIR__ . "/../../config/header.php";       // Global header configurations
require_once __DIR__ . "/../../config/dbop.php";         // Database operations
require_once "../controller/dor-leader-method.php";      // Leader-specific methods

// Set page title
$title = "Leader Dashboard";

// Initialize leader methods controller
$method = new Method(1);

// Get current tablet ID from session
$currentTabletId = $_SESSION['hostnameId'] ?? null;

// Fetch all online tablets (excluding current one if set)
$hostnames = $method->getOnlineTablets($currentTabletId);
$productionCode = $_SESSION['production_code'] ?? null;

// Redirect to login if user is not authenticated
if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    header('Location: dor-leader-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title) ?></title>
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="../../css/bootstrap.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/leader-dashboard.css" rel="stylesheet" />
    
    <!-- Inline Styles -->
    <style>
        /* Extra large modal */
        .modal-xl {
            max-width: 90%;
        }
        
        /* Pointer cursor for clickable elements */
        .cursor-pointer {
            cursor: pointer;
        }
        
        /* Ensure toast notifications appear above other content */
        #toast-container {
            z-index: 1080;
        }
        
        /* Fix for modal backdrop */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }
    </style>
</head>

<body>
    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
        <div class="container-lg">
            <!-- Mobile toggle button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Left-aligned menu items -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active fs-5" href="dor-leader-dashboard.php">DOR System</a>
                    </li>
                    
                    <!-- Master Data Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fs-5" href="#" data-bs-toggle="dropdown">Master Data</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dor-model.php"><i class="bi bi-diagram-3"></i> Model</a></li>
                            <li><a class="dropdown-item" href="dor-user.php"><i class="bi bi-person"></i> User</a></li>
                            <li><a class="dropdown-item" href="dor-line.php"><i class="bi bi-tablet"></i> Line</a></li>
                            <li><a class="dropdown-item" href="dor-tablet-management.php"><i class="bi bi-tablet"></i> Tablet</a></li>
                        </ul>
                    </li>
                    
                    <!-- Reports Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fs-5" href="#" data-bs-toggle="dropdown">Reports</a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dor-report.php">DOR</a></li>
                        </ul>
                    </li>
                </ul>
                
                <!-- Right-aligned user controls -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <?php
                        // Get current tablet information
                        $currentTablet = isset($_SESSION['hostnameId']) ? $method->getCurrentTablet($_SESSION['hostnameId']) : null;
                        $tabletName = $currentTablet ? htmlspecialchars($currentTablet['Hostname']) : 'Tablet Name';
                        ?>
                        <a class="nav-link dropdown-toggle fw-bold" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-tablet"></i> <?= $tabletName ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php" onclick="exitApplication(event)"><i class="bi bi-box-arrow-right"></i> Exit Application</a></li>
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Message Display (if any) -->
    <?php if (!empty($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast("<?= addslashes($_SESSION['flash_message']) ?>", "warning");
            });
        </script>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3"></div>

    <!-- Main Content Container -->
    <div class="container mt-5">
        <h4 class="mb-3">Running Lines</h4>
        
        <!-- Tablet/Line Cards Container -->
        <div id="tablet-list">
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
                <?php if (!empty($hostnames)): ?>
                    <?php foreach ($hostnames as $row): ?>
                        <div class="col">
                            <div class="card text-center shadow-sm border-success position-relative">
                                <!-- Quick View Button -->
                                <button class="btn btn-sm btn-outline-success open-tab3-modal position-absolute top-0 end-0 m-1 z-3"
                                    data-hostname-id="<?= $row['HostnameId'] ?>"
                                    data-record-id="<?= isset($row['RecordId']) ? htmlspecialchars($row['RecordId']) : 'null' ?>"
                                    title="Quick View Tab 3">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                                
                                <!-- Card Body - Clickable to go to tablet detail page -->
                                <div class="card-body py-3 cursor-pointer"
                                    onclick="window.location.href='dor-tablet.php?hostname_id=<?= $row['HostnameId'] ?>'"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-hostname="<?= htmlspecialchars($row['Hostname']) ?>"
                                    data-record-id="<?= $row['RecordId'] ?? 'new' ?>">
                                    <h6 class="card-title mb-1"><?= htmlspecialchars($row['Hostname']) ?></h6>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i> No running lines
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab3 Quick View Modal -->
    <div class="modal fade" id="tab3QuickModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" id="tab3ModalContent">
                <?php include '../../controller/load-tab3-content.php' ?>
            </div>
        </div>
    </div>

    <!-- Downtime Modal -->
    <div class="modal fade" id="downtimeModal" tabindex="-1" aria-labelledby="downtimeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" id="downtimeModalContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script src="../js/dor-dashboard.js"></script>
                    
</body>
</html>