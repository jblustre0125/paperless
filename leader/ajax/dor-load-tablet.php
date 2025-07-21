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
$hostnames = $method->getOnlineTablets($currentTabletId);

ob_start();
?>
<div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
    <?php if (!empty($hostnames)): ?>
        <?php foreach ($hostnames as $row): ?>
            <div class="col">
                <div class="card text-center shadow-sm border-success position-relative">

                    <!-- Modal Shortcut Button -->
                    <button type="button"
                        class="btn btn-sm btn-outline-success position-absolute top-0 end-0 m-1 z-3"
                        onclick="event.stopPropagation(); openTab3Modal(<?= $row['HostnameId'] ?>, <?= $row['RecordId'] ?? 'null' ?>)"
                        title="Quick View Tab 3">
                        <i class="bi bi-eye-fill"></i>
                    </button>

                    <!-- Card Body with Redirect -->
                    <div class="card-body py-3 cursor-pointer"
                        onclick="window.location.href='dor-tablet.php?hostname_id=<?= $row['HostnameId'] ?>'"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
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
                <i class="bi bi-info-circle me-2"></i> No running lines
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
echo ob_get_clean();
