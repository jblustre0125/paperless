<?php

$title = "Home";
ob_start();

require_once "../config/dbop.php";
require_once "../config/header.php";

$db1 = new DbOp(1);
$today = date('Y-m-d');
$errorMessages = [];

$currentHour = date('H');
$selectedShift = ($currentHour >= 7 && $currentHour < 19) ? "DS" : "NS";

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
                <div class="col">
                    <div class="input-group-text form-control-lg">
                        <input class="form-check-input me-2" type="radio" name="rdShift" id="rdDayShift" value="DS"
                            <?php echo ($_POST['rdShift'] ?? $selectedShift) === "DS" ? "checked" : ""; ?>>
                        <label class="form-check-label" for="rdDayShift">Day Shift</label>
                    </div>
                </div>
                <div class="col">
                    <div class="input-group-text form-control-lg">
                        <input class="form-check-input me-2" type="radio" name="rdShift" id="rdNightShift" value="NS"
                            <?php echo ($_POST['rdShift'] ?? $selectedShift) === "NS" ? "checked" : ""; ?>>
                        <label class="form-check-label" for="rdNightShift">Night Shift</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="cmbLeader" class="form-label-lg fw-bold">Leader</label>
            <select class="form-select form-select-lg" id="cmbLeader" name="cmbLeader">
                <option value="0" selected>Select Leader</option>
                <?php
                $opsLeader = loadLeader($_SESSION['processId'], 1);
                foreach ($opsLeader as $key => $value) {
                    $selected = ($_POST['cmbLeader'] ?? '') == $key ? 'selected' : '';
                    echo "<option value='$key' $selected>$value</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="cmbDorType" class="form-label-lg fw-bold">DOR Type</label>
            <select class="form-select form-select-lg" id="cmbDorType" name="cmbDorType">
                <option value="0" selected>Select DOR Type</option>
                <?php
                $opsDor = loadDorType($options);
                foreach ($opsDor as $key => $value) {
                    $selected = ($_POST['cmbDorType'] ?? '') == $key ? 'selected' : '';
                    echo "<option value='$key' $selected>$value</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="txtModelName" class="form-label-lg fw-bold">Model</label>
            <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName"
                placeholder="Scan or type product model"
                value="<?php echo $_POST["txtModelName"] ?? '7R0122-7020A'; ?>" required>
        </div>

        <div class="mb-3">
            <label for="txtQty" class="form-label-lg fw-bold">Quantity</label>
            <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty"
                placeholder="Scan or type quantity"
                value="<?php echo $_POST["txtQty"] ?? '100'; ?>" required>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" id="btnSubmit" name="btnCreateDor">Create DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" id="btnSearch" name="btnSearchDor">Search DOR</button>
        </div>

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $dorDate = testInput($_POST["dtpDate"]);
            $shift = testInput($_POST['rdShift']);
            $leaderId = testInput($_POST["cmbLeader"]);
            $dorTypeId = testInput($_POST["cmbDorType"]);
            $modelName = testInput($_POST["txtModelName"]);
            $qty = testInput($_POST["txtQty"]);

            if (empty($dorDate)) {
                $errorMessages[] = "- Date is required. Select a date.";
            }

            if (empty($shift)) {
                $errorMessages[] = "- Shift is required. Select a shift.";
            }

            if ($leaderId === "0") {
                $errorMessages[] = "- Select a leader from the list.";
            }

            if ($dorTypeId === "0") {
                $errorMessages[] = "- Select the type of DOR from the list.";
            }
            if (empty($modelName)) {
                $errorMessages[] = "- Scan or type the product model.";
            } else {
                if (!isValidModel($modelName)) {
                    $errorMessages[] = "- The model is not registered.";
                }
            }

            if (empty($qty) || $qty === '0') {
                $errorMessages[] = "- Enter the production quantity.";
            }

            // If there are errors, display them
            if (!empty($errorMessages)) {
                $errorPrompt = implode("<br>", $errorMessages); // Join errors with line breaks
            } else {
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
                        $errorPrompt = "DOR already exists.";
                        alertMsg(1);
                    } else {
                        $_SESSION["dorDate"] = $dorDate;
                        $_SESSION["dorShift"] = $shift;
                        $_SESSION["dorLineId"] = $lineId;
                        $_SESSION["dorQty"] = $qty;
                        $_SESSION["dorModelId"] = $modelId;
                        $_SESSION["dorLeaderId"] = $leaderId;
                        $_SESSION["dorTypeId"] = $dorTypeId;

                        header('Location: dor-form.php');
                    }
                }
            }
        }
        ?>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('#myForm');
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        const modalErrorMessage = document.getElementById('modalErrorMessage');

        form.addEventListener('submit', function(e) {
            let errors = [];

            // Validate Fields
            if (document.getElementById('dtpDate').value === '') {
                errors.push("Select a date.");
            }
            if (!document.querySelector('input[name="rdShift"]:checked')) {
                errors.push("Select a shift.");
            }
            if (document.getElementById('cmbLeader').value === "0") {
                errors.push("Select a leader.");
            }
            if (document.getElementById('cmbDorType').value === "0") {
                errors.push("Select a DOR type.");
            }
            if (document.getElementById('txtModelName').value.trim() === '') {
                errors.push("Enter the model name.");
            }
            if (document.getElementById('txtQty').value.trim() === '' || document.getElementById('txtQty').value == 0) {
                errors.push("Enter the quantity.");
            }

            // If Errors Exist, Show Modal
            if (errors.length > 0) {
                e.preventDefault(); // Stop form submission
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
            }
        });
    });
</script>

<script src="../js/jsQR.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const modelInput = document.getElementById("txtModelName");
        const qtyInput = document.getElementById("txtQty");
        const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
        let enterManually = false; // Flag to track manual entry

        let video = document.getElementById("qr-video");
        let canvas = document.createElement("canvas");
        let ctx = canvas.getContext("2d");
        let scanning = false;

        // Open QR Scanner when Model Input is Selected
        modelInput.addEventListener("focus", function() {
            if (!enterManually) {
                startScanning();
            }
        });

        function startScanning() {
            scannerModal.show();
            navigator.mediaDevices
                .getUserMedia({
                    video: {
                        facingMode: "environment"
                    }
                })
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
                    let parts = scannedText.split(" "); // Split by space

                    if (parts.length === 1) {
                        // If only 1 value is provided, assume it's the model name
                        modelInput.value = parts[0];
                        qtyInput.value = ""; // Clear qty
                        stopScanning();
                    } else if (parts.length === 3) {
                        // If exactly 3 values are provided, take the 1st as model and 3rd as quantity
                        let model = parts[0];
                        let qty = parts[2];

                        if (!isNaN(qty)) {
                            modelInput.value = model;
                            qtyInput.value = qty;
                            stopScanning();
                        } else {
                            alert("Invalid QR format. Quantity must be a number.");
                        }
                    } else {
                        alert("Invalid QR format. Please scan a valid code with 1 or 3 values.");
                    }
                }
            }

            requestAnimationFrame(scanQRCode);
        }

        function stopScanning() {
            scanning = false;
            let tracks = video.srcObject?.getTracks();
            if (tracks) {
                tracks.forEach((track) => track.stop());
            }
            scannerModal.hide();
        }

        // Stop scanning when modal closes
        document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", function() {
            stopScanning();
        });

        // Handle "Enter Manually" Button
        document.getElementById("enterManually").addEventListener("click", function() {
            enterManually = true; // Prevent QR scanner from reopening
            stopScanning();
            scannerModal.hide();
            setTimeout(() => modelInput.focus(), 300); // Ensure focus shifts properly
        });

        // Reset flag when the user types manually
        modelInput.addEventListener("input", function() {
            enterManually = true; // Keeps scanner disabled while typing
        });

        // Reset flag when input loses focus (so QR scanner works next time)
        modelInput.addEventListener("blur", function() {
            setTimeout(() => enterManually = false, 1000); // Delay prevents immediate re-trigger
        });
    });
</script>


<?php
$content = ob_get_clean();
include('../config/master.php');
?>