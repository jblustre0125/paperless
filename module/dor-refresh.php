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
            $response['redirectUrl'] = 'dor-dor.php';
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
                // Get radio button values
                $leaderToOperator = $_POST['leaderToOperator'] ?? 'NA';
                $operatorToLeader = $_POST['operatorToLeader'] ?? 'NA';

                // First check if record exists
                $checkSp = "EXEC RdAtoDorCheckpointRefreshRecordId @RecordId=?";
                $existingRecord = $db1->execute($checkSp, [$recordId], 1);

                if (!$existingRecord || empty($existingRecord)) {
                    // Execute stored procedure to save new responses

                    $insSp = "EXEC InsAtoDorCheckpointRefresh 
                        @RecordId=?, 
                        @OpLeaderResponse=?,
                        @OpOperatorResponse=?";

                    $result = $db1->execute($insSp, [
                        $recordId,
                        $leaderToOperator,
                        $operatorToLeader
                    ]);

                    if ($result !== false) {
                        $response['success'] = true;
                        $response['redirectUrl'] = "dor-dor.php";
                    } else {
                        $response['success'] = false;
                        $response['errors'][] = "Failed to save refreshment responses.";
                    }
                } else {
                    // Record exists, update it
                    $updSp = "EXEC UpdAtoDorCheckpointRefreshRecordId 
                        @RecordId=?,
                        @OpLeaderResponse=?,
                        @OpOperatorResponse=?";

                    $result = $db1->execute($updSp, [
                        $recordId,
                        $leaderToOperator,
                        $operatorToLeader
                    ]);

                    if ($result !== false) {
                        $response['success'] = true;
                        $response['redirectUrl'] = "dor-dor.php";
                    } else {
                        $response['success'] = false;
                        $response['errors'][] = "Failed to update refreshment responses.";
                    }
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

$drawingFile = getDrawing($_SESSION["dorTypeId"], $_SESSION['dorModelId']) ?? '';
$activeProcess = isset($_SESSION['activeProcess']) ? $_SESSION['activeProcess'] : 1;
$workInstructFile = getWorkInstruction($_SESSION["dorTypeId"], $_SESSION['dorModelId'], $activeProcess) ?? '';
$preCardFile = getPreparationCard($_SESSION['dorModelId']) ?? '';

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
                    <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group" id="btnWorkInstruction" data-file="<?php echo htmlspecialchars($workInstructFile); ?>">
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
                    <th colspan="2" class="text-center h5 py-3">Refreshment Checkpoint</th>
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
                        <div class="process-radio">
                            <label><input type="radio" name="leaderToOperator" value="O"> O</label>
                            <label><input type="radio" name="leaderToOperator" value="X"> X</label>
                            <label><input type="radio" name="leaderToOperator" value="NA" checked> NA</label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Operator to Leader</td>
                    <td>
                        <div class="process-radio">
                            <label><input type="radio" name="operatorToLeader" value="O"> O</label>
                            <label><input type="radio" name="operatorToLeader" value="X"> X</label>
                            <label><input type="radio" name="operatorToLeader" value="NA" checked> NA</label>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">
                        <div class="mb-2">
                            <strong>Legend:</strong>
                            <div class="ms-3 mt-1">
                                O - Good<br>
                                X - NG<br>
                                NA - Not Applicable
                            </div>
                        </div>
                        <div>
                            <strong>Note:</strong>
                            <div class="ms-3 mt-1">
                                Record the details at next page on detected problem/abnormality during jigs and tools checking.
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

    <!-- PiP Viewer HTML: supports maximize and minimize modes -->
    <div id="pipViewer" class="pip-viewer d-none maximize-mode">
        <div id="pipHeader">
            <button id="pipMaximize" class="pip-btn d-none" title="Maximize"><i class="bi bi-fullscreen"></i></button>
            <button id="pipMinimize" class="pip-btn" title="Minimize"><i class="bi bi-fullscreen-exit"></i></button>
            <button id="pipReset" class="pip-btn" title="Reset View"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button id="pipClose" class="pip-btn" title="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="pipContent"></div>
    </div>

    <div id="pipBackdrop"></div>

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
            // Variables for file paths
            const workInstructFile = <?php echo json_encode($workInstructFile); ?>;
            const preCardFile = <?php echo json_encode($preCardFile); ?>;
            const drawingFile = <?php echo json_encode($drawingFile); ?>;

            // Attach event listeners to buttons
            document.getElementById("btnDrawing").addEventListener("click", function() {
                if (drawingFile !== "") {
                    openPiPViewer(drawingFile, 'image');
                }
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

            document.getElementById("btnProceed").addEventListener("click", function(e) {
                e.preventDefault();

                // Create form data
                const formData = new FormData();
                formData.append('btnProceed', '1');
                formData.append('leaderToOperator', document.querySelector('input[name="leaderToOperator"]:checked').value);
                formData.append('operatorToLeader', document.querySelector('input[name="operatorToLeader"]:checked').value);

                // Send AJAX request
                fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirectUrl;
                        } else {
                            showErrorModal(data.errors.join('\n'));
                        }
                    })
                    .catch(error => {
                        showErrorModal('An error occurred while saving the responses.');
                    });
            });
        });
    </script>

    <script src="../js/pdf.min.js"></script>
    <script src="../js/pdf.worker.min.js"></script>
    <script src="../js/hammer.min.js"></script>
    <script src="../js/dor-pip-viewer.js"></script>

</body>

</html>