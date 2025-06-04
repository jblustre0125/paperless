<?php
$title = "DOR Item/Jig Condition";
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
                foreach ($_POST as $key => $value) {
                    if (preg_match('/^Process(\d+)_(\d+)$/', $key, $matches)) {
                        $processIndex = (int)$matches[1];
                        $sequenceId = (int)$matches[2];

                        $meta = $_POST['meta'][$key] ?? [];
                        $checkpointId = $meta['checkpointId'] ?? null;
                        $employeeCode = $_POST["userCode{$processIndex}"] ?? '';

                        if ($checkpointId && $employeeCode !== '') {
                            $insSp = "EXEC InsAtoDorCheckpointDefinition @RecordId=?, @ProcessIndex=?, @EmployeeCode=?, @CheckpointId=?, @CheckpointResponse=?";
                            $db1->execute($insSp, [
                                $recordId,
                                $processIndex,
                                $employeeCode,
                                $checkpointId,
                                $value
                            ]);
                        }
                    }
                }
            }

            $response['success'] = true;
            $response['redirectUrl'] = "dor-refresh.php";
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
    <title><?php echo htmlspecialchars($title ?? 'Work I Checkpoint'); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/dor-form.css" rel="stylesheet">
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
    <form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
        <div class="sticky-dor-bar">
            <div class="container-fluid px-2 py-2">
                <?php if (!empty($errorPrompt)) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $errorPrompt; ?>
                    </div>
                <?php endif; ?>

                <div class="sticky-process-tab d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-3">
                        <?php
                        if (!isset($_SESSION['tabQty']) || $_SESSION['tabQty'] <= 0) {
                            die("Invalid tabQty in session.");
                        }
                        for ($i = 1; $i <= $_SESSION['tabQty']; $i++) :
                            $debugUserCodes = [1 => '2503-004', 2 => '2503-005', 3 => 'FMB-0826', 4 => 'FMB-0570'];
                            $debugUserCodeValue = $debugUserCodes[$i] ?? '';
                        ?>
                            <div class="d-flex flex-column align-items-center">
                                <button type="button" class="tab-button btn btn-secondary btn-sm mb-1"
                                    onclick="openTab(event, 'Process<?php echo $i; ?>')">Process <?php echo $i; ?></button>
                                <input type="text" class="form-control form-control-md" id="userCode<?php echo $i; ?>"
                                    name="userCode<?php echo $i; ?>" placeholder="Employee ID"
                                    value="<?php echo $debugUserCodeValue; ?>">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="sticky-table-header">
                    Required Item and Jig Condition VS Work Instruction
                </div>
            </div>
        </div>
        <?php for ($i = 1; $i <= $_SESSION['tabQty']; $i++) : ?>
            <div id="Process<?php echo $i; ?>" class="tab-content" style="display: none;">
                <div>
                    <table class="table-checkpointA table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Checkpoint</th>
                                <th colspan="2">Criteria</th>
                                <th>Please complete all checkpoints</th>
                            </tr>
                        </thead>
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
                                            $debugResponse = '';
                                            switch ((int)$row['CheckpointTypeId']) {
                                                case 1:
                                                    $debugResponse = 'OK';
                                                    break;
                                                case 2:
                                                    $debugResponse = 'DEBUG';
                                                    break;
                                                case 3:
                                                    $debugResponse = 'CJ';
                                                    break;
                                                case 4:
                                                    $debugResponse = 'M';
                                                    break;
                                            }

                                            $controlType = $row['CheckpointControl'];
                                            $options = $row['Options'];

                                            if ($controlType === 'radio' && !empty($options)) {
                                                echo "<div class='process-radio'>";
                                                foreach ($options as $opt) {
                                                    $checked = ($opt === $debugResponse) ? "checked" : "";
                                                    echo "<label><input type='radio' name='{$inputName}' value='{$opt}' {$checked}> {$opt}</label> ";
                                                }
                                                echo "</div>";
                                            } elseif ($controlType === 'text') {
                                                echo "<input type='text' name='{$inputName}' class='form-control' value='{$debugResponse}'>";
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
    </form>

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

    <!-- To fix `Uncaught ReferenceError: Modal is not defined` -->
    <script src="../js/bootstrap.bundle.min.js"></script>

    <script src="../js/jsQR.min.js"></script>
    <script>
        function showErrorModal(message) {
            const modalErrorMessage = document.getElementById("modalErrorMessage");
            modalErrorMessage.innerText = message;
            const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
            errorModal.show();
        }
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
            document.querySelectorAll("input[id^='userCode']").forEach(input => {
                input.addEventListener("click", async function() {
                    const accessGranted = await navigator.mediaDevices.getUserMedia({
                        video: true
                    }).then(stream => {
                        stream.getTracks().forEach(track => track.stop());
                        return true;
                    }).catch(() => false);

                    if (accessGranted) {
                        activeInput = this;
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
                // If a tab is stored, display it
                document.getElementById(storedTab).style.display = "block";
                document.querySelector(`[onclick="openTab(event, '${storedTab}')"]`).classList.add("active");
            } else {
                // Default to "Process 1" if no tab is stored
                const defaultTab = "Process1";
                document.getElementById(defaultTab).style.display = "block";
                document.querySelector(`[onclick="openTab(event, '${defaultTab}')"]`).classList.add("active");
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
                const confirmLeave = confirm("Are you sure you want to delete this record?");
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
                        window.location.href = data.redirectUrl;
                    } else {
                        alert(data.errors?.[0] || "Failed to delete DOR record.");
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    alert("Error occurred while deleting.");
                });
        }

        // Form submission handling
        const form = document.querySelector("#myForm");
        const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
        const modalErrorMessage = document.getElementById("modalErrorMessage");

        let clickedButton = null;

        // Track which submit button was clicked
        document.querySelectorAll("button[type='submit']").forEach(button => {
            button.addEventListener("click", function() {
                clickedButton = this;
            });
        });
    </script>

    <form id="deleteDorForm" method="POST" action="dor-form.php">
        <input type="hidden" name="action" value="delete_dor">
    </form>

    <script src="../js/pdf.min.js"></script>
    <script src="../js/pdf.worker.min.js"></script>
    <script src="../js/hammer.min.js"></script>
    <script src="../js/dor-pip-viewer.js"></script>

</body>

</html>