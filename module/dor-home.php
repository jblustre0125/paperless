<?php
$title = "Home";
ob_start();

require_once __DIR__ . "/../config/dbop.php";
require_once __DIR__ . "/../config/header.php";
require_once __DIR__ . "/../config/method.php";

$db1 = new DbOp(1);

$selQry = "SELECT GETDATE() AS CurrentDate;";
$res = $db1->execute($selQry, [], 1);

if ($res !== false && !empty($res)) {
    foreach ($res as $row) {
        // Ensure $row['CurrentDate'] is a valid DateTime object
        if ($row['CurrentDate'] instanceof DateTime) {
            $todayString = $row['CurrentDate']->format('Y-m-d H:i:s'); // Retain both date and time
            $today = $row['CurrentDate']->format('Y-m-d'); // Format for date input
        } else {
            $todayString = $row['CurrentDate']; // Assume it's already a string
        }

        // Use the formatted string for further processing
        $currentHour = date('H', strtotime($todayString)); // Extract the hour

        // Determine the shift
        $selectedShift = ($currentHour >= 7 && $currentHour < 19) ? "DS" : "NS";
    }
} else {
    // Fallback if the query fails
    $today = date('Y-m-d'); // Date only for input field
    $currentHour = date('H');
    $selectedShift = ($currentHour >= 7 && $currentHour <= 19) ? "DS" : "NS";
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    (isset($_POST['btnCreateDor']) || isset($_POST['btnSearchDor']) || isset($_POST['btnSetValues']))
) {
    // Add debugging
    error_log("POST request received. POST data: " . print_r($_POST, true));

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'errors' => []];  // Initialize response array

    try {
        // Handle Set Test Values first (no validation needed)
        if (isset($_POST['btnSetValues'])) {
            error_log("Handling btnSetValues");
            handleSetTestValues($response);
            error_log("Response for btnSetValues: " . json_encode($response));
            echo json_encode($response);
            exit;
        }

        // Now handle other buttons with validation
        $dorDate = testInput($_POST["dtpDate"]);
        $shiftCode = testInput($_POST['rdShift']);
        $modelName = testInput($_POST["txtModelName"]);
        $qty = (int) testInput($_POST["txtQty"]);

        if (empty($dorDate)) {
            $response['errors'][] = "Select date.";
        }

        if (empty($shiftCode)) {
            $response['errors'][] = "Select shift.";
        }

        if (empty(trim($modelName))) {
            $response['errors'][] = "Enter model name.";
        } else {
            if (!isValidModel($modelName)) {
                $response['errors'][] = "Model name is not registered or inactive.";
            }
        }

        if (empty(trim($qty)) || $qty == 0) {
            $response['errors'][] = "Enter quantity.";
        }

        $shiftId = $lineId = $modelId = $dorTypeId = 0;

        // Initialize session fallbacks for testing/development
        if (!isset($_SESSION["hostnameId"])) {
            $_SESSION["hostnameId"] = 1; // Default for testing
        }
        if (!isset($_SESSION["hostname"])) {
            $_SESSION["hostname"] = "ATO1"; // Default for testing
        }

        // Determine lineId and dorTypeId based on hostname
        $hostnameId = $_SESSION["hostnameId"] ?? 1;
        // Look up the line in GenLine table using hostnameId (assumed to be LineId)
        $selLineQry = "SELECT LineId, DorTypeId FROM GenLine WHERE LineId = ?";
        $lineRes = $db1->execute($selLineQry, [$hostnameId], 1);
        if ($lineRes !== false && !empty($lineRes)) {
            foreach ($lineRes as $lineRow) {
                $lineId = $lineRow['LineId'];
                $dorTypeId = $lineRow['DorTypeId'];
                break;
            }
        } else {
            error_log("GenLine lookup failed for hostnameId: $hostnameId");
        }
        // Fallback if line lookup failed
        if ($lineId === 0 || $dorTypeId === 0) {
            $lineId = 1;     // Default to first line
            $dorTypeId = 1;  // Default to first DOR type
            error_log("Fallback to default lineId and dorTypeId for hostnameId: $hostnameId");
        }

        // Debug output (you can remove this in production)
        error_log("Debug Info - HostnameId: $hostnameId, LineId: $lineId, DorTypeId: $dorTypeId");

        // Get shift information
        $selQry = "EXEC RdGenShift @ShiftCode=?";
        $res = $db1->execute($selQry, [$shiftCode], 1);

        if ($res !== false) {
            foreach ($res as $row) {
                $shiftId = $row['ShiftId'];
            }
        }

        // Get model information
        $selQry = "EXEC RdGenModel @IsActive=?, @ITEM_ID=?";
        $res = $db1->execute($selQry, [1, $modelName], 1);

        if ($res !== false) {
            foreach ($res as $row) {
                $modelId = $row['MODEL_ID'];
            }
        }

        // Handle different button actions
        if (empty($response['errors'])) {
            // Ensure hostname and hostnameId are set in session for dor-form.php
            if (!isset($_SESSION["hostname"])) {
                // Try multiple ways to get hostname, log all possible sources
                $sources = [];
                if (function_exists('gethostname')) {
                    $sources['gethostname'] = gethostname();
                }
                if (!empty($_SERVER['COMPUTERNAME'])) {
                    $sources['SERVER.COMPUTERNAME'] = $_SERVER['COMPUTERNAME'];
                }
                if (!empty($_SERVER['HOSTNAME'])) {
                    $sources['SERVER.HOSTNAME'] = $_SERVER['HOSTNAME'];
                }
                if (!empty($_ENV['COMPUTERNAME'])) {
                    $sources['ENV.COMPUTERNAME'] = $_ENV['COMPUTERNAME'];
                }
                if (!empty($_ENV['HOSTNAME'])) {
                    $sources['ENV.HOSTNAME'] = $_ENV['HOSTNAME'];
                }
                // Windows specific: try php_uname('n')
                $unameHost = php_uname('n');
                if (!empty($unameHost)) {
                    $sources['php_uname'] = $unameHost;
                }
                // Log all sources for debugging
                foreach ($sources as $src => $val) {
                    error_log("Hostname source [$src]: $val");
                }
                // Pick the first non-empty, trimmed value
                $detectedHostname = "";
                foreach ($sources as $val) {
                    $val = trim($val);
                    if (!empty($val)) {
                        $detectedHostname = $val;
                        break;
                    }
                }
                // Sanitize: remove spaces, only allow alphanumeric and dash/underscore
                if (!empty($detectedHostname)) {
                    $detectedHostname = preg_replace('/[^A-Za-z0-9\-_]/', '', $detectedHostname);
                    $_SESSION["hostname"] = $detectedHostname;
                } else {
                    $_SESSION["hostname"] = "ATO1"; // Fallback
                }
                error_log("Final Detected Hostname: " . $_SESSION["hostname"]);
            }
            if (!isset($_SESSION["hostnameId"])) {
                // Try to get hostnameId from lineId (if available)
                $_SESSION["hostnameId"] = $lineId ?: 1;
            }

            if (isset($_POST['btnCreateDor'])) {
                error_log("Handling btnCreateDor");
                if (isExistDor($dorDate, $shiftId, $lineId)) {
                    $response['errors'][] = "DOR already exists.";
                } else {
                    handleCreateDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, $response);
                }
            } elseif (isset($_POST['btnSearchDor'])) {
                error_log("Handling btnSearchDor");
                handleSearchDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, $response);
            }
        }
    } catch (Exception $e) {
        globalExceptionHandler($e);
        $response['errors'][] = "An error occurred while processing your request.";
    }

    error_log("Final response: " . json_encode($response));
    echo json_encode($response);
    exit;
}

function handleCreateDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, &$response)
{
    global $db1;

    try {
        // Fetch the tabQty from the database
        $selProcessTab = "SELECT ISNULL(MP, 0) AS 'MP' FROM dbo.GenModel WHERE MODEL_ID = ?";
        $res = $db1->execute($selProcessTab, [$modelId], 1);

        if ($res !== false) {
            foreach ($res as $row) {
                $_SESSION["tabQty"] = $row['MP'];
            }
        } else {
            $_SESSION["tabQty"] = 0;
        }

        // Check if tabQty is 0
        if ($_SESSION["tabQty"] === 0) {
            $response['success'] = false;
            $response['errors'][] = "No process MP set to the selected model.";
            return; // Stop further execution
        }

        $recordId = 0;

        $insQry = "EXEC InsAtoDor @DorTypeId=?, @ShiftId=?, @DorDate=?, @ModelId=?, @LineId=?, @Quantity=?, @HostnameId=?, @RecordId=?";
        $params = [
            $dorTypeId,
            $shiftId,
            $dorDate,
            $modelId,
            $lineId,
            $qty,
            $_SESSION["hostnameId"],
            [&$recordId, SQLSRV_PARAM_OUT]
        ];

        $res = $db1->execute($insQry, $params, 1);

        if ($res === false) {
            $response['errors'][] = "SQL execution failed: " . print_r(sqlsrv_errors(), true);
        } elseif ($recordId === 0) {
            $response['errors'][] = "No rows affected. Insert failed.";
        } else {
            $_SESSION["dorDate"] = $dorDate;
            $_SESSION["dorShift"] = $shiftId;
            $_SESSION["dorLineId"] = $lineId;
            $_SESSION["dorQty"] = $qty;
            $_SESSION["dorModelId"] = $modelId;
            $_SESSION["dorModelName"] = testInput($_POST["txtModelName"]);
            $_SESSION["dorTypeId"] = $dorTypeId;
            $_SESSION["dorRecordId"] = $recordId;
            $response['success'] = true;
            $response['redirectUrl'] = "dor-form.php";
        }
    } catch (Exception $e) {
        globalExceptionHandler($e);
        $response['errors'][] = "An error occurred while creating the DOR.";
    }
}

// Add this new function to handle test values
function handleSetTestValues(&$response)
{
    global $db1;

    try {
        // Get a random active model from database
        $selQry = "SELECT TOP 1 ITEM_ID, ITEM_NAME FROM GenModel WHERE IsActive = 1 ORDER BY NEWID()";
        $res = $db1->execute($selQry, [], 1);

        $testModelName = "TEST-MODEL-001"; // Default fallback
        $testQty = rand(10, 100); // Random quantity between 10-100

        if ($res !== false && !empty($res)) {
            foreach ($res as $row) {
                $testModelName = $row['ITEM_ID'];
                break;
            }
        }

        // Get current date and shift using Asia/Manila timezone
        $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $currentDate = $dt->format('Y-m-d');
        $currentHour = (int)$dt->format('H');
        // 7:00 to 18:59 is Day Shift, 19:00 to 6:59 is Night Shift
        if ($currentHour >= 7 && $currentHour < 19) {
            $currentShift = "DS";
        } else {
            $currentShift = "NS";
        }

        $response['success'] = true;
        $response['testData'] = [
            'date' => $currentDate,
            'shift' => $currentShift,
            'modelName' => $testModelName,
            'quantity' => $testQty,
            'message' => 'Test values loaded from database'
        ];
    } catch (Exception $e) {
        globalExceptionHandler($e);
        $response['errors'][] = "An error occurred while getting test values.";
    }
}

//TODO: Populate the DOR form with the selected DOR details
function handleSearchDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, &$response)
{
    global $db1;

    try {
        if (isExistDor($dorDate, $shiftId, $lineId)) {
            // Get the existing DOR record details
            $selQry = "SELECT RecordId, Quantity FROM AtoDor WHERE DorDate = ? AND ShiftId = ? AND LineId = ? AND ModelId = ? AND DorTypeId = ?";
            $res = $db1->execute($selQry, [$dorDate, $shiftId, $lineId, $modelId, $dorTypeId], 1);

            if ($res !== false && !empty($res)) {
                $record = $res[0];

                // Fetch the tabQty from the database for the model
                $selProcessTab = "SELECT ISNULL(MP, 0) AS 'MP' FROM dbo.GenModel WHERE MODEL_ID = ?";
                $tabRes = $db1->execute($selProcessTab, [$modelId], 1);

                if ($tabRes !== false) {
                    foreach ($tabRes as $row) {
                        $_SESSION["tabQty"] = $row['MP'];
                    }
                } else {
                    $_SESSION["tabQty"] = 0;
                }

                // Set up session variables for the existing DOR
                $_SESSION["dorDate"] = $dorDate;
                $_SESSION["dorShift"] = $shiftId;
                $_SESSION["dorLineId"] = $lineId;
                $_SESSION["dorQty"] = $record['Quantity'];
                $_SESSION["dorModelId"] = $modelId;
                $_SESSION["dorModelName"] = testInput($_POST["txtModelName"]);
                $_SESSION["dorTypeId"] = $dorTypeId;
                $_SESSION["dorRecordId"] = $record['RecordId'];

                $response['success'] = true;
                $response['redirectUrl'] = "dor-form.php";
            } else {
                $response['success'] = false;
                $response['errors'][] = "DOR record not found in database.";
            }
        } else {
            $response['success'] = false;
            $response['errors'][] = "No DOR found for the selected criteria.";
        }
    } catch (Exception $e) {
        globalExceptionHandler($e);
        $response['errors'][] = "An error occurred while searching for the DOR.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'DOR System'); ?></title>
</head>

<form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
    <div class="container-fluid">
        <div class="mb-3">
            <label for="dtpDate" class="form-label-lg fw-bold">Date</label>
            <input type="date" class="form-control form-control-lg" id="dtpDate" name="dtpDate"
                value="<?php echo $_POST["dtpDate"] ?? $today; ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label-lg fw-bold">Shift</label>
            <div class="row">
                <?php foreach (["DS" => "Day Shift", "NS" => "Night Shift"] as $value => $label): ?>
                    <div class="col">
                        <div class="input-group-text form-control-lg">
                            <input class="form-check-input me-2" type="radio" name="rdShift" value="<?= $value; ?>"
                                <?= ($_POST['rdShift'] ?? $selectedShift) === $value ? "checked" : ""; ?>>
                            <label class="form-check-label"><?= $label; ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="txtModelName" class="form-label-lg fw-bold">Model</label>
            <div class="input-group">
                <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName"
                    placeholder="Enter model name" required value="<?php echo $_POST["txtModelName"] ?? ''; ?>">
                <button type="button" class="btn btn-outline-secondary btn-lg" id="btnScanModel">
                    <i class="bi bi-upc-scan"></i> Scan
                </button>
            </div>
        </div>

        <div class="mb-3">
            <label for="txtQty" class="form-label-lg fw-bold">Quantity</label>
            <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty" min="1"
                placeholder="Enter box quantity" required value="<?php echo $_POST["txtQty"] ?? ''; ?>">
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" name="btnCreateDor" value="1">Create DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" name="btnSearchDor" value="1">Search DOR</button>
            <button type="button" class="btn btn-secondary btn-lg" id="btnClearForm">Clear Form</button>
            <button type="button" class="btn btn-secondary btn-lg" id="btnSetValues">Set Test Values</button>
        </div>
    </div>

    <!-- QR Code Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scan ID Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

    <!-- Bootstrap Modal for Error Messages -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Error Summary</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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

<script src="../js/bootstrap.min.js"></script>
<script src="../js/jsQR.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Clear any existing DOR form data if no DOR session is active
        if (!<?php echo isset($_SESSION['dorRecordId']) ? 'true' : 'false'; ?>) {
            sessionStorage.removeItem('dorFormData');
        }

        // Restore form data from session storage
        restoreFormData();

        const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
        const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
        const errorModalElement = document.getElementById("errorModal");
        const modalErrorMessage = document.getElementById("modalErrorMessage");

        const video = document.getElementById("qr-video");
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d", {
            willReadFrequently: true
        });

        const modelInput = document.getElementById("txtModelName");
        const qtyInput = document.getElementById("txtQty");
        const form = document.querySelector("#myForm"); // Move form declaration here


        let enterManually = false;
        let scanning = false;
        let activeInput = null;
        let clickedButton = null;

        // form data when user navigates away
        window.addEventListener('beforeunload', function() {
            saveFormData();
        });

        errorModalElement.addEventListener('hidden.bs.modal', () => {
            const active = document.activeElement;
            if (active && errorModalElement.contains(active)) {
                active.blur();
            }
        });

        navigator.permissions.query({
            name: "camera"
        }).then((result) => {
            console.log("Camera permission:", result.state);
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

        function startScanning() {
            scannerModal.show();
            navigator.mediaDevices.getUserMedia(getCameraConstraints())
                .then(setupVideoStream)
                .catch((err1) => {
                    console.warn("Rear camera failed", err1);
                    return navigator.mediaDevices.getUserMedia({
                            video: {
                                facingMode: "user"
                            }
                        })
                        .then(setupVideoStream)
                        .catch((err2) => {
                            console.error("Camera access denied", err2);
                            alert("Camera access is blocked or not available on this tablet.");
                        });
                });
        }

        function setupVideoStream(stream) {
            video.srcObject = stream;
            video.setAttribute("playsinline", true);
            video.setAttribute("autoplay", true);
            video.muted = true;
            video.style.width = "100%";
            video.style.height = "auto";
            video.onloadedmetadata = () => {
                video.play().then(() => scanQRCode());
            };
            scanning = true;
            scanQRCode();
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

                    if (parts.length === 1) {
                        modelInput.value = parts[0];
                        qtyInput.value = "";
                        stopScanning();
                    } else if (parts.length === 3) {
                        const model = parts[0];
                        const qty = parts[1]; // fix here
                        const lineNum = parts[2]; // New: Extract line number

                        if (!isNaN(qty)) {
                            modelInput.value = model;
                            qtyInput.value = qty;
                            indicateScanSuccess();
                            stopScanning();
                        } else {
                            alert("Invalid QR Code format.");
                        }
                    } else {
                        alert("Invalid QR format.");
                    }
                }
            }
            requestAnimationFrame(scanQRCode);
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

        function stopScanning() {
            scanning = false;
            const tracks = video.srcObject?.getTracks();
            if (tracks) tracks.forEach(track => track.stop());
            video.srcObject = null;

            const modalEl = document.getElementById("qrScannerModal");
            if (modalEl.classList.contains("show")) {
                bootstrap.Modal.getInstance(modalEl)?.hide();
            }
        }

        // Scanner button for model input
        document.getElementById("btnScanModel").addEventListener("click", async function() {
            if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
                const accessGranted = await navigator.mediaDevices.getUserMedia({
                        video: true
                    })
                    .then(stream => {
                        stream.getTracks().forEach(track => track.stop());
                        return true;
                    })
                    .catch(() => false);

                if (accessGranted) {
                    activeInput = modelInput;
                    startScanning();
                } else {
                    alert("Camera access denied.");
                }
            } else {
                alert("Camera access is not supported on this device or browser.");
            }
        });

        // Function to parse model input and auto-populate fields
        function parseModelInput(inputValue) {
            const parts = inputValue.trim().split(" ");

            if (parts.length === 1) {
                // Single value - assume it's just the model name
                modelInput.value = parts[0];
                // Don't change qty input
            } else if (parts.length >= 2) {
                // Multiple values - first is model, second is qty
                const model = parts[0];
                const qty = parts[1];

                if (!isNaN(qty)) {
                    modelInput.value = model;
                    qtyInput.value = qty;
                } else {
                    // If second value is not a number, treat as single model name
                    modelInput.value = inputValue.trim();
                }
            }
        }

        // Handle Enter key on model input
        modelInput.addEventListener("keydown", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                const inputValue = this.value.trim();
                if (inputValue) {
                    parseModelInput(inputValue);
                }
                qtyInput.focus(); // Move focus to qty input
            }
        });

        // Handle input change for gun scanner (auto-trigger on value change)
        // REMOVED - Only parse on Enter key press

        document.getElementById("enterManually").addEventListener("click", () => {
            enterManually = true;
            stopScanning();
            setTimeout(() => {
                if (activeInput) activeInput.focus();
            }, 300);
        });

        document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);

        document.querySelectorAll("button[type='submit']").forEach(button => {
            button.addEventListener("click", function() {
                clickedButton = this;
            });
        });

        form.addEventListener("submit", function(e) {
            e.preventDefault();

            // If no button was clicked (e.g., Enter key), default to btnCreateDor
            if (!clickedButton) {
                clickedButton = document.querySelector("button[name='btnCreateDor']");
            }

            let errors = [];

            if (errors.length > 0) {
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
                return;
            }

            // Clear any existing DOR form data from session storage before creating/searching
            sessionStorage.removeItem('dorFormData');

            const formData = new FormData(form);
            if (clickedButton && clickedButton.name) {
                // Remove all other submit button names from FormData
                formData.delete('btnCreateDor');
                formData.delete('btnSearchDor');
                // Add only the clicked button
                formData.append(clickedButton.name, clickedButton.value || "1");
            }

            fetch(form.action, {
                    method: "POST",
                    body: formData
                })
                .then(response => response.text())
                .then((text) => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.redirectUrl) {
                            clearFormData();
                            window.location.href = data.redirectUrl;
                        } else if (data.errors && data.errors.length > 0) {
                            modalErrorMessage.innerHTML = "<ul><li>" + data.errors.join("</li><li>") + "</li></ul>";
                            errorModal.show();
                        }
                    } catch (e) {
                        console.error("Raw response:", text);
                        console.error("Parse error:", e);
                        modalErrorMessage.innerHTML = "An unexpected error occurred. Please try again.";
                        errorModal.show();
                    }
                })
                .finally(() => {
                    clickedButton = null; // Reset for next submit
                });
        });

        // Function to save form data to session storage
        function saveFormData() {
            const formData = {};

            // Save all form inputs
            const dateInput = document.getElementById('dtpDate');
            const modelInput = document.getElementById('txtModelName');
            const qtyInput = document.getElementById('txtQty');

            if (dateInput) {
                formData['dtpDate'] = dateInput.value;
            }
            if (modelInput) {
                formData['txtModelName'] = modelInput.value;
            }
            if (qtyInput) {
                formData['txtQty'] = qtyInput.value;
            }
            // Save radio button values
            const shiftRadios = document.querySelectorAll('input[name="rdShift"]');
            shiftRadios.forEach(radio => {
                if (radio.checked) {
                    formData['rdShift'] = radio.value;
                }
            });

            sessionStorage.setItem('dorHomeData', JSON.stringify(formData));
        }

        // Function to restore form data from session storage
        function restoreFormData() {
            const savedData = sessionStorage.getItem('dorHomeData');
            if (!savedData) return;

            try {
                const formData = JSON.parse(savedData);

                // Restore form inputs
                const dateInput = document.getElementById('dtpDate');
                const modelInput = document.getElementById('txtModelName');
                const qtyInput = document.getElementById('txtQty');

                if (dateInput && formData['dtpDate']) {
                    dateInput.value = formData['dtpDate'];
                }
                if (modelInput && formData['txtModelName']) {
                    modelInput.value = formData['txtModelName'];
                }
                if (qtyInput && formData['txtQty']) {
                    qtyInput.value = formData['txtQty'];
                }
                // Restore radio button values
                if (formData['rdShift']) {
                    const radio = document.querySelector(`input[name="rdShift"][value="${formData['rdShift']}"]`);
                    if (radio) {
                        radio.checked = true;
                    }
                }

            } catch (error) {
                console.error('Error restoring form data:', error);
            }
        }

        // Function to clear form data from session storage
        function clearFormData() {
            sessionStorage.removeItem('dorHomeData');
        }

        // Add test values function - now fetches from server
        function setTestValues() {
            console.log("setTestValues called"); // Debug log

            const formData = new FormData();
            formData.append('btnSetValues', '1');

            fetch(form.action, {
                    method: "POST",
                    body: formData
                })
                .then(response => {
                    console.log("Response status:", response.status); // Debug log
                    return response.text();
                })
                .then((text) => {
                    console.log("Raw response:", text); // Debug log
                    try {
                        const data = JSON.parse(text);
                        console.log("Parsed data:", data); // Debug log

                        if (data.success && data.testData) {
                            // Populate form with test data from server
                            document.getElementById("dtpDate").value = data.testData.dateTime || data.testData.date;
                            document.getElementById("txtModelName").value = data.testData.modelName;
                            document.getElementById("txtQty").value = data.testData.quantity;

                            // Set shift radio button (force uncheck others)
                            document.querySelectorAll('input[name="rdShift"]').forEach(radio => {
                                radio.checked = (radio.value === data.testData.shift);
                            });

                            // Show success message if available
                            if (data.testData.message) {
                                console.log("Success:", data.testData.message);
                            }
                        } else if (data.errors && data.errors.length > 0) {
                            console.error("Server errors:", data.errors);
                            modalErrorMessage.innerHTML = "<ul><li>" + data.errors.join("</li><li>") + "</li></ul>";
                            errorModal.show();
                        } else {
                            console.warn("Unexpected response format:", data);
                        }
                    } catch (e) {
                        console.error("Error parsing test values response:", text);
                        console.error("Parse error:", e);
                        // Fallback to static values if server request fails
                        document.getElementById("txtModelName").value = "7L0113-7021C";
                        document.getElementById("txtQty").value = "42";
                    }
                })
                .catch(error => {
                    console.error("Error fetching test values:", error);
                    // Fallback to static values if server request fails
                    document.getElementById("txtModelName").value = "7L0113-7021C";
                    document.getElementById("txtQty").value = "42";
                });
        }

        // Add clear form function
        function clearForm() {
            // Reset date to today
            document.getElementById("dtpDate").value = "<?php echo $today; ?>";

            // Reset model name
            document.getElementById("txtModelName").value = "";

            // Reset quantity
            document.getElementById("txtQty").value = "";

            // Reset shift to current shift
            const currentShift = "<?php echo $selectedShift; ?>";
            const shiftRadios = document.querySelectorAll('input[name="rdShift"]');
            shiftRadios.forEach(radio => {
                radio.checked = (radio.value === currentShift);
            });

            // Clear form data from session storage
            clearFormData();
        }

        // Add event listener for Clear Form button
        document.getElementById('btnClearForm').addEventListener('click', function(e) {
            e.preventDefault(); // Prevent form submission
            clearForm();
        });

        // Add event listener for Set Test Values button
        document.getElementById('btnSetValues').addEventListener('click', function(e) {
            e.preventDefault(); // Prevent form submission
            setTestValues();
        });
    });
</script>


<?php
$content = ob_get_clean();
include('../config/master.php');
?>
