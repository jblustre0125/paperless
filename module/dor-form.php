<?php
$title = "Item/Jig Condition";
session_start();
ob_start();

require_once "../config/dbop.php";
require_once "../config/method.php";

$db1 = new DbOp(1);
$db3 = new DbOp(3);

$errorPrompt = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'errors' => []];

    // Handle employee validation request
    if (isset($_POST['action']) && $_POST['action'] === 'validate_employee') {
        $productionCode = trim($_POST['employeeCode'] ?? '');
        $processIndex = trim($_POST['processIndex'] ?? '');

        if (empty($productionCode)) {
            $response['valid'] = false;
            $response['message'] = 'SA code is required for P' . $processIndex . '.';
        } else {
            $spRd1 = "EXEC RdGenOperator @ProductionCode=?, @IsActive=1, @IsLoggedIn=0";
            $res1 = $db1->execute($spRd1, [$productionCode], 1);

            if (!empty($res1)) {
                $response['valid'] = true;
                $response['message'] = 'SA code is valid for P' . $processIndex . '.';
            } else {
                $response['valid'] = false;
                $response['message'] = 'Invalid SA code for P' . $processIndex . '.';
            }
        }

        echo json_encode($response);
        exit;
    }

    // Handle Jig validation request
    if (isset($_POST['action']) && $_POST['action'] === 'validate_jig') {
        $jigName = trim($_POST['jigName'] ?? '');

        if (empty($jigName)) {
            $response['valid'] = false;
            $response['message'] = 'Jig number is required.';
        } else {
            // Query the MachineMonitoring database for Jig validation
            $jigQuery = "SELECT JigId, TRIM(JigName) AS JigName 
                        FROM MachineMonitoring.dbo.MntJig 
                        WHERE JigTypeId = 1 AND IsActive = 1 
                        AND TRIM(JigName) = ?";
            $res = $db3->execute($jigQuery, [$jigName]);

            if (!empty($res)) {
                $response['valid'] = true;
                $response['message'] = 'Jig number is valid.';
                $response['jigId'] = $res[0]['JigId'];
            } else {
                $response['valid'] = false;
                $response['message'] = 'Invalid Jig name.';
            }
        }

        echo json_encode($response);
        exit;
    }

    // Handle Jig autosuggest request
    if (isset($_POST['action']) && $_POST['action'] === 'suggest_jig') {
        $searchTerm = trim($_POST['searchTerm'] ?? '');

        if (!empty($searchTerm)) {
            // Query for Jig suggestions starting with 'Taping'
            $suggestQuery = "SELECT JigId, TRIM(JigName) AS JigName 
                           FROM MachineMonitoring.dbo.MntJig 
                           WHERE JigTypeId = 1 AND IsActive = 1 
                           AND TRIM(JigName) LIKE 'Taping%'
                           AND TRIM(JigName) LIKE ?
                           ORDER BY JigName";
            $res = $db3->execute($suggestQuery, ['%' . $searchTerm . '%']);



            $response['suggestions'] = $res ?: [];
        } else {
            $response['suggestions'] = [];
        }

        echo json_encode($response);
        exit;
    }

    // Handle deletion request
    if (isset($_POST['action']) && $_POST['action'] === 'delete_dor') {
        $recordId = $_SESSION['dorRecordId'] ?? null;

        if ($recordId) {
            $db1->execute("DELETE FROM AtoDor WHERE RecordId = ?", [$recordId]);

            // Clear all DOR-related session variables
            unset($_SESSION['dorRecordId']);
            unset($_SESSION['dorDate']);
            unset($_SESSION['dorShift']);
            unset($_SESSION['dorLineId']);
            unset($_SESSION['dorQty']);
            unset($_SESSION['dorModelId']);
            unset($_SESSION['dorModelName']);
            unset($_SESSION['dorTypeId']);
            unset($_SESSION['tabQty']);

            // Clear user codes
            for ($i = 1; $i <= 4; $i++) {
                unset($_SESSION["userCode{$i}"]);
            }

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
                // Store employee codes in session for use in dor-dor.php
                for ($i = 1; $i <= 4; $i++) {
                    if (isset($_POST["userCode{$i}"]) && !empty($_POST["userCode{$i}"])) {
                        $_SESSION["userCode{$i}"] = $_POST["userCode{$i}"];
                    }
                }

                // Get the last userCode from the form
                $lastUserCode = $_POST['lastUserCode'] ?? '';

                // Process Jig No. if provided
                $jigId = null;
                if (isset($_POST['jigId']) && !empty($_POST['jigId'])) {
                    $jigId = trim($_POST['jigId']);
                }

                // Update AtoDor table with both CreatedBy and JigId in a single call
                if (!empty($lastUserCode) || $jigId !== null) {
                    if ($jigId !== null) {
                        // Update both CreatedBy and JigId
                        $updateSp = "EXEC UpdAtoDor @RecordId=?, @CreatedBy=?, @JigId=?";
                        $db1->execute($updateSp, [$recordId, $lastUserCode, $jigId], 2);
                    } else {
                        // Update only CreatedBy
                        $updateSp = "EXEC UpdAtoDor @RecordId=?, @CreatedBy=?";
                        $db1->execute($updateSp, [$recordId, $lastUserCode], 2);
                    }
                }

                foreach ($_POST as $key => $value) {
                    if (preg_match('/^Process(\d+)_(\d+)$/', $key, $matches)) {
                        $processIndex = (int)$matches[1];
                        $sequenceId = (int)$matches[2];

                        $meta = $_POST['meta'][$key] ?? [];
                        $checkpointId = $meta['checkpointId'] ?? null;
                        $productionCode = $_POST["userCode{$processIndex}"] ?? '';

                        if ($checkpointId && $productionCode !== '') {
                            // Check if a record for this specific checkpoint already exists
                            $checkSp = "SELECT COUNT(*) as NumRecords FROM dbo.AtoDorCheckpointDefinition WHERE RecordId = ? AND CheckpointId = ? AND ProcessIndex = ?";
                            $result = $db1->execute($checkSp, [$recordId, $checkpointId, $processIndex]);
                            $recordExists = !empty($result) && isset($result[0]['NumRecords']) && $result[0]['NumRecords'] > 0;

                            if ($recordExists) {
                                // Record exists, so update it
                                $updateSp = "EXEC UpdAtoDorCheckpointDefinition @RecordId=?, @ProcessIndex=?, @EmployeeCode=?, @CheckpointId=?, @CheckpointResponse=?, @IsLeader";
                                $db1->execute($updateSp, [
                                    $recordId,
                                    $processIndex,
                                    $productionCode,
                                    $checkpointId,
                                    $value,
                                    0
                                ]);
                            } else {
                                // Record doesn't exist, so insert it
                                $insSp = "EXEC InsAtoDorCheckpointDefinition @RecordId=?, @ProcessIndex=?, @EmployeeCode=?, @CheckpointId=?, @CheckpointResponse=?, @IsLeader=?";
                                $db1->execute($insSp, [
                                    $recordId,
                                    $processIndex,
                                    $productionCode,
                                    $checkpointId,
                                    $value,
                                    0
                                ]);
                            }
                        }
                    }
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

$drawingFile = getDrawing($_SESSION["dorTypeId"], $_SESSION['dorModelId']) ?? '';
$activeProcess = isset($_SESSION['activeProcess']) ? $_SESSION['activeProcess'] : 1;
$workInstructFile = getWorkInstruction($_SESSION["dorTypeId"], $_SESSION['dorModelId'], $activeProcess) ?? '';
$preCardFile = getPreparationCard($_SESSION['dorModelId']) ?? '';

// Define DOR Type ID and Jig field visibility before HTML
$dorTypeId = $_SESSION['dorTypeId'] ?? 0;
$showJigField = in_array($dorTypeId, [2, 4]);

// Determine if any checkpoint has both Good and Not Good criteria
$criteriaColspan = 1;
foreach ($tabData as $checkpointName => $rows) {
    foreach ($rows as $row) {
        if (!empty($row['CriteriaNotGood'])) {
            $criteriaColspan = 2;
            break 2;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'DOR System'); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/dor-form.css" rel="stylesheet">
    <link href="../css/dor-navbar.css" rel="stylesheet">
    <link href="../css/dor-pip-viewer.css" rel="stylesheet">
    <!-- Autosuggest styles are now in dor-form.css -->
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
    <form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
        <div class="sticky-dor-bar">
            <div class="container-fluid px-2 py-0">
                <?php if (!empty($errorPrompt)) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $errorPrompt; ?>
                    </div>
                <?php endif; ?>

                <div class="sticky-process-tab d-flex justify-content-between align-items-center">
                    <div class="d-flex gap-3">
                        <?php
                        if (!isset($_SESSION['tabQty']) || $_SESSION['tabQty'] <= 0) {
                            die("Invalid tabQty in session.");
                        }
                        for ($i = 1; $i <= $_SESSION['tabQty']; $i++) :
                        ?>
                            <div class="d-flex flex-column align-items-center">
                                <button type="button" class="tab-button btn btn-secondary mb-1"
                                    onclick="openTab(event, 'Process<?php echo $i; ?>')">Process <?php echo $i; ?></button>
                                <div class="employee-validation">
                                    <input type="text" class="form-control form-control-md" id="userCode<?php echo $i; ?>"
                                        name="userCode<?php echo $i; ?>" placeholder="SA Code">
                                    <button type="button" class="btn btn-secondary btn-sm scan-btn" onclick="startScanning(<?php echo $i; ?>)">Scan ID</button>
                                    <div id="validationMessage<?php echo $i; ?>" class="validation-message mt-1"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="setAllTestValues(event)">Set Test Values</button>
            </div>
            <!-- Match EXACTLY the same container structure as the body table -->
            <div class="container-fluid px-2 py-0">
                <div class="table-container">
                    <table class="table-checkpointA table table-bordered align-middle">
                        <thead class="table-light">
                            <?php if ($showJigField): ?>
                                <tr>
                                    <th checkpoint-cell>Jig Number</th>
                                    <th colspan="<?php echo $criteriaColspan; ?>" class="criteria-cell">
                                        <div class="autosuggest-wrapper">
                                            <input type="text"
                                                class="form-control form-control-md"
                                                id="jigId"
                                                name="jigId"
                                                placeholder="Enter jig number"
                                                autocomplete="off"
                                                pattern="[0-9]*"
                                                inputmode="numeric"
                                                required>
                                            <div id="jigSuggestions" class="bg-white"></div>

                                        </div>
                                    </th>
                                    <th class="selection-cell"></th>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <?php if ($showJigField): ?>
                                    <th class="checkpoint-cell">Checkpoint</th>
                                    <th colspan="<?php echo $criteriaColspan; ?>" class="criteria-cell">Criteria</th>
                                    <th class="selection-cell">Assessment</th>
                                <?php else: ?>
                                    <th class="checkpoint-cell">Checkpoint</th>
                                    <th colspan="<?php echo $criteriaColspan; ?>" class="criteria-cell">Criteria</th>
                                    <th class="selection-cell">Assessment</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <?php
        // Debug: Check tabQty value
        $tabQty = $_SESSION['tabQty'] ?? 0;
        echo "<!-- Debug: tabQty = $tabQty -->";
        ?>
        <?php for ($i = 1; $i <= $tabQty; $i++) : ?>
            <div id="Process<?php echo $i; ?>" class="tab-content">
                <div class="container-fluid px-2 py-0">
                    <div class="table-container">
                        <table class="table-checkpointA table table-bordered align-middle">
                            <tbody>
                                <?php foreach ($tabData as $checkpointName => $rows): ?>
                                    <?php foreach ($rows as $index => $row): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td rowspan="<?php echo count($rows); ?>" class="checkpoint-cell">
                                                    <?php echo $row['SequenceId'] . ". " . $checkpointName; ?>
                                                </td>
                                            <?php endif; ?>

                                            <?php
                                            $criteriaGood = $row['CriteriaGood'] ?? '';
                                            $criteriaNotGood = $row['CriteriaNotGood'] ?? '';
                                            ?>

                                            <?php if (empty($criteriaNotGood)) : ?>
                                                <td class="criteria-cell" colspan="2"><?php echo $criteriaGood; ?></td>
                                            <?php else : ?>
                                                <td class="criteria-cell"><?php echo $criteriaGood; ?></td>
                                                <td class="criteria-cell"><?php echo $criteriaNotGood; ?></td>
                                            <?php endif; ?>

                                            <td class="selection-cell">
                                                <?php
                                                $inputName = "Process{$i}_{$row['CheckpointId']}";
                                                $controlType = $row['CheckpointControl'];
                                                $options = $row['Options'];

                                                if ($controlType === 'radio' && !empty($options)) {
                                                    echo "<div class='process-radio'>";
                                                    foreach ($options as $index => $opt) {
                                                        echo "<label><input type='radio' name='{$inputName}' value='{$opt}'> {$opt}</label> ";
                                                    }
                                                    echo "</div>";
                                                } elseif ($controlType === 'text') {
                                                    echo "<input type='text' name='{$inputName}' class='form-control'>";
                                                } else {
                                                    echo "<em>Unknown control type</em>";
                                                }

                                                echo "<input type='hidden' name='meta[{$inputName}][tabIndex]' value='{$i}'>";
                                                echo "<input type='hidden' name='meta[{$inputName}][checkpoint]' value='" . htmlspecialchars($row['CheckpointName']) . "'>";
                                                echo "<input type='hidden' name='meta[{$inputName}][checkpointId]' value='{$row['CheckpointId']}'>";
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endfor; ?>

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
        // Create a single modal instance
        let errorModalInstance = null;

        function showErrorModal(message) {
            const modalErrorMessage = document.getElementById("modalErrorMessage");
            modalErrorMessage.innerHTML = message;

            // Create modal instance only if it doesn't exist
            if (!errorModalInstance) {
                errorModalInstance = new bootstrap.Modal(document.getElementById("errorModal"));
            }

            errorModalInstance.show();
        }

        // Initialize modal when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            errorModalInstance = new bootstrap.Modal(document.getElementById("errorModal"));

            const errorModal = document.getElementById('errorModal');
            errorModal.addEventListener('hidden.bs.modal', function() {
                document.getElementById("modalErrorMessage").innerHTML = '';
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Clear any existing DOR form data if no DOR session is active
            if (!<?php echo isset($_SESSION['dorRecordId']) ? 'true' : 'false'; ?>) {
                sessionStorage.removeItem('dorFormData');
                // Redirect to home page if no DOR session
                window.location.href = 'dor-home.php';
                return;
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

            // Initialize Jig autosuggest functionality
            initializeJigAutosuggest();

            function getCameraConstraints() {
                return {
                    video: {
                        facingMode: {
                            ideal: "environment"
                        }
                    }
                };
            }

            function startScanning() {
                scannerModal.show();
                const constraints = getCameraConstraints();

                navigator.mediaDevices.getUserMedia(getCameraConstraints())
                    .then(setupVideoStream)
                    .catch((err1) => {
                        navigator.mediaDevices.getUserMedia({
                                video: {
                                    facingMode: "user"
                                }
                            })
                            .then(setupVideoStream)
                            .catch((err2) => {
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

            function scanQRCode() {
                if (!scanning) return;
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    let imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    let qrCodeData = jsQR(imageData.data, imageData.width, imageData.height);
                    if (qrCodeData) {
                        let scannedText = qrCodeData.data.trim();
                        if (activeInput) activeInput.value = scannedText;
                        stopScanning();
                    }
                }
                requestAnimationFrame(scanQRCode);
            }

            function stopScanning() {
                scanning = false;
                let tracks = video.srcObject?.getTracks();
                if (tracks) tracks.forEach(track => track.stop());
                scannerModal.hide();
            }

            let activeInput = null;
            // Remove the click event listener from userCode inputs since we'll use the scan button instead
            document.querySelectorAll("input[id^='userCode']").forEach(input => {
                // Remove the click event listener that was here
            });

            // Add click handler for scan buttons
            document.querySelectorAll(".scan-btn").forEach(button => {
                button.addEventListener("click", async function() {
                    const processIndex = this.closest('.employee-validation').querySelector('input[id^="userCode"]').id.replace('userCode', '');
                    const accessGranted = await navigator.mediaDevices.getUserMedia({
                        video: true
                    }).then(stream => {
                        stream.getTracks().forEach(track => track.stop());
                        return true;
                    }).catch(() => false);

                    if (accessGranted) {
                        activeInput = document.getElementById(`userCode${processIndex}`);
                        startScanning();
                    } else {
                        alert("Camera access denied");
                    }
                });
            });

            document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
            document.getElementById("enterManually").addEventListener("click", () => {
                stopScanning();
                setTimeout(() => {
                    if (activeInput) activeInput.focus();
                }, 300); // Delay to wait for modal fade-out animation
            });

        });
    </script>

    <script src="../js/pdf.min.js"></script>
    <script src="../js/pdf.worker.min.js"></script>
    <script src="../js/hammer.min.js"></script>
    <script src="../js/dor-pip-viewer.js"></script>

    <script>
        let isMinimized = false;
        const workInstructFile = <?php echo json_encode($workInstructFile); ?>;
        const preCardFile = <?php echo json_encode($preCardFile); ?>;
        const drawingFile = <?php echo json_encode($drawingFile); ?>;

        document.addEventListener("DOMContentLoaded", function() {
            // Restore form data from session storage
            restoreFormData();

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

            let storedTab = sessionStorage.getItem("activeTab");

            if (storedTab) {
                // If a tab is stored, display it
                document.getElementById(storedTab).style.display = "block";
                document.querySelector(`[onclick="openTab(event, '${storedTab}')"]`).classList.add("active");
            } else {
                // Default to "Process 1" if no tab is stored
                const defaultTab = "Process1";
                document.getElementById(defaultTab).style.display = "block";
                document.querySelector(`[onclick="openTab(event, '${defaultTab}')"]`).classList.add("active");
            }

            // Save form data when user navigates away
            window.addEventListener('beforeunload', function() {
                saveFormData();
            });

            // Save form data when user clicks back button
            document.querySelector('button[onclick="goBack()"]').addEventListener('click', function() {
                saveFormData();
            });

            // Form submission handling
            const form = document.querySelector("#myForm");
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

            form.addEventListener("submit", async function(e) {
                e.preventDefault();

                // Only run validation if btnProceed triggered this
                if (!clickedButton || clickedButton.id !== "btnProceed") {
                    return; // Skip validation if other buttons are clicked (e.g., Drawing, WI)
                }

                const errorsByOperator = {};
                const meta = {};
                const userCodes = {};
                const userCodeValues = [];
                let hasInvalidInputs = false;

                // Validate Jig No. field if visible and required
                const jigIdInput = document.getElementById('jigId');
                if (jigIdInput && jigIdInput.style.display !== 'none' && jigIdInput.hasAttribute('required')) {
                    const jigIdValue = jigIdInput.value.trim();
                    if (!jigIdValue) {
                        jigIdInput.classList.remove('is-valid');
                        jigIdInput.classList.add('is-invalid');
                        showErrorModal("<ul><li>Enter jig number or select from the list.</li></ul>");
                        return;
                    } else if (!selectedJigId) {
                        jigIdInput.classList.remove('is-valid');
                        jigIdInput.classList.add('is-invalid');
                        showErrorModal("<ul><li>Invalid jig number.</li></ul>");
                        return;
                    } else {
                        jigIdInput.classList.remove('is-invalid');
                        jigIdInput.classList.add('is-valid');
                    }
                }

                // Loop through up to 4 tabs
                for (let i = 1; i <= 4; i++) {
                    const input = form.querySelector(`#userCode${i}`);
                    if (!input) continue;

                    const code = input.value.trim();
                    if (!code) {
                        input.classList.remove('is-valid');
                        input.classList.add('is-invalid');
                        hasInvalidInputs = true;
                    } else {
                        if (userCodeValues.includes(code)) {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                            hasInvalidInputs = true;
                        }
                        userCodes[i] = code;
                        userCodeValues.push(code);
                    }
                }

                if (hasInvalidInputs) {
                    showErrorModal("<ul><li>Enter valid SA codes for all processes.</li></ul>");
                    return;
                }

                // Validate all employee codes
                const validationPromises = [];
                for (let i = 1; i <= 4; i++) {
                    const code = userCodes[i];
                    if (code) {
                        validationPromises.push(
                            fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=validate_employee&employeeCode=${encodeURIComponent(code)}&processIndex=${i}`
                            }).then(res => res.json())
                        );
                    }
                }

                try {
                    const validationResults = await Promise.all(validationPromises);
                    const invalidCodes = [];
                    let hasInvalidValidation = false;

                    validationResults.forEach((result, index) => {
                        const processNum = index + 1;
                        const input = document.getElementById(`userCode${processNum}`);

                        if (!result.valid) {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                            invalidCodes.push(`P${processNum}: ${userCodes[processNum]}`);
                            hasInvalidValidation = true;
                        } else {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        }
                    });

                    if (hasInvalidValidation) {
                        showErrorModal(`<ul><li>${invalidCodes.join('</li><li>')}</li></ul>`);
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
                    const processInputs = form.querySelectorAll("input[name^='Process'], input[type='text'][name^='Process']");

                    processInputs.forEach(input => {
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
                            const operator = tabIndex && userCodes[tabIndex] ? `P${tabIndex}: ${userCodes[tabIndex]}` : `Process ${tabIndex}`;

                            if (!errorsByOperator[operator]) errorsByOperator[operator] = [];
                            errorsByOperator[operator].push(checkpoint);
                        }
                    }

                    // Check if no Process inputs were found at all
                    if (Object.keys(groups).length === 0) {
                        showErrorModal("<ul><li>No process inputs found. Please check if the model has processes configured.</li></ul>");
                        return;
                    }

                    // Show modal if errors exist
                    const operatorList = Object.keys(errorsByOperator);
                    if (operatorList.length > 0) {
                        let html = `<ul>`;
                        operatorList.forEach(op => {
                            html += `<li><strong>${op}</strong>`;
                            if (errorsByOperator[op].length > 0) {
                                html += `<ul>`;
                                errorsByOperator[op].forEach(cp => {
                                    // Extract checkpoint number from the checkpoint name
                                    const checkpointMatch = cp.match(/^(\d+)\.\s*(.*)/);
                                    if (checkpointMatch) {
                                        const [, number, name] = checkpointMatch;
                                        html += `<li>${name}</li>`;
                                    } else {
                                        html += `<li>${cp}</li>`;
                                    }
                                });
                                html += `</ul>`;
                            }
                            html += `</li>`;
                        });
                        html += `</ul>`;
                        showErrorModal(html);
                        return;
                    }

                    // Submit if valid
                    let formData = new FormData(form);
                    if (clickedButton) {
                        formData.append(clickedButton.name, clickedButton.value || "1");
                    }

                    // Get the last userCode from the list
                    const lastUserCode = Object.values(userCodes).pop();
                    formData.append('lastUserCode', lastUserCode);

                    // Add the selected JigId if available
                    if (selectedJigId) {
                        formData.append('jigId', selectedJigId);
                    }

                    fetch(form.action, {
                            method: "POST",
                            body: formData
                        })
                        .then(response => response.json())
                        .then((data) => {
                            if (data.success) {
                                if (clickedButton.name === "btnProceed") {
                                    // Clear form data from session storage when proceeding to next page
                                    clearFormData();
                                    window.location.href = data.redirectUrl;
                                    return;
                                }
                            } else {
                                showErrorModal("<ul><li>" + data.errors.join("</li><li>") + "</li></ul>");
                            }
                        });
                } catch (error) {
                    console.error('Error:', error);
                    showErrorModal("<ul><li>Error validating employee IDs.</li></ul>");
                }
            });

        });

        // Function to save form data to session storage
        function saveFormData() {
            const formData = {};

            // Save employee codes
            for (let i = 1; i <= 4; i++) {
                const input = document.getElementById(`userCode${i}`);
                if (input) {
                    formData[`userCode${i}`] = input.value;
                }
            }

            // Save Jig ID if visible
            const jigIdInput = document.getElementById('jigId');
            if (jigIdInput && jigIdInput.style.display !== 'none') {
                formData['jigId'] = jigIdInput.value;
                formData['selectedJigId'] = selectedJigId;
            }

            // Save all Process inputs (radio buttons and text inputs)
            document.querySelectorAll("input[name^='Process']").forEach(input => {
                if (input.type === 'radio') {
                    if (input.checked) {
                        formData[input.name] = input.value;
                    }
                } else if (input.type === 'text') {
                    formData[input.name] = input.value;
                }
            });

            // Save active tab
            formData['activeTab'] = sessionStorage.getItem("activeTab") || "Process1";

            sessionStorage.setItem('dorFormData', JSON.stringify(formData));
        }

        // Function to restore form data from session storage
        function restoreFormData() {
            const savedData = sessionStorage.getItem('dorFormData');
            if (!savedData) return;

            try {
                const formData = JSON.parse(savedData);

                // Restore employee codes
                for (let i = 1; i <= 4; i++) {
                    const input = document.getElementById(`userCode${i}`);
                    if (input && formData[`userCode${i}`]) {
                        input.value = formData[`userCode${i}`];
                    }
                }

                // Restore Jig ID
                const jigIdInput = document.getElementById('jigId');
                if (jigIdInput && formData['jigId']) {
                    jigIdInput.value = formData['jigId'];
                    if (formData['selectedJigId']) {
                        selectedJigId = formData['selectedJigId'];
                        jigIdInput.classList.add('is-valid');
                    }
                }

                // Restore Process inputs
                Object.keys(formData).forEach(key => {
                    if (key.startsWith('Process')) {
                        const input = document.querySelector(`input[name="${key}"]`);
                        if (input) {
                            if (input.type === 'radio') {
                                input.checked = input.value === formData[key];
                            } else if (input.type === 'text') {
                                input.value = formData[key];
                            }
                        }
                    }
                });

                // Restore active tab
                if (formData['activeTab']) {
                    sessionStorage.setItem("activeTab", formData['activeTab']);
                }

            } catch (error) {
                console.error('Error restoring form data:', error);
            }
        }

        // Function to clear form data from session storage
        function clearFormData() {
            sessionStorage.removeItem('dorFormData');
        }

        // Add this new function for setting all test values
        function setAllTestValues(e) {
            // Prevent form submission
            e.preventDefault();

            const testCodes = {
                1: 'SA1346',
                2: 'SA161',
                3: 'SA883',
                4: 'SA1346'
            };

            // Set all employee codes
            for (let i = 1; i <= 4; i++) {
                const input = document.getElementById(`userCode${i}`);
                if (input && testCodes[i]) {
                    input.value = testCodes[i];
                }
            }

            // Set Jig No. test value if field is visible
            const jigIdInput = document.getElementById('jigId');
            if (jigIdInput && jigIdInput.style.display !== 'none') {
                jigIdInput.value = 'Taping-123';
            }

            // Set all radio buttons to "OK" (first option)
            document.querySelectorAll('input[type="radio"][name^="Process"]').forEach(input => {
                const radioGroup = document.querySelectorAll(`input[type="radio"][name="${input.name}"]`);
                if (radioGroup.length > 0) {
                    // Select the first radio button in each group (usually "OK")
                    radioGroup[0].checked = true;
                }
            });

            document.querySelectorAll('input[type="text"][name^="Process"]').forEach(input => {
                // Generate random number between 1 and 10
                const randomNum = Math.floor(Math.random() * 10) + 1;
                input.value = randomNum;
            });
        }

        // Modify the existing openTab function to remove table disabling
        function openTab(event, tabName) {
            let tabContents = document.querySelectorAll(".tab-content");
            tabContents.forEach(tab => tab.style.display = "none");

            let tabButtons = document.querySelectorAll(".tab-button");
            tabButtons.forEach(button => button.classList.remove("active"));

            const tabElement = document.getElementById(tabName);
            tabElement.style.display = "block";
            event.currentTarget.classList.add("active");

            // Store active process number
            const processNumber = parseInt(tabName.replace('Process', ''));

            // Send process number to PHP session without page reload
            fetch('set-process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `processNumber=${processNumber}`
            });

            sessionStorage.setItem("activeTab", tabName);
        }

        function goBack() {
            // Get all inputs inside the form
            const inputs = document.querySelectorAll("input[type='text'], input[type='radio']:checked");

            // Check if at least one input is filled
            let isFilled = false;
            inputs.forEach(input => {
                if (input.type === "text" && input.value.trim() !== "") {
                    isFilled = true;
                } else if (input.type === "radio") {
                    isFilled = true;
                }
            });

            // If at least one input is filled, show a confirmation dialog
            if (isFilled) {
                const confirmLeave = confirm("Are you sure you want to delete this DOR record?");
                if (!confirmLeave) {
                    return; // Stop navigation if the user cancels
                }
            }

            fetch(window.location.href, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        action: "delete_dor"
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.redirectUrl) {
                        // Clear form data when deleting DOR
                        clearFormData();
                        window.location.href = data.redirectUrl;
                    } else {
                        alert(data.errors?.[0] || "Failed to delete DOR record.");
                    }
                });
        }

        // Jig autosuggest and validation functions
        let jigSuggestionsTimeout = null;
        let selectedJigId = null;

        function initializeJigAutosuggest() {
            const jigInput = document.getElementById('jigId');
            const suggestionsDiv = document.getElementById('jigSuggestions');
            const validationDiv = document.getElementById('jigValidationMessage');

            if (!jigInput) return;

            // Add keyboard input restriction for numbers only
            jigInput.addEventListener('keydown', function(e) {
                // Allow: backspace, delete, tab, escape, enter, and navigation keys
                if ([8, 9, 27, 13, 46, 37, 38, 39, 40].indexOf(e.keyCode) !== -1 ||
                    // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                    (e.keyCode === 65 && e.ctrlKey === true) ||
                    (e.keyCode === 67 && e.ctrlKey === true) ||
                    (e.keyCode === 86 && e.ctrlKey === true) ||
                    (e.keyCode === 88 && e.ctrlKey === true)) {
                    return;
                }

                // Allow numbers 0-9
                if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 96 && e.keyCode <= 105)) {
                    return;
                }

                // Prevent all other keys
                e.preventDefault();
            });

            // Handle input for autosuggest
            jigInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                selectedJigId = null; // Reset selected JigId

                // Clear previous timeout
                if (jigSuggestionsTimeout) {
                    clearTimeout(jigSuggestionsTimeout);
                }

                // Hide suggestions if input is empty
                if (searchTerm.length === 0) {
                    suggestionsDiv.style.display = 'none';
                    validationDiv.innerHTML = '';
                    this.classList.remove('is-valid', 'is-invalid');
                    return;
                }

                // Show suggestions after 300ms delay
                jigSuggestionsTimeout = setTimeout(() => {
                    fetchSuggestions(searchTerm);
                }, 300);
            });

            // Handle suggestion selection
            suggestionsDiv.addEventListener('click', function(e) {
                if (e.target.classList.contains('suggestion-item')) {
                    const jigName = e.target.textContent;
                    const jigId = e.target.dataset.jigId;

                    jigInput.value = jigName;
                    selectedJigId = jigId;
                    suggestionsDiv.style.display = 'none';

                    // Validate the selected Jig
                    validateJig(jigName);
                }
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!jigInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.style.display = 'none';
                }
            });

            // Handle blur event for validation
            jigInput.addEventListener('blur', function() {
                setTimeout(() => {
                    if (this.value.trim() && !selectedJigId) {
                        validateJig(this.value.trim());
                    }
                }, 200);
            });

            // Clear validation classes when input is cleared
            jigInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    this.classList.remove('is-valid', 'is-invalid');
                    selectedJigId = null;
                }
            });
        }

        function fetchSuggestions(searchTerm) {
            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=suggest_jig&searchTerm=${encodeURIComponent(searchTerm)}`
                })
                .then(res => res.json())
                .then(data => {
                    displaySuggestions(data.suggestions);
                })
                .catch(error => {
                    console.error('Error fetching suggestions:', error);
                });
        }

        function displaySuggestions(suggestions) {
            const suggestionsDiv = document.getElementById('jigSuggestions');

            if (!suggestions || suggestions.length === 0) {
                suggestionsDiv.style.display = 'none';
                return;
            }

            let html = '';
            suggestions.forEach(jig => {
                html += `<div class="suggestion-item p-2 border-bottom" data-jig-id="${jig.JigId}" style="cursor: pointer; background-color: #f8f9fa;">${jig.JigName}</div>`;
            });

            suggestionsDiv.innerHTML = html;
            suggestionsDiv.style.display = 'block';
        }

        function validateJig(jigName) {
            fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=validate_jig&jigName=${encodeURIComponent(jigName)}`
                })
                .then(res => res.json())
                .then(data => {
                    const jigInput = document.getElementById('jigId');
                    const validationDiv = document.getElementById('jigValidationMessage');

                    if (data.valid) {
                        jigInput.classList.remove('is-invalid');
                        jigInput.classList.add('is-valid');
                        selectedJigId = data.jigId;
                    } else {
                        jigInput.classList.remove('is-valid');
                        jigInput.classList.add('is-invalid');
                        selectedJigId = null;
                    }
                })
                .catch(error => {
                    console.error('Error validating Jig:', error);
                });
        }
    </script>

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

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let storedTab = sessionStorage.getItem("activeTab");
        if (storedTab) {
            document.getElementById(storedTab).style.display = "block";
            document.querySelector(`[onclick="openTab(event, '${storedTab}')"]`).classList.add("active");
        } else {
            document.querySelector(".tab-content").style.display = "block";
        }
    });

    function openTab(evt, processName) {
        document.querySelectorAll(".tab-content").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tab-button").forEach(el => el.classList.remove("active"));
        document.getElementById(processName).style.display = "block";
        evt.currentTarget.classList.add("active");
        sessionStorage.setItem("activeTab", processName);
    }

    function submitForm() {
        document.getElementById("myForm").submit();
    }
</script>

<script>
    function showDrawing() {
        const dorTypeId = 1; // Change dynamically based on the selected DOR type

        fetch(`get_drawing.php?dorTypeId=${dorTypeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.drawing) {
                    document.getElementById("drawingImage").src = data.drawing;
                    document.getElementById("drawingWindow").style.display = "block";
                } else {
                    alert(data.error);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while fetching the drawing.");
            });
    }

    // Close the floating window
    function closeDrawing() {
        document.getElementById("drawingWindow").style.display = "none";
    }

    // Make the floating window draggable
    dragElement(document.getElementById("drawingWindow"));

    function dragElement(el) {
        let pos1 = 0,
            pos2 = 0,
            pos3 = 0,
            pos4 = 0;
        const header = document.getElementById("drawingHeader");

        if (header) {
            header.onmousedown = dragMouseDown;
        }

        function dragMouseDown(e) {
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            document.onmousemove = elementDrag;
        }

        function elementDrag(e) {
            e.preventDefault();
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            el.style.top = (el.offsetTop - pos2) + "px";
            el.style.left = (el.offsetLeft - pos1) + "px";
        }

        function closeDragElement() {
            document.onmouseup = null;
            document.onmousemove = null;
        }
    }
</script>

<script src="../js/bootstrap.bundle.min.js"></script>

</html>