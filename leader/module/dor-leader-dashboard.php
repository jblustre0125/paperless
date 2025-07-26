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
$hostnames = $method->getAllTabletWithStatus($currentTabletId);
$productionCode = $_SESSION['production_code'] ?? null;

// Redirect to login if user is not authenticated
if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    header('Location: dor-leader-login.php');
    exit;
}

// Prepare autocomplete data for JavaScript
$searchSuggestions = [];
foreach ($hostnames as $row) {
    // Add hostname to suggestions
    if (!empty($row['Hostname'])) {
        $searchSuggestions[] = [
            'value' => $row['Hostname'],
            'type' => 'hostname',
            'status' => $row['Status'],
            'id' => $row['HostnameId']
        ];
    }

    // Add line number to suggestions
    if (!empty($row['LineNumber'])) {
        $lineLabel = "Line: " . $row['LineNumber'];
        $searchSuggestions[] = [
            'value' => $lineLabel,
            'type' => 'line',
            'status' => $row['Status'],
            'id' => $row['HostnameId']
        ];
    }
}

// Remove duplicates and sort
$uniqueSuggestions = array_unique($searchSuggestions, SORT_REGULAR);
usort($uniqueSuggestions, function ($a, $b) {
    return strcmp($a['value'], $b['value']);
});
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

        /* Limit Tab3 Quick View Modal content to 7 rows and make it scrollable */
        #tab3ModalContent {
            max-height: 450px;
            overflow-y: auto;
        }

        /* Force downtime modal to always be extra wide */
        #downtimeModal .modal-dialog {
            max-width: 90% !important;
            width: 90% !important;
        }

        /* Limit downtime modal content to 5 rows and make it scrollable */
        /* #downtimeModalContent {
            max-height: 700px;
        } */

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

        /* Custom colors for line status */
        .border-pink {
            border-color: #e91e63 !important;
        }

        .bg-pink {
            background-color: #e91e63 !important;
        }

        .border-orange {
            border-color: #ff9800 !important;
        }

        .bg-orange {
            background-color: #ff9800 !important;
        }

        .text-orange {
            color: #ff9800 !important;
        }

        /* Autocomplete Suggestions Styles */
        .search-container {
            position: relative;
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .autocomplete-item:hover,
        .autocomplete-item.active {
            background-color: #f8f9fa;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item .item-text {
            flex: 1;
        }

        .autocomplete-item .item-badge {
            font-size: 0.75rem;
        }

        .autocomplete-item .item-type {
            color: #6c757d;
            font-size: 0.8rem;
            margin-left: 8px;
        }

        .no-suggestions {
            padding: 12px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        /* Highlight matching text */
        .highlight {
            background-color: #fff3cd;
            font-weight: 600;
        }

        .badge-multiline {
            white-space: normal !important;
            max-width: 120px;
            line-height: 1.2;
            padding: 0.5em 0.65em;
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
                            <li><a class="dropdown-item" href="dor-tablet-management.php"><i class="bi bi-tablet"></i>
                                    Tablet</a></li>
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
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php"
                                    onclick="exitApplication(event)"><i class="bi bi-box-arrow-right"></i> Exit Application</a>
                            </li>
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php"><i
                                        class="bi bi-box-arrow-right"></i> Log Out</a></li>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Running Lines</h4>

            <!-- Enhanced Search Box with Autocomplete -->
            <div class="search-container" style="max-width: 300px; position: relative;">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="tabletSearch" class="form-control" autocomplete="off"
                        placeholder="Search tablets or lines..." aria-describedby="searchHelp">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <!-- Autocomplete Dropdown -->
                <div id="autocompleteDropdown" class="autocomplete-dropdown">
                    <!-- Suggestions populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Status Legend -->
        <div class="mb-3">
            <small class="text-muted">
                <span class="badge bg-success me-2">
                    <i class="bi bi-circle-fill"></i> Normal Operation
                </span>
                <span class="badge bg-secondary me-2">
                    <i class="bi bi-circle-fill"></i> No Operation
                </span>
                <span class="badge bg-pink me-2">
                    <i class="bi bi-circle-fill"></i> Parts Request
                </span>
                <span class="badge bg-warning me-2">
                    <i class="bi bi-circle-fill"></i> For Checking
                </span>
                <span class="badge bg-orange me-2">
                    <i class="bi bi-circle-fill"></i> Breaktime/CR Break
                </span>
                <span class="badge bg-danger">
                    <i class="bi bi-circle-fill"></i> Abnormality
                </span>
            </small>
        </div>

        <!-- Tablet/Line Cards Container -->
        <div id="tablet-list">
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3" id="tabletGrid">
                <?php if (!empty($hostnames)): ?>
                    <?php foreach ($hostnames as $row): ?>
                        <?php
                        $lineStatusMap = [
                            1 => ['name' => 'Normal Operation', 'short' => 'Normal', 'border' => 'border-success', 'badge' => 'bg-success', 'text' => 'text-success'],
                            2 => ['name' => 'No Operation', 'short' => 'No Op', 'border' => 'border-secondary', 'badge' => 'bg-secondary', 'text' => 'text-secondary'],
                            3 => ['name' => 'Parts Request', 'short' => 'Parts', 'border' => 'border-pink', 'badge' => 'bg-pink', 'text' => 'text-pink'],
                            4 => ['name' => 'For Checking', 'short' => 'Check', 'border' => 'border-warning', 'badge' => 'bg-warning', 'text' => 'text-warning'],
                            5 => ['name' => 'Breaktime/CR Break', 'short' => 'Break', 'border' => 'border-orange', 'badge' => 'bg-orange', 'text' => 'text-orange'],
                            6 => ['name' => 'Abnormality', 'short' => 'Abnormality', 'border' => 'border-danger', 'badge' => 'bg-danger', 'text' => 'text-danger']
                        ];

                        // Auto-adjust line status based on login status
                        $originalLineStatusId = $row['LineStatusId'] ?? 2;

                        if ($row['IsLoggedIn'] == 1) {
                            // If operator is logged in, default to Normal Operation unless manually set to something else
                            $lineStatusId = ($originalLineStatusId == 2) ? 1 : $originalLineStatusId;
                        } else {
                            // If operator is logged out, force to No Operation
                            $lineStatusId = 2;
                        }

                        $statusConfig = $lineStatusMap[$lineStatusId] ?? $lineStatusMap[2];

                        $isOperational = ($row['IsLoggedIn'] == 1 && $lineStatusId != 2);
                        $borderClass = $statusConfig['border'];
                        $badgeClass = $statusConfig['badge'];
                        $iconClass = $statusConfig['text'];
                        $statusText = $statusConfig['name'];
                        $statusShort = $statusConfig['short'];
                        ?>
                        <div class="col tablet-card" data-hostname="<?= strtolower(htmlspecialchars($row['Hostname'])) ?>"
                            data-line="<?= strtolower(htmlspecialchars($row['LineNumber'] ?? '')) ?>" data-status="<?= $row['Status'] ?>"
                            data-line-status="<?= $lineStatusId ?>" data-is-logged-in="<?= $row['IsLoggedIn'] ?>"
                            data-hostname-id="<?= $row['HostnameId'] ?>">
                            <div class="card text-center shadow-sm <?= $borderClass ?> position-relative">
                                <!-- Status Badge -->
                                <div class="position-absolute top-0 start-0 m-2" style="z-index: 10;">
                                    <span class="badge <?= $badgeClass ?> rounded-pill badge-multiline">
                                        <i class="bi bi-circle-fill me-1"></i><?= $statusText ?>
                                    </span>
                                </div>

                                <?php if ($isOperational): ?>
                                    <!-- Quick View Button (only for online tablets) -->
                                    <button class="btn btn-sm btn-outline-success open-tab3-modal position-absolute top-0 end-0 m-1 z-3"
                                        data-hostname-id="<?= $row['HostnameId'] ?>"
                                        data-record-id="<?= isset($row['RecordId']) ? htmlspecialchars($row['RecordId']) : 'null' ?>"
                                        title="Quick View Tab 3">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Card Body -->
                                <div class="card-body py-3 <?= $isOperational ? 'cursor-pointer' : '' ?>"
                                    style="padding-top: 2.5rem; padding-bottom: 1rem; padding-left: 1rem; padding-right: 1rem;"
                                    <?php if ($isOperational): ?>
                                    onclick="window.location.href='dor-tablet.php?hostname_id=<?= $row['HostnameId'] ?>'"
                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Click to open tablet interface" <?php endif; ?>
                                    data-hostname="<?= htmlspecialchars($row['Hostname']) ?>"
                                    data-record-id="<?= $row['RecordId'] ?? 'new' ?>">

                                    <i class="bi bi-tablet <?= $iconClass ? 'text-success' : 'text-secondary' ?> fs-3 mb-2"
                                        style="margin-top: 0.5rem;"></i>
                                    <h6 class="card-title mb-1">
                                        <?= htmlspecialchars($row['Hostname']) ?>
                                    </h6>

                                    <?php if (!empty($row['LineNumber'])): ?>
                                        <small class="text-muted">
                                            Line: <?= htmlspecialchars($row['LineNumber']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!$isOperational): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-exclamation-triangle"></i> Not Available
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i> No operator tablets found
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- No Results Message -->
            <div id="noResults" class="text-center mt-4" style="display: none;">
                <div class="alert alert-warning">
                    <i class="bi bi-search me-2"></i> No tablets found matching your search criteria
                </div>
            </div>
        </div>
    </div>

    <!-- Tab3 Quick View Modal -->
    <div class="modal fade" id="tab3QuickModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" id="tab3ModalContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Downtime Modal -->
    <div class="modal fade" id="downtimeModal" tabindex="-1" aria-labelledby="downtimeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" id="downtimeModalContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Loading Modal
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Pass PHP data to JavaScript -->
    <script>
        // Make search suggestions available to JavaScript
        window.searchSuggestions = <?= json_encode($uniqueSuggestions) ?>;
    </script>


    <!-- JavaScript Dependencies -->
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script src="../js/dor-dashboard.js"></script>

</body>

</html>
