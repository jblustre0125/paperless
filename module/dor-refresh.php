<?php
$title = "WI Refreshment";
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

    // Handle deletion request
    if (isset($_POST['action']) && $_POST['action'] === 'delete_dor') {
        $recordId = $_SESSION['dorRecordId'] ?? null;

        if ($recordId) {
            $db1->execute("DELETE FROM AtoDor WHERE RecordId = ?", [$recordId]);
            unset($_SESSION['dorRecordId']);

            $response['success'] = true;
            $response['redirectUrl'] = 'dor-home.php';
        } else {
            $response['errors'][] = 'No record ID in session.';
        }

        echo json_encode($response);
        exit;
    }

    // Regular form submit handler (btnProceed)
    if (empty($response['errors'])) {
        if (isset($_POST['btnProceed'])) {
            $recordId = $_SESSION['dorRecordId'] ?? 0;
            if ($recordId > 0) {
                $hasUpdates = false;
                foreach ($_POST as $key => $value) {
                    if (preg_match('/^Process(\d+)_(\d+)$/', $key, $matches)) {
                        $processIndex = (int)$matches[1];
                        $sequenceId = (int)$matches[2];

                        $meta = $_POST['meta'][$key] ?? [];
                        $checkpointId = $meta['checkpointId'] ?? null;
                        $employeeCode = $_POST["userCode{$processIndex}"] ?? '';

                        if ($checkpointId && $employeeCode !== '') {
                            $insSp = "EXEC InsAtoDorCheckpointDefinition @RecordId=?, @ProcessIndex=?, @EmployeeCode=?, @CheckpointId=?, @CheckpointResponse=?";
                            $result = $db1->execute($insSp, [
                                $recordId,
                                $processIndex,
                                $employeeCode,
                                $checkpointId,
                                $value
                            ]);
                            if ($result !== false) {
                                $hasUpdates = true;
                            }
                        }
                    }
                }

                if ($hasUpdates) {
                    $response['success'] = true;
                    $response['redirectUrl'] = "dor-refresh.php";
                } else {
                    $response['success'] = false;
                    $response['errors'][] = "No updates were made. Please check your inputs.";
                }
            } else {
                $response['success'] = false;
                $response['errors'][] = "Invalid record ID.";
            }
        }
    }

    echo json_encode($response);
    exit;
}

// Fetch checkpoints based on DOR Type
$procA = "EXEC RdGenDorCheckpointDefinition @DorTypeId=?";
$resA = $db1->execute($procA, [$_SESSION['dorTypeId']], 1);

$tabData = [];

foreach ($resA as $row) {
    $checkpointId = $row['CheckpointId'];
    $checkpointName = $row['CheckpointName'];
    $sequenceId = $row['SequenceId'];

    // Use $checkpointName as top-level grouping key (to preserve your row merge)
    if (!isset($tabData[$checkpointName])) {
        $tabData[$checkpointName] = [];
    }

    // If this checkpointId already added, just push the option
    $found = false;
    foreach ($tabData[$checkpointName] as &$existing) {
        if ($existing['CheckpointId'] === $checkpointId) {
            if (!empty($row['CheckpointTypeDetailName'])) {
                $existing['Options'][] = $row['CheckpointTypeDetailName'];
            }
            $found = true;
            break;
        }
    }

    // New checkpoint entry
    if (!$found) {
        $tabData[$checkpointName][] = [
            'CheckpointId' => $checkpointId,
            'SequenceId' => $sequenceId,
            'CheckpointName' => $checkpointName,
            'CriteriaGood' => $row['CriteriaGood'],
            'CriteriaNotGood' => $row['CriteriaNotGood'],
            'CheckpointTypeId' => $row['CheckpointTypeId'],
            'CheckpointControl' => $row['CheckpointControl'],
            'Options' => !empty($row['CheckpointTypeDetailName']) ? [$row['CheckpointTypeDetailName']] : [],
        ];
    }
}

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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($title ?? 'Work I Checkpoint'); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/dor-refresh.css" rel="stylesheet">
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
                        <span class="short-label">Next</span>
                        <span class="long-label">Proceed to Next Checkpoint</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-5 pt-4">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th colspan="2" class="text-center h5 py-3">REFRESHMENT CHECKPOINT</th>
                </tr>
                <tr>
                    <th>Reading of Work Instruction</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Leader to Operator</td>
                    <td>
                        <div class="d-flex gap-4 justify-content-center">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="leaderToOperator" id="leaderToOperator_O" value="O">
                                <label class="form-check-label" for="leaderToOperator_O">O</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="leaderToOperator" id="leaderToOperator_NA" value="NA">
                                <label class="form-check-label" for="leaderToOperator_NA">NA</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="leaderToOperator" id="leaderToOperator_X" value="X">
                                <label class="form-check-label" for="leaderToOperator_X">X</label>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Operator to Leader</td>
                    <td>
                        <div class="d-flex gap-4 justify-content-center">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="operatorToLeader" id="operatorToLeader_O" value="O">
                                <label class="form-check-label" for="operatorToLeader_O">O</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="operatorToLeader" id="operatorToLeader_NA" value="NA">
                                <label class="form-check-label" for="operatorToLeader_NA">NA</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="operatorToLeader" id="operatorToLeader_X" value="X">
                                <label class="form-check-label" for="operatorToLeader_X">X</label>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">
                        <div class="mt-2">
                            <strong>Legend:</strong><br>
                            <div class="ms-3">
                                O - Good<br>
                                NA - Not Applicable<br>
                                X - NG
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>Note:</strong><br>
                            <div class="ms-3">
                                Record the details at page 2 on detected problem/abnormality during jigs and tools checking then encircle the X if already corrected.
                            </div>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- QR Code Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scan Employee ID</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="qr-video" autoplay muted playsinline></video>
                    <p class="text-muted mt-2">Align the QR code within the frame.</p>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" id="enterManually">Enter Manually</button>
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
                    <h5 class="modal-title" id="errorModalLabel">Please complete the checkpoint</h5>
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

    <!-- Required Scripts -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/jsQR.min.js"></script>
    <script>
        function showErrorModal(message) {
            const modalErrorMessage = document.getElementById("modalErrorMessage");
            modalErrorMessage.innerText = message;
            const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
            errorModal.show();
        }

        function goBack() {
            window.location.href = "dor-form.php";
        }

        // Add event handler for btnProceed
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("btnProceed").addEventListener("click", function() {
                window.location.href = "dor-form.php";
            });
        });
    </script>

</body>

</html>