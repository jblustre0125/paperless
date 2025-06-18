<?php
$title = "Homepage";
ob_start();

require_once __DIR__ . "/../config/dbop.php";
require_once __DIR__ . "/../config/header.php";

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
    $today = date('Y-m-d H:i:s'); // Include both date and time
    $currentHour = date('H');
    $selectedShift = ($currentHour >= 7 && $currentHour <= 19) ? "DS" : "NS";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'errors' => []];  // Initialize response array

    try {
        $dorDate = testInput($_POST["dtpDate"]);
        $shiftCode = testInput($_POST['rdShift']);
        $lineNumber = testInput($_POST["txtLineNumber"]);
        $dorTypeId = testInput($_POST["cmbDorType"]);
        $modelName = testInput($_POST["txtModelName"]);
        $qty = (int) testInput($_POST["txtQty"]);

        if (empty($dorDate)) {
            $response['errors'][] = "Select date.";
        }

        if (empty($shiftCode)) {
            $response['errors'][] = "Select shift.";
        }

        if (empty(trim($lineNumber)) || $lineNumber == "0") {
            $response['errors'][] = "Enter line number.";
        } else {
            if (!isValidLine($lineNumber)) {
                $response['errors'][] = "Line number is not valid or inactive.";
            }
        }

        if (testInput($dorTypeId == "0")) {
            $response['errors'][] = "Select DOR type.";
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

        $shiftId = $lineId = $modelId = 0;

        $selQry = "EXEC RdGenShift @ShiftCode=?";
        $res = $db1->execute($selQry, [$shiftCode], 1);

        if ($res !== false) {
            foreach ($res as $row) {
                $shiftId = $row['ShiftId'];
            }
        }

        $selQry = "EXEC RdGenLine @LineNumber=?";
        $res = $db1->execute($selQry, [$lineNumber], 1);

        if (!empty($res)) {
            foreach ($res as $row) {
                $lineId = $row['LineId'];
            }
        }

        $selQry = "EXEC RdGenModel @IsActive=?, @ITEM_ID=?";
        $res = $db1->execute($selQry, [1, $modelName], 1);

        if ($res !== false) {
            foreach ($res as $row) {
                $modelId = $row['MODEL_ID'];
            }
        }

        if (empty($response['errors'])) {
            if (isset($_POST['btnCreateDor'])) {
                if (isExistDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId)) {
                    $response['errors'][] = "DOR already exists.";
                } else {
                    handleCreateDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, $response);
                }
            } elseif (isset($_POST['btnSearchDor'])) {
                handleSearchDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, $response);
            }
        }
    } catch (Exception $e) {
        globalExceptionHandler($e);
        $response['errors'][] = "An error occurred while processing your request.";
    }

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

//TODO: Populate the DOR form with the selected DOR details
function handleSearchDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId, $qty, &$response)
{
    try {
        if (isExistDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId)) {
            $response['success'] = true;
            $response['redirectUrl'] = "dor-form.php";
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
                            <input class="form-check-input me-2" type="radio" name="rdShift" value="<?= $value; ?>" <?= ($_POST['rdShift'] ?? $selectedShift) === $value ? "checked" : ""; ?>>
                            <label class="form-check-label"><?= $label; ?></label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="txtLineNumber" class="form-label-lg fw-bold">Line No</label>
            <input type="number" class="form-control form-control-lg" id="txtLineNumber" name="txtLineNumber" min="1" required placeholder="Enter line number"
                value="<?php echo $_POST["txtLineNumber"] ?? ''; ?>">
        </div>

        <div class="mb-3">
            <label for="cmbDorType" class="form-label-lg fw-bold">DOR Type</label>
            <select class="form-select form-select-lg" id="cmbDorType" name="cmbDorType">
                <option value="0">Select DOR Type</option>
                <?php foreach (loadDorType() as $key => $value): ?>
                    <option value="<?= $key; ?>" <?= ($_POST['cmbDorType'] ?? '') == $key ? 'selected' : ''; ?>><?= $value; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="txtModelName" class="form-label-lg fw-bold">Model</label>
            <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName" placeholder="Tap to scan ID tag" required data-scan
                value="<?php echo $_POST["txtModelName"] ?? ''; ?>">
        </div>

        <div class="mb-3">
            <label for="txtQty" class="form-label-lg fw-bold">Quantity</label>
            <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty" min="1" placeholder="Tap to scan ID tag" required
                value="<?php echo $_POST["txtQty"] ?? ''; ?>">
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" name="btnCreateDor">Create DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" name="btnSearchDor">Search DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" name="btnSetValues">Set Test Values</button>
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
                    <h5 class="modal-title" id="errorModalLabel">Please complete all information</h5>
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

        let enterManually = false;
        let scanning = false;
        let activeInput = null;
        let clickedButton = null;

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

        document.querySelectorAll("input[data-scan]").forEach(input => {
            input.addEventListener("click", async function() {
                const accessGranted = await navigator.mediaDevices.getUserMedia({
                        video: true
                    })
                    .then(stream => {
                        stream.getTracks().forEach(track => track.stop());
                        return true;
                    })
                    .catch(() => false);

                if (accessGranted) {
                    activeInput = this;
                    startScanning();
                } else {
                    alert("Camera access denied.");
                }
            });
        });

        document.getElementById("enterManually").addEventListener("click", () => {
            enterManually = true;
            stopScanning();
            setTimeout(() => {
                if (activeInput) activeInput.focus();
            }, 300);
        });

        document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);

        const form = document.querySelector("#myForm");
        document.querySelectorAll("button[type='submit']").forEach(button => {
            button.addEventListener("click", function() {
                clickedButton = this;
            });
        });

        form.addEventListener("submit", function(e) {
            e.preventDefault();
            let errors = [];

            if (errors.length > 0) {
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
                return;
            }

            const formData = new FormData(form);
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
                        window.location.href = data.redirectUrl;
                    } else {
                        modalErrorMessage.innerHTML = "<ul><li>" + data.errors.join("</li><li>") + "</li></ul>";
                        errorModal.show();
                    }
                })
                .catch(error => console.error("Error:", error));
        });

        // Add test values function
        function setTestValues() {
            document.getElementById("txtLineNumber").value = "1";
            // document.getElementById("txtModelName").value = "7M0656-7020";
            document.getElementById("txtModelName").value = "7L0113-7021C";
            document.getElementById("cmbDorType").value = "3";
            document.getElementById("txtQty").value = "100";
        }

        // Add event listener for Set Test Values button
        document.querySelector('button[name="btnSetValues"]').addEventListener('click', function(e) {
            e.preventDefault(); // Prevent form submission
            setTestValues();
        });
    });
</script>


<?php
$content = ob_get_clean();
include('../config/master.php');
?>