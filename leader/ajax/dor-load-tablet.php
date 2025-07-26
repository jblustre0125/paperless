<?php

require_once __DIR__ . "/../../config/dbop.php";
require_once "../controller/dor-leader-method.php";
session_start();

header("Content-Type: text/html");

if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$method = new Method(1);
$currentTabletId = $_SESSION['hostnameId'] ?? null;
// Use the same method as dashboard to get only operator tablets
$hostnames = $method->getAllTabletWithStatus($currentTabletId);

ob_start();
?>
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
                        <span class="badge <?= $badgeClass ?> rounded-pill" data-bs-toggle="tooltip" data-bs-placement="bottom"
                            title="<?= $statusText ?>">
                            <i class="bi bi-circle-fill me-1"></i><?= $statusShort ?>
                        </span>
                    </div>

                    <?php if ($isOperational): ?>
                        <!-- Quick View Button (only for online tablets) -->
                        <button class="btn btn-sm btn-outline-success open-tab3-modal position-absolute top-0 end-0 m-1"
                            style="z-index: 10;" data-hostname-id="<?= $row['HostnameId'] ?>"
                            data-record-id="<?= isset($row['RecordId']) ? htmlspecialchars($row['RecordId']) : 'null' ?>"
                            title="Quick View Tab 3">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    <?php endif; ?>

                    <!-- Card Body -->
                    <div class="card-body <?= $isOperational ? 'cursor-pointer' : '' ?>"
                        style="padding-top: 2.5rem; padding-bottom: 1rem; padding-left: 1rem; padding-right: 1rem;"
                        <?php if ($isOperational): ?>
                        onclick="window.location.href='dor-tablet.php?hostname_id=<?= $row['HostnameId'] ?>'" data-bs-toggle="tooltip"
                        data-bs-placement="top" title="Click to open tablet interface" <?php endif; ?>
                        data-hostname="<?= htmlspecialchars($row['Hostname']) ?>" data-record-id="<?= $row['RecordId'] ?? 'new' ?>">

                        <i class="bi bi-tablet <?= $iconClass ?> fs-3 mb-2" style="margin-top: 0.5rem;"></i>
                        <h6 class="card-title mb-1">
                            <?= htmlspecialchars($row['Hostname']) ?>
                        </h6>

                        <?php if (!empty($row['LineNumber'])): ?>
                            <small class="text-muted">
                                Line: <?= htmlspecialchars($row['LineNumber']) ?>
                            </small>
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

<!-- No Results Message (hidden by default, shown by JavaScript when search has no results) -->
<div id="noResults" class="text-center mt-4" style="display: none;">
    <div class="alert alert-warning">
        <i class="bi bi-search me-2"></i> No tablets found matching your search criteria
    </div>
</div>
<?php
echo ob_get_clean();
?>
