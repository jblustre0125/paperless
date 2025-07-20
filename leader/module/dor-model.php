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

$title = "Model Management";
$method = new Method(1);

// Check authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    header('Location: dor-leader-login.php');
    exit;
}

// Handle CRUD operations
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $itemId = trim($_POST['item_id']);
                $itemName = trim($_POST['item_name']);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $dimChecking = (int)$_POST['dim_checking'];
                $mp = (int)$_POST['mp'];

                if (empty($itemId) || empty($itemName)) {
                    $message = 'Item ID and Item Name are required';
                    $messageType = 'danger';
                } else {
                    // Check if Item ID already exists
                    $db = new DbOp(1);
                    $checkSql = "SELECT COUNT(*) as count FROM GenModel WHERE ITEM_ID = ?";
                    $checkResult = $db->execute($checkSql, [$itemId], 1);

                    if ($checkResult && $checkResult[0]['count'] > 0) {
                        $message = 'Item ID already exists in the database';
                        $messageType = 'danger';
                    } else {
                        $sql = "INSERT INTO GenModel (ITEM_ID, ITEM_NAME, ISACTIVE, DIM_CHECKING, MP) 
                                VALUES (?, ?, ?, ?, ?)";
                        $params = [$itemId, $itemName, $isActive, $dimChecking, $mp];

                        if ($db->execute($sql, $params) !== false) {
                            $message = 'Model added successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Error adding model';
                            $messageType = 'danger';
                        }
                    }
                }
                break;

            case 'update':
                $modelId = (int)$_POST['model_id'];
                $itemId = trim($_POST['item_id']);
                $itemName = trim($_POST['item_name']);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $dimChecking = (int)$_POST['dim_checking'];
                $mp = (int)$_POST['mp'];

                if (empty($itemId) || empty($itemName)) {
                    $message = 'Item ID and Item Name are required';
                    $messageType = 'danger';
                } else {
                    // Check if Item ID already exists (excluding current model)
                    $db = new DbOp(1);
                    $checkSql = "SELECT COUNT(*) as count FROM GenModel WHERE ITEM_ID = ? AND MODEL_ID != ?";
                    $checkResult = $db->execute($checkSql, [$itemId, $modelId], 1);

                    if ($checkResult && $checkResult[0]['count'] > 0) {
                        $message = 'Item ID already exists in the database';
                        $messageType = 'danger';
                    } else {
                        $sql = "UPDATE GenModel SET ITEM_ID=?, ITEM_NAME=?, ISACTIVE=?, DIM_CHECKING=?, MP=? 
                                WHERE MODEL_ID=?";
                        $params = [$itemId, $itemName, $isActive, $dimChecking, $mp, $modelId];

                        if ($db->execute($sql, $params) !== false) {
                            $message = 'Model updated successfully';
                            $messageType = 'success';
                        } else {
                            $message = 'Error updating model';
                            $messageType = 'danger';
                        }
                    }
                }
                break;

            case 'delete':
                $modelId = (int)$_POST['model_id'];

                // Check if model is being used
                $db = new DbOp(1);
                $checkSql = "SELECT COUNT(*) as count FROM AtoDor WHERE MODEL_ID = ?";
                $checkResult = $db->execute($checkSql, [$modelId], 1);

                if ($checkResult && $checkResult[0]['count'] > 0) {
                    $message = 'Cannot delete model - it is currently in use';
                    $messageType = 'warning';
                } else {
                    $sql = "DELETE FROM GenModel WHERE MODEL_ID = ?";
                    if ($db->execute($sql, [$modelId]) !== false) {
                        $message = 'Model deleted successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Error deleting model';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$activityFilter = $_GET['activity'] ?? '';

// Build query with filters
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(ITEM_ID LIKE ? OR ITEM_NAME LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($statusFilter !== '') {
    $whereConditions[] = "ISACTIVE = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination settings
$rowsPerPage = 100;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $rowsPerPage;

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM GenModel m" . (!empty($whereClause) ? " $whereClause" : "");
$db = new DbOp(1);
$countResult = $db->execute($countSql, $params, 1);
$totalRecords = $countResult ? $countResult[0]['total'] : 0;
$totalPages = ceil($totalRecords / $rowsPerPage);

// Get models with activity status and pagination
if ($activityFilter === '1') {
    // Filter for running models only
    $sql = "SELECT TOP " . $rowsPerPage . " m.*, 
            CASE WHEN a.RecordId IS NOT NULL THEN 1 ELSE 0 END as IS_RUNNING
            FROM GenModel m 
            LEFT JOIN AtoDor a ON a.ModelId = m.MODEL_ID 
            INNER JOIN (SELECT HostnameId FROM GenHostname WHERE IsLoggedIn = 1) b ON a.HostnameId = b.HostnameId" .
        (!empty($whereClause) ? " $whereClause" : "") . "
            ORDER BY m.ITEM_ID DESC";
} else {
    // Show all models with correct running status validation and search priority
    if (!empty($search)) {
        // Search with priority: exact Item ID matches first, then Item Name matches
        $sql = "SELECT TOP " . $rowsPerPage . " m.*, 
                CASE WHEN a.RecordId IS NOT NULL THEN 1 ELSE 0 END as IS_RUNNING,
                CASE WHEN m.ITEM_ID = ? THEN 1 ELSE 0 END as is_exact_match
                FROM GenModel m 
                LEFT JOIN AtoDor a ON a.ModelId = m.MODEL_ID 
                LEFT JOIN (SELECT HostnameId FROM GenHostname WHERE IsLoggedIn = 1) b ON a.HostnameId = b.HostnameId" .
            (!empty($whereClause) ? " $whereClause" : "") . "
                ORDER BY is_exact_match DESC, m.ITEM_ID DESC";
        $params[] = $search; // Add search term for exact match
    } else {
        // No search - normal order
        $sql = "SELECT TOP " . $rowsPerPage . " m.*, 
                CASE WHEN a.RecordId IS NOT NULL THEN 1 ELSE 0 END as IS_RUNNING
                FROM GenModel m 
                LEFT JOIN AtoDor a ON a.ModelId = m.MODEL_ID 
                LEFT JOIN (SELECT HostnameId FROM GenHostname WHERE IsLoggedIn = 1) b ON a.HostnameId = b.HostnameId" .
            (!empty($whereClause) ? " $whereClause" : "") . "
                ORDER BY m.ITEM_ID DESC";
    }
}

$models = $db->execute($sql, $params, 1);

// Check if query failed
if ($models === false) {
    $models = [];
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
    <style>
        /* Sticky navbar */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Compact and wider layout */
        .container {
            max-width: 100%;
            padding: 0 15px;
        }

        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }

        .form-control,
        .btn {
            font-size: 14px;
        }

        /* Compact table */
        .table th,
        .table td {
            padding: 0.5rem 0.75rem;
            font-size: 14px;
        }

        /* Compact cards */
        .card-body {
            padding: 1rem;
        }

        /* Compact form groups */
        .row.g-3 {
            --bs-gutter-y: 0.75rem;
            --bs-gutter-x: 0.75rem;
        }

        .modal-dialog {
            max-width: 95%;
        }

        /* Action buttons with icons */
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 12px;
        }

        .btn-group-sm .btn i {
            font-size: 14px;
        }

        /* Ensure Bootstrap Icons are visible */
        .bi {
            display: inline-block;
            font-size: inherit;
            vertical-align: middle;
        }

        /* Action column specific styling */
        .table .btn-group-sm .btn {
            min-width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .table .btn-group-sm .btn i {
            font-size: 16px;
            line-height: 1;
        }

        @media (max-width: 768px) {
            .modal-dialog {
                max-width: 98%;
            }
        }
    </style>
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
                            <li><a class="dropdown-item active" href="../../leader/module/dor-model.php">
                                    Model
                                </a></li>
                            <li><a class="dropdown-item" href="../../leader/module/dor-user.php">
                                    User
                                </a></li>
                            <li><a class="dropdown-item" href="../../leader/module/dor-line.php">
                                    Line
                                </a></li>
                            <li><a class="dropdown-item" href="../../leader/module/dor-tablet-management.php">
                                    Tablet
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
                            data-bs-toggle="dropdown" aria-expanded="false"><?= $tabletName ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php" onclick="exitApplication(event)">
                                    Exit Application
                                </a>
                            </li>
                            <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php">
                                    Log Out
                                </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-2">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4>Model Management</h4>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModelModal">
                <i class="bi bi-plus-circle"></i> Add New Model
            </button>
        </div>

        <!-- Message Display -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="card mb-2">
            <div class="card-body py-2">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search by Item ID or Item Name">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="activity" class="form-label">Activity</label>
                        <select class="form-select" id="activity" name="activity">
                            <option value="">All Activity</option>
                            <option value="1" <?= $activityFilter === '1' ? 'selected' : '' ?>>Running</option>
                            <option value="0" <?= $activityFilter === '0' ? 'selected' : '' ?>>Not Running</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary me-2">Search
                        </button>
                        <a href="dor-model.php" class="btn btn-outline-secondary">Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pagination Info -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted">
                <?php
                $displayCount = count($models);
                $totalDisplay = $activityFilter === '1' ? $displayCount : $totalRecords;
                ?>
                Showing <?= ($offset + 1) ?> to <?= min($offset + $rowsPerPage, $displayCount) ?> of <?= number_format($totalDisplay) ?> models
            </div>
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Model pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- First Page -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Page -->
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Last Page -->
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Models Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>ID</th>
                                <th>Item ID</th>
                                <th>Item Name</th>
                                <th>MP</th>
                                <th>Dim Checking</th>
                                <th>Status</th>
                                <th>Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($models)): ?>
                                <?php foreach ($models as $model): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($model['MODEL_ID'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($model['ITEM_ID'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($model['ITEM_NAME'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($model['MP'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($model['DIM_CHECKING'] ?? '') ?></td>
                                        <td>
                                            <span class="badge <?= $model['ISACTIVE'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $model['ISACTIVE'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $model['IS_RUNNING'] ? 'bg-warning' : 'bg-light text-dark' ?>">
                                                <?= $model['IS_RUNNING'] ? 'Running' : 'Not Running' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-primary btn-sm"
                                                    onclick="editModel(<?= htmlspecialchars(json_encode($model)) ?>)"
                                                    title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteModel(<?= $model['MODEL_ID'] ?>, '<?= htmlspecialchars($model['ITEM_NAME'] ?? '') ?>')"
                                                    title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <i class="bi bi-inbox"></i> No models found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Model Modal -->
    <div class="modal fade" id="addModelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Model</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="item_id" class="form-label">Item ID *</label>
                                <input type="text" class="form-control" id="item_id" name="item_id" required>
                            </div>
                            <div class="col-12">
                                <label for="item_name" class="form-label">Item Name *</label>
                                <input type="text" class="form-control" id="item_name" name="item_name" required>
                            </div>

                            <div class="col-12">
                                <label for="mp" class="form-label">No. of MP per Line *</label>
                                <input type="number" class="form-control" id="mp" name="mp" value="1" required>
                            </div>
                            <div class="col-12">
                                <label for="dim_checking" class="form-label">Dimension Checking *</label>
                                <input type="number" class="form-control" id="dim_checking" name="dim_checking" value="1" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="addModelBtn">Add Model</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Model Modal -->
    <div class="modal fade" id="editModelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Model</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="model_id" id="edit_model_id">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="edit_item_id" class="form-label">Item ID *</label>
                                <input type="text" class="form-control" id="edit_item_id" name="item_id" required>
                            </div>
                            <div class="col-12">
                                <label for="edit_item_name" class="form-label">Item Name *</label>
                                <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
                            </div>

                            <div class="col-12">
                                <label for="edit_mp" class="form-label">No. of MP per Line *</label>
                                <input type="number" class="form-control" id="edit_mp" name="mp" required>
                            </div>
                            <div class="col-12">
                                <label for="edit_dim_checking" class="form-label">Dimension Checking *</label>
                                <input type="number" class="form-control" id="edit_dim_checking" name="dim_checking" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                    <label class="form-check-label" for="edit_is_active">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updateModelBtn">Update Model</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the model "<span id="deleteModelName"></span>"?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="model_id" id="delete_model_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script>
        function editModel(model) {
            document.getElementById('edit_model_id').value = model.MODEL_ID;
            document.getElementById('edit_item_id').value = model.ITEM_ID;
            document.getElementById('edit_item_name').value = model.ITEM_NAME;
            document.getElementById('edit_mp').value = model.MP || 0;
            document.getElementById('edit_dim_checking').value = model.DIM_CHECKING || 0;
            document.getElementById('edit_is_active').checked = model.ISACTIVE == 1;

            new bootstrap.Modal(document.getElementById('editModelModal')).show();
        }

        function deleteModel(modelId, modelName) {
            document.getElementById('delete_model_id').value = modelId;
            document.getElementById('deleteModelName').textContent = modelName;
            new bootstrap.Modal(document.getElementById('deleteModelModal')).show();
        }

        function exitApplication(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to exit the application?')) {
                window.location.href = '../controller/dor-leader-logout.php?exit=1';
            }
        }

        // Validation functions
        function validateAddModel(event) {
            event.preventDefault();

            const itemId = document.getElementById('item_id').value.trim();
            const itemName = document.getElementById('item_name').value.trim();
            const mp = parseInt(document.getElementById('mp').value);
            const dimChecking = parseInt(document.getElementById('dim_checking').value);
            const isActive = document.getElementById('is_active').checked;

            // Clear previous error messages
            clearValidationErrors('addModelModal');

            let isValid = true;
            let errorMessage = '';

            // Check if fields are empty
            if (!itemId) {
                showFieldError('item_id', 'Item ID is required');
                isValid = false;
            }

            if (!itemName) {
                showFieldError('item_name', 'Item Name is required');
                isValid = false;
            }

            if (mp < 0) {
                showFieldError('mp', 'No. of MP per Line cannot be negative');
                isValid = false;
            }

            if (dimChecking < 0) {
                showFieldError('dim_checking', 'Dimension Checking cannot be negative');
                isValid = false;
            }

            if (!isValid) {
                return false;
            }

            // Check if Item ID already exists
            checkItemIdExists(itemId, null, function(exists) {
                if (exists) {
                    showFieldError('item_id', 'Item ID already exists in the database');
                } else {
                    // Submit the form
                    document.querySelector('#addModelModal form').submit();
                }
            });

            return false;
        }

        function validateUpdateModel(event) {
            event.preventDefault();

            const modelId = document.getElementById('edit_model_id').value;
            const itemId = document.getElementById('edit_item_id').value.trim();
            const itemName = document.getElementById('edit_item_name').value.trim();
            const mp = parseInt(document.getElementById('edit_mp').value);
            const dimChecking = parseInt(document.getElementById('edit_dim_checking').value);
            const isActive = document.getElementById('edit_is_active').checked;

            // Clear previous error messages
            clearValidationErrors('editModelModal');

            let isValid = true;

            // Check if fields are empty
            if (!itemId) {
                showFieldError('edit_item_id', 'Item ID is required');
                isValid = false;
            }

            if (!itemName) {
                showFieldError('edit_item_name', 'Item Name is required');
                isValid = false;
            }

            if (mp < 0) {
                showFieldError('edit_mp', 'No. of MP per Line cannot be negative');
                isValid = false;
            }

            if (dimChecking < 0) {
                showFieldError('edit_dim_checking', 'Dimension Checking cannot be negative');
                isValid = false;
            }

            if (!isValid) {
                return false;
            }

            // Check if Item ID already exists (excluding current model)
            checkItemIdExists(itemId, modelId, function(exists) {
                if (exists) {
                    showFieldError('edit_item_id', 'Item ID already exists in the database');
                } else {
                    // Submit the form
                    document.querySelector('#editModelModal form').submit();
                }
            });

            return false;
        }

        function checkItemIdExists(itemId, modelId, callback) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../ajax/check-item-id.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            callback(response.exists);
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            callback(false);
                        }
                    } else {
                        console.error('AJAX request failed');
                        callback(false);
                    }
                }
            };

            let data = 'item_id=' + encodeURIComponent(itemId);
            if (modelId) {
                data += '&model_id=' + encodeURIComponent(modelId);
            }

            xhr.send(data);
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback d-block';
            errorDiv.textContent = message;

            field.classList.add('is-invalid');
            field.parentNode.appendChild(errorDiv);
        }

        function clearValidationErrors(modalId) {
            const modal = document.getElementById(modalId);
            const invalidFields = modal.querySelectorAll('.is-invalid');
            const errorMessages = modal.querySelectorAll('.invalid-feedback');

            invalidFields.forEach(field => field.classList.remove('is-invalid'));
            errorMessages.forEach(error => error.remove());
        }

        // Add event listeners when modals are shown
        document.addEventListener('DOMContentLoaded', function() {
            // Add Model form validation
            document.querySelector('#addModelModal form').addEventListener('submit', validateAddModel);

            // Edit Model form validation
            document.querySelector('#editModelModal form').addEventListener('submit', validateUpdateModel);

            // Clear validation errors when modals are hidden
            document.getElementById('addModelModal').addEventListener('hidden.bs.modal', function() {
                clearValidationErrors('addModelModal');
            });

            document.getElementById('editModelModal').addEventListener('hidden.bs.modal', function() {
                clearValidationErrors('editModelModal');
            });
        });
    </script>
</body>

</html>