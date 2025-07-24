<?php
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => isset($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax'
]);
ob_start();
session_start();

// file_put_contents('debug-session.log', print_r([
//     'SESSION' => $_SESSION,
//     'POST' => $_POST
// ], true));
$title = "DOR Dashboard";

require_once '../controller/dor-checkpoint-definition.php';
require_once '../controller/dor-visual-checkpoint.php';
require_once '../controller/dor-dimension-checkpoint.php';
require_once '../controller/dor-dor.php';
require_once '../controller/dor-downtime.php';
require_once '../controller/dor-leader-method.php';
require_once '../controller/dor-documents-viewer.php';

$isSubmitted = isset($_GET['submitted']) && $_GET['submitted'] == 1;

// Get latest record
$sql = "SELECT TOP 1 RecordId, DorTypeId, ModelId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
$result = $db->execute($sql, [$hostname_id]);

$recordId = $result[0]['RecordId'];
$dorTypeId = $result[0]['DorTypeId'];
$modelId = $result[0]['ModelId'];

// Get Item ID from GenModel
$sql = "SELECT ITEM_ID FROM GenModel WHERE MODEL_ID = ?";
$result = $db->execute($sql, [$modelId]);
$itemId = $result[0]['ITEM_ID'] ?? 0;

// Prepare dimension checks
$dimChecks = $db->execute("SELECT * FROM AtoDimensionCheck WHERE RecordId = ? ORDER BY DimCheckId ASC", [$recordId]);

$indexedDimChecks = [];
$counter = 1;
foreach ($dimChecks as $row) {
  $indexedDimChecks[$counter++] = $row;
}
$drawingFile = $drawing ?? '';
$prepCardFile = $prepCard ?? '';
$workInstructFile = $workInstruction ?? '';

?>
<?php if (isset($_SESSION['flash_success'])): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  showToast("<?= addslashes($_SESSION['flash_success']) ?>", "success");
});
</script>
<?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  showToast("<?= addslashes($_SESSION['flash_error']) ?>", "danger");
});
</script>
<?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <link rel="stylesheet" href="../../css/bootstrap.min.css">
  <link href="../css/leader-dashboard.css" rel="stylesheet">
  <link href="../../css/dor-navbar.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/dor-tablet.css">

  <style>
  /* Ensure thead is sticky and on top */
  /* Make thead sticky and on top */
  thead.sticky-table th {
    position: sticky;
    top: 0;
    z-index: 20;
    /* must be higher than tbody */
    background: white;
    border-bottom: 1px solid #dee2e6;
  }

  /* Ensure table borders don't collapse into each other */
  table.table {
    border-collapse: separate;
    border-spacing: 0;
  }

  thead {
    z-index: 10;
  }

  /* All tbody cells have borders and white background */
  table tbody td {
    border: 1px solid #dee2e6;
    background: #fff;
    position: relative;
    border-bottom: 1px solid #dde2e6 !important;
    z-index: 1;
    /* lower than thead */
  }

  /* First row of tbody: top border (to avoid overlap) */
  table tbody tr:first-child td {
    border-top: 1px solid #dee2e6;
    border-bottom: 1px solid #dde2e6 !important;
    z-index: 1;
  }

  .viewer {
    position: fixed;
    width: 600px;
    height: 500px;
    background: #fff;
    border: 1px solid #aaa;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    z-index: 9999;
    overflow: hidden;
  }

  .viewer .header {
    background: #444;
    color: #fff;
    padding: 8px;
    cursor: move;
    font-weight: bold;
  }

  .viewer iframe,
  .viewer img {
    width: 100%;
    height: calc(100% - 40px);
    /* subtract header height */
    border: none;
    display: block;
  }
  </style>
</head>

<body class="fs-6" style="margin-left: 0; margin-right: 0; padding: 0;">
  <form method="POST">
    <nav class="navbar navbar-expand navbar-light bg-light shadow-sm fixed-top">
      <div class="container-fluid py-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
          <!-- Left group: File viewers -->
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-secondary btn-lg nav-btn-group btn-nav-fixed" id="btnDrawing"
              data-file="<?= htmlspecialchars($drawingFile) ?>" aria-label="Open Drawing">
              Drawing
            </button>

            <button type="button" class="btn btn-secondary btn-lg nav-btn-group btn-nav-fixed" id="btnWorkInstruction"
              data-file="<?= htmlspecialchars($workInstructFile) ?>" aria-label="Open Work Instruction">
              <span class="short-label">WI</span>
              <span class="long-label">Work Instruction</span>
            </button>

            <button type="button" class="btn btn-secondary btn-lg nav-btn-group btn-nav-fixed" id="btnPrepCard"
              data-file="<?= htmlspecialchars($prepCardFile) ?>" aria-label="Open Preparation Card">
              <span class="short-label">Prep Card</span>
              <span class="long-label">Preparation Card</span>
            </button>

            <button type="button" class="btn btn-secondary btn-lg nav-btn-group btn-nav-fixed"
              onclick="window.location.href='dor-leader-dashboard.php'" aria-label="Go to Operator Tablets">
              <span class="short-label">Opt Tab</span>
              <span class="long-label">Operator Tablets</span>
            </button>
          </div>

          <!-- Hidden form values -->
          <input type="hidden" name="record_id" value="<?= $recordId ?>">
          <input type="hidden" name="current_tab_index" id="currentTabInput"
            value="<?= isset($_GET['tab']) ? (int)$_GET['tab'] : 0 ?>">

          <!-- Right group: Navigation -->
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-secondary btn-lg nav-btn-group btn-nav-fixed" id="btnBack"
              aria-label="Go Back">
              Back
            </button>

            <button type="submit" class="btn btn-primary btn-lg nav-btn-group btn-nav-fixed" name="btnNext" id="btnNext"
              aria-label="Proceed to Next Checkpoint">
              <span class="short-label">Next</span>
              <span class="long-label">Proceed to Next Checkpoint</span>
            </button>
          </div>

        </div>
      </div>
    </nav>


    <!-- PDF Viewer Container -->
    <div id="pdfModal"
      style="display:none; position:fixed; top:10%; left:10%; width:80%; height:80%; background:#fff; z-index:1000; box-shadow: 0 0 20px rgba(0,0,0,0.5); overflow:auto;">
      <div style="text-align:right; padding:10px;">
        <button onclick="closePDFViewer()">Close</button>
      </div>
      <div id="pdfViewer" style="padding: 10px;"></div>
    </div>


    <div class="container-fluid py-0 m-0">
      <!-- CheckpointA -->
      <div class="tab-content fixed-top" style="margin-top: 30px; display:none;" id="dorTabContent">
        <div <?php
              $tabIndex = isset($_GET['tab']) ? intval($_GET['tab']) : 0;
              $isTab0Saved = isset($isTab0Saved) ? $isTab0Saved : false;
              ?>
          class="tab-pane fade <?php if ($tabIndex == 0) echo 'show active '; ?><?php if ($isTab0Saved) echo 'read-only'; ?>"
          id="tab-0" role="tabpanel">
          <div class="table-container" style="max-height:90vh; overflow-y: auto; margin-top: 10px;">
            <table class="table table-bordered text-center align-middle w-100">
              <thead class="table-light text-center  sticky-table">
                <tr>
                  <th class="fs-6" colspan="<?= 6 + count($processIndexes) ?>">A. Required Item and Jig Condition VS
                    Work Instruction</th>
                </tr>
                <tr>
                  <th class="fs-6 text-start" rowspan="2" style="width: 25%;">Checkpoints</th>
                  <th class="fs-6" colspan="2" rowspan="2" style="width: 30%;">Criteria</th>
                  <th class="fs-6" colspan="<?= count($processIndexes) ?>" style="width: 30%;">Operator</th>
                  <th class="fs-6" rowspan="2" style="width: 15%;">Leader</th>
                </tr>
                <tr>
                  <?php foreach ($processIndexes as $index): ?>
                  <th style="width: <?= floor(30 / count($processIndexes)) ?>%;">P<?= $index ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php
                $grouped = [];
                foreach ($checkpoints as $cp) {
                  $grouped[$cp['SequenceId']][] = $cp;
                }

                foreach ($grouped as $sequenceId => $group):
                  foreach ($group as $index => $cp):
                    $checkpointId = (int)$cp['CheckpointId'];
                    $good = trim($cp['CriteriaGood']);
                    $notGood = trim($cp['CriteriaNotGood']);
                    $colspanGood = empty($notGood) ? 2 : 1;
                    $leaderDefault = $leaderResponses[$checkpointId] ?? null;
                ?>
                <tr>
                  <?php if ($index === 0): ?>
                  <td class="text-start fs-6" rowspan="<?= count($group) ?>" style="width: 25%;">
                    <?= $cp['SequenceId']; ?>. <?= htmlspecialchars($cp['CheckpointName']); ?>
                  </td>
                  <?php endif; ?>

                  <td class="fs-6" colspan="<?= $colspanGood; ?>"
                    style="width: <?= $colspanGood === 2 ? '30%' : '15%' ?>;">
                    <?= htmlspecialchars($good); ?>
                  </td>

                  <?php if (!empty($notGood)): ?>
                  <td class="fs-6" style="width: 15%;"><?= htmlspecialchars($notGood); ?></td>
                  <?php endif; ?>

                  <?php if (!empty($processIndexes)): ?>
                  <?php foreach ($processIndexes as $procIdx): ?>
                  <?php
                          $procIdx = (int)$procIdx;
                          $debugInfo = "CheckpointID: $checkpointId, ProcessIndex: $procIdx";
                          $responseExists = isset($operatorResponses[$checkpointId][$procIdx]);
                          $rawResponse = $responseExists ? trim($operatorResponses[$checkpointId][$procIdx]) : null;

                          if ($responseExists && $rawResponse !== '') {
                            $displayResponse = htmlspecialchars($rawResponse, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $title = "$debugInfo: $rawResponse";
                          } else {
                            $displayResponse = '<span class="text-muted">-</span>';
                            $title = "$debugInfo: No data";
                          }
                          ?>
                  <td class="fs-6 text-center" style="width: <?= floor(30 / count($processIndexes)) ?>%;"
                    title="<?= htmlspecialchars($title) ?>">
                    <?= $displayResponse ?>
                  </td>
                  <?php endforeach; ?>
                  <?php else: ?>
                  <td class="fs-6 text-center" colspan="1" style="width: 30%;">
                    <span class="text-muted">No operator data available</span>
                  </td>
                  <?php endif; ?>

                  <td class="py-2 px-3 fs-6" style="width: 15%;">
                    <?php
                        $radioName = "leader[$checkpointId]";
                        $hasResponse = isset($leaderResponses[$checkpointId]);
                        ?>

                    <?php if ($hasResponse): ?>
                    <?php
                          $badgeClass = match ($leaderDefault) {
                            'OK' => 'text-success fw-bold',
                            'NG' => 'text-danger fw-bold',
                            'NA' => 'text-secondary fw-bold',
                            default => 'text-muted',
                          };
                          ?>
                    <div class="text-center">
                      <span class="<?= $badgeClass ?>"><?= htmlspecialchars($leaderDefault) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="d-flex justify-content-center align-items-center gap-3 flex-nowrap overflow-auto"
                      style="white-space: nowrap;">
                      <?php foreach (['OK', 'NA', 'NG'] as $val): ?>
                      <div class="form-check d-flex align-items-center m-0" style="gap: 4px;">
                        <input type="radio" name="<?= $radioName ?>"
                          id="leader-<?= $checkpointId ?>-<?= strtolower($val) ?>" class="form-check-input"
                          value="<?= $val ?>" <?= ($leaderDefault === $val) ? 'checked' : '' ?>>
                        <label class="form-check-label m-0" for="leader-<?= $checkpointId ?>-<?= strtolower($val) ?>">
                          <?= $val ?>
                        </label>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach;
                endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="submit" name="btnVisual" id="hiddenVisual" style="display: none;">Submit</button>
        </div>
        <div class="tab-pane fade" id="tab-1" role="tabpanel">
          <div class="mt-5">
            <?php $tabNames = ['Hatsumono', 'Nakamono', 'Owarimono']; ?>
            <!-- Sub-tabs -->
            <ul class="nav nav-tabs" id="visualCheckpointTab" role="tablist">
              <?php foreach ($tabNames as $index => $tab):
                $tabId = strtolower($tab); ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="<?= $tabId ?>-tab" data-bs-toggle="tab"
                  data-bs-target="#<?= $tabId ?>" type="button" role="tab" aria-controls="<?= $tabId ?>"
                  aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                  <?= $tab ?>
                </button>
              </li>
              <?php endforeach; ?>
            </ul>
            <div class="tab-content mt-4" id="visualCheckpointTabContent">
              <?php foreach ($tabNames as $index => $tab):
                $tabId = strtolower($tab); ?>
              <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="<?= $tabId ?>" role="tabpanel"
                aria-labelledby="<?= $tabId ?>-tab">
                <input type="hidden" name="record_id" value="<?= htmlspecialchars($recordId) ?>">
                <input type="hidden" name="production_code"
                  value="<?= htmlspecialchars($_SESSION['production_code'] ?? '') ?>">
                <div class="table-wrapper mt-3" style="border: solid 1px #ddd">
                  <table class="table table-bordered text-center align-middle">
                    <thead class="table-light sticky-table">
                      <tr>
                        <th colspan="4" class="fs-6">B. Visual Inspection Checkpoint</th>
                      </tr>
                      <tr>
                        <th class="fs-6">Checkpoints</th>
                        <th class="fs-6" colspan="2">Criteria</th>
                        <th class="fs-6"><?= $tab ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $groupedVisuals = [];
                        foreach ($visualCheckpoints as $v) {
                          $groupedVisuals[$v['SequenceId']][] = $v;
                        }

                        foreach ($groupedVisuals as $sequenceId => $group):
                          foreach ($group as $index => $v):
                            $checkpointId = $v['CheckpointId'];
                            $typeId = $v['CheckpointTypeId'];
                            $options = $checkpointControlMap[$typeId] ?? ['text'];
                            $value = $v[$tab] ?? '';
                            $isRadio = $options !== ['text'];
                        ?>
                      <tr>
                        <?php if ($index === 0): ?>
                        <td class="text-start fs-6" rowspan="<?= count($group) ?>" style="min-width: 110px;">
                          <?= $v['SequenceId'] ?>. <?= htmlspecialchars($v['CheckpointName']) ?>
                        </td>
                        <?php endif; ?>

                        <td class="fs-6" colspan="<?= empty($v['CriteriaNotGood']) ? 2 : 1 ?>"
                          style="min-width: 100px;">
                          <?= htmlspecialchars($v['CriteriaGood']) ?>
                        </td>

                        <?php if (!empty($v['CriteriaNotGood'])): ?>
                        <td class="fs-6" style="min-width: 100px;">
                          <?= htmlspecialchars($v['CriteriaNotGood']) ?>
                        </td>
                        <?php endif; ?>
                        <td class="fs-6" style="min-width: 220px;">
                          <?php $name = "visual[$checkpointId][$tab]"; ?>
                          <?php if ($isRadio): ?>
                          <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <?php foreach ($options as $opt): ?>
                            <div class="form-check form-check-inline">
                              <input type="radio" class="form-check-input" name="<?= $name ?>" value="<?= $opt ?>"
                                id="<?= $name ?>_<?= strtolower(str_replace(' ', '', $opt)) ?>"
                                <?= $value === $opt ? 'checked' : '' ?>>
                              <label class="form-check-label"
                                for="<?= $name ?>_<?= strtolower(str_replace(' ', '', $opt)) ?>">
                                <?= $opt ?>
                              </label>
                            </div>
                            <?php endforeach; ?>
                          </div>
                          <?php else: ?>
                          <input type="text" name="<?= $name ?>" class="form-control"
                            value="<?= htmlspecialchars($value) ?>">
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  <button type="submit" name="btnProceed" id="btnProceed"
                    class="btn btn-primary float-end">Submit</button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="tab-2" role="tabpanel">
          <div class="container-fluid mt-3" style="max-height: 90vh; overflow-y: auto; position: relative;">
            <table class="table table-bordered text-center align-middle mb-0">
              <thead class="table-light sticky-table">
                <tr>
                  <th class="fs-6 sticky-header" colspan="10" style="top: 0; border-top: 2px solid #dee2e6;">C.
                    Dimension Check</th>
                </tr>
                <tr>
                  <th class="fs-6 sticky-header" rowspan="2" style="width: 8%; min-width: 50px; top: 38px;">No.</th>
                  <?php foreach (['Hatsumono', 'Nakamono', 'Owarimono'] as $section): ?>
                  <th class="fs-6 sticky-header" colspan="3" style="width: 30.666%; top: 38px;"><?= $section ?></th>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <?php for ($i = 1; $i <= 9; $i++): ?>
                  <th class="fs-6 sticky-header" style="width: 10.222%; top: 76px;"><?= (($i - 1) % 3) + 1 ?></th>
                  <?php endfor; ?>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 1; $i <= 20; $i++): ?>
                <?php $row = $indexedDimChecks[$i] ?? []; ?>
                <tr>
                  <td class="fs-6"><?= $i ?></td>
                  <input type="hidden" name="dim_check_id[<?= $i ?>]" value="<?= $row['DimCheckId'] ?? '' ?>">
                  <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                  <?php for ($j = 1; $j <= 3; $j++): ?>
                  <td>
                    <?php
                          $key = ucfirst($section) . $j;
                          $value = $row[$key] ?? ($_POST["{$section}_value_{$j}"][$i] ?? '');
                          ?>
                    <input type="number" class="form-control" name="<?= $section ?>_value_<?= $j ?>[<?= $i ?>]"
                      value="<?= htmlspecialchars($value) ?>" />
                  </td>
                  <?php endfor; ?>
                  <?php endforeach; ?>
                </tr>
                <?php endfor; ?>

                <tr>
                  <td class="fw-bold text-center fs-6">Judge</td>
                  <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                  <?php for ($i = 1; $i <= 3; $i++): ?>
                  <td>
                    <div class="d-flex flex-column align-items-center gap-1">
                      <?php foreach (['OK', 'NA', 'NG'] as $opt): ?>
                      <label class="form-check-label small w-100 text-center">
                        <input type="radio" class="form-check-input me-1" name="judge_<?= $section ?>_<?= $i ?>"
                          value="<?= $opt ?>"
                          <?= (($_POST["judge_{$section}_{$i}"] ?? $record[ucfirst($section) . $i . "Judge"] ?? '') === $opt) ? 'checked' : '' ?>>
                        <?= $opt ?>
                      </label>
                      <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="checkby_<?= $section . $i ?>"
                      value="<?= htmlspecialchars($_POST["checkby_{$section}{$i}"] ?? $record[ucfirst($section) . $i . "CheckBy"] ?? ($_SESSION['production_code'] ?? '')) ?>">
                  </td>
                  <?php endfor; ?>
                  <?php endforeach; ?>
                </tr>

              </tbody>
            </table>
          </div>

          <button type="submit" name="btnSubmit" id="hiddenSubmit" style="display: none;">Submit</button>
        </div>

        <!-- Tab Pane -->
        <div class="tab-pane fade" id="tab-3" role="tabpanel">
          <div class="dor-table-container mt-3" style="max-height: 90vh; overflow-y: auto; position: relative;">
            <!-- Table with integrated DOR Summary in header -->
            <table class="table table-bordered table-dor mb-0 w-100">
              <thead class="table-light text-center sticky-table" style="position: sticky; top: 0; z-index: 15;">
                <!-- DOR Summary Row -->
                <tr style="background: white; border-bottom: 1px solid #dee2e6;">
                  <th colspan="8" style="padding: 12px; border: 1px solid #dee2e6;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <small class="badge bg-light text-dark border p-2" style="font-size: 0.875rem;">
                        <i class="bi bi-box-seam me-1"></i>Total Box Qty: <span id="totalBoxQty"
                          class="fw-bold text-primary">0</span>
                      </small>
                      <small class="badge bg-light text-dark border p-2" style="font-size: 0.875rem;">
                        <i class="bi bi-clock me-1"></i>Total Duration: <span id="totalDuration"
                          class="fw-bold text-primary">0 mins</span>
                      </small>
                      <small class="badge bg-light text-dark border p-2" style="font-size: 0.875rem;">
                        <i class="bi bi-speedometer2 me-1"></i>Ave. Time/Box: <span id="averagePerBox"
                          class="fw-bold text-primary">0 mins</span>
                      </small>
                      <small class="badge bg-light text-dark border p-2" style="font-size: 0.875rem;">
                        <i class="bi bi-pause-circle me-1"></i>Total Downtime: <span id="totalDowntime"
                          class="fw-bold text-primary">0</span>
                      </small>
                    </div>
                  </th>
                </tr>
                <!-- Table Title Row -->
                <tr style="background: white;">
                  <th colspan="8"
                    style="padding: 8px; border-left: 1px solid #dee2e6; border-right: 1px solid #dee2e6; border-bottom: 1px solid #dee2e6;">
                    Daily Operation Record
                  </th>
                </tr>
                <!-- Column Headers Row -->
                <tr style="background: #f8f9fa;">
                  <th style="width: 5%;">#</th>
                  <th style="width: 15%;">Box No.</th>
                  <th style="width: 12%;">Start Time</th>
                  <th style="width: 12%;">End Time</th>
                  <th style="width: 12%;">Duration</th>
                  <th style="width: 22%;">Operator</th>
                  <th style="width: 17%;">Downtime</th>
                  <th style="width: 5%;">*</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Assume there's a single $recordId used for all rows.
                $recordId = $headers[0]['RecordId'] ?? $fallbackRecordIds[0] ?? null;
                $employeeCodes = [];

                if ($recordId) {
                  // Fetch EmployeeCodes once
                  $employeeQuery = $db->execute(
                    "SELECT DISTINCT EmployeeCode FROM AtoDorCheckpointDefinition WHERE RecordId = ? AND IsLeader = 0",
                    [$recordId]
                  );
                  $codes = array_column($employeeQuery, 'EmployeeCode');

                  // Get ModelId and MP from AtoDor and GenModel
                  $atoDor = $db->execute("SELECT ModelId FROM AtoDor WHERE RecordId = ?", [$recordId]);
                  $modelId = $atoDor[0]['ModelId'] ?? null;

                  $mp = 0;
                  if ($modelId) {
                    $genModel = $db->execute("SELECT MP FROM GenModel WHERE MODEL_ID = ?", [$modelId]);
                    $mp = (int)($genModel[0]['MP'] ?? 0);
                  }

                  // Slice operators based on MP
                  $employeeCodes = array_slice($codes, 0, $mp);
                }
                ?>
                <?php
                $modals = [];

                // Get fallback RecordIds directly from AtoDor (for use in all 20 rows)
                $fallbackQuery = $db->execute("SELECT RecordId FROM AtoDor WHERE HostnameId = ?", [$hostnameId]);
                $fallbackRecordIds = array_column($fallbackQuery, 'RecordId');
                $fallbackRecordId = $fallbackRecordIds[0] ?? null;

                // Get employee codes for fallback RecordId
                $fallbackEmployeeCodes = [];
                if ($fallbackRecordId) {
                  $employeeQuery = $db->execute(
                    "SELECT DISTINCT EmployeeCode FROM AtoDorCheckpointDefinition WHERE RecordId = ? AND IsLeader = 0",
                    [$fallbackRecordId]
                  );
                  $fallbackEmployeeCodes = array_column($employeeQuery, 'EmployeeCode');

                  // Get MP (Max Personnel) from GenModel
                  $atoDor = $db->execute("SELECT ModelId FROM AtoDor WHERE RecordId = ?", [$fallbackRecordId]);
                  $modelId = $atoDor[0]['ModelId'] ?? null;

                  $mp = 0;
                  if ($modelId) {
                    $genModel = $db->execute("SELECT MP FROM GenModel WHERE MODEL_ID = ?", [$modelId]);
                    $mp = (int)($genModel[0]['MP'] ?? 0);
                  }

                  // Limit codes to MP
                  $fallbackEmployeeCodes = array_slice($fallbackEmployeeCodes, 0, $mp);
                }

                for ($i = 1; $i <= 20; $i++) {
                  $header = $headers[$i - 1] ?? [];

                  $recordHeaderId = $header['RecordHeaderId'] ?? 'unknown_' . $i;
                  $recordHeaderIdSafe = htmlentities($recordHeaderId);

                  // Default to fallback
                  $employeeCodes = $fallbackEmployeeCodes;

                  // Try to load from AtoDorDetail
                  $operatorResult = $db->execute(
                    "SELECT OperatorCode1, OperatorCode2, OperatorCode3, OperatorCode4 FROM AtoDorDetail WHERE RecordHeaderId = ?",
                    [$recordHeaderId]
                  );
                  if (!empty($operatorResult)) {
                    $operatorRow = $operatorResult[0];
                    $employeeCodesFromDetail = array_filter([
                      $operatorRow['OperatorCode1'] ?? null,
                      $operatorRow['OperatorCode2'] ?? null,
                      $operatorRow['OperatorCode3'] ?? null,
                      $operatorRow['OperatorCode4'] ?? null,
                    ]);

                    if (!empty($employeeCodesFromDetail)) {
                      $employeeCodes = $employeeCodesFromDetail;
                    }
                  }


                  $modalId = "operatorModal_" . htmlspecialchars($recordHeaderId);
                ?>
                <tr data-row-id="<?= $recordHeaderIdSafe ?>"
                  data-mp-requirement="<?= htmlspecialchars($header['MP'] ?? 1) ?>"
                  data-model-name="<?= htmlspecialchars($header['ITEM_ID'] ?? '') ?>">
                  <td class="text-center align-middle" style="width: 5%;">
                    <?= $i ?> <i class="bi bi-qr-code-scan ms-1"></i>
                  </td>
                  <td style="width: 15%;">
                    <input type="text" class="form-control text-center scan-box-no" id="boxNo<?= $i ?>"
                      name="boxNo<?= $i ?>" value="<?= htmlspecialchars($header['BoxNumber'] ?? '') ?>" disabled>
                    <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                    <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
                  </td>

                  <td style="width: 12%;">
                    <input type="text" class="form-control text-center time-input" id="timeStart<?= $i ?>"
                      name="timeStart<?= $i ?>"
                      value="<?= isset($header['TimeStart']) ? date('H:i', $header['TimeStart'] instanceof \DateTime ? $header['TimeStart']->getTimestamp() : strtotime($header['TimeStart'])) : '' ?>"
                      placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" disabled>
                  </td>

                  <td style="width: 12%;">
                    <input type="text" class="form-control text-center time-input" id="timeEnd<?= $i ?>"
                      name="timeEnd<?= $i ?>"
                      value="<?= isset($header['TimeEnd']) ? date('H:i', $header['TimeEnd'] instanceof \DateTime ? $header['TimeEnd']->getTimestamp() : strtotime($header['TimeEnd'])) : '' ?>"
                      placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" disabled>
                  </td>

                  <td class="text-center align-middle" style="width: 12%;">
                    <span id="duration<?= $i ?>" class="duration-value">
                      <?= htmlspecialchars($header['Duration'] ?? '') ?>
                    </span>
                  </td>

                  <td class="text-center align-middle" style="width: 22%;">
                    <div class="d-flex flex-column align-items-center">
                      <button type="button" class="btn btn-outline-primary btn-sm btn-operator mb-1"
                        data-bs-toggle="modal" data-bs-target="#operatorModal<?= $recordHeaderId ?>"
                        data-record-id="operator<?= $recordHeaderId ?>">
                        <i class="bi bi-person-plus"></i> View Operators
                      </button>

                      <div class="operator-codes d-flex flex-wrap justify-content-center"
                        id="operatorList<?= $recordHeaderId ?>">
                        <?php foreach (array_unique($employeeCodes) as $code):
                            $name = $operatorMap[$code] ?? 'No operators'; ?>
                        <small class="badge bg-light text-dark border me-1 mb-1" title="<?= htmlspecialchars($name) ?>">
                          <?= htmlspecialchars($code) ?>
                        </small>
                        <?php endforeach; ?>
                      </div>

                      <input type="hidden" id="operators<?= htmlspecialchars($recordHeaderId) ?>"
                        value="<?= htmlspecialchars(implode(',', $employeeCodes)) ?>">
                    </div>
                  </td>

                  <td class="text-center align-middle" style="width: 17%;">
                    <?php
                      $atoDorDetails = $controller->AtoDor();
                      $downtimeOptions = $controller->getDowntimeList();
                      $actionTakenOptions = $controller->getActionList();
                      $remarksOptions = $controller->getRemarksList();

                      // Map AtoDorDetail by RecordHeaderId
                      $details = [];
                      foreach ($atoDorDetails as $row) {
                        $details[$row['RecordHeaderId']][] = $row;
                      }

                      // Map DowntimeId => downtime data
                      $downtimeMap = [];
                      foreach ($downtimeOptions as $downtime) {
                        $downtimeMap[$downtime['DowntimeId']] = $downtime;
                      }

                      // Map ActionTakenId => action data
                      $actionTakenMap = [];
                      foreach ($actionTakenOptions as $action) {
                        $actionTakenMap[$action['ActionTakenId']] = [
                          'ActionDescription' => $action['ActionTakenName']
                        ];
                      }
                      ?>
                    <div class="d-flex flex-column align-items-center">
                      <button type="button" class="btn btn-outline-secondary btn-sm p-1 mb-1 downtime-trigger"
                        data-bs-toggle="modal" data-bs-target="#downtimeModal<?= $recordHeaderIdSafe ?>"
                        data-record-id="<?= $recordHeaderIdSafe ?>">
                        <i class="bi bi-clock-history"></i> View Downtime
                      </button>

                      <div class="downtime-info d-flex flex-wrap justify-content-center"
                        id="downtimeInfo<?= $recordHeaderIdSafe ?>">
                        <?php
                          $allDetails = $details[$recordHeaderId] ?? [];

                          if (empty($allDetails)) {
                            echo '<small class="badge bg-light text-dark border me-1 mb-1">No Downtime</small>';
                          } else {
                            foreach ($allDetails as $detail) {
                              $downtimeId = $detail['DowntimeId'] ?? null;
                              $actionTakenId = $detail['ActionTakenId'] ?? null;

                              $downtimeCode = $downtimeMap[$downtimeId]['DowntimeCode'] ?? null;
                              $actionTakenTitle = $actionTakenMap[$actionTakenId]['ActionDescription'] ?? 'No Description';

                              if ($downtimeCode) {
                                echo '<small class="badge bg-light text-dark border me-1 mb-1" title="' . htmlspecialchars($actionTakenTitle) . '">'
                                  . htmlspecialchars($downtimeCode) .
                                  '</small>';
                              }
                            }
                          }
                          ?>
                      </div>
                    </div>
                  </td>


                  <td class="text-center align-middle" style="width: 5%;">
                    <button type="button" class="btn btn-outline-danger btn-sm delete-row" data-row-id="<?= $i ?>"
                      title="Delete Row" onclick="clearRowFields(<?= $i ?>)">
                      <span style="font-size: 1.2rem; font-weight: bold;">×</span>
                    </button>
                  </td>
                </tr>

                <!-- Operator Modal -->
                <?php ob_start(); ?>
                <div class="modal fade operator-modal" id="operatorModal<?= $recordHeaderId ?>" tabindex="-1"
                  aria-labelledby="operatorModalLabel<?= $recordHeaderId ?>" aria-hidden="true"
                  data-record-id="<?= $recordHeaderId ?>">

                  <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content shadow">
                      <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="operatorModalLabel<?= $recordHeaderId ?>">
                          Manage Operators for Row #<?= htmlspecialchars($i) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                          aria-label="Close"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <input type="text" class="form-control operator-search"
                            placeholder="Search operator name or code...">
                          <div class="search-results mt-2"></div>
                        </div>

                        <div class="current-operators d-flex flex-wrap gap-3 justify-content-start">
                          <?php foreach (array_unique($employeeCodes) as $code):
                              $name = $operatorMap[$code] ?? 'No operator'; ?>
                          <div class="card border-primary operator-card" data-code="<?= htmlspecialchars($code) ?>"
                            style="min-width: 160px;">
                            <div class="card-body text-center p-2">
                              <h6 class="card-title mb-1"><?= htmlspecialchars($name) ?></h6>
                              <small class="text-muted"><?= htmlspecialchars($code) ?></small>
                              <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-remove-operator">
                                <i class="bi bi-x-circle"></i> Remove
                              </button>
                            </div>
                          </div>
                          <?php endforeach; ?>
                        </div>

                        <input type="hidden" class="updated-operators"
                          id="operatorsHidden<?= htmlspecialchars($recordHeaderId) ?>"
                          value="<?= htmlspecialchars(implode(',', $employeeCodes)) ?>">
                      </div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-success btn-save-operators"
                          data-record-id="<?= $recordHeaderId ?>">
                          Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Downtime Modal -->
                <div class="modal fade" id="downtimeModal<?= $recordHeaderId ?>" tabindex="-1"
                  aria-labelledby="downtimeModalLabel<?= $recordHeaderId ?>" aria-hidden="true"
                  data-record-id="<?= $recordHeaderId ?>">

                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content shadow">
                      <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title" id="downtimeModalLabel<?= $recordHeaderId ?>">
                          Manage Downtime for Row #<?= htmlspecialchars($i) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                          aria-label="Close"></button>
                      </div>

                      <div class="modal-body">
                        <!-- Time Inputs -->
                        <div class="mb-3">
                          <table class="table table-bordered text-center align-middle">
                            <thead class="table-light">
                              <tr>
                                <th style="width:25%;">Time Start</th>
                                <th style="width:25%;">Time End</th>
                                <th>Duration</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td>
                                  <input type="text" class="form-control text-center time-start" placeholder="HH:mm"
                                    maxlength="5" pattern="[0-9]{2}:[0-9]{2}" id="timeStart<?= $recordHeaderId ?>" />
                                </td>
                                <td>
                                  <input type="text" class="form-control text-center time-end" placeholder="HH:mm"
                                    maxlength="5" pattern="[0-9]{2}:[0-9]{2}" id="timeEnd<?= $recordHeaderId ?>" />
                                </td>
                                <td>
                                  <span id="duration<?= $recordHeaderId ?>" class="badge bg-secondary">00:00</span>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

                        <!-- Downtime Dropdown -->
                        <div class="mb-3">
                          <label class="form-label">Downtime</label>
                          <div class="searchable-dropdown">
                            <input type="text" class="form-control" onkeyup="filterDropdown(this)"
                              placeholder="Search Downtime...">
                            <ul class="dropdown-list list-group position-absolute w-100"
                              style="max-height: 150px; overflow-y: auto; z-index: 1000; display: none;">
                              <?php foreach ($downtimeOptions as $d): ?>
                              <li class="list-group-item list-group-item-action" onclick="selectOption(this)"
                                data-id="<?= $d['DowntimeId'] ?>">
                                <?= htmlspecialchars($d['DowntimeCode']) ?> -
                                <?= htmlspecialchars($d['DowntimeName']) ?>
                              </li>
                              <?php endforeach; ?>
                              <li class="list-group-item list-group-item-action text-primary fw-bold"
                                onclick="selectOption(this)" data-id="custom">Others</li>
                            </ul>
                            <input type="hidden" id="downtimeSelect<?= $recordHeaderId ?>" />
                            <input type="text" id="downtimeInput<?= $recordHeaderId ?>" class="form-control mt-2 d-none"
                              placeholder="Enter custom downtime">
                          </div>
                        </div>

                        <!-- Action Taken Dropdown -->
                        <div class="mb-3">
                          <label class="form-label">Action Taken</label>
                          <div class="searchable-dropdown">
                            <input type="text" class="form-control" onkeyup="filterDropdown(this)"
                              placeholder="Search Action Taken...">
                            <ul class="dropdown-list list-group position-absolute w-100"
                              style="max-height: 150px; overflow-y: auto; z-index: 1000; display: none;">
                              <?php foreach ($actionTakenOptions as $a): ?>
                              <li class="list-group-item list-group-item-action" onclick="selectOption(this)"
                                data-id="<?= $a['ActionTakenId'] ?>">
                                <?= htmlspecialchars($a['ActionTakenCode']) ?> -
                                <?= htmlspecialchars($a['ActionTakenName']) ?>
                              </li>
                              <?php endforeach; ?>
                              <li class="list-group-item list-group-item-action text-primary fw-bold"
                                onclick="selectOption(this)" data-id="custom">Others</li>
                            </ul>
                            <input type="hidden" id="actionTakenSelect<?= $recordHeaderId ?>" />
                            <input type="text" id="actionTakenInput<?= $recordHeaderId ?>"
                              class="form-control mt-2 d-none" placeholder="Enter custom action taken">
                          </div>
                        </div>

                        <!-- Remarks Dropdown -->
                        <div class="mb-3">
                          <label class="form-label">Remarks</label>
                          <div class="searchable-dropdown">
                            <input type="text" class="form-control" onkeyup="filterDropdown(this)"
                              placeholder="Search Remarks...">
                            <ul class="dropdown-list list-group position-absolute w-100"
                              style="max-height: 150px; overflow-y: auto; z-index: 1000; display: none;">
                              <?php foreach ($remarksOptions as $r): ?>
                              <li class="list-group-item list-group-item-action" onclick="selectOption(this)"
                                data-id="<?= $r['RemarksId'] ?>">
                                <?= htmlspecialchars($r['RemarksCode']) ?> -
                                <?= htmlspecialchars($r['RemarksName']) ?>
                              </li>
                              <?php endforeach; ?>
                              <li class="list-group-item list-group-item-action text-primary fw-bold"
                                onclick="selectOption(this)" data-id="custom">Others</li>
                            </ul>
                            <input type="hidden" id="remarksSelect<?= $recordHeaderId ?>" />
                            <input type="text" id="remarksInput<?= $recordHeaderId ?>" class="form-control mt-2 d-none"
                              placeholder="Enter custom remarks">
                          </div>
                        </div>

                        <!-- PIC -->
                        <div class="mb-3">
                          <label for="picInput<?= $recordHeaderId ?>" class="form-label">PIC (Person In
                            Charge)</label>
                          <input type="text" id="picInput<?= $recordHeaderId ?>" class="form-control"
                            placeholder="Enter PIC name">
                        </div>
                      </div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-success btn-save-downtime"
                          data-record-id="<?= $recordHeaderId ?>">
                          Save Downtime
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
                  $modals[] = ob_get_clean();
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </form>

  <?php
  echo implode("\n", $modals);
  ?>
  <?php
  $downtimeMap = $downtimeMap ?? [];
  $actionTakenMap = $actionTakenMap ?? [];
  ?>
  <script>
  // window.downtimeMap = <?= json_encode($downtimeMap, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
  // window.actionTakenMap = <?= json_encode($actionTakenMap, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
  window.operatorMap = <?php echo json_encode($operatorMap); ?> || {};
  //console.log("Loaded downtimeMap:", window.downtimeMap);
  </script>

  <script src="../../js/pdf.min.js"></script>
  <script src="../../js/hammer.min.js"></script>
  <script src="../../js/bootstrap.bundle.min.js"></script>
  <script src="../js/dor-downtime.js"></script>
  <script src="../js/dor-operator.js"></script>
  <script src="../js/dor-tab.js"></script>
  <script src="../js/viewer.js"></script>
  <script>
  function clearRowFields(rowNum) {
    if (!confirm('Are you sure you want to delete this row? This action cannot be undone.')) return;
    var boxNo = document.getElementById('boxNo' + rowNum);
    var timeStart = document.getElementById('timeStart' + rowNum);
    var timeEnd = document.getElementById('timeEnd' + rowNum);
    var duration = document.getElementById('duration' + rowNum);
    var rows = document.querySelectorAll('tr[data-row-id]');
    var recordHeaderId = null;
    if (rows[rowNum - 1]) {
      recordHeaderId = rows[rowNum - 1].getAttribute('data-row-id');
    }
    if (!recordHeaderId) {
      if (boxNo) boxNo.value = '';
      if (timeStart) timeStart.value = '';
      if (timeEnd) timeEnd.value = '';
      if (duration) duration.textContent = '';
      return;
    }
    // Send JSON POST to controller/dor-dor.php
    fetch('../controller/dor-dor.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          type: 'deleteHeader',
          recordHeaderId: recordHeaderId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (boxNo) boxNo.value = '';
          if (timeStart) timeStart.value = '';
          if (timeEnd) timeEnd.value = '';
          if (duration) duration.textContent = '';

          // Refresh the page after successful deletion
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          alert('Failed to delete row data: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(() => {
        alert('Failed to delete row data.');
      });
  }

  // DOR Summary Functions
  function calculateSummary() {
    let totalBoxQty = 0;
    let totalDurationMins = 0;

    // Count filled box numbers
    for (let i = 1; i <= 20; i++) {
      const boxNo = document.getElementById(`boxNo${i}`);
      if (boxNo && boxNo.value.trim() !== '') {
        totalBoxQty++;
      }

      // Calculate duration
      const durationElement = document.getElementById(`duration${i}`);
      if (durationElement && durationElement.textContent.trim() !== '') {
        const durationText = durationElement.textContent.trim();

        // Handle different duration formats
        if (durationText.includes('hours') || durationText.includes('mins')) {
          // Parse formats like "2 hours 30 mins", "2 hours", "30 mins"
          const hoursMatch = durationText.match(/(\d+)\s*hours/);
          const minutesMatch = durationText.match(/(\d+)\s*mins/);

          const hours = hoursMatch ? parseInt(hoursMatch[1]) : 0;
          const minutes = minutesMatch ? parseInt(minutesMatch[1]) : 0;

          totalDurationMins += (hours * 60) + minutes;
        } else if (durationText.includes('h') || durationText.includes('m')) {
          // Parse formats like "2h 30m", "1h", "45m"
          const hoursMatch = durationText.match(/(\d+)h/);
          const minutesMatch = durationText.match(/(\d+)m/);

          const hours = hoursMatch ? parseInt(hoursMatch[1]) : 0;
          const minutes = minutesMatch ? parseInt(minutesMatch[1]) : 0;

          totalDurationMins += (hours * 60) + minutes;
        } else if (durationText.includes(':')) {
          // Handle "HH:mm" format
          const [hours, minutes] = durationText.split(':').map(Number);
          totalDurationMins += (hours * 60) + minutes;
        } else {
          // Handle plain numbers (assume minutes)
          totalDurationMins += parseInt(durationText) || 0;
        }
      }
    }

    // Count downtime incidents from badges (exclude "No Downtime" messages)
    let totalDowntimeCount = 0;
    document.querySelectorAll('[id^="downtimeInfo"]').forEach(container => {
      const badges = container.querySelectorAll('.badge');
      badges.forEach(badge => {
        const badgeText = badge.textContent.trim().toLowerCase();
        // Exclude any badge that contains "no downtime" or similar messages
        if (!badgeText.includes('no downtime') &&
          !badgeText.includes('no downtime recorded') &&
          !badgeText.includes('downtime recorded') &&
          badgeText !== '' &&
          badgeText !== '-') {
          totalDowntimeCount++;
        }
      });
    });

    // Update display with proper formatting
    document.getElementById('totalBoxQty').textContent = totalBoxQty;

    const totalHours = Math.floor(totalDurationMins / 60);
    const remainingMins = totalDurationMins % 60;

    // Format output to match your preferred format
    if (totalHours > 0 && remainingMins > 0) {
      document.getElementById('totalDuration').textContent = `${totalHours} hours ${remainingMins} mins`;
    } else if (totalHours > 0) {
      document.getElementById('totalDuration').textContent = `${totalHours} hours`;
    } else {
      document.getElementById('totalDuration').textContent = `${remainingMins} mins`;
    }

    document.getElementById('totalDowntime').textContent = totalDowntimeCount;

    // Calculate and display average per box
    let averagePerBoxMins = 0;
    if (totalBoxQty > 0) {
      averagePerBoxMins = Math.round(totalDurationMins / totalBoxQty);
    }

    const avgHours = Math.floor(averagePerBoxMins / 60);
    const avgRemainingMins = averagePerBoxMins % 60;

    if (avgHours > 0 && avgRemainingMins > 0) {
      document.getElementById('averagePerBox').textContent = `${avgHours} hours ${avgRemainingMins} mins`;
    } else if (avgHours > 0) {
      document.getElementById('averagePerBox').textContent = `${avgHours} hours`;
    } else {
      document.getElementById('averagePerBox').textContent = `${avgRemainingMins} mins`;
    }
  }

  function refreshSummary() {
    calculateSummary();
    showToast('Summary refreshed successfully!', 'success');
  }

  function exportSummary() {
    const summary = {
      totalBoxQty: document.getElementById('totalBoxQty').textContent,
      totalDuration: document.getElementById('totalDuration').textContent,
      totalDowntime: document.getElementById('totalDowntime').textContent,
      averagePerBox: document.getElementById('averagePerBox').textContent,
      timestamp: new Date().toISOString()
    };

    const dataStr = JSON.stringify(summary, null, 2);
    const dataBlob = new Blob([dataStr], {
      type: 'application/json'
    });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `dor-summary-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);

    showToast('Summary exported successfully!', 'success');
  }

  // Auto-refresh summary when data changes
  function initSummaryAutoRefresh() {
    // Watch for changes in box numbers and durations
    const observers = [];

    for (let i = 1; i <= 20; i++) {
      const boxNo = document.getElementById(`boxNo${i}`);
      const duration = document.getElementById(`duration${i}`);

      if (boxNo) {
        boxNo.addEventListener('input', calculateSummary);
      }

      if (duration) {
        const observer = new MutationObserver(calculateSummary);
        observer.observe(duration, {
          childList: true,
          subtree: true
        });
        observers.push(observer);
      }
    }

    // Watch for downtime changes
    document.querySelectorAll('[id^="downtimeInfo"]').forEach(container => {
      const observer = new MutationObserver(calculateSummary);
      observer.observe(container, {
        childList: true,
        subtree: true
      });
      observers.push(observer);
    });

    // Initial calculation
    calculateSummary();
  }

  // Initialize when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    initSummaryAutoRefresh();
  });
  </script>

</body>

</html>