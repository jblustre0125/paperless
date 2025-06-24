<?php
$title = "DOR";
session_start();
ob_start();

require_once "../config/dbop.php";
require_once "../config/method.php";

$db1 = new DbOp(1);

$errorPrompt = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $response = ['success' => false, 'errors' => []];

  // Handle operator validation request
  if (isset($_POST['action']) && $_POST['action'] === 'validate_operator') {
    $employeeCode = trim($_POST['employeeCode'] ?? '');

    if (empty($employeeCode)) {
      $response['valid'] = false;
      $response['message'] = 'Employee ID is required.';
    } else {
      $spRd1 = "EXEC RdGenEmployeeAll @EmployeeCode=?, @IsActive=1, @IsLoggedIn=0";
      $res1 = $db1->execute($spRd1, [$employeeCode], 1);

      if (!empty($res1)) {
        $response['valid'] = true;
        $response['message'] = 'Employee ID is valid.';
        $response['employeeName'] = $res1[0]['EmployeeName'] ?? '';
      } else {
        $response['valid'] = false;
        $response['message'] = 'Invalid employee ID.';
      }
    }

    echo json_encode($response);
    exit;
  }

  // Handle operator autosuggest request
  if (isset($_POST['action']) && $_POST['action'] === 'suggest_operator') {
    $searchTerm = trim($_POST['searchTerm'] ?? '');

    if (!empty($searchTerm)) {
      // Query for employee suggestions
      $suggestQuery = "SELECT EmployeeCode, EmployeeName 
                      FROM GenEmployeeAll 
                      WHERE IsActive = 1 AND IsLoggedIn = 0 
                      AND (EmployeeCode LIKE ? OR EmployeeName LIKE ?)
                      ORDER BY EmployeeName";
      $res = $db1->execute($suggestQuery, ['%' . $searchTerm . '%', '%' . $searchTerm . '%']);

      $response['suggestions'] = $res ?: [];
    } else {
      $response['suggestions'] = [];
    }

    echo json_encode($response);
    exit;
  }

  // Regular form submit handler (btnProceed)
  if (empty($response['errors'])) {
    if (isset($_POST['btnProceed'])) {
      $recordId = $_SESSION['dorRecordId'] ?? 0;
      if ($recordId > 0) {
        foreach ($_POST as $key => $value) {
          // if (preg_match('/^Process(\d+)_(\d+)$/', $key, $matches)) {
          //   $processIndex = (int)$matches[1];
          //   $sequenceId = (int)$matches[2];

          //   $meta = $_POST['meta'][$key] ?? [];
          //   $checkpointId = $meta['checkpointId'] ?? null;
          //   $employeeCode = $_POST["userCode{$processIndex}"] ?? '';

          //   if ($checkpointId && $employeeCode !== '') {
          //     $insSp = "EXEC InsAtoDorCheckpointDefinition @RecordId=?, @ProcessIndex=?, @EmployeeCode=?, @CheckpointId=?, @CheckpointResponse=?";
          //     $db1->execute($insSp, [
          //       $recordId,
          //       $processIndex,
          //       $employeeCode,
          //       $checkpointId,
          //       $value
          //     ]);
          //   }
          // }
        }
      }

      $response['success'] = true;
      $response['redirectUrl'] = "dor-home.php";
    } else {
      $response['success'] = false;
      $response['errors'][] = "Error.";
    }
  }

  echo json_encode($response);
  exit;
}

// Check if required session variables exist
if (!isset($_SESSION['dorTypeId']) || !isset($_SESSION['dorModelId'])) {
  header("Location: dor-home.php");
  exit;
}

$employeeCode = isset($_SESSION['employeeCode']) ? $_SESSION['employeeCode'] : 'Unknown Operator';

$drawingFile = '';
$workInstructFile = '';
$preCardFile = '';

try {
  $drawingFile = getDrawing($_SESSION["dorTypeId"], $_SESSION['dorModelId']) ?? '';
} catch (Throwable $e) {
  $drawingFile = '';
}

try {
  $workInstructFile = getWorkInstruction($_SESSION["dorTypeId"], $_SESSION['dorModelId']) ?? '';
} catch (Throwable $e) {
  $workInstructFile = '';
}

try {
  $preCardFile = getPreparationCard($_SESSION['dorModelId']) ?? '';
} catch (Throwable $e) {
  $preCardFile = '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title ?? ''); ?></title>
  <link href="../css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/dor-dor.css" rel="stylesheet">
  <link href="../css/dor-navbar.css" rel="stylesheet">
  <link href="../css/dor-pip-viewer.css" rel="stylesheet">
</head>

<body>
  <nav class="navbar navbar-expand navbar-light bg-light shadow-sm fixed-top">
    <div class="container-fluid px-2 py-2">
      <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
        <!-- Left-aligned group -->
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group" id="btnDrawing">Drawing</button>
          <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group" id="btnWorkInstruction">
            <span class="short-label">WI</span>
            <span class="long-label">Work Instruction</span>
          </button>
          <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group" id="btnPrepCard">
            <span class="short-label">Prep Card</span>
            <span class="long-label">Preparation Card</span>
          </button>
        </div>

        <!-- Right-aligned group -->
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-secondary btn-lg nav-btn-group" onclick="goBack()">Back</button>
          <button type="submit" class="btn btn-primary btn-lg nav-btn-group" id="btnProceed" name="btnProceed">
            <span class="short-label">Save DOR</span>
            <span class="long-label">Save DOR</span>
          </button>
        </div>
      </div>
    </div>
  </nav>

  <form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
    <!-- Temporary debug section -->
    <?php if (isset($_GET['debug'])): ?>
      <div class="alert alert-info">
        <strong>Debug Info:</strong><br>
        Employee Codes in Session:<br>
        <?php
        for ($j = 1; $j <= 4; $j++) {
          echo "userCode$j: " . ($_SESSION["userCode$j"] ?? 'NOT SET') . "<br>";
        }
        echo "Current Employee Code: " . $employeeCode . "<br>";
        ?>
      </div>
    <?php endif; ?>

    <div class="sticky-dor-bar">
      <div class="container-fluid py-0">
        <div class="sticky-table-header">
          <div class="dor-summary">
            <span class="summary-item">Total Box Qty: <span id="totalBoxQty">0</span></span>
            <span class="summary-item">Total Duration: <span id="totalDuration">0 mins</span></span>
            <span class="summary-item">Total Downtime: <span id="totalDowntime">0</span></span>
          </div>
        </div>
      </div>
      <!-- Match EXACTLY the same container structure as the body table -->
      <div class="container-fluid py-0">
        <div class="table-container">
          <table class="table-dor table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Box No.</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
                <th>Operator</th>
                <th>Downtime</th>
                <th>*</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>

    <div class="container-fluid py-0">
      <div class="table-container">
        <table class="table-dor table table-bordered align-middle">
          <tbody>
            <?php for ($i = 1; $i <= 20; $i++) { ?>
              <tr data-row-id="<?= $i ?>">
                <td class="clickable-row text-center align-middle row-number-cell">
                  <?= $i ?>
                  <i class="bi bi-qr-code-scan ms-1"></i>
                </td>
                <td class="box-no-column">
                  <input type="text" class="form-control scan-box-no box-no-input text-center" id="boxNo<?= $i ?>" name="boxNo<?= $i ?>" disabled>
                  <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                  <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
                </td>
                <td class="time-column">
                  <input type="text" class="form-control scan-box-no text-center time-input" id="timeStart<?= $i ?>" pattern="[0-9]{2}:[0-9]{2}" placeholder="HH:mm" maxlength="5">
                </td>
                <td class="time-column">
                  <input type="text" class="form-control scan-box-no text-center time-input" id="timeEnd<?= $i ?>" pattern="[0-9]{2}:[0-9]{2}" placeholder="HH:mm" maxlength="5" disabled>
                </td>
                <td class="duration-column text-center align-middle">
                  <span id="duration<?= $i ?>" class="duration-value"></span>
                </td>
                <td class="operator-column align-middle text-center">
                  <div class="action-container">
                    <button type="button" class="btn btn-outline-primary btn-sm btn-operator" id="operator<?= $i ?>">
                      <i class="bi bi-person-plus"></i> Manage Operators
                    </button>
                    <div class="operator-codes" id="operatorList<?= $i ?>">
                      <?php
                      // Get employee codes from session
                      $employeeCodes = [];
                      for ($j = 1; $j <= 4; $j++) {
                        if (isset($_SESSION["userCode$j"]) && !empty($_SESSION["userCode$j"])) {
                          $employeeCodes[] = $_SESSION["userCode$j"];
                        }
                      }

                      // If no employee codes found in session, use current operator as fallback
                      if (empty($employeeCodes)) {
                        $employeeCodes[] = $employeeCode;
                      }

                      // Add sample employee codes for row 2 to demonstrate 2x2 layout
                      if ($i === 2) {
                        $employeeCodes = ['2503-005', '2503-004', 'FMB-0826', 'FMB-0570'];
                      }

                      // Debug: Check what employee codes are being processed
                      if ($i === 2) {
                        echo "<!-- Debug: Row $i has " . count($employeeCodes) . " employee codes -->";
                      }

                      // Display employee codes as badges
                      foreach ($employeeCodes as $code) {
                        echo "<small class='badge bg-light text-dark border'>" . htmlspecialchars($code) . "</small>";
                      }
                      ?>
                    </div>
                  </div>
                  <input type="hidden" id="operators<?= $i ?>" name="operators<?= $i ?>" value="<?= implode(',', $employeeCodes) ?>">
                </td>
                <td class="remarks-column align-middle text-center">
                  <div class="action-container">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="downtime<?= $i ?>">
                      <i class="bi bi-clock-history"></i> Manage Downtime
                    </button>
                    <div class="downtime-info" id="downtimeInfo<?= $i ?>">
                      <?php
                      // This logic now correctly uses the $employeeCodes array defined in the previous column
                      // to determine how many placeholder badges are needed to ensure vertical alignment.

                      // We define a sample downtime record set for row 2 for demonstration
                      $downtimeRecords = [];
                      if ($i === 2) {
                        $downtimeRecords = ['Machine Setup', 'Material Change', 'Break Time', 'Quality Check'];
                      }

                      // If there are no actual downtime records, we create invisible placeholders.
                      if (empty($downtimeRecords)) {
                        $operatorBadgeCount = count($employeeCodes); // Count badges from the operators column
                        for ($k = 0; $k < $operatorBadgeCount; $k++) {
                          // Each placeholder has the same classes as a real badge plus 'placeholder-badge' to make it invisible.
                          echo "<small class='badge placeholder-badge'>&nbsp;</small>";
                        }
                      } else {
                        // If there are records, display them as normal.
                        foreach ($downtimeRecords as $record) {
                          echo "<small class='badge bg-light text-dark border'>" . htmlspecialchars($record) . "</small>";
                        }
                      }
                      ?>
                    </div>
                  </div>
                </td>
                <td class="delete-column align-middle text-center">
                  <button type="button" class="btn btn-outline-danger btn-sm delete-row" data-row-id="<?= $i ?>" title="Delete Row">
                    <span style="font-size: 1.2rem; font-weight: bold;">×</span>
                  </button>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <!-- <button type="button" class="btn btn-primary floating-legends-btn drawer-style" data-bs-toggle="modal" data-bs-target="#legendsModal">
    <i class="bi bi-info-circle"></i>
  </button>

  <div class="modal fade" id="legendsModal" tabindex="-1" aria-labelledby="legendsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header" id="legendsModalHeader">
          <h5 class="modal-title" id="legendsModalLabel">LEGENDS</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="legendsModalBody">
          <div class="legend-content">
            <div class="legend-row">
              <div class="legend-group">
                <div><strong>A. Jig Alarm</strong></div>
                <div><span class="legend-code">TS</span> - Tape Sensor</div>
                <div><span class="legend-code">TC</span> - Toggle Clamp</div>
                <div><span class="legend-code">W1</span> - Wire 1 LED not ON</div>
                <div><span class="legend-code">W2</span> - Wire 2 LED not ON</div>
                <div><span class="legend-code">ST</span> - Stop tape trigger</div>
                <div><span class="legend-code">CT</span> - Connector trigger</div>
              </div>
              <div class="legend-group">
                <div><strong>B. Abnormality</strong></div>
                <div><span class="legend-code">WS</span> - Wrong Setting</div>
                <div><span class="legend-code">CC</span> - Counter complete</div>
                <div><span class="legend-code">DP</span> - Drop Parts</div>
                <div><span class="legend-code">E</span> - Excess parts</div>
                <div><span class="legend-code">L</span> - Lacking parts</div>
                <div><span class="legend-code">IN</span> - Incoming NG parts</div>
                <div><span class="legend-code">IP</span> - In process defects</div>
                <div><span class="legend-code">FOC</span> - For confirmation harness</div>
              </div>
              <div class="legend-group">
                <div><strong>C. Remarks</strong></div>
                <div><span class="legend-code">FC</span> - For Continue</div>
                <div><span class="legend-code">C</span> - Continuation</div>
                <div><span class="legend-code">AM</span> - Affected of AM</div>
              </div>
              <div class="legend-group">
                <div><strong>D. Action Taken</strong></div>
                <div><span class="legend-code">RJ</span> - Reset jig counter</div>
                <div><span class="legend-code">RH</span> - Reset jig then recheck Spcs.</div>
                <div><span class="legend-code">RP</span> - Check affected drop parts</div>
                <div><span class="legend-code">RL</span> - Return excess parts</div>
                <div><span class="legend-code">RLS</span> - Return excess then sort</div>
                <div><span class="legend-code">CP</span> - Change part</div>
                <div><span class="legend-code">CPS</span> - Change part then sort</div>
                <div><span class="legend-code">HP</span> - Hold affected part</div>
                <div><span class="legend-code">HB</span> - Hold affected box</div>
                <div><span class="legend-code">HL</span> - Hold affected lot</div>
                <div><span class="legend-code">RC</span> - Rework then check</div>
                <div><span class="legend-code">RM</span> - Request rework check</div>
                <div><span class="legend-code">COG</span> - Confirm as GOOD</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div> -->

  <!-- QR Code Scanner Modal -->
  <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Scan ID Tag</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <video id="qr-video" autoplay muted playsinline></video>
          <p class="text-muted mt-2">Align the QR code within the frame.</p>
        </div>
        <div class="modal-footer d-flex justify-content-between">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap Modal for Error Messages -->
  <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content border-danger">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="errorModalLabel">Error Summary</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="modalErrorMessage">
          <!-- Error messages will be injected here by JS -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Operator Management Modal -->
  <div class="modal fade" id="operatorModal" tabindex="-1" aria-labelledby="operatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 700px;">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white py-3">
          <div class="d-flex justify-content-between align-items-center w-100">
            <h5 class="modal-title mb-0" id="operatorModalLabel">Manage Operators - Row <span id="operatorRowNumber"></span></h5>
            <div class="d-flex align-items-center">
              <span class="text-white me-3">Lot Number: <span id="modalLotNumber" class="fw-bold"></span></span>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-8">
              <div class="autosuggest-wrapper">
                <label for="operatorSearch" class="form-label mb-2">Add Operator</label>
                <div class="position-relative">
                  <input type="text"
                    class="form-control"
                    id="operatorSearch"
                    placeholder="Search by Employee ID or Name"
                    autocomplete="off"
                    style="height: 45px; font-size: 16px;">
                  <div id="operatorValidationMessage" class="position-absolute" style="top: -20px; right: 0; font-size: 12px;"></div>
                </div>
                <div id="operatorSuggestions" class="bg-white border rounded mt-1" style="max-height: 150px; overflow-y: auto; display: none;"></div>
              </div>
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
              <button type="button" class="btn btn-primary w-100" id="addOperatorBtn" disabled
                style="height: 45px; font-size: 16px;">
                <i class="bi bi-plus-circle"></i> Add
              </button>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <h6 class="mb-3">Current Operators</h6>
              <div id="currentOperators" class="border rounded p-3" style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                <p class="text-muted text-center mb-0">No operators assigned yet.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="height: 45px; font-size: 16px;">Close</button>
          <button type="button" class="btn btn-primary" id="saveOperatorsBtn" style="height: 45px; font-size: 16px;">Save Changes</button>
        </div>
      </div>
    </div>
  </div>
  </form>

  <!-- PiP Viewer HTML: supports maximize and minimize modes -->
  <div id="pipViewer" class="pip-viewer d-none maximize-mode">
    <div id="pipHeader">
      <div id="pipProcessLabels" class="pip-process-labels">
        <!-- Process labels will be dynamically inserted here -->
      </div>
      <div class="pip-controls">
        <button id="pipMaximize" class="pip-btn d-none" title="Maximize"><i class="bi bi-fullscreen"></i></button>
        <button id="pipMinimize" class="pip-btn" title="Minimize"><i class="bi bi-fullscreen-exit"></i></button>
        <button id="pipReset" class="pip-btn" title="Reset View"><i class="bi bi-arrow-counterclockwise"></i></button>
        <button id="pipClose" class="pip-btn" title="Close"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <div id="pipContent"></div>
  </div>

  <div id="pipBackdrop"></div>

  <!-- To fix `Uncaught ReferenceError: Modal is not defined` -->
  <script src="../js/bootstrap.bundle.min.js"></script>

  <script src="../js/jsQR.min.js"></script>
  <script>
    let errorModalInstance = null;

    function showErrorModal(message) {
      const modalErrorMessage = document.getElementById("modalErrorMessage");
      modalErrorMessage.innerText = message;

      // Create modal instance only once
      if (!errorModalInstance) {
        errorModalInstance = new bootstrap.Modal(document.getElementById("errorModal"));
      }

      errorModalInstance.show();
    }

    // Ensure modal backdrop is properly cleaned up
    document.addEventListener('DOMContentLoaded', function() {
      const errorModalElement = document.getElementById("errorModal");

      // Clean up modal backdrop when modal is hidden
      errorModalElement.addEventListener('hidden.bs.modal', function() {
        // Remove any lingering backdrop elements
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());

        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
      });
    });
  </script>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
      const video = document.getElementById("qr-video");
      let canvas = document.createElement("canvas");
      let ctx = canvas.getContext("2d", {
        willReadFrequently: true
      });
      let scanning = false;
      let activeRowId = null;

      // Time input validation
      document.querySelectorAll('.time-input').forEach(input => {
        input.addEventListener('input', function(e) {
          let value = this.value;
          const cursorPosition = this.selectionStart;

          // Check if user is trying to delete (backspace or delete key)
          const isDeleting = e.inputType === 'deleteContentBackward' || e.inputType === 'deleteContentForward';

          if (isDeleting) {
            // Allow deletion to proceed normally
            return;
          }

          // Remove any non-numeric characters (except colon)
          let cleanValue = value.replace(/[^0-9:]/g, '');

          // Format the time as user types
          if (cleanValue.length > 0) {
            // If user types 4 digits, format as HH:mm
            if (cleanValue.length >= 4 && !cleanValue.includes(':')) {
              let hours = cleanValue.slice(0, 2);
              let minutes = cleanValue.slice(2, 4);

              // Validate hours and minutes
              if (parseInt(hours) > 23) hours = '23';
              if (parseInt(minutes) > 59) minutes = '59';

              cleanValue = hours + ':' + minutes;
            }
            // If user types 2 digits and no colon, add colon
            else if (cleanValue.length === 2 && !cleanValue.includes(':')) {
              let hours = cleanValue;
              if (parseInt(hours) > 23) hours = '23';
              cleanValue = hours + ':';
            }
          }

          this.value = cleanValue;
        });

        // Validate time on blur (when input loses focus)
        input.addEventListener('blur', function(e) {
          let value = this.value;
          if (value.length === 5) { // HH:mm format
            let [hours, minutes] = value.split(':').map(Number);

            // Check if time is valid
            if (isNaN(hours) || isNaN(minutes) ||
              hours < 0 || hours > 23 ||
              minutes < 0 || minutes > 59) {
              // Show error message
              showErrorModal('Incorrect time format. Enter time between 00:00 and 23:59');
              // Clear invalid input
              this.value = '';
              // Add error styling
              this.classList.add('is-invalid');
            } else {
              // Remove error styling if valid
              this.classList.remove('is-invalid');

              // Check if both time start and time end are filled, then validate duration
              const rowId = this.closest('tr').getAttribute('data-row-id');
              const timeStartInput = document.getElementById(`timeStart${rowId}`);
              const timeEndInput = document.getElementById(`timeEnd${rowId}`);

              if (timeStartInput && timeEndInput && timeStartInput.value && timeEndInput.value) {
                const duration = calculateDuration(timeStartInput.value, timeEndInput.value);

                if (duration === 'INVALID') {
                  // Show error for invalid duration
                  showErrorModal(`Incorrect start time and end time in row ${rowId}.`);

                  // Clear the invalid time input but don't force focus
                  this.value = '';
                  this.classList.add('is-invalid');
                  return;
                }

                updateDuration(rowId);
              }
            }
          } else if (value.length > 0) {
            // If input is not empty but not in correct format
            showErrorModal('Enter time in HH:mm format (e.g., 09:30)');
            this.value = '';
            this.classList.add('is-invalid');
          }
        });

        // Prevent paste of invalid characters
        input.addEventListener('paste', function(e) {
          e.preventDefault();
          let pastedText = (e.clipboardData || window.clipboardData).getData('text');
          let cleanText = pastedText.replace(/[^0-9]/g, '');

          // Format pasted time
          if (cleanText.length >= 4) {
            let hours = cleanText.slice(0, 2);
            let minutes = cleanText.slice(2, 4);

            // Validate hours and minutes
            if (parseInt(hours) > 23) hours = '23';
            if (parseInt(minutes) > 59) minutes = '59';

            this.value = hours + ':' + minutes;
          } else {
            this.value = cleanText;
          }
        });
      });

      function getCameraConstraints() {
        return {
          video: {
            facingMode: {
              ideal: "environment"
            }
          }
        };
      }

      function startScanning(rowId) {
        activeRowId = rowId;
        scannerModal.show();
        const constraints = getCameraConstraints();

        navigator.mediaDevices.getUserMedia(getCameraConstraints())
          .then(setupVideoStream)
          .catch((err1) => {
            console.error("Back camera failed", err1);

            navigator.mediaDevices.getUserMedia({
                video: {
                  facingMode: "user"
                }
              })
              .then(setupVideoStream)
              .catch((err2) => {
                console.error("Front camera failed", err2);
                alert("Camera access is blocked or not available on this tablet.");
              });
          });
      }

      function setupVideoStream(stream) {
        video.srcObject = stream;
        video.setAttribute("playsinline", true);
        video.onloadedmetadata = () => {
          video.play().then(() => scanQRCode());
        };
        scanning = true;
      }

      function indicateScanSuccess() {
        const videoContainer = video.parentElement;
        videoContainer.style.position = "relative";

        const overlay = document.createElement("div");
        overlay.style.position = "absolute";
        overlay.style.top = "0";
        overlay.style.left = "0";
        overlay.style.width = "100%";
        overlay.style.height = "100%";
        overlay.style.backgroundColor = "rgba(0,255,0,0.25)";
        overlay.style.zIndex = "9999";
        videoContainer.appendChild(overlay);

        setTimeout(() => overlay.remove(), 300);
      }

      function scanQRCode() {
        if (!scanning) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
          const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const qrCodeData = jsQR(imageData.data, imageData.width, imageData.height);

          if (qrCodeData) {
            const scannedText = qrCodeData.data.trim();
            const parts = scannedText.split(" ");
            const modelName = <?php echo json_encode($_SESSION['dorModelName'] ?? ''); ?>;

            // Function to check for duplicate box numbers
            function isDuplicateBoxNumber(boxNumber) {
              for (let i = 1; i <= 20; i++) {
                if (i === activeRowId) continue; // Skip current row
                const existingBoxNo = document.getElementById(`boxNo${i}`).value.trim();
                if (existingBoxNo === boxNumber) {
                  return true;
                }
              }
              return false;
            }

            if (parts.length === 1) {
              // Single part - Check if it matches model name
              if (parts[0] === modelName) {
                // If model name matches and time start exists, set time end
                if (activeRowId) {
                  const timeStartInput = document.getElementById(`timeStart${activeRowId}`);
                  const timeEndInput = document.getElementById(`timeEnd${activeRowId}`);
                  const boxNoInput = document.getElementById(`boxNo${activeRowId}`);

                  if (timeStartInput && timeStartInput.value) {
                    // Set current time in 24-hour format for time end
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    timeEndInput.value = `${hours}:${minutes}`;
                    timeEndInput.dispatchEvent(new Event('change'));
                    updateDuration(activeRowId);
                    indicateScanSuccess();
                  } else {
                    showErrorModal("Please set time start first.");
                  }
                }
                stopScanning();
                return;
              }

              // If not matching model name, use as box number
              if (activeRowId) {
                const boxNoInput = document.getElementById(`boxNo${activeRowId}`);
                const timeStartInput = document.getElementById(`timeStart${activeRowId}`);

                if (boxNoInput) {
                  // Check for duplicate box number only if this is a new box number (not time end scan)
                  if (!timeStartInput.value && isDuplicateBoxNumber(parts[0])) {
                    showErrorModal(`Lot number ${parts[0]} already scanned.`);
                    stopScanning();
                    return;
                  }

                  boxNoInput.value = parts[0];
                  boxNoInput.dispatchEvent(new Event('change'));

                  // Set current time in 24-hour format for time start
                  const now = new Date();
                  const hours = String(now.getHours()).padStart(2, '0');
                  const minutes = String(now.getMinutes()).padStart(2, '0');
                  timeStartInput.value = `${hours}:${minutes}`;
                  timeStartInput.dispatchEvent(new Event('change'));
                  updateDuration(activeRowId);

                  indicateScanSuccess();
                }
              }
              stopScanning();
            } else if (parts.length === 3) {
              // Three parts - Model, Quantity, Lot/Box Number
              const scannedModel = parts[0];
              const qty = parts[1];
              const lotNumber = parts[2];

              if (scannedModel === modelName) {
                if (activeRowId) {
                  const boxNoInput = document.getElementById(`boxNo${activeRowId}`);
                  const timeStartInput = document.getElementById(`timeStart${activeRowId}`);
                  const timeEndInput = document.getElementById(`timeEnd${activeRowId}`);

                  if (boxNoInput && timeStartInput && timeEndInput) {
                    // Check for duplicate box number only if this is a new box number (not time end scan)
                    if (!timeStartInput.value && isDuplicateBoxNumber(lotNumber)) {
                      showErrorModal(`Lot number ${lotNumber} already scanned.`);
                      stopScanning();
                      return;
                    }

                    // Set box number
                    boxNoInput.value = lotNumber;
                    boxNoInput.dispatchEvent(new Event('change'));

                    // Check if time start already has a value
                    if (timeStartInput.value) {
                      // Set current time in 24-hour format for time end
                      const now = new Date();
                      const hours = String(now.getHours()).padStart(2, '0');
                      const minutes = String(now.getMinutes()).padStart(2, '0');
                      timeEndInput.value = `${hours}:${minutes}`;
                      timeEndInput.dispatchEvent(new Event('change'));
                      updateDuration(activeRowId);
                    } else {
                      // Set current time in 24-hour format for time start
                      const now = new Date();
                      const hours = String(now.getHours()).padStart(2, '0');
                      const minutes = String(now.getMinutes()).padStart(2, '0');
                      timeStartInput.value = `${hours}:${minutes}`;
                      timeStartInput.dispatchEvent(new Event('change'));
                      updateDuration(activeRowId);
                    }

                    indicateScanSuccess();
                  }
                }
                stopScanning();
              } else {
                showErrorModal("Invalid QR code: Model name mismatch.");
                stopScanning();
              }
            } else {
              showErrorModal("Invalid QR code format.");
              stopScanning();
            }
          }
        }
        requestAnimationFrame(scanQRCode);
      }

      function stopScanning() {
        scanning = false;
        let tracks = video.srcObject?.getTracks();
        if (tracks) tracks.forEach(track => track.stop());
        scannerModal.hide();
        activeRowId = null;
      }

      // Add click event listeners to row numbers
      document.querySelectorAll('.clickable-row').forEach(cell => {
        cell.addEventListener('click', async function() {
          const rowId = this.closest('tr').getAttribute('data-row-id');
          const accessGranted = await navigator.mediaDevices.getUserMedia({
            video: true
          }).then(stream => {
            stream.getTracks().forEach(track => track.stop());
            return true;
          }).catch(() => false);

          if (accessGranted) {
            startScanning(rowId);
          } else {
            alert("Camera access denied");
          }
        });
      });

      document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
    });
  </script>

  <script>
    let isMinimized = false;
    const workInstructFile = <?php echo json_encode($workInstructFile); ?>;
    const preCardFile = <?php echo json_encode($preCardFile); ?>;

    document.addEventListener("DOMContentLoaded", function() {
      // Attach event listeners to buttons
      document.getElementById("btnDrawing").addEventListener("click", function() {
        openPiPViewer("<?php echo $drawingFile; ?>", 'image');
      });

      document.getElementById("btnWorkInstruction").addEventListener("click", function() {
        if (workInstructFile !== "") {
          openPiPViewer(workInstructFile, 'pdf');
        }
      });

      document.getElementById("btnPrepCard").addEventListener("click", function() {
        if (preCardFile !== "") {
          openPiPViewer(preCardFile, 'pdf');
        }
      });

      let storedTab = sessionStorage.getItem("activeTab");

      if (storedTab) {
        const tabElement = document.getElementById(storedTab);
        const tabButton = document.querySelector(`[onclick="openTab(event, '${storedTab}')"]`);

        if (tabElement) {
          tabElement.style.display = "block";
        }

        if (tabButton) {
          tabButton.classList.add("active");
        }
      } else {
        // Default to "Process 1" if no tab is stored
        const defaultTab = "Process1";
        const defaultTabElement = document.getElementById(defaultTab);
        const defaultTabButton = document.querySelector(`[onclick="openTab(event, '${defaultTab}')"]`);

        if (defaultTabElement) {
          defaultTabElement.style.display = "block";
        }

        if (defaultTabButton) {
          defaultTabButton.classList.add("active");
        }
      }

      form.addEventListener("submit", function(e) {
        e.preventDefault();

        // Only run validation if btnProceed triggered this
        if (!clickedButton || clickedButton.id !== "btnProceed") {
          return; // Skip validation if other buttons are clicked (e.g., Drawing, WI)
        }

        const errorsByOperator = {};
        const meta = {};
        const userCodes = {};
        const userCodeValues = [];

        let userCodeErrorHtml = "";

        // Loop through up to 4 tabs
        for (let i = 1; i <= 4; i++) {
          const input = form.querySelector(`#userCode${i}`);
          if (!input) continue;

          const code = input.value.trim();
          if (!code) {
            userCodeErrorHtml += `<li>Enter Employee ID of P${i}.</li>`;
          } else {
            if (userCodeValues.includes(code)) {
              userCodeErrorHtml += `<li>Employee ID "${code}" is duplicated.</li>`;
            }
            userCodes[i] = code;
            userCodeValues.push(code);
          }
        }

        if (userCodeErrorHtml) {
          modalErrorMessage.innerHTML = `${userCodeErrorHtml}`;
          errorModal.show();
          return;
        }

        // Collect meta info per ProcessX_Y
        form.querySelectorAll("input[name^='meta[']").forEach(input => {
          const match = input.name.match(/meta\[(.*?)\]\[(.*?)\]/);
          if (match) {
            const field = match[1]; // e.g. Process1_105
            const key = match[2]; // checkpoint or tabIndex
            if (!meta[field]) meta[field] = {};
            meta[field][key] = input.value;
          }
        });

        const groups = {};

        // Group radio and text inputs by field name
        form.querySelectorAll("input[name^='Process'], input[type='text'][name^='Process']").forEach(input => {
          const name = input.name;
          if (!groups[name]) groups[name] = [];
          groups[name].push(input);
        });

        for (const name in groups) {
          const group = groups[name];
          const type = group[0].type;

          let valid = false;
          if (type === "radio") {
            valid = group.some(input => input.checked);
          } else if (type === "text") {
            valid = group[0].value.trim() !== "";
          }

          if (!valid) {
            const checkpoint = meta[name]?.checkpoint || name;
            const tabIndex = meta[name]?.tabIndex;
            const operator = tabIndex && userCodes[tabIndex] ? userCodes[tabIndex] : `Process ${tabIndex}`;

            if (!errorsByOperator[operator]) errorsByOperator[operator] = [];
            errorsByOperator[operator].push(checkpoint);
          }
        }

        // Show modal if errors exist
        const operatorList = Object.keys(errorsByOperator);
        if (operatorList.length > 0) {
          let html = ``;
          operatorList.forEach(op => {
            html += `<div><strong>${op}</strong><ul>`;
            errorsByOperator[op].forEach(cp => {
              html += `<li>${cp}</li>`;
            });
            html += `</ul></div>`;
          });
          modalErrorMessage.innerHTML = html;
          errorModal.show();
          return;
        }

        // Submit if valid
        let formData = new FormData(form);
        if (clickedButton) {
          formData.append(clickedButton.name, clickedButton.value || "1");
        }

        fetch(form.action, {
            method: "POST",
            body: formData
          })
          .then(response => response.json())
          .then((data) => {
            if (data.success) {
              if (clickedButton.name === "btnProceed") {
                window.location.href = data.redirectUrl;
                return;
              }
            } else {
              modalErrorMessage.innerHTML = "<ul><li>" + data.errors.join("</li><li>") + "</li></ul>";
              errorModal.show();
            }
          })
          .catch(error => console.error("Error:", error));
      });

    });

    function openTab(event, tabName) {
      let tabContents = document.querySelectorAll(".tab-content");
      tabContents.forEach(tab => tab.style.display = "none");

      let tabButtons = document.querySelectorAll(".tab-button");
      tabButtons.forEach(button => button.classList.remove("active"));

      document.getElementById(tabName).style.display = "block";
      event.currentTarget.classList.add("active");

      sessionStorage.setItem("activeTab", tabName);
    }

    function goBack() {
      window.location.href = "dor-refresh.php";
    }

    // Form submission handling
    const form = document.querySelector("#myForm");
    const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
    const modalErrorMessage = document.getElementById("modalErrorMessage");

    let clickedButton = null;

    // Track which submit button was clicked
    document.querySelectorAll("button[type='submit']").forEach(button => {
      button.addEventListener("click", function(e) {
        e.preventDefault(); // Prevent default form submission
        clickedButton = this;

        // If it's the proceed button, trigger form validation and submission
        if (this.id === "btnProceed") {
          form.dispatchEvent(new Event('submit'));
        }
      });
    });

    // Add delete row functionality
    document.querySelectorAll('.delete-row').forEach(button => {
      button.addEventListener('click', function() {
        const rowId = parseInt(this.getAttribute('data-row-id'));

        if (!confirm(`Are you sure you want to delete row ${rowId}?`)) {
          return;
        }

        // Loop from the deleted row to the second-to-last row (19) to shift all content up
        for (let i = rowId; i < 20; i++) {
          const currentRow = document.querySelector(`tr[data-row-id="${i}"]`);
          const nextRow = document.querySelector(`tr[data-row-id="${i + 1}"]`);

          if (!currentRow || !nextRow) continue;

          // 1. Move all simple input values from the next row to the current row
          const fields = ['boxNo', 'timeStart', 'timeEnd', 'operators'];
          fields.forEach(field => {
            const currentInput = document.getElementById(`${field}${i}`);
            const nextInput = document.getElementById(`${field}${i + 1}`);
            if (currentInput && nextInput) {
              currentInput.value = nextInput.value;
            }
          });

          // 2. Move the operator badges
          const currentOperatorDiv = document.getElementById(`operatorList${i}`);
          const nextOperatorDiv = document.getElementById(`operatorList${i + 1}`);
          if (currentOperatorDiv && nextOperatorDiv) {
            currentOperatorDiv.innerHTML = nextOperatorDiv.innerHTML;
          }

          // 3. Intelligently move or create placeholders for the downtime column
          const currentDowntimeDiv = document.getElementById(`downtimeInfo${i}`);
          const nextDowntimeDiv = document.getElementById(`downtimeInfo${i + 1}`);
          if (currentDowntimeDiv && nextDowntimeDiv) {
            const nextDowntimeBadges = nextDowntimeDiv.querySelectorAll('.badge:not(.placeholder-badge)');

            currentDowntimeDiv.innerHTML = ''; // Always clear the target div first

            if (nextDowntimeBadges.length > 0) {
              // If the next row has real downtime records, copy them
              nextDowntimeBadges.forEach(badge => {
                currentDowntimeDiv.appendChild(badge.cloneNode(true));
              });
            } else {
              // If the next row is empty, create placeholders based on the operator codes we just moved
              const operatorBadgeCount = currentOperatorDiv.querySelectorAll('.badge').length;
              for (let k = 0; k < operatorBadgeCount; k++) {
                const placeholder = document.createElement('small');
                placeholder.className = 'badge placeholder-badge';
                placeholder.innerHTML = '&nbsp;';
                currentDowntimeDiv.appendChild(placeholder);
              }
            }
          }
        }

        // 4. Clear the last row completely
        clearRow(20);

        // 5. Update the active/inactive state of all rows
        updateRowStates();

        // 6. Recalculate all durations
        for (let i = 1; i <= 20; i++) {
          updateDuration(i);
        }

        // 7. Update DOR summary
        updateDORSummary();
      });
    });

    function clearRow(rowId) {
      const row = document.querySelector(`tr[data-row-id="${rowId}"]`);

      if (row) {
        // Clear all inputs in the row
        row.querySelectorAll('input').forEach(input => {
          input.value = '';
        });

        // Clear operator codes
        const operatorList = document.getElementById(`operatorList${rowId}`);
        if (operatorList) {
          operatorList.innerHTML = '';
        }

        // Clear downtime info
        const downtimeInfo = document.getElementById(`downtimeInfo${rowId}`);
        if (downtimeInfo) {
          downtimeInfo.innerHTML = '';
        }

        // Clear duration
        const durationSpan = document.getElementById(`duration${rowId}`);
        if (durationSpan) {
          durationSpan.textContent = '';
        }
      }
    }

    // Function to check if a row is complete
    function isRowComplete(rowId) {
      const boxNo = document.getElementById(`boxNo${rowId}`).value.trim();
      const timeStart = document.getElementById(`timeStart${rowId}`).value.trim();
      const timeEnd = document.getElementById(`timeEnd${rowId}`).value.trim();
      return boxNo && timeStart && timeEnd; // All three fields are required
    }

    // Function to set row active/inactive
    function setRowActive(rowId, active) {
      const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
      const inputs = row.querySelectorAll('input:not([type="hidden"])');
      const buttons = row.querySelectorAll('button');

      if (active) {
        row.classList.remove('row-inactive');
        row.classList.add('row-active');
        inputs.forEach(input => {
          input.disabled = false;
          if (input.id.startsWith('boxNo') || input.id.startsWith('timeStart') || input.id.startsWith('timeEnd')) {
            input.classList.add('required-field');
          }
        });
        buttons.forEach(button => button.disabled = false);
      } else {
        row.classList.remove('row-active');
        row.classList.add('row-inactive');
        inputs.forEach(input => {
          input.disabled = true;
          if (input.id.startsWith('boxNo') || input.id.startsWith('timeStart') || input.id.startsWith('timeEnd')) {
            input.classList.add('required-field');
          }
        });
        buttons.forEach(button => button.disabled = true);
      }
    }

    // Function to check and update row states
    function updateRowStates() {
      for (let i = 2; i <= 20; i++) {
        const prevRowComplete = isRowComplete(i - 1);
        setRowActive(i, prevRowComplete);
      }
    }

    // Add event listeners to required fields
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      const timeStartInput = document.getElementById(`timeStart${i}`);
      const timeEndInput = document.getElementById(`timeEnd${i}`);

      // Add change event listeners
      [boxNoInput, timeStartInput, timeEndInput].forEach(input => {
        if (input) {
          input.addEventListener('change', updateRowStates);
          // Add required field styling
          input.classList.add('required-field');
        }
      });

      // Add box number duplicate validation
      if (boxNoInput) {
        // Validate on change (when user types and input loses focus)
        boxNoInput.addEventListener('change', function() {
          validateBoxNumberDuplicate(this);
        });

        // Validate on blur (when user tabs away or clicks elsewhere)
        boxNoInput.addEventListener('blur', function(e) {
          if (!this.disabled) {
            validateBoxNumberDuplicate(this);
          }
        });
      }
    }

    // Function to validate box number duplicates
    function validateBoxNumberDuplicate(input) {
      const boxNumber = input.value.trim();
      if (!boxNumber) return true; // Skip validation if empty

      const currentRowId = input.closest('tr').getAttribute('data-row-id');

      // Check for duplicate box numbers in other rows
      for (let i = 1; i <= 20; i++) {
        if (i === parseInt(currentRowId)) continue; // Skip current row
        const existingBoxNo = document.getElementById(`boxNo${i}`).value.trim();
        if (existingBoxNo === boxNumber) {
          showErrorModal(`Lot number ${boxNumber} already scanned.`);
          // Keep the duplicate value visible but mark as invalid
          input.classList.add('is-invalid');
          return false; // Indicate validation failed
        }
      }

      // Remove error styling if no duplicates found
      input.classList.remove('is-invalid');
      return true; // Indicate validation passed
    }

    // Initialize row states
    setRowActive(1, true); // First row is always active
    updateRowStates();

    // Function to calculate duration between two times
    function calculateDuration(timeStart, timeEnd) {
      if (!timeStart || !timeEnd) return '';

      const [startHours, startMinutes] = timeStart.split(':').map(Number);
      const [endHours, endMinutes] = timeEnd.split(':').map(Number);

      let totalStartMinutes = startHours * 60 + startMinutes;
      let totalEndMinutes = endHours * 60 + endMinutes;
      let durationMinutes = totalEndMinutes - totalStartMinutes;

      // Handle case where end time is on the next day
      if (durationMinutes < 0) {
        durationMinutes += 24 * 60; // Add 24 hours worth of minutes
      }

      // A shift should not be longer than 16 hours (960 minutes)
      // This helps distinguish between an overnight shift and a data entry error.
      const MAX_SHIFT_MINUTES = 16 * 60;

      if (durationMinutes === 0 || durationMinutes > MAX_SHIFT_MINUTES) {
        return 'INVALID'; // Return special value to indicate invalid duration
      }

      return durationMinutes.toString();
    }

    // Function to update duration for a specific row
    function updateDuration(rowId) {
      const timeStartInput = document.getElementById(`timeStart${rowId}`);
      const timeEndInput = document.getElementById(`timeEnd${rowId}`);
      const durationSpan = document.getElementById(`duration${rowId}`);

      if (timeStartInput && timeEndInput && durationSpan) {
        const duration = calculateDuration(timeStartInput.value, timeEndInput.value);

        if (duration === 'INVALID') {
          // Show error for invalid duration
          showErrorModal(`Incorrect start time and end time in row ${rowId}.`);

          // Clear the invalid end time and focus on it
          timeEndInput.value = '';
          timeEndInput.classList.add('is-invalid');
          timeEndInput.focus();

          // Clear duration display
          durationSpan.textContent = '';
          return;
        }

        // Remove error styling if duration is valid
        timeEndInput.classList.remove('is-invalid');
        durationSpan.textContent = duration;
      }
    }

    // Add event listeners for time inputs to update duration
    document.querySelectorAll('.time-input').forEach(input => {
      input.addEventListener('change', function() {
        const rowId = this.closest('tr').getAttribute('data-row-id');
        updateDuration(rowId);
        updateDORSummary(); // Update summary when duration changes
      });
    });

    // Function to format duration in readable format
    function formatDuration(minutes) {
      if (!minutes || minutes === 0) return '0 mins';

      const hours = Math.floor(minutes / 60);
      const mins = minutes % 60;

      if (hours === 0) {
        return `${mins} mins`;
      } else if (mins === 0) {
        return `${hours} hours`;
      } else {
        return `${hours} hours and ${mins} mins`;
      }
    }

    // Function to calculate total box quantity
    function calculateTotalBoxQty() {
      let count = 0;
      for (let i = 1; i <= 20; i++) {
        const boxNoInput = document.getElementById(`boxNo${i}`);
        if (boxNoInput && boxNoInput.value.trim() !== '') {
          count++;
        }
      }
      return count;
    }

    // Function to calculate total duration
    function calculateTotalDuration() {
      let totalMinutes = 0;
      for (let i = 1; i <= 20; i++) {
        const durationSpan = document.getElementById(`duration${i}`);
        if (durationSpan && durationSpan.textContent.trim() !== '') {
          const duration = parseInt(durationSpan.textContent);
          if (!isNaN(duration)) {
            totalMinutes += duration;
          }
        }
      }
      return totalMinutes;
    }

    // Function to calculate total downtime records
    function calculateTotalDowntime() {
      let count = 0;
      for (let i = 1; i <= 20; i++) {
        const downtimeDiv = document.getElementById(`downtimeInfo${i}`);
        if (downtimeDiv) {
          // Count only real downtime badges (not placeholder badges)
          const realDowntimeBadges = downtimeDiv.querySelectorAll('.badge:not(.placeholder-badge)');
          count += realDowntimeBadges.length;
        }
      }
      return count;
    }

    // Function to update DOR summary
    function updateDORSummary() {
      const totalBoxQty = calculateTotalBoxQty();
      const totalDuration = calculateTotalDuration();
      const totalDowntime = calculateTotalDowntime();

      // Update the summary display
      document.getElementById('totalBoxQty').textContent = totalBoxQty;
      document.getElementById('totalDuration').textContent = formatDuration(totalDuration);
      document.getElementById('totalDowntime').textContent = totalDowntime;
    }

    // Add event listeners to update summary when box numbers change
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      if (boxNoInput) {
        boxNoInput.addEventListener('change', updateDORSummary);
        boxNoInput.addEventListener('input', updateDORSummary);
      }
    }

    // Initialize summary on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateDORSummary();
    });
  </script>

  <script>
    // Operator Management Modal Functionality
    let operatorModalInstance = null;
    let currentRowId = null;
    let selectedOperator = null;
    let operatorSuggestionsTimeout = null;
    let currentOperators = {};

    // Initialize operator modal
    document.addEventListener('DOMContentLoaded', function() {
      operatorModalInstance = new bootstrap.Modal(document.getElementById("operatorModal"));
      initializeOperatorAutosuggest();

      // Add event listeners to operator management buttons
      document.querySelectorAll('.btn-operator').forEach(button => {
        button.addEventListener('click', function() {
          const rowId = this.closest('tr').getAttribute('data-row-id');
          openOperatorModal(rowId);
        });
      });

      // Add event listeners for modal buttons
      document.getElementById('addOperatorBtn').addEventListener('click', addOperator);
      document.getElementById('saveOperatorsBtn').addEventListener('click', saveOperators);
    });

    function openOperatorModal(rowId) {
      currentRowId = rowId;
      document.getElementById('operatorRowNumber').textContent = rowId;

      // Get lot number from the box number input
      const boxNoInput = document.getElementById(`boxNo${rowId}`);
      const lotNumber = boxNoInput ? boxNoInput.value.trim() : '';
      document.getElementById('modalLotNumber').textContent = lotNumber || 'N/A';

      // Load current operators for this row
      loadCurrentOperators(rowId);

      // Clear search field
      document.getElementById('operatorSearch').value = '';
      document.getElementById('operatorSuggestions').style.display = 'none';
      document.getElementById('operatorValidationMessage').innerHTML = '';
      document.getElementById('addOperatorBtn').disabled = true;
      selectedOperator = null;

      operatorModalInstance.show();
    }

    function loadCurrentOperators(rowId) {
      const operatorsInput = document.getElementById(`operators${rowId}`);
      const operatorsDiv = document.getElementById('currentOperators');

      if (operatorsInput && operatorsInput.value.trim()) {
        const operatorCodes = operatorsInput.value.split(',');
        currentOperators = {};

        let html = '<div class="row g-2">';
        operatorCodes.forEach((code, index) => {
          if (code.trim()) {
            currentOperators[code.trim()] = {
              code: code.trim(),
              name: 'Loading...'
            };
            html += `
              <div class="col-12 col-md-6 mb-2">
                <div class="card border-0 bg-light">
                  <div class="card-body p-2">
                    <div class="d-flex align-items-center">
                      <div class="flex-grow-1">
                        <div class="fw-bold">${code.trim()}</div>
                        <div class="text-muted" id="operatorName_${code.trim()}">Loading...</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            `;
          }
        });
        html += '</div>';

        operatorsDiv.innerHTML = html;

        // Load employee names for existing operators
        Object.keys(currentOperators).forEach(code => {
          loadOperatorName(code);
        });
      } else {
        currentOperators = {};
        operatorsDiv.innerHTML = '<p class="text-muted text-center mb-0">No operators assigned yet.</p>';
      }
    }

    function loadOperatorName(employeeCode) {
      fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=validate_operator&employeeCode=${encodeURIComponent(employeeCode)}`
        })
        .then(res => res.json())
        .then(data => {
          if (data.valid && data.employeeName) {
            const nameElement = document.getElementById(`operatorName_${employeeCode}`);
            if (nameElement) {
              nameElement.textContent = data.employeeName;
            }
            if (currentOperators[employeeCode]) {
              currentOperators[employeeCode].name = data.employeeName;
            }
          }
        })
        .catch(error => {
          console.error('Error loading operator name:', error);
        });
    }

    function initializeOperatorAutosuggest() {
      const searchInput = document.getElementById('operatorSearch');
      const suggestionsDiv = document.getElementById('operatorSuggestions');
      const validationDiv = document.getElementById('operatorValidationMessage');
      const addBtn = document.getElementById('addOperatorBtn');

      if (!searchInput) return;

      // Handle input for autosuggest
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        selectedOperator = null;

        // Clear previous timeout
        if (operatorSuggestionsTimeout) {
          clearTimeout(operatorSuggestionsTimeout);
        }

        // Hide suggestions if input is empty
        if (searchTerm.length === 0) {
          suggestionsDiv.style.display = 'none';
          validationDiv.innerHTML = '';
          addBtn.disabled = true;
          return;
        }

        // Show suggestions after 300ms delay
        operatorSuggestionsTimeout = setTimeout(() => {
          fetchOperatorSuggestions(searchTerm);
        }, 300);
      });

      // Handle suggestion selection
      suggestionsDiv.addEventListener('click', function(e) {
        if (e.target.classList.contains('suggestion-item')) {
          const employeeCode = e.target.dataset.employeeCode;
          const employeeName = e.target.dataset.employeeName;

          searchInput.value = `${employeeCode} - ${employeeName}`;
          selectedOperator = {
            code: employeeCode,
            name: employeeName
          };
          suggestionsDiv.style.display = 'none';
          addBtn.disabled = false;
          validationDiv.innerHTML = `<div class="text-success">${employeeName}</div>`;
        }
      });

      // Hide suggestions when clicking outside
      document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
          suggestionsDiv.style.display = 'none';
        }
      });

      // Handle blur event for validation
      searchInput.addEventListener('blur', function() {
        setTimeout(() => {
          if (this.value.trim() && !selectedOperator) {
            validateOperator(this.value.trim());
          }
        }, 200);
      });
    }

    function fetchOperatorSuggestions(searchTerm) {
      fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=suggest_operator&searchTerm=${encodeURIComponent(searchTerm)}`
        })
        .then(res => res.json())
        .then(data => {
          displayOperatorSuggestions(data.suggestions);
        })
        .catch(error => {
          console.error('Error fetching operator suggestions:', error);
        });
    }

    function displayOperatorSuggestions(suggestions) {
      const suggestionsDiv = document.getElementById('operatorSuggestions');

      if (!suggestions || suggestions.length === 0) {
        suggestionsDiv.style.display = 'none';
        return;
      }

      let html = '';
      suggestions.forEach(operator => {
        html += `<div class="suggestion-item p-2 border-bottom" 
                      data-employee-code="${operator.EmployeeCode}" 
                      data-employee-name="${operator.EmployeeName}" 
                      style="cursor: pointer; background-color: #f8f9fa;">
                  <div class="fw-bold">${operator.EmployeeCode}</div>
                  <div class="text-muted">${operator.EmployeeName}</div>
                </div>`;
      });

      suggestionsDiv.innerHTML = html;
      suggestionsDiv.style.display = 'block';
    }

    function validateOperator(employeeCode) {
      fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `action=validate_operator&employeeCode=${encodeURIComponent(employeeCode)}`
        })
        .then(res => res.json())
        .then(data => {
          const searchInput = document.getElementById('operatorSearch');
          const validationDiv = document.getElementById('operatorValidationMessage');
          const addBtn = document.getElementById('addOperatorBtn');

          if (data.valid) {
            searchInput.classList.remove('is-invalid');
            searchInput.classList.add('is-valid');
            validationDiv.innerHTML = `<div class="text-success">${data.message}</div>`;
            selectedOperator = {
              code: employeeCode,
              name: data.employeeName
            };
            addBtn.disabled = false;
          } else {
            searchInput.classList.remove('is-valid');
            searchInput.classList.add('is-invalid');
            validationDiv.innerHTML = `<div class="text-danger">${data.message}</div>`;
            selectedOperator = null;
            addBtn.disabled = true;
          }
        })
        .catch(error => {
          console.error('Error validating operator:', error);
        });
    }

    function addOperator() {
      if (!selectedOperator || !currentRowId) return;

      const employeeCode = selectedOperator.code;

      // Check if operator already exists
      if (currentOperators[employeeCode]) {
        showErrorModal(`Operator ${employeeCode} is already assigned to this row.`);
        return;
      }

      // Check if maximum operators limit reached (4 operators max)
      if (Object.keys(currentOperators).length >= 4) {
        const validationDiv = document.getElementById('operatorValidationMessage');
        validationDiv.innerHTML = `<div class="text-danger">Maximum of 4 operators allowed per row.</div>`;
        return;
      }

      // Add to current operators
      currentOperators[employeeCode] = selectedOperator;

      // Update display
      updateCurrentOperatorsDisplay();

      // Clear search
      document.getElementById('operatorSearch').value = '';
      document.getElementById('operatorSuggestions').style.display = 'none';
      document.getElementById('operatorValidationMessage').innerHTML = '';
      document.getElementById('addOperatorBtn').disabled = true;
      selectedOperator = null;
    }

    function updateCurrentOperatorsDisplay() {
      const operatorsDiv = document.getElementById('currentOperators');

      if (Object.keys(currentOperators).length === 0) {
        operatorsDiv.innerHTML = '<p class="text-muted text-center mb-0">No operators assigned yet.</p>';
        return;
      }

      let html = '<div class="row g-2">';
      Object.values(currentOperators).forEach(operator => {
        html += `
          <div class="col-12 col-md-6 mb-2">
            <div class="card border-0 bg-light">
              <div class="card-body p-2">
                <div class="d-flex align-items-center">
                  <div class="flex-grow-1">
                    <div class="fw-bold">${operator.code}</div>
                    <div class="text-muted">${operator.name}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      });
      html += '</div>';

      operatorsDiv.innerHTML = html;
    }

    function saveOperators() {
      if (!currentRowId) return;

      // Update the hidden input field
      const operatorsInput = document.getElementById(`operators${currentRowId}`);
      const operatorCodes = Object.keys(currentOperators);

      if (operatorsInput) {
        operatorsInput.value = operatorCodes.join(',');
      }

      // Update the display in the main table
      const operatorListDiv = document.getElementById(`operatorList${currentRowId}`);
      if (operatorListDiv) {
        operatorListDiv.innerHTML = '';

        if (operatorCodes.length > 0) {
          operatorCodes.forEach(code => {
            const operator = currentOperators[code];
            const badge = document.createElement('small');
            badge.className = 'badge bg-light text-dark border me-1 mb-1';
            badge.textContent = operator.name || code;
            operatorListDiv.appendChild(badge);
          });
        }
      }

      // Update downtime placeholders to match operator count
      updateDowntimePlaceholders(currentRowId, operatorCodes.length);

      // Close modal
      operatorModalInstance.hide();

      // Update DOR summary
      updateDORSummary();
    }

    function updateDowntimePlaceholders(rowId, operatorCount) {
      const downtimeDiv = document.getElementById(`downtimeInfo${rowId}`);
      if (downtimeDiv) {
        // Remove existing placeholders
        const existingPlaceholders = downtimeDiv.querySelectorAll('.placeholder-badge');
        existingPlaceholders.forEach(placeholder => placeholder.remove());

        // Add new placeholders to match operator count
        for (let i = 0; i < operatorCount; i++) {
          const placeholder = document.createElement('small');
          placeholder.className = 'badge placeholder-badge';
          placeholder.innerHTML = '&nbsp;';
          downtimeDiv.appendChild(placeholder);
        }
      }
    }
  </script>

  <form id="deleteDorForm" method="POST" action="dor-form.php">
    <input type="hidden" name="action" value="delete_dor">
  </form>

  <script src="../js/pdf.min.js"></script>
  <script src="../js/pdf.worker.min.js"></script>
  <script src="../js/hammer.min.js"></script>
  <script src="../js/dor-pip-viewer.js"></script>

  <script>
    // Add this to your existing JavaScript
    function initializeProcessLabels() {
      const tabQty = <?php echo $_SESSION["tabQty"] ?? 0; ?>;
      const processLabelsContainer = document.getElementById('pipProcessLabels');
      processLabelsContainer.innerHTML = ''; // Clear existing labels

      for (let i = 1; i <= tabQty; i++) {
        const label = document.createElement('div');
        label.className = 'pip-process-label';
        label.textContent = `P${i}`;
        label.dataset.process = i;

        // Add click handler
        label.addEventListener('click', function() {
          // Remove active class from all labels
          document.querySelectorAll('.pip-process-label').forEach(l => l.classList.remove('active'));
          // Add active class to clicked label
          this.classList.add('active');

          // Load work instruction immediately
          const processNumber = parseInt(this.dataset.process);
          fetch(`/paperless/module/get-work-instruction.php?process=${processNumber}`)
            .then(response => response.json())
            .then(data => {
              if (data.success && data.file) {
                const pipContent = document.getElementById("pipContent");
                pipContent.innerHTML = "";
                loadPdfFile(data.file);
              }
            });
        });

        processLabelsContainer.appendChild(label);
      }

      // Set first process as active by default
      const firstLabel = processLabelsContainer.querySelector('.pip-process-label');
      if (firstLabel) {
        firstLabel.classList.add('active');
      }
    }

    // Call this when the PiP viewer is initialized
    document.addEventListener('DOMContentLoaded', function() {
      initializeProcessLabels();
    });
  </script>

</body>

</html>