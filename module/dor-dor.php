<?php
$title = "DOR";
session_start();
ob_start();

require_once "../config/dbop.php";
require_once "../config/method.php";

$db1 = new DbOp(1);

$errorPrompt = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // AJAX: Get latest operator and downtime for a specific row
  if (isset($_POST['action']) && $_POST['action'] === 'get_row_status') {
    $recordId = $_SESSION['dorRecordId'] ?? 0;
    $boxNo = trim($_POST['boxNo'] ?? '');
    $response = ['success' => false, 'downtimeBadges' => '', 'downtimeRecords' => []];
    if ($recordId > 0 && $boxNo !== '') {
      // Find the header for this box number
      $headerRes = $db1->execute("SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?", [$recordId, $boxNo], 1);
      if (!empty($headerRes) && isset($headerRes[0]['RecordHeaderId'])) {
        $headerId = $headerRes[0]['RecordHeaderId'];
        // Downtime: get all downtime records for this header
        $dtRes = $db1->execute("SELECT DowntimeId, TimeStart, TimeEnd, Duration FROM AtoDorDetail WHERE RecordHeaderId = ? AND DowntimeId IS NOT NULL", [$headerId], 0);
        $downtimeRecords = [];
        $badgeCodes = [];
        if (!empty($dtRes) && is_array($dtRes)) {
          foreach ($dtRes as $dtRow) {
            $downtimeId = $dtRow['DowntimeId'] ?? null;
            $downtimeCode = '';
            if ($downtimeId) {
              $codeRes = $db1->execute("SELECT DowntimeCode FROM GenDorDowntime WHERE DowntimeId = ?", [$downtimeId], 1);
              if (!empty($codeRes) && isset($codeRes[0]['DowntimeCode'])) {
                $downtimeCode = $codeRes[0]['DowntimeCode'];
                $badgeCodes[] = $downtimeCode;
              }
            }
            // Ensure timeStart and timeEnd are always strings
            $timeStart = $dtRow['TimeStart'] ?? '';
            $timeEnd = $dtRow['TimeEnd'] ?? '';
            if ($timeStart instanceof DateTime) {
              $timeStart = $timeStart->format('Y-m-d H:i:s');
            } elseif (is_object($timeStart)) {
              $timeStart = strval($timeStart);
            }
            if ($timeEnd instanceof DateTime) {
              $timeEnd = $timeEnd->format('Y-m-d H:i:s');
            } elseif (is_object($timeEnd)) {
              $timeEnd = strval($timeEnd);
            }
            $downtimeRecords[] = [
              'downtimeCode' => $downtimeCode,
              'timeStart' => $timeStart,
              'timeEnd' => $timeEnd,
              'duration' => $dtRow['Duration'] ?? ''
            ];
          }
        }
        // Prepare badge HTML for all unique downtime codes
        $badgeCodes = array_unique($badgeCodes);
        $badgesHtml = '';
        foreach ($badgeCodes as $code) {
          if ($code !== '') {
            $badgesHtml .= '<small class="badge bg-light text-dark border mx-1">' . htmlspecialchars($code) . '</small>';
          }
        }
        $response['downtimeBadges'] = $badgesHtml;
        $response['downtimeRecords'] = $downtimeRecords;

        // Get operator codes for this row
        $operatorCodes = [];
        $opRes = $db1->execute("SELECT OperatorCode1, OperatorCode2, OperatorCode3, OperatorCode4 FROM AtoDorDetail WHERE RecordHeaderId = ?", [$headerId], 1);
        if (!empty($opRes[0])) {
          for ($j = 1; $j <= 4; $j++) {
            $code = $opRes[0]["OperatorCode$j"] ?? '';
            if ($code !== '' && $code !== null) {
              $operatorCodes[] = $code;
            }
          }
        }
        $operatorCodes = array_unique($operatorCodes);
        $operatorBadgesHtml = '';
        foreach ($operatorCodes as $code) {
          if ($code !== '') {
            $operatorBadgesHtml .= '<small class="badge bg-light text-dark border mx-1">' . htmlspecialchars($code) . '</small>';
          }
        }
        $response['operatorBadges'] = $operatorBadgesHtml;
        $response['operatorCodes'] = $operatorCodes; // Add this line to return actual codes

        $response['success'] = true;
      }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
  }

  // AJAX: Get all current rows to detect deletions by leaders
  if (isset($_POST['action']) && $_POST['action'] === 'get_all_rows') {
    $recordId = $_SESSION['dorRecordId'] ?? 0;
    $response = ['success' => false, 'existingBoxNumbers' => []];
    if ($recordId > 0) {
      // Get all box numbers that exist in the database for this record
      $existingRows = $db1->execute("SELECT BoxNumber FROM AtoDorHeader WHERE RecordId = ? ORDER BY BoxNumber", [$recordId], 0);
      $existingBoxNumbers = [];
      if (!empty($existingRows)) {
        foreach ($existingRows as $row) {
          $existingBoxNumbers[] = $row['BoxNumber'];
        }
      }
      $response['existingBoxNumbers'] = $existingBoxNumbers;
      $response['success'] = true;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
  }
  ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $response = ['success' => false, 'errors' => []];

  // Handle operator validation request
  if (isset($_POST['action']) && $_POST['action'] === 'validate_operator') {
    $productionCode = trim($_POST['employeeCode'] ?? '');

    if (empty($productionCode)) {
      $response['valid'] = false;
      $response['message'] = 'Production code is required.';
    } else {
      $spRd1 = "EXEC RdGenOperator @ProductionCode=?, @IsActive=1, @IsLoggedIn=0";
      $res1 = $db1->execute($spRd1, [$productionCode], 1);

      if (!empty($res1)) {
        $response['valid'] = true;
        // Removed success message
        $response['employeeName'] = $res1[0]['EmployeeName'] ?? '';
      } else {
        $response['valid'] = false;
        $response['message'] = 'Invalid production code.';
      }
    }

    echo json_encode($response);
    exit;
  }


  // Handle delete row request
  if (isset($_POST['action']) && $_POST['action'] === 'delete_row') {
    $recordId = $_SESSION['dorRecordId'] ?? 0;
    if ($recordId <= 0) {
      $response['success'] = false;
      $response['errors'][] = "Invalid record ID.";
      echo json_encode($response);
      exit;
    }

    $rowId = (int)($_POST['rowId'] ?? 0);
    $boxNo = trim($_POST['boxNo'] ?? '');

    if (empty($boxNo)) {
      $response['success'] = false;
      $response['errors'][] = "Box number is required for deletion.";
      echo json_encode($response);
      exit;
    }

    try {
      // Find the header record to delete
      $checkHeaderSp = "SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?";
      $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $boxNo], 1);

      if (!empty($existingHeader)) {
        $headerId = $existingHeader[0]['RecordHeaderId'];

        // Delete detail record first (foreign key constraint)
        $deleteDetailSp = "DELETE FROM AtoDorDetail WHERE RecordHeaderId = ?";
        $detailResult = $db1->execute($deleteDetailSp, [$headerId]);

        // Delete header record
        $deleteHeaderSp = "DELETE FROM AtoDorHeader WHERE RecordHeaderId = ?";
        $headerResult = $db1->execute($deleteHeaderSp, [$headerId]);

        if ($headerResult !== false) {
          $response['success'] = true;
          // Removed success message
        } else {
          $response['success'] = false;
          $response['errors'][] = "Failed to delete row {$rowId}.";
        }
      } else {
        $response['success'] = false;
        $response['errors'][] = "Row {$rowId} not found in database.";
      }
    } catch (Exception $e) {
      $response['success'] = false;
      $response['errors'][] = "Database error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
  }

  // Handle clear row request (when row is emptied)
  if (isset($_POST['action']) && $_POST['action'] === 'clear_row') {
    $recordId = $_SESSION['dorRecordId'] ?? 0;
    if ($recordId <= 0) {
      $response['success'] = false;
      $response['errors'][] = "Invalid record ID.";
      echo json_encode($response);
      exit;
    }

    $rowId = (int)($_POST['rowId'] ?? 0);
    $boxNo = trim($_POST['boxNo'] ?? '');

    if (empty($boxNo)) {
      $response['success'] = false;
      $response['errors'][] = "Box number is required for clearing.";
      echo json_encode($response);
      exit;
    }

    try {
      // Find the header record to clear
      $checkHeaderSp = "SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?";
      $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $boxNo], 1);

      if (!empty($existingHeader)) {
        $headerId = $existingHeader[0]['RecordHeaderId'];

        // Clear detail record first (set all operator codes to null)
        $clearDetailSp = "UPDATE AtoDorDetail SET OperatorCode1 = NULL, OperatorCode2 = NULL, OperatorCode3 = NULL, OperatorCode4 = NULL WHERE RecordHeaderId = ?";
        $detailResult = $db1->execute($clearDetailSp, [$headerId]);

        // Clear header record (set times to null and duration to 0)
        $clearHeaderSp = "UPDATE AtoDorHeader SET TimeStart = NULL, TimeEnd = NULL, Duration = 0 WHERE RecordHeaderId = ?";
        $headerResult = $db1->execute($clearHeaderSp, [$headerId]);

        if ($headerResult !== false) {
          $response['success'] = true;
          // Removed success message
        } else {
          $response['success'] = false;
          $response['errors'][] = "Failed to clear row {$rowId}.";
        }
      } else {
        $response['success'] = false;
        $response['errors'][] = "Row {$rowId} not found in database.";
      }
    } catch (Exception $e) {
      $response['success'] = false;
      $response['errors'][] = "Database error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
  }

  // Handle update row request (for shifting data after delete)
  if (isset($_POST['action']) && $_POST['action'] === 'update_row') {
    $recordId = $_SESSION['dorRecordId'] ?? 0;
    if ($recordId <= 0) {
      $response['success'] = false;
      $response['errors'][] = "Invalid record ID.";
      echo json_encode($response);
      exit;
    }

    $rowId = (int)($_POST['rowId'] ?? 0);
    $oldBoxNo = trim($_POST['oldBoxNo'] ?? '');
    $newBoxNo = trim($_POST['newBoxNo'] ?? '');
    $timeStart = trim($_POST['timeStart'] ?? '');
    $timeEnd = trim($_POST['timeEnd'] ?? '');
    $operators = trim($_POST['operators'] ?? '');

    if (empty($oldBoxNo) || empty($newBoxNo)) {
      $response['success'] = false;
      $response['errors'][] = "Both old and new box numbers are required.";
      echo json_encode($response);
      exit;
    }

    try {
      // Find the header record to update
      $checkHeaderSp = "SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?";
      $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $oldBoxNo], 1);

      if (!empty($existingHeader)) {
        $headerId = $existingHeader[0]['RecordHeaderId'];

        // Update header record
        $updateHeaderSp = "UPDATE AtoDorHeader SET BoxNumber = ?";
        $params = [$newBoxNo];

        // Add time updates if provided
        if (!empty($timeStart) && !empty($timeEnd)) {
          $dateTimeStart = date('Y-m-d H:i:s', strtotime($timeStart));
          $dateTimeEnd = date('Y-m-d H:i:s', strtotime($timeEnd));
          $startTime = strtotime($timeStart);
          $endTime = strtotime($timeEnd);
          $duration = round(($endTime - $startTime) / 60);

          $updateHeaderSp .= ", TimeStart = ?, TimeEnd = ?, Duration = ?";
          $params = array_merge($params, [$dateTimeStart, $dateTimeEnd, $duration]);
        }

        $updateHeaderSp .= " WHERE RecordHeaderId = ?";
        $params[] = $headerId;

        $headerResult = $db1->execute($updateHeaderSp, $params);

        if ($headerResult !== false) {
          $response['success'] = true;
          // Removed success message
        } else {
          $response['success'] = false;
          $response['errors'][] = "Failed to update row {$rowId}.";
        }
      } else {
        $response['success'] = false;
        $response['errors'][] = "Row {$rowId} not found in database.";
      }
    } catch (Exception $e) {
      $response['success'] = false;
      $response['errors'][] = "Database error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
  }

  // Handle individual row save request
  if (isset($_POST['action']) && $_POST['action'] === 'save_row') {
    $recordId = $_SESSION['dorRecordId'] ?? 0;
    if ($recordId <= 0) {
      $response['success'] = false;
      $response['errors'][] = "Invalid record ID.";
      echo json_encode($response);
      exit;
    }

    $rowId = (int)($_POST['rowId'] ?? 0);
    $boxNo = trim($_POST['boxNo'] ?? '');
    $timeStart = trim($_POST['timeStart'] ?? '');
    $timeEnd = trim($_POST['timeEnd'] ?? '');
    $operators = trim($_POST['operators'] ?? '');

    // Validate required fields
    if (empty($boxNo) || empty($timeStart) || empty($timeEnd)) {
      $response['success'] = false;
      $response['errors'][] = "All fields are required for row {$rowId}.";
      echo json_encode($response);
      exit;
    }

    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $timeStart) || !preg_match('/^\d{2}:\d{2}$/', $timeEnd)) {
      $response['success'] = false;
      $response['errors'][] = "Invalid time format for row {$rowId}.";
      echo json_encode($response);
      exit;
    }

    try {
      // Convert time strings to datetime format
      $dateTimeStart = date('Y-m-d H:i:s', strtotime($timeStart));
      $dateTimeEnd = date('Y-m-d H:i:s', strtotime($timeEnd));

      // Calculate duration in minutes
      $startTime = strtotime($timeStart);
      $endTime = strtotime($timeEnd);
      $duration = round(($endTime - $startTime) / 60);

      // Validate duration (should be positive and reasonable)
      if ($duration <= 0 || $duration > 960) { // Max 16 hours
        $response['success'] = false;
        $response['errors'][] = "Invalid duration for row {$rowId}.";
        echo json_encode($response);
        exit;
      }

      // If oldBoxNo is provided and different from boxNo, update the row with oldBoxNo
      if (!empty($oldBoxNo) && $oldBoxNo !== $boxNo) {
        // Check if header record exists for oldBoxNo
        $checkHeaderSp = "SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?";
        $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $oldBoxNo], 1);
        if (!empty($existingHeader)) {
          $headerId = $existingHeader[0]['RecordHeaderId'];
          // Update header record with new box number and times
          $updateHeaderSp = "UPDATE AtoDorHeader SET BoxNumber = ?, TimeStart = ?, TimeEnd = ?, Duration = ? WHERE RecordHeaderId = ?";
          $headerResult = $db1->execute($updateHeaderSp, [
            $boxNo,
            $dateTimeStart,
            $dateTimeEnd,
            $duration,
            $headerId
          ]);
          if (!$headerResult) {
            $response['success'] = false;
            $response['errors'][] = "Row {$rowId}: Failed to update header record (box number change).";
            echo json_encode($response);
            exit;
          }
        } else {
          // If not found, fall back to normal insert
          $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $boxNo], 1);
        }
      } else {
        // Check if header record already exists for this row
        $checkHeaderSp = "SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?";
        $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $boxNo], 1);
      }

      if (!empty($existingHeader)) {
        // Update existing header record
        $headerId = $existingHeader[0]['RecordHeaderId'];
        $updateHeaderSp = "UPDATE AtoDorHeader SET TimeStart = ?, TimeEnd = ?, Duration = ? WHERE RecordId = ? AND BoxNumber = ?";
        $headerResult = $db1->execute($updateHeaderSp, [
          $dateTimeStart,
          $dateTimeEnd,
          $duration,
          $recordId,
          $boxNo
        ]);
        if (!$headerResult) {
          $response['success'] = false;
          $response['errors'][] = "Row {$rowId}: Failed to update header record.";
          echo json_encode($response);
          exit;
        }
      } else {
        // Insert new header record
        $insHeaderSp = "EXEC InsAtoDorHeader @RecordId=?, @BoxNumber=?, @TimeStart=?, @TimeEnd=?, @Duration=?";
        $headerResult = $db1->execute($insHeaderSp, [
          $recordId,
          $boxNo,
          $dateTimeStart,
          $dateTimeEnd,
          $duration
        ]);
        if (!$headerResult) {
          $response['success'] = false;
          $response['errors'][] = "Row {$rowId}: Failed to save header record.";
          echo json_encode($response);
          exit;
        }
        // Get the newly inserted header ID
        $getHeaderIdSp = "SELECT TOP 1 RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ? ORDER BY RecordHeaderId DESC";
        $headerIdResult = $db1->execute($getHeaderIdSp, [$recordId, $boxNo], 1);
        $headerId = $headerIdResult[0]['RecordHeaderId'] ?? null;
        if (!$headerId) {
          $response['success'] = false;
          $response['errors'][] = "Row {$rowId}: Failed to get header ID.";
          echo json_encode($response);
          exit;
        }
      }

      // Process operators
      $operatorCodes = [];
      if (!empty($operators)) {
        $operatorCodes = explode(',', $operators);
        $operatorCodes = array_map('trim', $operatorCodes);
        $operatorCodes = array_filter($operatorCodes); // Remove empty values
      }

      // Pad operator codes array to 4 elements
      while (count($operatorCodes) < 4) {
        $operatorCodes[] = null;
      }

      // Check if detail record exists
      $checkDetailSp = "SELECT RecordHeaderId FROM AtoDorDetail WHERE RecordHeaderId = ?";
      $existingDetail = $db1->execute($checkDetailSp, [$headerId], 1);

      if (!empty($existingDetail)) {
        // Update existing detail record
        $updateDetailSp = "UPDATE AtoDorDetail SET OperatorCode1 = ?, OperatorCode2 = ?, OperatorCode3 = ?, OperatorCode4 = ? WHERE RecordHeaderId = ?";
        $detailResult = $db1->execute($updateDetailSp, [
          $operatorCodes[0] ?? null,
          $operatorCodes[1] ?? null,
          $operatorCodes[2] ?? null,
          $operatorCodes[3] ?? null,
          $headerId
        ]);
      } else {
        // Insert new detail record
        $insDetailSp = "EXEC InsAtoDorDetail @RecordHeaderId=?, @OperatorCode1=?, @OperatorCode2=?, @OperatorCode3=?, @OperatorCode4=?";
        $detailResult = $db1->execute($insDetailSp, [
          $headerId,
          $operatorCodes[0] ?? null,
          $operatorCodes[1] ?? null,
          $operatorCodes[2] ?? null,
          $operatorCodes[3] ?? null
        ]);
      }

      if (!$detailResult) {
        $response['success'] = false;
        $response['errors'][] = "Row {$rowId}: Failed to save detail record.";
        echo json_encode($response);
        exit;
      }

      $response['success'] = true;
      // Removed success message
      $response['headerId'] = $headerId;
    } catch (Exception $e) {
      $response['success'] = false;
      $response['errors'][] = "Database error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
  }

  // Regular form submit handler (btnProceed) - now just syncs data
  if (isset($_POST['btnProceed'])) {
    // Debug: Log that btnProceed was detected
    error_log("btnProceed detected in POST data");

    $recordId = $_SESSION['dorRecordId'] ?? 0;

    // ...existing DOR save logic (validation, saving rows, etc.)...
    // (Keep all the validation and saving logic as before)

    // After successful save, end session and start new one with same hostname
    if ($response['success'] === true || (isset($response['redirectUrl']) && $response['redirectUrl'] === "dor-home.php")) {
      $oldHostname = $_SESSION['hostname'] ?? '';
      session_unset();
      session_destroy();
      session_start();
      $_SESSION['hostname'] = $oldHostname;
      header("Location: dor-home.php");
      exit;
    }

    echo json_encode($response);
    exit;
  }
}

// Check if required session variables exist
if (!isset($_SESSION['dorTypeId']) || !isset($_SESSION['dorModelId'])) {
  header("Location: dor-home.php");
  exit;
}

// Clear any existing form data when starting a new DOR session
// This ensures old data from previous DOR sessions doesn't persist
if (isset($_SESSION['dorRecordId']) && $_SESSION['dorRecordId'] > 0) {
  // Only clear sessionStorage if starting a truly new DOR session (not on every reload)
  // Do NOT clear sessionStorage on every reload, only when starting a new DOR from dor-home.php
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
                <input type="text" class="form-control scan-box-no box-no-input text-center" id="boxNo<?= $i ?>"
                  name="boxNo<?= $i ?>" <?= $i === 1 ? '' : ' disabled' ?>>
                <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
              </td>
              <td class="time-column">
                <input type="text" class="form-control scan-box-no text-center time-input" id="timeStart<?= $i ?>"
                  name="timeStart<?= $i ?>" pattern="[0-9]{2}:[0-9]{2}" placeholder="HH:mm" maxlength="5"
                  <?= $i === 1 ? '' : ' disabled' ?>>
              </td>
              <td class="time-column">
                <input type="text" class="form-control scan-box-no text-center time-input" id="timeEnd<?= $i ?>"
                  name="timeEnd<?= $i ?>" pattern="[0-9]{2}:[0-9]{2}" placeholder="HH:mm" maxlength="5"
                  <?= $i === 1 ? '' : ' disabled' ?>>
              </td>
              <td class="duration-column text-center align-middle">
                <span id="duration<?= $i ?>" class="duration-value"></span>
              </td>
              <td class="operator-column align-middle text-center">
                <div class="action-container">
                  <button type="button" class="btn btn-outline-primary btn-sm btn-operator" id="operator<?= $i ?>"
                    disabled title="Operator management restricted to leaders">
                    <i class="bi bi-person-plus"></i> View Operators
                  </button>
                  <div class="operator-codes" id="operatorList<?= $i ?>">
                    <?php
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

                      // Display employee codes as badges
                      foreach ($employeeCodes as $code) {
                        echo "<small class='badge bg-light text-dark border'>" . htmlspecialchars($code) . "</small>";
                      }
                      ?>
                  </div>
                </div>
                <input type="hidden" id="operators<?= $i ?>" name="operators<?= $i ?>"
                  value="<?= implode(',', $employeeCodes) ?>">
              </td>
              <td class="remarks-column align-middle text-center">
                <div class="action-container">
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="downtime<?= $i ?>">
                    <i class="bi bi-clock-history"></i> View Downtime
                  </button>
                  <div class="downtime-info" id="downtimeInfo<?= $i ?>">
                    <?php
                      // This logic now correctly uses the $employeeCodes array defined in the previous column
                      // to determine how many placeholder badges are needed to ensure vertical alignment.

                      // We define a sample downtime record set for row 2 for demonstration
                      $downtimeRecords = [];

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
                <!-- Delete button removed for operator interface -->
              </td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

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
            <h5 class="modal-title mb-0" id="operatorModalLabel">Operators - Row <span id="operatorRowNumber"></span>
            </h5>
            <div class="d-flex align-items-center">
              <span class="text-white me-3">Box Number: <span id="modalLotNumber" class="fw-bold"></span></span>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                aria-label="Close"></button>
            </div>
          </div>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3 mb-4" style="display: none;">
            <div class="col-12 col-md-8">
              <div class="autosuggest-wrapper">
                <h6 class="mb-2">Add Operator</h6>
                <div class="position-relative">
                  <input type="text" class="form-control" id="operatorSearch"
                    placeholder="Search by Employee ID or Name" autocomplete="off" disabled
                    style="height: 45px; font-size: 16px;">
                  <div id="operatorValidationMessage" class="position-absolute"
                    style="top: -20px; right: 0; font-size: 12px;"></div>
                </div>
                <div id="operatorSuggestions" class="bg-white border rounded mt-1"
                  style="max-height: 150px; overflow-y: auto; display: none;"></div>
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
              <div id="currentOperators" class="border rounded p-3"
                style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                <p class="text-muted text-center mb-0">No operators assigned yet.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
            style="height: 45px; font-size: 16px;">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Downtime Management Modal -->
  <div class="modal fade" id="downtimeModal" tabindex="-1" aria-labelledby="downtimeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 700px;">
      <div class="modal-content">
        <div class="modal-header bg-secondary text-white py-3">
          <div class="d-flex justify-content-between align-items-center w-100">
            <h5 class="modal-title mb-0" id="downtimeModalLabel">Downtime - Row <span id="downtimeRowNumber"></span>
            </h5>
            <div class="d-flex align-items-center">
              <span class="text-white me-3">Box Number: <span id="downtimeModalLotNumber" class="fw-bold"></span></span>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                aria-label="Close"></button>
            </div>
          </div>
        </div>
        <div class="modal-body p-4">
          <form id="downtimeForm" autocomplete="off" style="display: none;">
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label for="downtimeSelect" class="form-label">Downtime</label>
                <select class="form-select" id="downtimeSelect" required>
                  <option value="">Select Downtime</option>
                  <option value="TS">Tape Sensor</option>
                  <option value="TC">Toggle Clamp</option>
                  <option value="W1">Wire 1 LED not ON</option>
                  <option value="W2">Wire 2 LED not ON</option>
                  <option value="ST">Spot tape trigger</option>
                  <option value="CT">Connector trigger</option>
                  <option value="WS">Wrong Setting</option>
                  <option value="CC">Counter complete</option>
                  <option value="DP">Drop Parts</option>
                  <option value="E">Excess Parts</option>
                  <option value="L">Lacking Parts</option>
                  <option value="IN">Incoming Defects</option>
                  <option value="IP">In-process Defects</option>
                  <option value="FOC">For confirmation harness</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label for="actionTakenSelect" class="form-label">Action Taken</label>
                <select class="form-select" id="actionTakenSelect" required>
                  <option value="">Select Action</option>
                  <option value="RJ">Reset jig counter</option>
                  <option value="RH">Reset jig then recheck 3 pcs affected harness</option>
                  <option value="DPR">Check the affected of drop parts</option>
                  <option value="RE">Return the excess parts</option>
                  <option value="RES">Return the excess parts then conduct sorting</option>
                  <option value="RL">Replenish the lacking parts</option>
                  <option value="RLS">Replenish the lacking parts then conduct sorting</option>
                  <option value="CP">Change part</option>
                  <option value="CPS">Change part then conduct sorting</option>
                  <option value="HP">Hold affected part</option>
                  <option value="HB">Hold affected box</option>
                  <option value="HL">Hold affected lot</option>
                  <option value="RC">Rework affected part then conduct checking</option>
                  <option value="RM">Request to rework then check affected product</option>
                  <option value="COG">Confirm as Good</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label for="remarksSelect" class="form-label">Remarks</label>
                <select class="form-select" id="remarksSelect">
                  <option value="">Select Remarks</option>
                  <option value="FC">For Continue</option>
                  <option value="C">Continuation</option>
                  <option value="4M">Affected of 4M</option>
                </select>
              </div>
              <div class="col-6 col-md-3">
                <label for="downtimeTimeStart" class="form-label">Time Start</label>
                <input type="text" class="form-control time-input" id="downtimeTimeStart" placeholder="HH:mm"
                  maxlength="5" required>
              </div>
              <div class="col-6 col-md-3">
                <label for="downtimeTimeEnd" class="form-label">Time End</label>
                <input type="text" class="form-control time-input" id="downtimeTimeEnd" placeholder="HH:mm"
                  maxlength="5" required>
              </div>
              <div class="col-12 col-md-6">
                <label for="downtimePic" class="form-label">PIC (Employee ID)</label>
                <input type="text" class="form-control" id="downtimePic" placeholder="Enter Employee ID" maxlength="20"
                  required>
              </div>
              <div class="col-12 col-md-6 d-flex align-items-end">
                <button type="button" class="btn btn-secondary w-100" id="addDowntimeBtn" disabled
                  style="height: 45px; font-size: 16px;">
                  <i class="bi bi-plus-circle"></i> Add
                </button>
              </div>
            </div>
          </form>
          <div class="row">
            <div class="col-12">
              <div id="currentDowntimeRecords" class="border rounded p-3"
                style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                <?php
                // Fetch actual downtime records for the selected row
                $rowBoxNo = isset($_POST['modalBoxNo']) ? trim($_POST['modalBoxNo']) : '';
                $recordId = $_SESSION['dorRecordId'] ?? 0;
                $downtimeRecords = [];
                if ($recordId > 0 && $rowBoxNo !== '') {
                  $headerRes = $db1->execute("SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?", [$recordId, $rowBoxNo], 1);
                  if (!empty($headerRes) && isset($headerRes[0]['RecordHeaderId'])) {
                    $headerId = $headerRes[0]['RecordHeaderId'];
                    $dtRows = $db1->execute("SELECT DowntimeId, TimeStart, TimeEnd FROM AtoDorDetail WHERE RecordHeaderId = ? AND DowntimeId IS NOT NULL", [$headerId], 0);
                    if (!empty($dtRows)) {
                      foreach ($dtRows as $dtRow) {
                        $downtimeId = $dtRow['DowntimeId'] ?? null;
                        $code = '';
                        if ($downtimeId) {
                          $codeRes = $db1->execute("SELECT DowntimeCode FROM GenDorDowntime WHERE DowntimeId = ?", [$downtimeId], 1);
                          if (!empty($codeRes) && isset($codeRes[0]['DowntimeCode'])) {
                            $code = $codeRes[0]['DowntimeCode'];
                          }
                        }
                        $start = $dtRow['TimeStart'] ?? '';
                        $end = $dtRow['TimeEnd'] ?? '';
                        $downtimeRecords[] = [
                          'code' => $code,
                          'start' => $start,
                          'end' => $end
                        ];
                      }
                    }
                  }
                }
                if (empty($downtimeRecords)) {
                  echo '<p id="noDowntimeRecordsMsg" class="text-muted text-center mb-0">No downtime records added yet.</p>';
                } else {
                  foreach ($downtimeRecords as $rec) {
                    $duration = '';
                    if (!empty($rec['start']) && !empty($rec['end'])) {
                      $start = strtotime($rec['start']);
                      $end = strtotime($rec['end']);
                      if ($start !== false && $end !== false && $end > $start) {
                        $duration = round(($end - $start) / 60) . ' min';
                      }
                    }
                    // Fetch PIC, Action Taken, Remarks for this downtime record
                    $pic = '';
                    $actionTaken = '';
                    $remarks = '';
                    if (isset($rec['detailId'])) {
                      $detailRes = $db1->execute("SELECT Pic, ActionTaken, Remarks FROM AtoDorDetail WHERE DetailId = ?", [$rec['detailId']], 1);
                      if (!empty($detailRes[0])) {
                        $pic = $detailRes[0]['Pic'] ?? '';
                        $actionTaken = $detailRes[0]['ActionTaken'] ?? '';
                        $remarks = $detailRes[0]['Remarks'] ?? '';
                      }
                    }
                    echo '<div class="d-flex flex-wrap align-items-center mb-2 p-2 border rounded bg-light">';
                    echo '<div class="d-flex align-items-center flex-wrap">';
                    echo '<small class="badge bg-light text-dark border mx-1" style="min-width:60px;text-align:center;">' . htmlspecialchars($rec['code']) . '</small>';
                    echo '<input type="text" class="form-control form-control-sm mx-1" value="' . htmlspecialchars($rec['start']) . '" readonly style="width:80px;">';
                    echo '<span class="mx-1">-</span>';
                    echo '<input type="text" class="form-control form-control-sm mx-1" value="' . htmlspecialchars($rec['end']) . '" readonly style="width:80px;">';
                    echo '<span class="mx-1">Duration:</span>';
                    echo '<input type="text" class="form-control form-control-sm mx-1" value="' . htmlspecialchars($duration) . '" readonly style="width:70px;">';
                    if ($pic !== '') {
                      echo '<small class="badge bg-light text-dark border mx-1">PIC: ' . htmlspecialchars($pic) . '</small>';
                    }
                    if ($actionTaken !== '') {
                      echo '<small class="badge bg-light text-dark border mx-1">Action: ' . htmlspecialchars($actionTaken) . '</small>';
                    }
                    if ($remarks !== '') {
                      echo '<small class="badge bg-light text-dark border mx-1">Remarks: ' . htmlspecialchars($remarks) . '</small>';
                    }
                    echo '</div>';
                    echo '</div>';
                  }
                }
                ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
            style="height: 45px; font-size: 16px;">Close</button>
          <button type="button" class="btn btn-secondary" id="saveDowntimeBtn"
            style="height: 45px; font-size: 16px; display: none;">Save Changes</button>
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
  // --- AJAX polling for operator/downtime updates per row ---
  function fetchRowStatus(rowId) {
    const boxNoInput = document.getElementById(`boxNo${rowId}`);
    const boxNo = boxNoInput ? boxNoInput.value.trim() : '';
    if (!boxNo) return;
    const formData = new FormData();
    formData.append('action', 'get_row_status');
    formData.append('boxNo', boxNo);
    fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show downtime code badges outside modal (in table)
          const downtimeDiv = document.getElementById(`downtimeInfo${rowId}`);
          if (downtimeDiv) {
            if (typeof data.downtimeBadges === 'string' && data.downtimeBadges.trim() !== '') {
              downtimeDiv.innerHTML = data.downtimeBadges;
            } else {
              downtimeDiv.innerHTML = '<small class="badge bg-light text-dark border mx-1">No downtime</small>';
            }
          }
          // Show operator badges outside modal (in table)
          const operatorDiv = document.getElementById(`operatorList${rowId}`);
          if (operatorDiv) {
            if (typeof data.operatorBadges === 'string' && data.operatorBadges.trim() !== '') {
              operatorDiv.innerHTML = data.operatorBadges;

              // Update the hidden operators input to preserve database values
              const operatorsInput = document.getElementById(`operators${rowId}`);
              if (operatorsInput && data.operatorCodes) {
                // data.operatorCodes should be an array of operator codes from the database
                operatorsInput.value = data.operatorCodes.join(',');
              }
            } else {
              operatorDiv.innerHTML = '<small class="badge bg-light text-dark border mx-1">No operator</small>';

              // Clear the hidden input if no operators
              const operatorsInput = document.getElementById(`operators${rowId}`);
              if (operatorsInput) {
                operatorsInput.value = '';
              }
            }
          }
          // Attach click event to View Downtime button to show modal with details
          const viewBtn = document.getElementById(`downtime${rowId}`);
          if (viewBtn) {
            viewBtn.onclick = function() {
              // Fill modal with downtime details for this row
              const modalEl = document.getElementById('downtimeModal');
              if (!modalEl) return;
              const modal = new bootstrap.Modal(modalEl);
              document.getElementById('downtimeRowNumber').textContent = rowId;
              document.getElementById('downtimeModalLotNumber').textContent = boxNo;
              const recordsDiv = document.getElementById('currentDowntimeRecords');
              if (recordsDiv) {
                if (Array.isArray(data.downtimeRecords) && data.downtimeRecords.length > 0) {
                  let html = '';
                  data.downtimeRecords.forEach(rec => {
                    // Defensive: fallback for undefined/null
                    const code = rec.downtimeCode !== undefined && rec.downtimeCode !== null ? rec
                      .downtimeCode : '';
                    let tStart = (rec.timeStart !== undefined && rec.timeStart !== null) ? String(rec
                      .timeStart) : '';
                    let tEnd = (rec.timeEnd !== undefined && rec.timeEnd !== null) ? String(rec.timeEnd) : '';
                    // Try to extract HH:mm if full datetime
                    if (tStart.length >= 16 && tStart.includes(':')) tStart = tStart.substring(11, 16);
                    if (tEnd.length >= 16 && tEnd.includes(':')) tEnd = tEnd.substring(11, 16);
                    if (tStart.length === 0) tStart = '--:--';
                    if (tEnd.length === 0) tEnd = '--:--';
                    let duration = (rec.duration !== undefined && rec.duration !== null && rec.duration !==
                      '') ? rec.duration + ' min' : '--';
                    html += `<div class="d-flex flex-wrap align-items-center mb-2 p-2 border rounded bg-light">
                        <div class="d-flex align-items-center flex-wrap">
                          <small class="badge bg-light text-dark border mx-1" style="min-width:60px;text-align:center;">${code}</small>
                          <span class="mx-1">Start:</span>
                          <input type="text" class="form-control form-control-sm mx-1" value="${tStart}" readonly style="width:80px;">
                          <span class="mx-1">End:</span>
                          <input type="text" class="form-control form-control-sm mx-1" value="${tEnd}" readonly style="width:80px;">
                          <span class="mx-1">Duration:</span>
                          <input type="text" class="form-control form-control-sm mx-1" value="${duration}" readonly style="width:70px;">
                        </div>
                      </div>`;
                  });
                  recordsDiv.innerHTML = html;
                } else {
                  recordsDiv.innerHTML =
                    '<p id="noDowntimeRecordsMsg" class="text-muted text-center mb-0">No downtime records added yet.</p>';
                }
              }
              // Re-enable modal buttons if they were disabled
              const closeBtn = modalEl.querySelector('button[data-bs-dismiss="modal"]');
              if (closeBtn) closeBtn.disabled = false;
              const saveBtn = document.getElementById('saveDowntimeBtn');
              if (saveBtn) saveBtn.disabled = false;
              // Remove fade class to prevent stuck fade state
              modalEl.classList.remove('fade');
              // Remove any lingering modal-backdrop before showing
              document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
              // Remove modal-open class from body
              document.body.classList.remove('modal-open');
              document.body.style.overflow = '';
              document.body.style.paddingRight = '';
              // Add event to fully clean up modal state on close
              modalEl.addEventListener('hidden.bs.modal', function handler() {
                modalEl.classList.remove('show');
                modalEl.classList.remove('fade');
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                modalEl.removeEventListener('hidden.bs.modal', handler);
              });
              modal.show();
            };
          }
        }
      });
  }

  // Poll every 1 second for each row with a box number
  setInterval(() => {
    // First, check for rows deleted by leaders
    checkForDeletedRows();

    // Then poll individual rows for updates
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      if (boxNoInput && boxNoInput.value.trim()) {
        fetchRowStatus(i);
      }
    }
    // After polling all rows, update the total downtime
    setTimeout(updateTotalDowntime, 500); // Give AJAX a moment to update DOM
    // Also save form data after polling to capture any operator updates
    setTimeout(saveFormData, 600);
  }, 1000);

  // Function to check for rows deleted by leaders
  function checkForDeletedRows() {
    const formData = new FormData();
    formData.append('action', 'get_all_rows');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const existingBoxNumbers = data.existingBoxNumbers || [];

          // Check each row in the UI
          for (let i = 1; i <= 20; i++) {
            const boxNoInput = document.getElementById(`boxNo${i}`);
            if (boxNoInput && boxNoInput.value.trim()) {
              const currentBoxNo = boxNoInput.value.trim();

              // If this box number no longer exists in database, it was deleted by a leader
              if (!existingBoxNumbers.includes(currentBoxNo)) {
                // Clear this row and shift rows up
                performRowShift(i);
                break; // Only handle one deletion at a time to avoid conflicts
              }
            }
          }
        }
      })
      .catch(error => {
        console.error('Error checking for deleted rows:', error);
      });
  }

  // Function to compute and update total downtime
  function updateTotalDowntime() {
    let totalDowntime = 0;
    for (let i = 1; i <= 20; i++) {
      const downtimeDiv = document.getElementById(`downtimeInfo${i}`);
      if (downtimeDiv) {
        // Count only real downtime badges (not placeholders or 'No downtime')
        const badges = downtimeDiv.querySelectorAll('small.badge');
        badges.forEach(badge => {
          const text = badge.textContent.trim();
          // Exclude placeholders and 'No downtime'
          if (
            !badge.classList.contains('placeholder-badge') &&
            text !== '' &&
            text.toLowerCase() !== 'no downtime'
          ) {
            totalDowntime++;
          }
        });
      }
    }
    document.getElementById('totalDowntime').textContent = totalDowntime;
  }

  // Function to merge database values with session storage, giving priority to database
  function mergeOperatorData(rowId, databaseCodes, sessionCodes) {
    const operatorsInput = document.getElementById(`operators${rowId}`);
    const operatorListDiv = document.getElementById(`operatorList${rowId}`);

    // If database has operator codes, use those (leader has assigned operators)
    if (databaseCodes && databaseCodes.length > 0) {
      if (operatorsInput) {
        operatorsInput.value = databaseCodes.join(',');
      }

      if (operatorListDiv) {
        operatorListDiv.innerHTML = '';
        databaseCodes.forEach(code => {
          const badge = document.createElement('small');
          badge.className = 'badge bg-light text-dark border me-1 mb-1';
          badge.textContent = code;
          operatorListDiv.appendChild(badge);
        });
      }

      return databaseCodes;
    }
    // Otherwise, use session codes (fallback)
    else if (sessionCodes && sessionCodes.length > 0) {
      if (operatorsInput) {
        operatorsInput.value = sessionCodes.join(',');
      }

      if (operatorListDiv) {
        operatorListDiv.innerHTML = '';
        sessionCodes.forEach(code => {
          const badge = document.createElement('small');
          badge.className = 'badge bg-light text-dark border me-1 mb-1';
          badge.textContent = code;
          operatorListDiv.appendChild(badge);
        });
      }

      return sessionCodes;
    }

    return [];
  }

  let errorModalInstance = null;
  let errorModalIsOpen = false;

  function showErrorModal(message) {
    const modalErrorMessage = document.getElementById("modalErrorMessage");

    // Format message as bullet points if it's not already formatted
    if (message.includes('<ul>') || message.includes('<li>')) {
      // Message is already formatted as HTML
      modalErrorMessage.innerHTML = message;
    } else {
      // Format as bullet points
      modalErrorMessage.innerHTML = "<ul><li>" + message + "</li></ul>";
    }

    // Create modal instance only once
    if (!errorModalInstance) {
      errorModalInstance = new bootstrap.Modal(document.getElementById("errorModal"));
    }

    // Only show if not already open
    if (!errorModalIsOpen) {
      errorModalInstance.show();
      errorModalIsOpen = true;
    }
  }

  // Ensure modal backdrop is properly cleaned up and allow closing
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
      errorModalIsOpen = false;
    });
  });
  </script>

  <script>
  document.addEventListener("DOMContentLoaded", function() {
    // Save form data on every change to prevent data loss on reload
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      const timeStartInput = document.getElementById(`timeStart${i}`);
      const timeEndInput = document.getElementById(`timeEnd${i}`);
      const operatorsInput = document.getElementById(`operators${i}`);

      if (boxNoInput) {
        boxNoInput.addEventListener('input', saveFormData);
        boxNoInput.addEventListener('change', saveFormData);
        boxNoInput.addEventListener('blur', saveFormData);
      }
      if (timeStartInput) {
        timeStartInput.addEventListener('input', saveFormData);
        timeStartInput.addEventListener('change', saveFormData);
        timeStartInput.addEventListener('blur', saveFormData);
      }
      if (timeEndInput) {
        timeEndInput.addEventListener('input', saveFormData);
        timeEndInput.addEventListener('change', saveFormData);
        timeEndInput.addEventListener('blur', saveFormData);
      }
      if (operatorsInput) {
        operatorsInput.addEventListener('input', saveFormData);
        operatorsInput.addEventListener('change', saveFormData);
        operatorsInput.addEventListener('blur', saveFormData);
      }
    }
    // Restore form data from session storage
    restoreFormData();

    const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
    const video = document.getElementById("qr-video");
    let canvas = document.createElement("canvas");
    let ctx = canvas.getContext("2d", {
      willReadFrequently: true
    });
    let scanning = false;
    let activeRowId = null;

    // Save form data when user navigates away
    window.addEventListener('beforeunload', function() {
      saveFormData();
    });

    // Save form data when user clicks back button
    document.querySelector('button[onclick="goBack()"]').addEventListener('click', function() {
      saveFormData();
    });

    // Time input validation
    document.querySelectorAll('.time-input').forEach(input => {
      input.addEventListener('input', function(e) {
        let value = this.value;
        const cursorPosition = this.selectionStart;

        // Check if user is trying to delete (backspace or delete key)
        const isDeleting = e.inputType === 'deleteContentBackward' || e.inputType ===
          'deleteContentForward';

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
          // If user types 2 digits and no colon, add colon but don't trigger other events
          else if (cleanValue.length === 2 && !cleanValue.includes(':')) {
            let hours = cleanValue;
            if (parseInt(hours) > 23) hours = '23';
            cleanValue = hours + ':';
          }
        }

        this.value = cleanValue;

        // Only update duration if we have a complete HH:mm format
        if (cleanValue.length === 5 && cleanValue.includes(':')) {
          const rowId = this.closest('tr').getAttribute('data-row-id');
          const timeStartInput = document.getElementById(`timeStart${rowId}`);
          const timeEndInput = document.getElementById(`timeEnd${rowId}`);

          // Only calculate duration if both times are complete
          if (timeStartInput && timeEndInput &&
            timeStartInput.value.length === 5 && timeEndInput.value.length === 5) {
            updateDuration(rowId);
          }
        }
      });

      // Validate time on blur (when input loses focus)
      input.addEventListener('blur', function(e) {
        let value = this.value;

        // Only validate if the input has some content and user has finished typing
        if (value.length > 0) {
          if (value.length === 5) { // HH:mm format
            let [hours, minutes] = value.split(':').map(Number);

            // Check if time is valid
            if (isNaN(hours) || isNaN(minutes) ||
              hours < 0 || hours > 23 ||
              minutes < 0 || minutes > 59) {
              // Show error message
              if (!errorModalIsOpen) showErrorModal('Enter time between 00:00 and 23:59');
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
                  if (!errorModalIsOpen) showErrorModal(
                    `Incorrect start time and end time in row ${rowId}`);

                  // Clear the invalid time input but don't force focus
                  this.value = '';
                  this.classList.add('is-invalid');

                  // Update row states since this row is now incomplete
                  updateRowStates();
                  return;
                }

                updateDuration(rowId);
              }
            }
          } else if (value.length >= 3) {
            // Only show error if user has typed at least 3 characters but format is wrong
            // This prevents showing error while user is still typing
            if (!errorModalIsOpen) showErrorModal('Enter time in HH:mm format');
            this.value = '';
            this.classList.add('is-invalid');
          }
          // If length is 1-2, don't show error - user might still be typing
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
              alert("Camera permission denied");
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

          if (parts.length === 3) {
            // Three parts - Model, Quantity, Lot/Box Number
            const scannedModel = parts[0];
            const scannedQty = parts[1];
            const lotNumber = parts[2];
            const sessionQty = <?php echo json_encode($_SESSION['dorQty'] ?? ''); ?>;

            // Check if model matches
            if (scannedModel !== modelName) {
              showErrorModal("Incorrect model name");
              stopScanning();
              return;
            }

            // Check if quantity matches
            if (scannedQty !== sessionQty.toString()) {
              showErrorModal("Incorrect quantity");
              stopScanning();
              return;
            }

            // Both model and quantity match, proceed with box number
            if (activeRowId) {
              const boxNoInput = document.getElementById(`boxNo${activeRowId}`);
              const timeStartInput = document.getElementById(`timeStart${activeRowId}`);
              const timeEndInput = document.getElementById(`timeEnd${activeRowId}`);

              if (boxNoInput && timeStartInput && timeEndInput) {
                // Check for duplicate box number only if this is a new box number (not time end scan)
                if (!timeStartInput.value && isDuplicateBoxNumber(lotNumber)) {
                  showErrorModal(`Box number ${lotNumber} already exists in row ${activeRowId}`);
                  stopScanning();
                  return;
                }

                // Set box number
                boxNoInput.value = lotNumber;
                boxNoInput.select(); // Ensure next scan/typing replaces the value
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
                  // Move focus to next box number input
                  const nextBoxNoInput = document.getElementById(`boxNo${parseInt(activeRowId) + 1}`);
                  if (nextBoxNoInput && !nextBoxNoInput.disabled) {
                    nextBoxNoInput.focus();
                  }
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
            showErrorModal("Invalid QR code format");
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

    // Add click event listeners to row numbers (now supports both camera and gun scanner)
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
          // Fallback to focusing the box number input for gun scanner
          const boxNoInput = document.getElementById(`boxNo${rowId}`);
          if (boxNoInput && !boxNoInput.disabled) {
            boxNoInput.focus();
          }
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
    // Restore form data from session storage
    restoreFormData();

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
      if (errorModalIsOpen) {
        // Prevent repeated validation/modal if already open
        e.preventDefault();
        return;
      }

      // Only run validation if btnProceed triggered this
      if (!clickedButton || clickedButton.id !== "btnProceed") {
        // If no button was clicked, allow form submission for btnProceed
        if (e.submitter && e.submitter.id === "btnProceed") {
          clickedButton = e.submitter;
        } else {
          return; // Skip validation if other buttons are clicked (e.g., Drawing, WI)
        }
      }

      // Validate incomplete information in table rows
      let incompleteRows = [];
      let completeRows = 0;
      let totalRowsWithData = 0;

      for (let i = 1; i <= 20; i++) {
        const boxNoInput = document.getElementById(`boxNo${i}`);
        const timeStartInput = document.getElementById(`timeStart${i}`);
        const timeEndInput = document.getElementById(`timeEnd${i}`);

        if (boxNoInput && timeStartInput && timeEndInput) {
          const boxNo = boxNoInput.value.trim();
          const timeStart = timeStartInput.value.trim();
          const timeEnd = timeEndInput.value.trim();

          // Check if any field has a value
          const hasAnyValue = boxNo || timeStart || timeEnd;
          const isValidTime = (val) => val && val !== 'HH:mm';
          const hasAllValues = boxNo && isValidTime(timeStart) && isValidTime(timeEnd);

          if (hasAnyValue) {
            totalRowsWithData++;

            // If any field has a value, all three must have values
            if (!hasAllValues) {
              incompleteRows.push(i);
            } else {
              completeRows++;
            }
          }
        }
      }

      // If there are incomplete rows, show that error first
      if (incompleteRows.length > 0) {
        // Highlight incomplete fields and scroll to first incomplete row
        incompleteRows.forEach(rowNum => {
          const boxNoInput = document.getElementById(`boxNo${rowNum}`);
          const timeStartInput = document.getElementById(`timeStart${rowNum}`);
          const timeEndInput = document.getElementById(`timeEnd${rowNum}`);
          [boxNoInput, timeStartInput, timeEndInput].forEach(input => {
            if (input && !input.value.trim()) {
              input.classList.add('is-invalid');
              input.style.borderColor = 'red';
            }
          });
        });
        // Scroll to first incomplete row
        if (incompleteRows.length > 0) {
          const firstRow = document.querySelector(`tr[data-row-id='${incompleteRows[0]}']`);
          if (firstRow) {
            firstRow.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
          }
        }
        let errorHtml = "<ul>";
        incompleteRows.forEach(rowNum => {
          errorHtml += `<li>Incomplete data in row ${rowNum}</li>`;
        });
        errorHtml += "</ul>";
        showErrorModal(errorHtml);
        e.preventDefault();
        return;
      }

      // If there are no complete rows, show "No records found"
      if (completeRows === 0) {
        showErrorModal("No records to save");
        e.preventDefault();
        return;
      }

      // Create and submit form data
      let formData = new FormData(form);
      if (clickedButton) {
        formData.append(clickedButton.name, clickedButton.value || "1");
      }

      // Submit form data to server
      fetch(form.action, {
          method: "POST",
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
        .then(response => {
          if (response.redirected) {
            window.location.href = response.url;
          } else {
            return response.json();
          }
        })
        .then(data => {
          if (data) {
            if (data.success && data.redirectUrl) {
              window.location.href = data.redirectUrl;
            } else if (data.errors && data.errors.length > 0) {
              showErrorModal(data.errors.join('\n'));
            }
          }
        })
        .catch(error => {
          console.error("Error:", error);
          showErrorModal('An error occurred while processing your request.');
        });
    });

  });

  // Function to save form data to session storage
  function saveFormData() {
    const formData = {};

    // Save all box numbers, time inputs, and operator inputs
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      const timeStartInput = document.getElementById(`timeStart${i}`);
      const timeEndInput = document.getElementById(`timeEnd${i}`);
      const operatorsInput = document.getElementById(`operators${i}`);

      if (boxNoInput) {
        formData[`boxNo${i}`] = boxNoInput.value;
      }
      if (timeStartInput) {
        formData[`timeStart${i}`] = timeStartInput.value;
      }
      if (timeEndInput) {
        formData[`timeEnd${i}`] = timeEndInput.value;
      }
      if (operatorsInput) {
        formData[`operators${i}`] = operatorsInput.value;
      }
    }

    sessionStorage.setItem('dorDorData', JSON.stringify(formData));
    // Also save the current record ID to prevent restoring data from different DOR sessions
    sessionStorage.setItem('dorRecordId', <?php echo $_SESSION['dorRecordId'] ?? 0; ?>);

    // Save timestamp to track when data was last saved
    sessionStorage.setItem('dorDataTimestamp', Date.now().toString());
  }

  // Function to restore form data from session storage
  function restoreFormData() {
    const savedData = sessionStorage.getItem('dorDorData');
    if (!savedData) return;

    // Check if this data belongs to the current DOR session
    const currentRecordId = <?php echo $_SESSION['dorRecordId'] ?? 0; ?>;
    const savedRecordId = sessionStorage.getItem('dorRecordId');

    // If record IDs don't match, clear the old data and don't restore
    if (savedRecordId && parseInt(savedRecordId) !== currentRecordId) {
      sessionStorage.removeItem('dorDorData');
      sessionStorage.removeItem('dorRecordId');
      return;
    }

    try {
      const formData = JSON.parse(savedData);

      // Restore all form inputs
      for (let i = 1; i <= 20; i++) {
        const boxNoInput = document.getElementById(`boxNo${i}`);
        const timeStartInput = document.getElementById(`timeStart${i}`);
        const timeEndInput = document.getElementById(`timeEnd${i}`);
        const operatorsInput = document.getElementById(`operators${i}`);

        if (boxNoInput && formData[`boxNo${i}`]) {
          boxNoInput.value = formData[`boxNo${i}`];
        }
        if (timeStartInput && formData[`timeStart${i}`]) {
          timeStartInput.value = formData[`timeStart${i}`];
        }
        if (timeEndInput && formData[`timeEnd${i}`]) {
          timeEndInput.value = formData[`timeEnd${i}`];
        }
        if (operatorsInput && formData[`operators${i}`]) {
          operatorsInput.value = formData[`operators${i}`];

          // Update operator display to match the restored data
          const operatorListDiv = document.getElementById(`operatorList${i}`);
          if (operatorListDiv) {
            const operatorCodes = formData[`operators${i}`].split(',').filter(code => code.trim());
            operatorListDiv.innerHTML = '';

            operatorCodes.forEach(code => {
              if (code.trim()) {
                const badge = document.createElement('small');
                badge.className = 'badge bg-light text-dark border me-1 mb-1';
                badge.textContent = code.trim();
                operatorListDiv.appendChild(badge);
              }
            });

            // If no operators, show placeholder badges based on default session operators
            if (operatorCodes.length === 0) {
              // Get the original session operator codes for placeholders
              const defaultOperators = <?php echo json_encode($employeeCodes ?? []); ?>;
              defaultOperators.forEach(code => {
                const badge = document.createElement('small');
                badge.className = 'badge bg-light text-dark border me-1 mb-1';
                badge.textContent = code;
                operatorListDiv.appendChild(badge);
              });
            }
          }
        }
      }

      // Update row states and durations after restoring data
      updateRowStates();
      for (let i = 1; i <= 20; i++) {
        updateDuration(i);
      }
      updateDORSummary();

      // After restoring session data, fetch any database updates to override session values
      setTimeout(() => {
        for (let i = 1; i <= 20; i++) {
          const boxNoInput = document.getElementById(`boxNo${i}`);
          if (boxNoInput && boxNoInput.value.trim()) {
            fetchRowStatus(i); // This will get the latest database values
          }
        }
      }, 500);

    } catch (error) {
      console.error('Error restoring form data:', error);
    }
  }

  // Function to clear all form data from session storage and form inputs
  function clearAllFormData() {
    // Clear session storage
    sessionStorage.removeItem('dorFormData');
    sessionStorage.removeItem('dorRefreshData');
    sessionStorage.removeItem('dorDorData');
    sessionStorage.removeItem('dorHomeData');
    sessionStorage.removeItem('activeTab');
    sessionStorage.removeItem('dorRecordId');

    // Clear all form inputs
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      const timeStartInput = document.getElementById(`timeStart${i}`);
      const timeEndInput = document.getElementById(`timeEnd${i}`);
      const operatorsInput = document.getElementById(`operators${i}`);
      const downtimeInput = document.getElementById(`downtime${i}`);

      if (boxNoInput) {
        boxNoInput.value = '';
        boxNoInput.disabled = i !== 1; // Re-enable only first row
      }
      if (timeStartInput) {
        timeStartInput.value = '';
      }
      if (timeEndInput) {
        timeEndInput.value = '';
      }
      if (operatorsInput) {
        operatorsInput.value = '';
      }
      if (downtimeInput) {
        downtimeInput.value = '';
      }

      // Clear hidden inputs
      const modelNameInput = document.getElementById(`modelName${i}`);
      const lotNumberInput = document.getElementById(`lotNumber${i}`);

      if (modelNameInput) {
        modelNameInput.value = '';
      }
      if (lotNumberInput) {
        lotNumberInput.value = '';
      }
    }

    // Reset summary displays
    document.getElementById('totalBoxQty').textContent = '0';
    document.getElementById('totalDuration').textContent = '0 mins';
    document.getElementById('totalDowntime').textContent = '0';

    // Reset row states
    updateRowStates();
  }

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
  // Remove the conflicting modal instance creation
  // const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
  const modalErrorMessage = document.getElementById("modalErrorMessage");

  let clickedButton = null;

  // Track which submit button was clicked
  document.getElementById("btnProceed").addEventListener("click", function(e) {
    e.preventDefault(); // Prevent default button behavior
    clickedButton = this;
    // Trigger form submit event
    form.dispatchEvent(new Event('submit'));
  });

  // Function to perform row shifting after deletion
  function performRowShift(deletedRowId) {
    // Loop from the deleted row to the second-to-last row (19) to shift all content up
    for (let i = deletedRowId; i < 20; i++) {
      const currentRow = document.querySelector(`tr[data-row-id="${i}"]`);
      const nextRow = document.querySelector(`tr[data-row-id="${i + 1}"]`);

      if (!currentRow || !nextRow) continue;

      // Store old values for database update
      const oldBoxNo = document.getElementById(`boxNo${i}`).value.trim();
      const newBoxNo = document.getElementById(`boxNo${i + 1}`).value.trim();
      const timeStart = document.getElementById(`timeStart${i + 1}`).value.trim();
      const timeEnd = document.getElementById(`timeEnd${i + 1}`).value.trim();
      const operators = document.getElementById(`operators${i + 1}`).value.trim();

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

      // Update database if there was data in the current row
      if (oldBoxNo && newBoxNo && oldBoxNo !== newBoxNo) {
        updateRowInDatabase(i, oldBoxNo, newBoxNo, timeStart, timeEnd, operators);
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
  }

  // Function to update row in database after shift
  function updateRowInDatabase(rowId, oldBoxNo, newBoxNo, timeStart, timeEnd, operators) {
    const formData = new FormData();
    formData.append('action', 'update_row');
    formData.append('rowId', rowId);
    formData.append('oldBoxNo', oldBoxNo);
    formData.append('newBoxNo', newBoxNo);
    formData.append('timeStart', timeStart);
    formData.append('timeEnd', timeEnd);
    formData.append('operators', operators);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {} else {
          console.error(`Failed to update row ${rowId} in database:`, data.errors);
        }
      })
      .catch(error => {
        console.error(`Error updating row ${rowId}:`, error);
      });
  }

  function clearRow(rowId) {
    const row = document.querySelector(`tr[data-row-id="${rowId}"]`);

    if (row) {
      // Get the box number before clearing
      const boxNoInput = document.getElementById(`boxNo${rowId}`);
      const boxNo = boxNoInput ? boxNoInput.value.trim() : '';

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

      // Clear from database if there was data
      if (boxNo) {
        clearRowFromDatabase(rowId, boxNo);
      }
    }
  }

  // Function to clear row from database
  function clearRowFromDatabase(rowId, boxNo) {
    const formData = new FormData();
    formData.append('action', 'clear_row');
    formData.append('rowId', rowId);
    formData.append('boxNo', boxNo);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {} else {
          console.error(`Failed to clear row ${rowId} from database:`, data.errors);
        }
      })
      .catch(error => {
        console.error(`Error clearing row ${rowId}:`, error);
      });
  }

  // Function to check if a row is complete
  function isRowComplete(rowId) {
    const boxNoInput = document.getElementById(`boxNo${rowId}`);
    const timeStartInput = document.getElementById(`timeStart${rowId}`);
    const timeEndInput = document.getElementById(`timeEnd${rowId}`);

    if (!boxNoInput || !timeStartInput || !timeEndInput) {
      return false;
    }

    const boxNo = boxNoInput.value.trim();
    const timeStart = timeStartInput.value.trim();
    const timeEnd = timeEndInput.value.trim();

    // All three fields are required and must be valid
    return boxNo && timeStart && timeEnd && timeStart !== 'HH:mm' && timeEnd !== 'HH:mm';
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

      // If previous row is complete, enable the current row's box number input
      if (prevRowComplete) {
        const boxNoInput = document.getElementById(`boxNo${i}`);
        if (boxNoInput && boxNoInput.disabled) {
          boxNoInput.disabled = false;
        }
      }
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
      // Store old box number on focus
      boxNoInput.addEventListener('focus', function() {
        this.dataset.oldBoxNo = this.value;
      });

      // Validate and save on change (when user types and input loses focus)
      boxNoInput.addEventListener('change', function() {
        validateBoxNumberDuplicate(this);
        // Save row with old and new box numbers
        const rowId = this.closest('tr').getAttribute('data-row-id');
        const oldBoxNo = this.dataset.oldBoxNo || '';
        const newBoxNo = this.value.trim();
        const timeStart = document.getElementById(`timeStart${rowId}`).value.trim();
        const timeEnd = document.getElementById(`timeEnd${rowId}`).value.trim();
        const operators = document.getElementById(`operators${rowId}`).value.trim();
        if (newBoxNo && timeStart && timeEnd && timeStart !== 'HH:mm' && timeEnd !== 'HH:mm') {
          // Validate time format
          if (!/^\d{2}:\d{2}$/.test(timeStart) || !/^\d{2}:\d{2}$/.test(timeEnd)) {
            return;
          }
          // Validate duration
          const duration = calculateDuration(timeStart, timeEnd);
          if (duration === 'INVALID') {
            return;
          }
          // Prepare form data for saving
          const formData = new FormData();
          formData.append('action', 'save_row');
          formData.append('rowId', rowId);
          formData.append('boxNo', newBoxNo);
          formData.append('oldBoxNo', oldBoxNo);
          formData.append('timeStart', timeStart);
          formData.append('timeEnd', timeEnd);
          formData.append('operators', operators);
          // Add visual indicator that auto-save is in progress
          const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
          if (row) {
            row.classList.add('row-saving');
          }
          // Save row to database
          fetch(window.location.href, {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              // Remove saving indicator
              if (row) {
                row.classList.remove('row-saving');
              }
              if (data.success) {
                // Add a small visual indicator that the row was saved
                if (row) {
                  row.classList.add('row-saved');
                  setTimeout(() => {
                    row.classList.remove('row-saved');
                  }, 2000); // Remove the indicator after 2 seconds
                }
                // Update the oldBoxNo to the new value
                boxNoInput.dataset.oldBoxNo = newBoxNo;
              } else {
                console.error(`Failed to auto-save row ${rowId}:`, data.errors);
                // Optionally show error to user
                if (data.errors && data.errors.length > 0) {
                  showErrorModal(`Auto-save failed for row ${rowId}: ${data.errors.join(', ')}`);
                }
              }
            })
            .catch(error => {
              // Remove saving indicator on error
              if (row) {
                row.classList.remove('row-saving');
              }
              console.error(`Error auto-saving row ${rowId}:`, error);
            });
        }
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
      // Only calculate duration if both times are complete HH:mm format
      if (timeStartInput.value.length === 5 && timeEndInput.value.length === 5) {
        const duration = calculateDuration(timeStartInput.value, timeEndInput.value);

        if (duration === 'INVALID') {
          // Show error for invalid duration
          showErrorModal(`Incorrect start time and end time in row ${rowId}`);

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

        // Auto-save row when all required fields are complete
        autoSaveRow(rowId);

        // Check if row is complete and enable next row
        if (isRowComplete(rowId)) {
          setTimeout(() => {
            const nextRowId = parseInt(rowId) + 1;
            if (nextRowId <= 20) {
              const nextBoxNoInput = document.getElementById(`boxNo${nextRowId}`);
              if (nextBoxNoInput) {
                // Ensure the next row is enabled
                nextBoxNoInput.disabled = false;
                // Update row states to ensure proper enabling
                updateRowStates();
                // Focus and select the next box number input
                nextBoxNoInput.focus();
                nextBoxNoInput.select();
              }
            }
          }, 100); // Small delay to ensure DOM updates are complete
        }
      } else {
        // Clear duration display if times are incomplete
        durationSpan.textContent = '';
      }
    }
  }

  // Function to auto-save a row when it's complete
  function autoSaveRow(rowId) {
    const boxNoInput = document.getElementById(`boxNo${rowId}`);
    const timeStartInput = document.getElementById(`timeStart${rowId}`);
    const timeEndInput = document.getElementById(`timeEnd${rowId}`);
    const operatorsInput = document.getElementById(`operators${rowId}`);

    if (!boxNoInput || !timeStartInput || !timeEndInput || !operatorsInput) {
      return;
    }

    const boxNo = boxNoInput.value.trim();
    const timeStart = timeStartInput.value.trim();
    const timeEnd = timeEndInput.value.trim();
    const operators = operatorsInput.value.trim();

    // Check if all required fields are filled
    if (boxNo && timeStart && timeEnd && timeStart !== 'HH:mm' && timeEnd !== 'HH:mm') {
      // Validate time format
      if (!/^\d{2}:\d{2}$/.test(timeStart) || !/^\d{2}:\d{2}$/.test(timeEnd)) {
        return;
      }

      // Validate duration
      const duration = calculateDuration(timeStart, timeEnd);
      if (duration === 'INVALID') {
        return;
      }

      // Prepare form data for saving
      const formData = new FormData();
      formData.append('action', 'save_row');
      formData.append('rowId', rowId);
      formData.append('boxNo', boxNo);
      formData.append('timeStart', timeStart);
      formData.append('timeEnd', timeEnd);
      formData.append('operators', operators);

      // Add visual indicator that auto-save is in progress
      const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
      if (row) {
        row.classList.add('row-saving');
      }

      // Save row to database
      fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          // Remove saving indicator
          if (row) {
            row.classList.remove('row-saving');
          }

          if (data.success) {
            // Add a small visual indicator that the row was saved
            if (row) {
              row.classList.add('row-saved');
              setTimeout(() => {
                row.classList.remove('row-saved');
              }, 2000); // Remove the indicator after 2 seconds
            }
          } else {
            console.error(`Failed to auto-save row ${rowId}:`, data.errors);
            // Optionally show error to user
            if (data.errors && data.errors.length > 0) {
              showErrorModal(`Auto-save failed for row ${rowId}: ${data.errors.join(', ')}`);
            }
          }
        })
        .catch(error => {
          // Remove saving indicator on error
          if (row) {
            row.classList.remove('row-saving');
          }
          console.error(`Error auto-saving row ${rowId}:`, error);
        });
    }
  }

  // Add event listeners for time inputs to update duration
  document.querySelectorAll('.time-input').forEach(input => {
    input.addEventListener('change', function() {
      const rowId = this.closest('tr').getAttribute('data-row-id');
      updateDuration(rowId);
      updateDORSummary(); // Update summary when duration changes

      // Check if current row is complete and enable next row
      if (isRowComplete(rowId)) {
        setTimeout(() => {
          const nextRowId = parseInt(rowId) + 1;
          if (nextRowId <= 20) {
            const nextBoxNoInput = document.getElementById(`boxNo${nextRowId}`);
            if (nextBoxNoInput) {
              // Ensure the next row is enabled
              nextBoxNoInput.disabled = false;
              // Update row states to ensure proper enabling
              updateRowStates();
              // Focus and select the next box number input
              nextBoxNoInput.focus();
              nextBoxNoInput.select();
            }
          }
        }, 100); // Small delay to ensure DOM updates are complete
      }
    });

    // Also handle input event for real-time updates
    input.addEventListener('input', function() {
      const rowId = this.closest('tr').getAttribute('data-row-id');

      // Only enable next row if this is time end input with complete HH:mm format
      if (this.id.includes('timeEnd') && this.value.trim() && this.value.length === 5) {
        const boxNoInput = document.getElementById(`boxNo${rowId}`);
        const timeStartInput = document.getElementById(`timeStart${rowId}`);

        // Check if all required fields are filled and complete
        if (boxNoInput && timeStartInput &&
          boxNoInput.value.trim() && timeStartInput.value.trim() &&
          timeStartInput.value.length === 5) {

          // Enable next row immediately
          setTimeout(() => {
            const nextRowId = parseInt(rowId) + 1;
            if (nextRowId <= 20) {
              const nextBoxNoInput = document.getElementById(`boxNo${nextRowId}`);
              if (nextBoxNoInput && nextBoxNoInput.disabled) {
                nextBoxNoInput.disabled = false;
                nextBoxNoInput.focus();
                nextBoxNoInput.select();
              }
            }
          }, 100); // Small delay to ensure DOM updates are complete
        }
      }
    });
  });

  // Add event listeners for box number inputs to handle gun scanner parsing
  document.querySelectorAll('.box-no-input').forEach(input => {
    // Select all text on focus to allow easy replacement by scan or typing
    input.addEventListener('focus', function() {
      this.select();
    });
    // Function to parse box number input (gun scanner)
    function parseBoxNumberInput(inputValue, rowId) {
      const modelName = <?php echo json_encode($_SESSION['dorModelName'] ?? ''); ?>;
      const sessionQty = <?php echo json_encode($_SESSION['dorQty'] ?? ''); ?>;
      const parts = inputValue.trim().split(" ");

      if (parts.length === 3) {
        // Three values - Model, Quantity, Box Number
        const scannedModel = parts[0];
        const scannedQty = parts[1];
        const boxNumber = parts[2];

        // Check if model matches
        if (scannedModel !== modelName) {
          showErrorModal("Incorrect model name");
          return {
            success: false
          };
        }

        // Check if quantity matches
        if (scannedQty !== sessionQty.toString()) {
          showErrorModal("Incorrect quantity");
          return {
            success: false
          };
        }

        // Check if this is a second scan (time end) - box number should match existing one
        const currentBoxNoInput = document.getElementById(`boxNo${rowId}`);
        const timeStartInput = document.getElementById(`timeStart${rowId}`);
        const timeEndInput = document.getElementById(`timeEnd${rowId}`);

        if (currentBoxNoInput && currentBoxNoInput.value.trim() &&
          timeStartInput && timeStartInput.value.trim() &&
          !timeEndInput.value.trim()) {
          // This is a second scan for time end - box number should match
          // Extract just the box number from the current input (in case it's full scanner data)
          let currentBoxNumber = currentBoxNoInput.value.trim();
          // If current input contains spaces, extract the last part as box number
          if (currentBoxNumber.includes(' ')) {
            const currentParts = currentBoxNumber.split(' ');
            currentBoxNumber = currentParts[currentParts.length - 1];
          }

          if (currentBoxNumber !== boxNumber) {
            showErrorModal(`Please scan ID tag with box number ${currentBoxNumber}`);
            return {
              success: false
            };
          }
          // Box number matches, allow the scan for time end
          return {
            success: true,
            boxNumber: boxNumber,
            isTimeEndScan: true
          };
        }

        // First scan - check for duplicate box number in other rows
        for (let i = 1; i <= 20; i++) {
          if (i === parseInt(rowId)) continue; // Skip current row
          const existingBoxNo = document.getElementById(`boxNo${i}`);
          if (existingBoxNo && existingBoxNo.value.trim() === boxNumber) {
            showErrorModal(`Box number ${boxNumber} already exists in row ${i}`);
            return {
              success: false
            };
          }
        }

        // All validations passed for first scan
        return {
          success: true,
          boxNumber: boxNumber,
          isTimeEndScan: false
        };
      } else {
        showErrorModal("Invalid QR code format");
        return {
          success: false
        };
      }
    }
    // Validate on change/blur
    function validateBoxNumberInput(input) {
      const value = input.value.trim();
      const rowId = input.closest('tr').getAttribute('data-row-id');

      // Skip validation for empty values (user might be about to scan)
      if (!value) {
        input.classList.remove('is-invalid');
        return true;
      }

      // Check for duplicate
      for (let i = 1; i <= 20; i++) {
        if (i == rowId) continue;
        const other = document.getElementById(`boxNo${i}`);
        if (other && other.value.trim() === value) {
          showErrorModal(`Box number ${value} already exists in row ${i}`);
          input.focus();
          return false;
        }
      }

      // Passed validation
      input.classList.remove('is-invalid');
      return true;
    }
    input.addEventListener('change', function() {
      validateBoxNumberInput(this);
    });
    input.addEventListener('blur', function() {
      validateBoxNumberInput(this);
    });
    // Handle Enter key on box number input (gun scanner)
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const value = this.value.trim();
        const rowId = this.closest('tr').getAttribute('data-row-id');

        // Allow empty input - user might be about to scan
        if (!value) {
          this.focus();
          return;
        }

        const result = parseBoxNumberInput(value, rowId);
        if (result && result.success) {
          // Set the box number
          this.value = result.boxNumber;
          this.select(); // Ensure next scan/typing replaces the value

          // Auto-populate time start or end
          const timeStartInput = document.getElementById(`timeStart${rowId}`);
          const timeEndInput = document.getElementById(`timeEnd${rowId}`);

          if (timeStartInput && timeEndInput) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentTime = `${hours}:${minutes}`;

            if (timeStartInput.value && !timeEndInput.value) {
              // Second scan - set end time
              timeEndInput.value = currentTime;
              timeEndInput.dispatchEvent(new Event('change'));

              // Enable next row and focus on its box number
              setTimeout(() => {
                const nextRowId = parseInt(rowId) + 1;
                if (nextRowId <= 20) {
                  const nextBoxNoInput = document.getElementById(`boxNo${nextRowId}`);
                  if (nextBoxNoInput) {
                    // Ensure the next row is enabled
                    nextBoxNoInput.disabled = false;
                    // Update row states to ensure proper enabling
                    updateRowStates();
                    // Focus and select the next box number input
                    nextBoxNoInput.focus();
                    nextBoxNoInput.select();
                  }
                }
              }, 100); // Small delay to ensure DOM updates are complete
            } else if (!timeStartInput.value) {
              // First scan - set start time
              timeStartInput.value = currentTime;
              timeStartInput.dispatchEvent(new Event('change'));
            }
          }
        } else {
          // Clear invalid input
          this.value = '';
          this.focus();
        }
      }
    });
  });
  // Prevent moving to time start/end if box number is invalid
  document.querySelectorAll('.time-input').forEach(input => {
    input.addEventListener('focus', function(e) {
      const rowId = this.closest('tr').getAttribute('data-row-id');
      const boxNoInput = document.getElementById(`boxNo${rowId}`);
      if (boxNoInput && !boxNoInput.value.trim()) {
        showErrorModal('Please scan box number before entering time');
        boxNoInput.focus();
        e.preventDefault();
        return;
      }

      // Check for duplicate box numbers
      if (boxNoInput && boxNoInput.value.trim()) {
        for (let i = 1; i <= 20; i++) {
          if (i == rowId) continue;
          const other = document.getElementById(`boxNo${i}`);
          if (other && other.value.trim() === boxNoInput.value.trim()) {
            showErrorModal(`Box number ${boxNoInput.value.trim()} already exists in row ${i}`);
            boxNoInput.focus();
            e.preventDefault();
            return;
          }
        }
      }
    });

    // Handle Enter key on time inputs
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const rowId = this.closest('tr').getAttribute('data-row-id');
        const boxNoInput = document.getElementById(`boxNo${rowId}`);

        // Don't enable next row if box number is empty
        if (!boxNoInput || !boxNoInput.value.trim()) {
          showErrorModal('Please scan box number before entering time');
          if (boxNoInput) {
            boxNoInput.focus();
          }
          return;
        }

        // If this is time end and it's valid, enable next row
        if (this.id.includes('timeEnd') && this.value.trim()) {
          const nextRowId = parseInt(rowId) + 1;
          if (nextRowId <= 20) {
            const nextBoxNoInput = document.getElementById(`boxNo${nextRowId}`);
            if (nextBoxNoInput && nextBoxNoInput.disabled) {
              nextBoxNoInput.disabled = false;
              nextBoxNoInput.focus();
              nextBoxNoInput.select();
            }
          }
        }
      }
    });
  });

  // Add event listeners for operator changes to trigger auto-save
  for (let i = 1; i <= 20; i++) {
    const operatorsInput = document.getElementById(`operators${i}`);
    if (operatorsInput) {
      operatorsInput.addEventListener('change', function() {
        const rowId = this.closest('tr').getAttribute('data-row-id');
        // Trigger auto-save after a short delay
        setTimeout(() => {
          autoSaveRow(rowId);
        }, 500);
      });
    }
  }

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
      showErrorModal(`${employeeCode} is already assigned to this row`);
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

    // Automatically save changes
    saveOperatorChanges();

    // Clear search
    document.getElementById('operatorSearch').value = '';
    document.getElementById('operatorSuggestions').style.display = 'none';
    document.getElementById('operatorValidationMessage').innerHTML = '';
    document.getElementById('addOperatorBtn').disabled = true;
    selectedOperator = null;
  }

  function removeOperator(employeeCode) {
    if (!currentRowId || !currentOperators[employeeCode]) return;

    // Remove operator
    delete currentOperators[employeeCode];

    // Update display
    updateCurrentOperatorsDisplay();

    // Automatically save changes
    saveOperatorChanges();
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
                  <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeOperator('${operator.code}')" style="font-size: 12px; padding: 2px 6px;" title="Remove operator">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        `;
    });
    html += '</div>';

    operatorsDiv.innerHTML = html;
  }

  function saveOperatorChanges() {
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
          const badge = document.createElement('small');
          badge.className = 'badge bg-light text-dark border me-1 mb-1';
          badge.textContent = code; // Display employee code instead of name
          operatorListDiv.appendChild(badge);
        });
      }
    }

    // Update downtime placeholders to match operator count
    updateDowntimePlaceholders(currentRowId, operatorCodes.length);

    // Update DOR summary
    updateDORSummary();

    // Trigger auto-save after operator changes
    setTimeout(() => {
      autoSaveRow(currentRowId);
    }, 500);
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

  <script>
  // Downtime Management Modal Functionality
  let downtimeModalInstance = null;
  let currentDowntimeRowId = null;
  let currentDowntimeRecords = [];

  // Initialize downtime modal
  document.addEventListener('DOMContentLoaded', function() {
    downtimeModalInstance = new bootstrap.Modal(document.getElementById("downtimeModal"));
    initializeDowntimeFormValidation();

    // Add event listeners to downtime management buttons
    document.querySelectorAll('.btn-outline-secondary[id^="downtime"]').forEach(button => {
      button.addEventListener('click', function() {
        const rowId = this.closest('tr').getAttribute('data-row-id');
        openDowntimeModal(rowId);
      });
    });

    document.getElementById('addDowntimeBtn').addEventListener('click', addDowntimeRecord);
    document.getElementById('saveDowntimeBtn').addEventListener('click', saveDowntimeRecords);
  });

  function openDowntimeModal(rowId) {
    currentDowntimeRowId = rowId;
    document.getElementById('downtimeRowNumber').textContent = rowId;

    // Get lot number from the box number input
    const boxNoInput = document.getElementById(`boxNo${rowId}`);
    const lotNumber = boxNoInput ? boxNoInput.value.trim() : '';
    document.getElementById('downtimeModalLotNumber').textContent = lotNumber || 'N/A';

    // Load current downtime records for this row
    loadCurrentDowntimeRecords(rowId);

    // Reset form fields
    document.getElementById('downtimeForm').reset();
    document.getElementById('addDowntimeBtn').disabled = true;
    downtimeModalInstance.show();
  }

  function loadCurrentDowntimeRecords(rowId) {
    const downtimeDiv = document.getElementById(`downtimeInfo${rowId}`);
    const recordsDiv = document.getElementById('currentDowntimeRecords');

    if (downtimeDiv) {
      // Try to load existing records from data attribute (if you want to persist)
      // For now, just parse badges (showing code only)
      const existingBadges = downtimeDiv.querySelectorAll('.badge:not(.placeholder-badge)');
      currentDowntimeRecords = [];
      if (existingBadges.length > 0) {
        existingBadges.forEach(badge => {
          // For now, just store code as all fields empty except downtimeCode
          currentDowntimeRecords.push({
            downtimeCode: badge.textContent.trim(),
            actionTaken: '',
            remarks: '',
            timeStart: '',
            timeEnd: '',
            pic: ''
          });
        });
      }
    } else {
      currentDowntimeRecords = [];
    }
    updateCurrentDowntimeDisplay();
  }

  function initializeDowntimeFormValidation() {
    const form = document.getElementById('downtimeForm');
    const addBtn = document.getElementById('addDowntimeBtn');
    const fields = [
      'downtimeSelect',
      'actionTakenSelect',
      'downtimeTimeStart',
      'downtimeTimeEnd',
      'downtimePic'
    ];
    fields.forEach(id => {
      document.getElementById(id).addEventListener('input', validateDowntimeForm);
    });
    document.getElementById('remarksSelect').addEventListener('input', validateDowntimeForm);
    // Time input: restrict to HH:mm
    document.getElementById('downtimeTimeStart').addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9:]/g, '').slice(0, 5);
    });
    document.getElementById('downtimeTimeEnd').addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9:]/g, '').slice(0, 5);
    });
    // Enter key on any field triggers add if valid
    form.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !addBtn.disabled) {
        e.preventDefault();
        addDowntimeRecord();
      }
    });
  }

  function validateDowntimeForm() {
    const downtime = document.getElementById('downtimeSelect').value;
    const action = document.getElementById('actionTakenSelect').value;
    const remarks = document.getElementById('remarksSelect').value;
    const timeStart = document.getElementById('downtimeTimeStart').value;
    const timeEnd = document.getElementById('downtimeTimeEnd').value;
    const pic = document.getElementById('downtimePic').value.trim();
    let valid = true;
    // All required except remarks
    if (!downtime || !action || !timeStart || !timeEnd || !pic) valid = false;
    // Time format check
    if (timeStart && !/^\d{2}:\d{2}$/.test(timeStart)) valid = false;
    if (timeEnd && !/^\d{2}:\d{2}$/.test(timeEnd)) valid = false;
    // PIC min length
    if (pic.length < 3) valid = false;
    // Prevent duplicate (same downtime, timeStart, timeEnd, pic)
    if (valid && currentDowntimeRecords.some(r => r.downtimeCode === downtime && r.timeStart === timeStart && r
        .timeEnd === timeEnd && r.pic === pic)) valid = false;
    document.getElementById('addDowntimeBtn').disabled = !valid;
  }

  function addDowntimeRecord() {
    const downtime = document.getElementById('downtimeSelect').value;
    const downtimeText = document.getElementById('downtimeSelect').selectedOptions[0].textContent;
    const action = document.getElementById('actionTakenSelect').value;
    const actionText = document.getElementById('actionTakenSelect').selectedOptions[0].textContent;
    const remarks = document.getElementById('remarksSelect').value;
    const remarksText = document.getElementById('remarksSelect').selectedOptions[0].textContent;
    const timeStart = document.getElementById('downtimeTimeStart').value;
    const timeEnd = document.getElementById('downtimeTimeEnd').value;
    const pic = document.getElementById('downtimePic').value.trim();

    // Add as object
    currentDowntimeRecords.push({
      downtimeCode: downtime,
      downtimeText: downtimeText,
      actionTaken: action,
      actionText: actionText,
      remarks: remarks,
      remarksText: remarksText,
      timeStart: timeStart,
      timeEnd: timeEnd,
      pic: pic
    });
    updateCurrentDowntimeDisplay();
    document.getElementById('downtimeForm').reset();
    document.getElementById('addDowntimeBtn').disabled = true;
  }

  function removeDowntimeRecord(index) {
    if (index >= 0 && index < currentDowntimeRecords.length) {
      currentDowntimeRecords.splice(index, 1);
      updateCurrentDowntimeDisplay();
    }
  }

  function updateCurrentDowntimeDisplay() {
    const recordsDiv = document.getElementById('currentDowntimeRecords');
    if (currentDowntimeRecords.length === 0) {
      recordsDiv.innerHTML = '<p class="text-muted text-center mb-0">No downtime records.</p>';
      return;
    }
    let html = '<div class="row g-2">';
    currentDowntimeRecords.forEach((record, index) => {
      html += `
          <div class="col-12 mb-2">
            <div class="card border-0 bg-light">
              <div class="card-body p-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                  <div><span class="badge bg-primary text-white me-1">${record.downtimeCode}</span> <span class="fw-bold">${record.downtimeText}</span></div>
                  <div><span class="badge bg-info text-dark me-1">${record.actionTaken}</span> <span>${record.actionText}</span></div>
                  <div><span class="badge bg-secondary text-white me-1">${record.remarks}</span> <span>${record.remarksText}</span></div>
                  <div><span class="badge bg-light border me-1">${record.timeStart} - ${record.timeEnd}</span></div>
                  <div><span class="badge bg-dark text-white me-1">${record.pic}</span></div>
                  <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="removeDowntimeRecord(${index})" style="font-size: 12px; padding: 2px 6px;"><i class="bi bi-trash"></i></button>
                </div>
              </div>
            </div>
          </div>
        `;
    });
    html += '</div>';
    recordsDiv.innerHTML = html;
  }

  function saveDowntimeRecords() {
    if (!currentDowntimeRowId) return;
    // Update the display in the main table
    const downtimeDiv = document.getElementById(`downtimeInfo${currentDowntimeRowId}`);
    if (downtimeDiv) {
      downtimeDiv.innerHTML = '';
      if (currentDowntimeRecords.length > 0) {
        currentDowntimeRecords.forEach(record => {
          const badge = document.createElement('small');
          badge.className = 'badge bg-light text-dark border me-1 mb-1';
          badge.textContent = record.downtimeCode + (record.actionTaken ? '/' + record.actionTaken : '');
          badge.title =
            `${record.downtimeText}\n${record.actionText}\n${record.remarksText}\n${record.timeStart}-${record.timeEnd}\nPIC: ${record.pic}`;
          downtimeDiv.appendChild(badge);
        });
      } else {
        // If no downtime records, create placeholders based on operator count
        const operatorDiv = document.getElementById(`operatorList${currentDowntimeRowId}`);
        if (operatorDiv) {
          const operatorBadgeCount = operatorDiv.querySelectorAll('.badge').length;
          for (let i = 0; i < operatorBadgeCount; i++) {
            const placeholder = document.createElement('small');
            placeholder.className = 'badge placeholder-badge';
            placeholder.innerHTML = '&nbsp;';
            downtimeDiv.appendChild(placeholder);
          }
        }
      }
    }
    downtimeModalInstance.hide();
    updateDORSummary();

    // Trigger auto-save after downtime changes

    setTimeout(() => {
      autoSaveRow(currentDowntimeRowId);
    }, 500);
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
    if (!processLabelsContainer) {
      console.error('pipProcessLabels element not found');
      return;
    }
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
  }

  // Set first process as active by default
  if (processLabelsContainer) {
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