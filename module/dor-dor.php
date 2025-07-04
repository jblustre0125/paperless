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
    $productionCode = trim($_POST['employeeCode'] ?? '');

    if (empty($productionCode)) {
      $response['valid'] = false;
      $response['message'] = 'Employee ID is required.';
    } else {
      $spRd1 = "EXEC RdGenOperator @ProductionCode=?, @IsActive=1, @IsLoggedIn=0";
      $res1 = $db1->execute($spRd1, [$productionCode], 1);

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
          $response['message'] = "Row {$rowId} deleted successfully.";
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
          $response['message'] = "Row {$rowId} cleared successfully.";
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
          $response['message'] = "Row {$rowId} updated successfully.";
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
      $response['message'] = "Row {$rowId} saved successfully.";
      $response['headerId'] = $headerId;
    } catch (Exception $e) {
      $response['success'] = false;
      $response['errors'][] = "Database error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
  }

  // Regular form submit handler (btnProceed) - now just syncs data
  if (empty($response['errors'])) {
    if (isset($_POST['btnProceed'])) {
      $recordId = $_SESSION['dorRecordId'] ?? 0;

      // Debug: Log all POST data
      error_log("POST data received: " . print_r($_POST, true));

      // Debug: Check for time-related fields
      $timeFields = [];
      foreach ($_POST as $key => $value) {
        if (strpos($key, 'time') !== false || strpos($key, 'Time') !== false) {
          $timeFields[$key] = $value;
        }
      }
      error_log("Time-related fields found: " . print_r($timeFields, true));

      if ($recordId > 0) {
        try {
          // Process each row (1-20) to ensure all data is synced
          $completeRows = 0;
          $incompleteRows = [];
          $rowsToProcess = [];

          // First pass: validate and collect data
          function isValidTime($val)
          {
            // More robust time validation
            $val = trim($val);
            return !empty($val) &&
              $val !== 'HH:mm' &&
              $val !== '' &&
              strlen($val) > 0 &&
              preg_match('/^\d{1,2}:\d{2}$/', $val); // Basic time format validation
          }
          for ($i = 1; $i <= 20; $i++) {
            $boxNo = trim($_POST["boxNo{$i}"] ?? '');
            $timeStart = trim($_POST["timeStart{$i}"] ?? '');
            $timeEnd = trim($_POST["timeEnd{$i}"] ?? '');
            $operators = trim($_POST["operators{$i}"] ?? '');

            // Debug: Log what values are being detected
            error_log("Row {$i} - BoxNo: '{$boxNo}', TimeStart: '{$timeStart}', TimeEnd: '{$timeEnd}'");

            // Check if any field has a meaningful value (skip completely empty rows)
            // More strict check to avoid placeholder values or whitespace-only values
            $hasAnyValue = (!empty($boxNo) && $boxNo !== '') ||
              (!empty($timeStart) && $timeStart !== '' && $timeStart !== 'HH:mm') ||
              (!empty($timeEnd) && $timeEnd !== '' && $timeEnd !== 'HH:mm');

            // Only validate rows that have some data
            if ($hasAnyValue) {
              $hasAllValues = !empty($boxNo) && isValidTime($timeStart) && isValidTime($timeEnd);

              // Debug: Log validation details
              error_log("Row {$i} - hasAllValues: " . ($hasAllValues ? 'true' : 'false') .
                ", boxNo: " . (!empty($boxNo) ? 'valid' : 'empty') .
                ", timeStart: " . (isValidTime($timeStart) ? 'valid' : 'invalid') .
                ", timeEnd: " . (isValidTime($timeEnd) ? 'valid' : 'invalid'));

              if ($hasAllValues) {
                $completeRows++;
                $rowsToProcess[] = [
                  'row' => $i,
                  'boxNo' => $boxNo,
                  'timeStart' => $timeStart,
                  'timeEnd' => $timeEnd,
                  'operators' => $operators
                ];
                error_log("Row {$i} - Complete row added");
              } else {
                $incompleteRows[] = $i;
                error_log("Row {$i} - Incomplete row added");
              }
            } else {
              error_log("Row {$i} - Skipped (no values)");
            }
            // If no values at all, skip this row entirely (don't add to incompleteRows)
          }

          // Debug: Log the final counts
          error_log("Complete rows: {$completeRows}, Incomplete rows: " . implode(',', $incompleteRows));

          // Check if there's at least one complete record
          if ($completeRows === 0) {
            $sampleRow1 = [
              'boxNo' => $_POST['boxNo1'] ?? 'NOT_SET',
              'timeStart' => $_POST['timeStart1'] ?? 'NOT_SET',
              'timeEnd' => $_POST['timeEnd1'] ?? 'NOT_SET'
            ];
            $response['success'] = false;
            $response['errors'][] = "No valid rows found. Please fill Box No., Start Time, and End Time in at least one row. Debug: Complete rows: {$completeRows}, Incomplete rows: " . implode(',', $incompleteRows) . ". Row 1 data: BoxNo='{$sampleRow1['boxNo']}', TimeStart='{$sampleRow1['timeStart']}', TimeEnd='{$sampleRow1['timeEnd']}'";
            $response['debug'] = [
              'completeRows' => $completeRows,
              'incompleteRows' => $incompleteRows,
              'totalRowsChecked' => 20,
              'sampleRow1' => $sampleRow1
            ];
            echo json_encode($response);
            exit;
          }

          // Check for incomplete rows
          if (!empty($incompleteRows)) {
            $response['success'] = false;
            $response['errors'][] = "Incomplete data in row(s): " . implode(', ', $incompleteRows) . ". Please fill Box No., Start Time, and End Time in HH:mm format for each row.";
            echo json_encode($response);
            exit;
          }

          // Second pass: sync complete records (they should already be saved, but ensure they're up to date)
          foreach ($rowsToProcess as $rowData) {
            $i = $rowData['row'];
            $boxNo = $rowData['boxNo'];
            $timeStart = $rowData['timeStart'];
            $timeEnd = $rowData['timeEnd'];
            $operators = $rowData['operators'];

            // Convert time strings to datetime format
            $dateTimeStart = date('Y-m-d H:i:s', strtotime($timeStart));
            $dateTimeEnd = date('Y-m-d H:i:s', strtotime($timeEnd));

            // Calculate duration in minutes
            $startTime = strtotime($timeStart);
            $endTime = strtotime($timeEnd);
            $duration = round(($endTime - $startTime) / 60);

            // Check if header record exists and update it
            $checkHeaderSp = "SELECT RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ?";
            $existingHeader = $db1->execute($checkHeaderSp, [$recordId, $boxNo], 1);

            if (!empty($existingHeader)) {
              // Update existing header record
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
                $response['errors'][] = "Row {$i}: Failed to sync header record.";
                break;
              }

              $headerId = $existingHeader[0]['RecordHeaderId'];
            } else {
              // Insert new header record (in case it wasn't saved before)
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
                $response['errors'][] = "Row {$i}: Failed to sync header record.";
                break;
              }

              // Get the newly inserted header ID
              $getHeaderIdSp = "SELECT TOP 1 RecordHeaderId FROM AtoDorHeader WHERE RecordId = ? AND BoxNumber = ? ORDER BY RecordHeaderId DESC";
              $headerIdResult = $db1->execute($getHeaderIdSp, [$recordId, $boxNo], 1);
              $headerId = $headerIdResult[0]['RecordHeaderId'] ?? null;

              if (!$headerId) {
                $response['success'] = false;
                $response['errors'][] = "Row {$i}: Failed to get header ID.";
                break;
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

            // Check if detail record exists and update it
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
              $response['errors'][] = "Row {$i}: Failed to sync detail record.";
              break;
            }
          }

          if ($response['success'] !== false) {
            $response['success'] = true;
            $response['redirectUrl'] = "dor-home.php";
            $response['message'] = "DOR saved successfully. Redirecting...";

            // Clear all DOR-related session variables
            unset($_SESSION['dorTypeId']);
            unset($_SESSION['dorModelId']);
            unset($_SESSION['dorModelName']);
            unset($_SESSION['dorRecordId']);
            unset($_SESSION['dorDate']);
            unset($_SESSION['dorShift']);
            unset($_SESSION['dorLineId']);
            unset($_SESSION['dorQty']);
            unset($_SESSION['dorBoxNumber']);
            unset($_SESSION['dorTimeStart']);
            unset($_SESSION['dorTimeEnd']);
            unset($_SESSION['dorDuration']);
            unset($_SESSION['dorOperators']);
            unset($_SESSION['dorDowntime']);
            unset($_SESSION['tabQty']);

            // Clear any user codes that might have been set
            for ($j = 1; $j <= 4; $j++) {
              unset($_SESSION["userCode$j"]);
            }

            // Clear any other DOR-related session data
            foreach ($_SESSION as $key => $value) {
              if (strpos($key, 'dor') === 0 || strpos($key, 'userCode') === 0) {
                unset($_SESSION[$key]);
              }
            }
          }
        } catch (Exception $e) {
          $response['success'] = false;
          $response['errors'][] = "Database error: " . $e->getMessage();
        }
      } else {
        $response['success'] = false;
        $response['errors'][] = "Invalid record ID.";
      }
    } else {
      $response['success'] = false;
      $response['errors'][] = "Invalid request.";
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
    <?php /* if (isset($_GET['debug'])): ?>
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
    <?php endif; */ ?>

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
                  <input type="text" class="form-control scan-box-no box-no-input text-center" id="boxNo<?= $i ?>" name="boxNo<?= $i ?>" <?= $i === 1 ? '' : ' disabled' ?>>
                  <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                  <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
                </td>
                <td class="time-column">
                  <input type="text" class="form-control scan-box-no text-center time-input" id="timeStart<?= $i ?>" name="timeStart<?= $i ?>" pattern="[0-9]{2}:[0-9]{2}" placeholder="HH:mm" maxlength="5" <?= $i === 1 ? '' : ' disabled' ?>>
                </td>
                <td class="time-column">
                  <input type="text" class="form-control scan-box-no text-center time-input" id="timeEnd<?= $i ?>" name="timeEnd<?= $i ?>" pattern="[0-9]{2}:[0-9]{2}" placeholder="HH:mm" maxlength="5" <?= $i === 1 ? '' : ' disabled' ?>>
                </td>
                <td class="duration-column text-center align-middle">
                  <span id="duration<?= $i ?>" class="duration-value"></span>
                </td>
                <td class="operator-column align-middle text-center">
                  <div class="action-container">
                    <button type="button" class="btn btn-outline-primary btn-sm btn-operator" id="operator<?= $i ?>" disabled title="Operator management restricted to leaders">
                      <i class="bi bi-person-plus"></i> View Operators
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
                      /* if ($i === 2) {
                        $employeeCodes = ['2503-005', '2503-004', 'FMB-0826', 'FMB-0570'];
                      } */

                      // Debug: Check what employee codes are being processed
                      /* if ($i === 2) {
                        echo "<!-- Debug: Row $i has " . count($employeeCodes) . " employee codes -->";
                      } */

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
                      <i class="bi bi-clock-history"></i> View Downtime
                    </button>
                    <div class="downtime-info" id="downtimeInfo<?= $i ?>">
                      <?php
                      // This logic now correctly uses the $employeeCodes array defined in the previous column
                      // to determine how many placeholder badges are needed to ensure vertical alignment.

                      // We define a sample downtime record set for row 2 for demonstration
                      $downtimeRecords = [];
                      /* if ($i === 2) {
                        $downtimeRecords = ['Machine Setup', 'Material Change', 'Break Time', 'Quality Check'];
                      } */

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
            <h5 class="modal-title mb-0" id="operatorModalLabel">Operators - Row <span id="operatorRowNumber"></span></h5>
            <div class="d-flex align-items-center">
              <span class="text-white me-3">Box Number: <span id="modalLotNumber" class="fw-bold"></span></span>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3 mb-4" style="display: none;">
            <div class="col-12 col-md-8">
              <div class="autosuggest-wrapper">
                <h6 class="mb-2">Add Operator</h6>
                <div class="position-relative">
                  <input type="text"
                    class="form-control"
                    id="operatorSearch"
                    placeholder="Search by Employee ID or Name"
                    autocomplete="off"
                    disabled
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
              <div id="currentOperators" class="border rounded p-3" style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                <p class="text-muted text-center mb-0">No operators assigned yet.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="height: 45px; font-size: 16px;">Close</button>
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
            <h5 class="modal-title mb-0" id="downtimeModalLabel">Downtime - Row <span id="downtimeRowNumber"></span></h5>
            <div class="d-flex align-items-center">
              <span class="text-white me-3">Box Number: <span id="downtimeModalLotNumber" class="fw-bold"></span></span>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                <input type="text" class="form-control time-input" id="downtimeTimeStart" placeholder="HH:mm" maxlength="5" required>
              </div>
              <div class="col-6 col-md-3">
                <label for="downtimeTimeEnd" class="form-label">Time End</label>
                <input type="text" class="form-control time-input" id="downtimeTimeEnd" placeholder="HH:mm" maxlength="5" required>
              </div>
              <div class="col-12 col-md-6">
                <label for="downtimePic" class="form-label">PIC (Employee ID)</label>
                <input type="text" class="form-control" id="downtimePic" placeholder="Enter Employee ID" maxlength="20" required>
              </div>
              <div class="col-12 col-md-6 d-flex align-items-end">
                <button type="button" class="btn btn-secondary w-100" id="addDowntimeBtn" disabled style="height: 45px; font-size: 16px;">
                  <i class="bi bi-plus-circle"></i> Add
                </button>
              </div>
            </div>
          </form>
          <div class="row">
            <div class="col-12">
              <div id="currentDowntimeRecords" class="border rounded p-3" style="min-height: 80px; max-height: 200px; overflow-y: auto;">
                <p class="text-muted text-center mb-0">No downtime records added yet.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-3">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="height: 45px; font-size: 16px;">Close</button>
          <button type="button" class="btn btn-secondary" id="saveDowntimeBtn" style="height: 45px; font-size: 16px; display: none;">Save Changes</button>
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
              if (!errorModalIsOpen) showErrorModal('Incorrect time format. Enter time between 00:00 and 23:59');
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
                  if (!errorModalIsOpen) showErrorModal(`Incorrect start time and end time in row ${rowId}`);

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
          } else if (value.length > 0) {
            // If input is not empty but not in correct format
            if (!errorModalIsOpen) showErrorModal('Enter time in HH:mm format (e.g., 09:30)');
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
        console.log('Form submit event triggered'); // Debug log
        if (errorModalIsOpen) {
          // Prevent repeated validation/modal if already open
          return;
        }
        e.preventDefault();

        // Only run validation if btnProceed triggered this
        if (!clickedButton || clickedButton.id !== "btnProceed") {
          return; // Skip validation if other buttons are clicked (e.g., Drawing, WI)
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
          let errorHtml = "<ul>";
          incompleteRows.forEach(rowNum => {
            errorHtml += `<li>Incomplete data in row ${rowNum}</li>`;
          });
          errorHtml += "</ul>";
          showErrorModal(errorHtml);
          return;
        }

        // If there are no complete rows, show "No records found"
        if (completeRows === 0) {
          showErrorModal("No records found");
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
            console.log('Response data:', data); // Debug log
            console.log('Clicked button:', clickedButton); // Debug log
            if (data.success) {
              if (clickedButton && clickedButton.name === "btnProceed") {
                console.log('Redirecting to:', data.redirectUrl); // Debug log
                // Clear all form data from session storage when saving DOR
                clearAllFormData();
                // Try multiple redirect methods
                try {
                  window.location.href = data.redirectUrl;
                } catch (e) {
                  console.error('Redirect failed:', e);
                  window.location.replace(data.redirectUrl);
                }
                // Fallback redirect after 2 seconds if the above methods fail
                setTimeout(() => {
                  if (window.location.pathname.indexOf('dor-dor.php') !== -1) {
                    console.log('Fallback redirect to:', data.redirectUrl);
                    window.location.href = data.redirectUrl;
                  }
                }, 2000);
                return;
              }
            } else {
              // Use the proper showErrorModal function instead of direct modal manipulation
              showErrorModal(data.errors.join('\n'));
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
    }

    // Function to restore form data from session storage
    function restoreFormData() {
      const savedData = sessionStorage.getItem('dorDorData');
      if (!savedData) return;

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
          }
        }

        // Update row states and durations after restoring data
        updateRowStates();
        for (let i = 1; i <= 20; i++) {
          updateDuration(i);
        }
        updateDORSummary();

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
    console.log('Form element found:', form); // Debug log
    // Remove the conflicting modal instance creation
    // const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
    const modalErrorMessage = document.getElementById("modalErrorMessage");

    let clickedButton = null;

    // Track which submit button was clicked
    document.querySelectorAll("button[type='submit']").forEach(button => {
      button.addEventListener("click", function(e) {
        console.log('Button click event triggered for:', this.id, this.name); // Debug log
        e.preventDefault(); // Prevent default form submission
        clickedButton = this;
        console.log('Button clicked:', this.id, this.name); // Debug log

        // If it's the proceed button, trigger form validation and submission
        if (this.id === "btnProceed") {
          console.log('Dispatching form submit event'); // Debug log
          form.dispatchEvent(new Event('submit'));
        }
      });
    });

    // Add delete row functionality
    document.querySelectorAll('.delete-row').forEach(button => {
      button.addEventListener('click', function() {
        const rowId = parseInt(this.getAttribute('data-row-id'));
        const boxNoInput = document.getElementById(`boxNo${rowId}`);
        const boxNo = boxNoInput ? boxNoInput.value.trim() : '';

        if (!confirm(`Are you sure you want to delete row ${rowId}?`)) {
          return;
        }

        // If the row has data, delete it from database first
        if (boxNo) {
          const formData = new FormData();
          formData.append('action', 'delete_row');
          formData.append('rowId', rowId);
          formData.append('boxNo', boxNo);

          fetch(window.location.href, {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                console.log(`Row ${rowId} deleted from database successfully`);
                performRowShift(rowId);
              } else {
                console.error(`Failed to delete row ${rowId} from database:`, data.errors);
                if (data.errors && data.errors.length > 0) {
                  showErrorModal(`Failed to delete row ${rowId}: ${data.errors.join(', ')}`);
                }
              }
            })
            .catch(error => {
              console.error(`Error deleting row ${rowId}:`, error);
              showErrorModal(`Error deleting row ${rowId}: ${error.message}`);
            });
        } else {
          // If no data in the row, just perform the shift
          performRowShift(rowId);
        }
      });
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
          if (data.success) {
            console.log(`Row ${rowId} updated in database successfully`);
          } else {
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
          if (data.success) {
            console.log(`Row ${rowId} cleared from database successfully`);
          } else {
            console.error(`Failed to clear row ${rowId} from database:`, data.errors);
          }
        })
        .catch(error => {
          console.error(`Error clearing row ${rowId}:`, error);
        });
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
                  // Row saved successfully - you can add visual feedback here if needed
                  console.log(`Row ${rowId} auto-saved successfully`);
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
              // Row saved successfully - you can add visual feedback here if needed
              console.log(`Row ${rowId} auto-saved successfully`);

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
      });
    });

    // Add event listeners for box number inputs to handle gun scanner parsing
    document.querySelectorAll('.box-no-input').forEach(input => {
      // Function to parse box number input (gun scanner)
      function parseBoxNumberInput(inputValue, rowId) {
        const modelName = <?php echo json_encode($_SESSION['dorModelName'] ?? ''); ?>;
        const sessionQty = <?php echo json_encode($_SESSION['dorQty'] ?? ''); ?>;
        const parts = inputValue.trim().split(" ");

        if (parts.length === 1) {
          // Single value - check if it's the model name
          if (parts[0] === modelName) {
            showErrorModal("You scanned model name, please scan box number");
            return false;
          }
          // Otherwise accept as box number
          return {
            boxNumber: parts[0],
            shouldSetTime: true
          };
        } else if (parts.length === 3) {
          // Three values - Model, Quantity, Box Number
          const scannedModel = parts[0];
          const scannedQty = parts[1];
          const boxNumber = parts[2];

          // Check if model matches
          if (scannedModel !== modelName) {
            showErrorModal("Invalid QR code: Model name mismatch");
            return false;
          }

          // Check if quantity matches
          if (scannedQty !== sessionQty.toString()) {
            showErrorModal("Invalid QR code: Quantity mismatch");
            return false;
          }

          // Both match, accept box number
          return {
            boxNumber: boxNumber,
            shouldSetTime: true
          };
        } else {
          showErrorModal("Invalid QR code format");
          return false;
        }
      }

      // Handle input change for gun scanner (auto-trigger on value change)
      input.addEventListener('input', function() {
        const value = this.value;
        const rowId = this.closest('tr').getAttribute('data-row-id');

        // Check if input contains spaces (likely from gun scanner)
        if (value.includes(" ")) {
          const result = parseBoxNumberInput(value, rowId);
          if (result) {
            // Set the box number
            this.value = result.boxNumber;

            if (result.shouldSetTime) {
              // Check if time start already has a value
              const timeStartInput = document.getElementById(`timeStart${rowId}`);
              const timeEndInput = document.getElementById(`timeEnd${rowId}`);

              if (timeStartInput && timeEndInput) {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentTime = `${hours}:${minutes}`;

                if (timeStartInput.value && !timeEndInput.value) {
                  // Set end time
                  timeEndInput.value = currentTime;
                  timeEndInput.dispatchEvent(new Event('change'));
                } else if (!timeStartInput.value) {
                  // Set start time
                  timeStartInput.value = currentTime;
                  timeStartInput.dispatchEvent(new Event('change'));
                }
              }
            }
          } else {
            // Clear invalid input
            this.value = '';
          }
        }
      });

      // Handle Enter key on box number input
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          const value = this.value;
          const rowId = this.closest('tr').getAttribute('data-row-id');

          const result = parseBoxNumberInput(value, rowId);
          if (result) {
            // Set the box number
            this.value = result.boxNumber;

            if (result.shouldSetTime) {
              // Check if time start already has a value
              const timeStartInput = document.getElementById(`timeStart${rowId}`);
              const timeEndInput = document.getElementById(`timeEnd${rowId}`);

              if (timeStartInput && timeEndInput) {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentTime = `${hours}:${minutes}`;

                if (timeStartInput.value && !timeEndInput.value) {
                  // Set end time
                  timeEndInput.value = currentTime;
                  timeEndInput.dispatchEvent(new Event('change'));
                } else if (!timeStartInput.value) {
                  // Set start time
                  timeStartInput.value = currentTime;
                  timeStartInput.dispatchEvent(new Event('change'));
                }
              }
            }
          } else {
            // Clear invalid input
            this.value = '';
          }
        }
      });

      // Original change event for auto-save
      input.addEventListener('change', function() {
        const rowId = this.closest('tr').getAttribute('data-row-id');
        // Trigger auto-save after a short delay to ensure all fields are updated
        setTimeout(() => {
          autoSaveRow(rowId);
        }, 500);
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
      document.querySelectorAll('[id^="downtime"]').forEach(button => {
        if (button.id !== 'downtimeModal' && button.id !== 'downtimeInput' &&
          button.id !== 'downtimeValidationMessage' && button.id !== 'downtimeModalLabel' &&
          button.id !== 'downtimeRowNumber' && button.id !== 'downtimeModalLotNumber') {
          button.addEventListener('click', function() {
            const rowId = this.closest('tr').getAttribute('data-row-id');
            openDowntimeModal(rowId);
          });
        }
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
      if (valid && currentDowntimeRecords.some(r => r.downtimeCode === downtime && r.timeStart === timeStart && r.timeEnd === timeEnd && r.pic === pic)) valid = false;
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
        recordsDiv.innerHTML = '<p class="text-muted text-center mb-0">No downtime records added yet.</p>';
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
            badge.title = `${record.downtimeText}\n${record.actionText}\n${record.remarksText}\n${record.timeStart}-${record.timeEnd}\nPIC: ${record.pic}`;
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