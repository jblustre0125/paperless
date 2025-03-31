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
$response = ['success' => false, 'errors' => []];
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
                <?php foreach (loadDorType($options) as $key => $value): ?>
                    <option value="<?= $key; ?>" <?= ($_POST['cmbDorType'] ?? '') == $key ? 'selected' : ''; ?>><?= $value; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="txtModelName" class="form-label-lg fw-bold">Model</label>
            <<<<<<< Updated upstream
                <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName" placeholder="Scan or type product model" required>
                =======
                <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName"
                    placeholder="Scan or type product model" required>
                <<<<<<< Updated upstream>>>>>>> Stashed changes
                    =======
                    >>>>>>> Stashed changes
        </div>

        <div class="mb-3">
            <label for="txtQty" class="form-label-lg fw-bold">Quantity</label>
            <<<<<<< Updated upstream
                <<<<<<< Updated upstream
                <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty" placeholder="Scan or type quantity" required>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" name="btnCreateDor">Create DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" name="btnSearchDor">Search DOR</button>
        </div>
        =======
        <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty"
            placeholder="Scan or type quantity" required>
    </div>

    <div class="d-grid gap-2">
        =======
        <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty"
            placeholder="Scan or type quantity" required>
    </div>

    <div class="d-grid gap-2">
        >>>>>>> Stashed changes
        <button type="submit" class="btn btn-primary btn-lg" id="btnCreateDor" name="btnCreateDor">Create DOR</button>
        <button type="submit" class="btn btn-secondary btn-lg" id="btnSearchDor" name="btnSearchDor">Search DOR</button>
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
    >>>>>>> Stashed changes
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
    <<<<<<< Updated upstream
        <<<<<<< Updated upstream

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            header('Content-Type: application/json; charset=utf-8');

            $dorDate = testInput($_POST["dtpDate"] ?? "");
            $shift = testInput($_POST['rdShift'] ?? "");
            $leaderId = testInput($_POST["cmbLeader"] ?? "0");
            $dorTypeId = testInput($_POST["cmbDorType"] ?? "0");
            $modelName = testInput($_POST["txtModelName"] ?? "");
            $qty = testInput($_POST["txtQty"] ?? "");

            $errors = [];
            if (empty($dorDate)) $errors[] = "Select a date.";
            if (empty($shift)) $errors[] = "Select a shift.";
            if ($leaderId == "0") $errors[] = "Select a leader.";
            if ($dorTypeId == "0") $errors[] = "Select a DOR type.";
            if (empty(trim($modelName)) || !isValidModel($modelName)) $errors[] = "Enter a valid model name.";
            if (empty(trim($qty)) || $qty == 0) $errors[] = "Enter a valid quantity.";

            if (empty($errors)) {
                $shiftId = $lineId = $modelId = 0;

                $res = $db1->execute("EXEC RdGenShift @ShiftCode=?", [$shift], 1);
                if ($res) $shiftId = $res[0]['ShiftId'] ?? 0;

                $res = $db1->execute("EXEC RdAtoLine @LineId=?", [$_SESSION["deviceName"]], 1);
                if ($res) $lineId = $res[0]['LineId'] ?? 0;

                $res = $db1->execute("EXEC RdGenModel @IsActive=?, @ITEM_ID=?", [1, $modelName], 1);
                if ($res) $modelId = $res[0]['ITEM_ID'] ?? 0;

                if (isExistDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId)) {
                    $errors[] = "DOR already exists.";
                } else {
                    $_SESSION = array_merge($_SESSION, [
                        "dorDate" => $dorDate,
                        "dorShift" => $shift,
                        "dorLineId" => $lineId,
                        "dorQty" => $qty,
                        "dorModelId" => $modelId,
                        "dorLeaderId" => $leaderId,
                        "dorTypeId" => $dorTypeId
                    ]);
                    $response['success'] = true;
                    $response['redirect'] = "dor-form.php";
                }
            }

            header('Content-Type: application/json'); // Ensure JSON response
            $data = ['status' => 'success', 'message' => 'Data saved'];
            ob_end_clean();
            echo json_encode($data);

            exit;
        }
        ?>=======>>>>>>> Stashed changes
        =======
        >>>>>>> Stashed changes
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
        const form = document.querySelector('form');

        form.addEventListener('myForm', function(e) {
            e.preventDefault(); // stop form from submitting normally

            // use fetch API to submit the form data
            fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(response => response.text())
                .then(html => alert(html)) // display response
                .catch(error => console.error('Error:', error));
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');

        form.addEventListener('myForm', function(e) {
            e.preventDefault(); // stop form from submitting normally

            // use fetch API to submit the form data
            fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(response => response.text())
                .then(html => alert(html)) // display response
                .catch(error => console.error('Error:', error));
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('#myForm');
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        const modalErrorMessage = document.getElementById('modalErrorMessage');

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Stop default submission

            let errors = [];

            // 🔹 Client-Side Validation
            if (document.getElementById('dtpDate').value.trim() === '') {
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

            // If Client-Side Errors Exist, Show Modal
            if (errors.length > 0) {
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
                return;
            }

            // 🔹 Send Data to Server (Including `isValidModel()` Check)
            let formData = new FormData(form);
            formData.append('validateModel', '1'); // Flag to check model

            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json()) // Parse response as JSON
                .then(data => {
                    if (!data.validModel) {
                        // Show error modal if model is invalid
                        modalErrorMessage.innerHTML = "<ul><li>Invalid model name. Please check.</li></ul>";
                        errorModal.show();
                        return;
                    }

                    // 🔹 If Model is Valid, Continue Saving Data
                    if (data.success) {
                        window.location.href = data.redirect; // Redirect to dor-form.php
                    } else {
                        modalErrorMessage.innerHTML = "<ul><li>" + data.errors.join("</li><li>") + "</li></ul>";
                        errorModal.show();
                    }
                })
                .catch(error => console.error("Error:", error));
        });
    });
</script>

<script src="../js/jsQR.min.js"></script>

<script>
    // document.addEventListener("DOMContentLoaded", function() {
    //     const modelInput = document.getElementById("txtModelName");
    //     const qtyInput = document.getElementById("txtQty");
    //     const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
    //     let enterManually = false; // Flag to track manual entry


    //     let video = document.getElementById("qr-video");
    //     let canvas = document.createElement("canvas");
    //     let ctx = canvas.getContext("2d", {
    //         willReadFrequently: true
    //     });
    //     let scanning = false;

    //     let video = document.getElementById("qr-video");
    // let canvas = document.createElement("canvas");
    // let ctx = canvas.getContext("2d", {
    //     willReadFrequently: true 
    // });
    // let scanning = false;

    // Retrieve camera setting from PHP session
    let cameraSetting = <?php echo isset($_SESSION['cameraSetting']) ? $_SESSION['cameraSetting'] : 1; ?>;

    function getCameraConstraints() {
        return {
            video: {
                facingMode: cameraSetting === 1 ? "environment" : "user"
            }
        };
    }

    // Open QR Scanner when Model Input is Selected
    modelInput.addEventListener("focus", function() {
        if (!enterManually) {
            startScanning();
        }
    });
    let scanning = false;

    //     // Retrieve camera setting from PHP session
    //     let cameraSetting = <?php echo isset($_SESSION['cameraSetting']) ? $_SESSION['cameraSetting'] : 1; ?>;

    //     function getCameraConstraints() {
    //         return {
    //             video: {
    //                 facingMode: cameraSetting === 1 ? "environment" : "user"
    //             }
    //         };
    //     }

    //     // Open QR Scanner when Model Input is Selected
    //     modelInput.addEventListener("focus", function() {
    //         if (!enterManually) {
    //             startScanning();
    //         }
    //     });

    //     function startScanning() {
    //         scannerModal.show();
    //         navigator.mediaDevices
    //             .getUserMedia(getCameraConstraints())
    //             .then(function(stream) {
    //                 video.srcObject = stream;
    //                 video.setAttribute("playsinline", true);
    //                 video.play();
    //                 scanning = true;
    //                 scanQRCode();
    //             })
    //             .catch(function(err) {
    //                 alert("Camera access denied: " + err.message);
    //             });
    //     }

    //     function scanQRCode() {
    //         if (!scanning) return;


    //         if (video.readyState === video.HAVE_ENOUGH_DATA) {
    //             canvas.width = video.videoWidth;
    //             canvas.height = video.videoHeight;
    //             ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    //             let imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    //             let qrCodeData = jsQR(imageData.data, imageData.width, imageData.height);

    //             if (qrCodeData) {
    //                 let scannedText = qrCodeData.data.trim();

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        let imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        let qrCodeData = jsQR(imageData.data, imageData.width, imageData.height);

        //                 if (parts.length === 1) {
        //                     // If only 1 value is provided, assume it's the model name
        //                     modelInput.value = parts[0];
        //                     qtyInput.value = ""; // Clear qty
        //                     stopScanning();
        //                 } else if (parts.length === 3) {
        //                     // If exactly 3 values are provided, take the 1st as model and 3rd as quantity
        //                     let model = parts[0];
        //                     let qty = parts[2];

        //                     if (!isNaN(qty)) {
        //                         modelInput.value = model;
        //                         qtyInput.value = qty;
        //                         stopScanning();
        //                     } else {
        //                         alert("Invalid QR format. Quantity must be a number.");
        //                     }
        //                 } else {
        //                     alert("Invalid QR format. Please scan a valid code with 1 or 3 values.");
        //                 }
        //             }
        //         }

        //         requestAnimationFrame(scanQRCode);
        //     }

        //     function stopScanning() {
        //         scanning = false;
        //         let tracks = video.srcObject?.getTracks();
        //         if (tracks) {
        //             tracks.forEach((track) => track.stop());
        //         }
        //         scannerModal.hide();
        //     }

        //     // Stop scanning when modal closes
        //     document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", function() {
        //         stopScanning();
        //     });

        //     // Handle "Enter Manually" Button
        //     document.getElementById("enterManually").addEventListener("click", function() {
        //         enterManually = true; // Prevent QR scanner from reopening
        //         stopScanning();
        //         scannerModal.hide();
        //         setTimeout(() => modelInput.focus(), 300); // Ensure focus shifts properly
        //     });

        //     // Reset flag when the user types manually
        //     modelInput.addEventListener("input", function() {
        //         enterManually = true; // Keeps scanner disabled while typing
        //     });

        //     // Reset flag when input loses focus (so QR scanner works next time)
        //     modelInput.addEventListener("blur", function() {
        //         setTimeout(() => enterManually = false, 1000); // Delay prevents immediate re-trigger
        //     });
        // });
</script>

<?php
$content = ob_get_clean();
include('../config/master.php');
?>