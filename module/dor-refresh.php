<?php
$title = "Checkpoint Refreshment";
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
            $response['redirectUrl'] = 'dor-form.php';
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

$drawingFile = getDrawing($_SESSION["dorTypeId"], $_SESSION['dorModelId']);
$preCardFile = getPreparationCard($_SESSION['dorModelId']);
$workInstructFile = getWorkInstruction($_SESSION["dorTypeId"], $_SESSION['dorModelId']);

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Work I Checkpoint'); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/dor-form.css" rel="stylesheet">
</head>

<body>
    <form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
        <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarContent">
                    <div class="d-flex flex-column flex-lg-row align-items-center justify-content-between w-100">
                        <button type="button" class="btn btn-secondary btn-lg mb-2 mb-lg-0" onclick="goBack()" aria-label="Go Back">Back</button>

                        <div class="d-flex flex-wrap gap-3 mt-2 mt-lg-0 justify-content-center">
                            <button type="button" class="btn btn-secondary btn-lg" id="btnDrawing" aria-label="Open Drawing">Drawing</button>
                            <button type="button" class="btn btn-secondary btn-lg" id="btnWorkInstruction" aria-label="Open Work Instruction">Work Instruction</button>
                            <button type="button" class="btn btn-secondary btn-lg" id="btnPrepCard" aria-label="Open Preparation Card">Preparation Card</button>
                        </div>

                        <button class="btn btn-primary btn-lg mt-2 mt-lg-0" type="submit" id="btnProceed" name="btnProceed" aria-label="Proceed to DOR">Proceed to DOR</button>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <?php if (!empty($errorPrompt)) : ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $errorPrompt; ?>
                </div>
            <?php endif; ?>

            <div class="tab-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <!-- Title -->
                    <h6 class="fw-bold mb-0" style="font-size: 1rem; max-width: 60%; word-wrap: break-word;">
                        Required Item and Jig Condition VS Work Instruction
                    </h6>

                    <!-- Process Buttons and Textboxes -->
                    <div class="d-flex gap-3">
                        <?php for ($i = 1; $i <= $_SESSION['tabQty']; $i++) : ?>
                            <div class="d-flex flex-column align-items-center">
                                <!-- Process Button -->
                                <button type="button" class="tab-button btn btn-secondary btn-sm mb-1" onclick="openTab(event, 'Process<?php echo $i; ?>')">Process <?php echo $i; ?></button>

                                <!-- Textbox for User Code -->
                                <?php
                                // DEBUG PRESET EMPLOYEE IDs — comment this block to disable
                                $debugUserCodes = [
                                    1 => '2503-004',
                                    2 => '2503-005',
                                    3 => 'FMB-0826',
                                    4 => 'FMB-0570'
                                ];
                                $debugUserCodeValue = $debugUserCodes[$i] ?? '';
                                ?>

                                <input type="text"
                                    class="form-control form-control-md"
                                    id="userCode<?php echo $i; ?>"
                                    name="userCode<?php echo $i; ?>"
                                    placeholder="Employee ID"
                                    value="<?php echo $debugUserCodeValue; ?>"
                                    style="width: 120px;">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <?php for ($i = 1; $i <= $_SESSION['tabQty']; $i++) : ?>
                    <div id="Process<?php echo $i; ?>" class="tab-content" style="display: none;">
                        <table class="table-checkpointA table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Checkpoint</th>
                                    <th colspan="2">Criteria</th>
                                    <th class="col-auto text-nowrap">Plase complete all checkpoints</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabData as $checkpointName => $rows) : ?>
                                    <tr>
                                        <td rowspan="<?php echo count($rows); ?>" class="checkpoint-cell">
                                            <?php echo $rows[0]['SequenceId'] . ". " . $checkpointName; ?>
                                        </td>
                                        <?php foreach ($rows as $index => $row) : ?>
                                            <?php if ($index > 0) echo "<tr>"; ?>

                                            <?php
                                            $criteriaTypeId = $row['CheckpointTypeId'];
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

                                                // Debug response value — comment out this block when done
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

                                                // hidden fields
                                                echo "<input type='hidden' name='meta[{$inputName}][tabIndex]' value='{$i}'>";
                                                echo "<input type='hidden' name='meta[{$inputName}][checkpoint]' value='" . htmlspecialchars($row['CheckpointName']) . "'>";
                                                echo "<input type='hidden' name='meta[{$inputName}][checkpointId]' value='{$row['CheckpointId']}'>";
                                                ?>
                                            </td>

                                            <?php if ($index == count($rows) - 1) : ?>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endfor; ?>
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

        <!-- QR Code Scanner Modal -->
        <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Scan Employee ID</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <video id="qr-video" style="width: 100%; height: auto;" autoplay muted playsinline></video>
                        <p class="text-muted mt-2">Align the QR code within the frame.</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" id="enterManually">Enter Manually</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- PiP Viewer HTML: supports maximize and minimize modes -->
    <div id="pipViewer" class="pip-viewer d-none maximize-mode">
        <div id="pipHeader">
            <button id="pipMaximize" class="pip-btn d-none">&#x26F6;</button>
            <button id="pipMinimize" class="pip-btn">&#x1F5D5;</button>
            <button id="pipClose" class="pip-btn">&#x2715;</button>
        </div>
        <div id="pipContent"></div>
    </div>

    <div id="pipBackdrop"></div>

    <!-- To fix `Uncaught ReferenceError: Modal is not defined` -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/jsQR.min.js"></script>

    <script>
        function checkCameraAccessBeforeScanning() {
            const constraints = {
                video: {
                    facingMode: {
                        ideal: "environment"
                    }
                }
            };

            return navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    // Stop the stream immediately after checking
                    stream.getTracks().forEach(track => track.stop());
                    return true;
                })
                .catch(error => {
                    console.error("Camera access failed:", error);
                    showErrorModal("Unable to access camera. Please check your browser permissions or device settings.");
                    return false;
                });
        }

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
            let video = document.getElementById("qr-video");
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
                navigator.mediaDevices.getUserMedia(getCameraConstraints())
                    .then(setupVideoStream)
                    .catch(() => navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: "user"
                        }
                    }).then(setupVideoStream));
            }

            function setupVideoStream(stream) {
                video.srcObject = stream;
                video.setAttribute("playsinline", true);
                video.setAttribute("autoplay", true);
                video.style.width = "100%";
                video.muted = true;
                video.play().then(() => scanQRCode());
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
                if ($preCardFile !== "") {
                    openPiPViewer("<?php echo $preCardFile; ?>", 'pdf');
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
            // Get all inputs inside the tab-container
            const tabContainer = document.querySelector(".tab-container");
            const inputs = tabContainer.querySelectorAll("input[type='text'], input[type='radio']:checked");

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
                const confirmLeave = confirm("Are you sure you want to go back?");
                if (!confirmLeave) {
                    return; // Stop navigation if the user cancels
                }
            }

            fetch("", {
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

    <script src="../js/hammer.min.js"></script>
    <script src="../js/pdf.min.js"></script>
    <script src="../js/pdf.worker.min.js"></script>

    <script>
        const pipViewer = document.getElementById('pipViewer');
        const pipContent = document.getElementById('pipContent');
        const btnMin = document.getElementById('pipMinimize');
        const btnMax = document.getElementById('pipMaximize');
        const btnClose = document.getElementById('pipClose');

        let currentType = '',
            currentPath = '',
            currentScale = 1,
            currentPdf = null,
            currentPage = 1;

        function openPiPViewer(path, type) {
            currentType = type;
            currentPath = path;
            currentScale = 1;
            currentPage = 1;

            // FIX: Ensure viewer is visible before applying mode
            pipViewer.classList.remove('d-none');
            pipViewer.classList.remove('minimize-mode');
            pipViewer.classList.remove('maximize-mode'); // Reset both first
            pipViewer.style.top = '';
            pipViewer.style.left = '';
            pipViewer.style.right = '';
            pipViewer.style.bottom = '';
            pipViewer.style.transform = '';
            pipViewer.classList.add('maximize-mode');

            pipContent.innerHTML = '';
            btnMin.classList.remove('d-none');
            btnMax.classList.add('d-none');

            document.body.classList.add('no-scroll');
            document.getElementById('pipBackdrop').style.display = 'block';

            if (type === 'image') {
                const img = document.createElement('img');
                img.src = path;
                img.id = 'pipImage';
                pipContent.appendChild(img);
                initImageZoom(img);
            } else if (type === 'pdf') {
                pdfjsLib.getDocument(path).promise.then(pdf => {
                    currentPdf = pdf;
                    showPdfPage(currentPage);
                });
            }
        }

        function showPdfPage(pageNum) {
            currentPdf.getPage(pageNum).then(page => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                // Get container size (minimized or maximized)
                const contentRect = pipContent.getBoundingClientRect();
                const containerWidth = contentRect.width;
                const containerHeight = contentRect.height;

                // Get original size at scale 1
                const viewportAt1 = page.getViewport({
                    scale: 1
                });
                const pageWidth = viewportAt1.width;
                const pageHeight = viewportAt1.height;

                // Compute scale that fits the page into the container
                const widthScale = containerWidth / pageWidth;
                const heightScale = containerHeight / pageHeight;
                const scale = Math.min(widthScale, heightScale);

                const viewport = page.getViewport({
                    scale
                });
                canvas.width = viewport.width;
                canvas.height = viewport.height;

                page.render({
                    canvasContext: ctx,
                    viewport
                });

                pipContent.innerHTML = '';
                pipContent.appendChild(canvas);

                initPdfSwipe(canvas);
                initPdfPinchZoom(canvas); // include if you're preserving pinch-zoom
            });
        }

        function initImageZoom(img) {
            const hammer = new Hammer(img);
            hammer.get('pinch').set({
                enable: true
            });
            hammer.on('pinch', ev => {
                currentScale = Math.min(Math.max(1, ev.scale), 5);
                img.style.transform = `scale(${currentScale})`;
            });
            hammer.on('tap', ev => {
                if (pipViewer.classList.contains('minimize-mode')) maximizeViewer();
                else if (ev.tapCount === 2) minimizeViewer();
            });
        }

        function initPdfSwipe(canvas) {
            const hammer = new Hammer(canvas);
            hammer.get('swipe').set({
                direction: Hammer.DIRECTION_VERTICAL
            });
            hammer.on('swipeup', () => {
                if (currentPage < currentPdf.numPages) showPdfPage(++currentPage);
            });
            hammer.on('swipedown', () => {
                if (currentPage > 1) showPdfPage(--currentPage);
            });
            hammer.on('tap', ev => {
                if (pipViewer.classList.contains('minimize-mode')) maximizeViewer();
                else minimizeViewer();
            });
        }

        function minimizeViewer() {
            pipViewer.classList.remove('maximize-mode');
            pipViewer.classList.add('minimize-mode');

            pipViewer.style.top = '';
            pipViewer.style.left = '';
            pipViewer.style.right = '1rem';
            pipViewer.style.bottom = '1rem';
            pipViewer.style.transform = '';

            document.body.classList.remove('no-scroll');
            document.getElementById('pipBackdrop').style.display = 'none';

            btnMin.classList.add('d-none');
            btnMax.classList.remove('d-none');

            if (currentType === 'pdf' && currentPdf) {
                showPdfPage(currentPage); // ⬅ redraw PDF smaller
            }
        }

        function maximizeViewer() {
            // CLEAR inline styles BEFORE class switch
            pipViewer.style.right = '';
            pipViewer.style.bottom = '';
            pipViewer.style.top = '';
            pipViewer.style.left = '';
            pipViewer.style.transform = '';

            pipViewer.classList.remove('minimize-mode');
            pipViewer.classList.add('maximize-mode');

            document.body.classList.add('no-scroll');
            document.getElementById('pipBackdrop').style.display = 'block';

            btnMin.classList.remove('d-none');
            btnMax.classList.add('d-none');

            if (currentType === 'pdf' && currentPdf) {
                showPdfPage(currentPage); // ⬅ redraw PDF at full scale
            }
        }

        btnMin.onclick = minimizeViewer;
        btnMax.onclick = maximizeViewer;
        btnClose.onclick = () => {
            pipViewer.classList.add('d-none');
            pipContent.innerHTML = '';
            currentPdf = null;

            document.body.classList.remove('no-scroll');
            document.getElementById('pipBackdrop').style.display = 'none';
        };

        // Drag logic only when minimized
        let offsetX, offsetY;
        document.getElementById('pipHeader').onmousedown = function(e) {
            if (!pipViewer.classList.contains('minimize-mode')) return;

            const viewer = pipViewer;
            const rect = viewer.getBoundingClientRect();
            const offsetX = e.clientX - rect.left;
            const offsetY = e.clientY - rect.top;

            document.onmousemove = function(e) {
                let x = e.clientX - offsetX;
                let y = e.clientY - offsetY;

                // Clamp within screen
                const maxX = window.innerWidth - viewer.offsetWidth;
                const maxY = window.innerHeight - viewer.offsetHeight;

                x = Math.max(0, Math.min(x, maxX));
                y = Math.max(0, Math.min(y, maxY));

                viewer.style.left = x + 'px';
                viewer.style.top = y + 'px';
                viewer.style.right = '';
                viewer.style.bottom = '';
            };

            document.onmouseup = function() {
                document.onmousemove = null;
                document.onmouseup = null;
            };
        };
    </script>

    <form id="deleteDorForm" method="POST" action="dor-form.php">
        <input type="hidden" name="action" value="delete_dor">
    </form>

</body>


</html>