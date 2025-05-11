<?php
require_once "../config/header.php";

$title = "Dashboard";
ob_start();
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-success">
            <div class="card-body">
                <h5 class="card-title">Production Overview</h5>
                <p class="card-text small">Live summary of daily output, shift status, and model throughput.</p>
                <a href="report-output.php" class="btn btn-success">View Output</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-warning">
            <div class="card-body">
                <h5 class="card-title">NG / Abnormalities</h5>
                <p class="card-text small">Track and extract abnormal data by date, shift, model, and line.</p>
                <a href="report-ng.php" class="btn btn-warning">NG Reports</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-info">
            <div class="card-body">
                <h5 class="card-title">Downtime Summary</h5>
                <p class="card-text small">View downtime occurrences and categorize by cause.</p>
                <a href="report-downtime.php" class="btn btn-info">Downtime Report</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-secondary">
            <div class="card-body">
                <h5 class="card-title">System Management</h5>
                <p class="card-text small">Configure DOR types, checkpoints, users, and production lines.</p>
                <a href="admin-checkpoints.php" class="btn btn-outline-secondary">Manage Setup</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-primary">
            <div class="card-body">
                <h5 class="card-title">Operator Status</h5>
                <p class="card-text small">Monitor currently logged-in operators by tablet and line.</p>
                <a href="admin-operators.php" class="btn btn-outline-primary">View Operators</a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once "../config/master-adm.php";
?>