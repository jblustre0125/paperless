<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$title = "Home";
ob_start();

require_once "../config/dbop.php";
require_once "../config/header.php";

$db1 = new DbOp(1);
$today = date('Y-m-d');
$currentHour = date('H');
$selectedShift = ($currentHour >= 7 && $currentHour < 19) ? "DS" : "NS";
$response = ['success' => false, 'isValidModel' => false, 'errors' => []];
?>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $dorDate = testInput($_POST["dtpDate"]);
    $shift = testInput($_POST['rdShift']);
    $leaderId = testInput($_POST["cmbLeader"]);
    $dorTypeId = testInput($_POST["cmbDorType"]);
    $modelName = testInput($_POST["txtModelName"]);
    $qty = testInput($_POST["txtQty"]);

    // Server-Side Validation
    $errorMessages = [];

    if (empty($dorDate)) {
        $errorMessages[] = "Select a date.";
    }

    if (empty($shift)) {
        $errorMessages[] = "Select a shift.";
    }

    if ($leaderId == "0") {
        $errorMessages[] = "Select a leader.";
    }

    if ($dorTypeId == "0") {
        $errorMessages[] = "Select a DOR type.";
    }

    if (empty(trim($modelName))) {
        $errorMessages[] = "Enter the model name.";
    } else {
        if (!isValidModel($modelName)) {
            $errorMessages[] = "Invalid model name.";
        }
    }

    $response['isValidModel'] = isValidModel($modelName);

    if (empty(trim($qty)) || $qty == 0) {
        $errorMessages[] = "Enter the quantity.";
    }

    $response['errors'] = $errorMessages;

    if (empty($response['errors'])) {
        $response['success'] = true;
        if (isset($_POST['btnCreateDor'])) {
            $shiftId = $lineId = $modelId = 0;

            $selQry = "EXEC RdGenShift @ShiftCode=?";
            $res = $db1->execute($selQry, [$shift], 1);

            if ($res !== false) {
                foreach ($res as $row) {
                    $shiftId = $row['ShiftId'];
                }
            }

            $selQry = "EXEC RdAtoLine @LineId=?";
            $res = $db1->execute($selQry, [$_SESSION["deviceName"]], 1);

            if (!empty($res)) {
                foreach ($res as $row) {
                    $lineId = $row['LineId'];
                }
            }

            $selQry = "EXEC RdGenModel @IsActive=?, @ITEM_ID=?";
            $res = $db1->execute($selQry, [1, $modelName], 1);

            if ($res !== false) {
                foreach ($res as $row) {
                    $modelId = $row['ITEM_ID'];
                }
            }

            if (isExistDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId) === true) {
                $response['errors'][] = "DOR already exists.";
            } else {
                $_SESSION["dorDate"] = $dorDate;
                $_SESSION["dorShift"] = $shift;
                $_SESSION["dorLineId"] = $lineId;
                $_SESSION["dorQty"] = $qty;
                $_SESSION["dorModelId"] = $modelId;
                $_SESSION["dorLeaderId"] = $leaderId;
                $_SESSION["dorTypeId"] = $dorTypeId;

                // Send a success response with the redirection URL
                $response['success'] = true;
            }
        }
    }

    echo json_encode($response);
    exit;
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
            <label for="cmbLeader" class="form-label-lg fw-bold">Leader</label>
            <select class="form-select form-select-lg" id="cmbLeader" name="cmbLeader">
                <option value="0">Select Leader</option>
                <?php foreach (loadLeader($_SESSION['processId'], 1) as $key => $value): ?>
                    <option value="<?= $key; ?>" <?= ($_POST['cmbLeader'] ?? '') == $key ? 'selected' : ''; ?>><?= $value; ?></option>
                <?php endforeach; ?>
            </select>
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
            <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName" placeholder="Scan or type product model" required
                value="<?php echo $_POST["txtModelName"] ?? '15F855-0050'; ?>">
        </div>

        <div class="mb-3">
            <label for="txtQty" class="form-label-lg fw-bold">Quantity</label>
            <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty" placeholder="Scan or type quantity" required
                value="<?php echo $_POST["txtQty"] ?? '100'; ?>">
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" name="btnCreateDor">Create DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" name="btnSearchDor">Search DOR</button>
        </div>
    </div>

    <!-- QR Code Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scan ID Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="qr-video" class="w-100 rounded shadow" autoplay></video>
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

<!-- Bootstrap Modal for Error Messages -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalErrorMessage"></div>
            </div>
        </div>
    </div>
</div>

<script src="../js/jsQR.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Elements for QR Scanner
        const modelInput = document.getElementById("txtModelName");
        const qtyInput = document.getElementById("txtQty");
        const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
        let enterManually = false;
        let video = document.getElementById("qr-video");
        let canvas = document.createElement("canvas");
        let ctx = canvas.getContext("2d", {
            willReadFrequently: true
        });
        let scanning = false;

        // Retrieve camera setting from PHP session
        let cameraSetting = <?php echo isset($_SESSION['cameraSetting']) ? (int)$_SESSION['cameraSetting'] : 1; ?>;

        function getCameraConstraints() {
            return {
                video: {
                    facingMode: cameraSetting === 1 ? "environment" : "user"
                }
            };
        }

        function startScanning() {
            scannerModal.show();
            navigator.mediaDevices.getUserMedia(getCameraConstraints())
                .then(function(stream) {
                    video.srcObject = stream;
                    video.setAttribute("playsinline", true);
                    video.play();
                    scanning = true;
                    scanQRCode();
                })
                .catch(function(err) {
                    alert("Camera access denied: " + err.message);
                });
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
                    let parts = scannedText.split(" ");

                    if (parts.length === 1) {
                        modelInput.value = parts[0];
                        qtyInput.value = "";
                        stopScanning();
                    } else if (parts.length === 3) {
                        let model = parts[0];
                        let qty = parts[2];

                        if (!isNaN(qty)) {
                            modelInput.value = model;
                            qtyInput.value = qty;
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

        function stopScanning() {
            scanning = false;
            let tracks = video.srcObject?.getTracks();
            if (tracks) tracks.forEach(track => track.stop());
            scannerModal.hide();
        }

        modelInput.addEventListener("focus", function() {
            // uncomment the line below to activate scanning mode
            // if (!enterManually) startScanning();
        });
        modelInput.addEventListener("input", function() {
            enterManually = true;
        });
        modelInput.addEventListener("blur", function() {
            setTimeout(() => enterManually = false, 1000);
        });
        document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
        document.getElementById("enterManually").addEventListener("click", function() {
            enterManually = true;
            stopScanning();
            setTimeout(() => modelInput.focus(), 300);
        });

        // Form Validation & Submission
        const form = document.querySelector("#myForm");
        const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
        const modalErrorMessage = document.getElementById("modalErrorMessage");

        form.addEventListener("submit", function(e) {
            e.preventDefault();
            let errors = [];

            if (document.getElementById("dtpDate").value.trim() === "") errors.push("Select a date.");
            if (!document.querySelector("input[name='rdShift']:checked")) errors.push("Select a shift.");
            if (document.getElementById("cmbLeader").value === "0") errors.push("Select a leader.");
            if (document.getElementById("cmbDorType").value === "0") errors.push("Select a DOR type.");
            if (modelInput.value.trim() === "") errors.push("Enter the model name.");
            if (qtyInput.value.trim() === "" || qtyInput.value == 0) errors.push("Enter the quantity.");

            if (errors.length > 0) {
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
                return;
            }

            let formData = new FormData(form);

            fetch(form.action, {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.isValidModel) {
                        modalErrorMessage.innerHTML = "<ul><li>Invalid model name.</li></ul>";
                        errorModal.show();
                        return;
                    }

                    if (data.success) {
                        window.location.href = "dor-form.php";
                    } else {
                        modalErrorMessage.innerHTML = "<ul><li>" + data.errors.join("</li><li>") + "</li></ul>";
                        errorModal.show();
                    }
                })
                .catch(error => console.error("Error:", error));
        });
    });
</script>

<?php
$content = ob_get_clean();
include('../config/master.php');
?>