<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila'); // Replace with your desired time zone

$title = "Home";
ob_start();

require_once "../config/dbop.php";
require_once "../config/header.php";

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
    $selectedShift = ($currentHour >= 7 && $currentHour < 19) ? "DS" : "NS";
}

$response = ['success' => false, 'isValidModel' => false, 'errors' => []];

?>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    $dorDate = testInput($_POST["dtpDate"]);
    $shift = testInput($_POST['rdShift']);
    $dorTypeId = testInput($_POST["cmbDorType"]);
    $modelName = testInput($_POST["txtModelName"]);
    $qty = (int) testInput($_POST["txtQty"]);

    // Server-Side Validation
    $errorMessages = [];

    if (empty($dorDate)) {
        $errorMessages[] = "Select a date.";
    }

    if (empty($shift)) {
        $errorMessages[] = "Select a shift.";
    }

    if ($dorTypeId == "0") {
        $errorMessages[] = "Select a DOR type.";
    }

    if (empty(trim($modelName))) {
        $errorMessages[] = "Enter the model name.";
    } else {
        if (!isValidModel($modelName)) {
            $response['isValidModel'] = false;
            $errorMessages[] = "Invalid model name.";
        } else {
            $response['isValidModel'] = true;
        }
    }

    if (empty(trim($qty)) || $qty == 0) {
        $errorMessages[] = "Enter the quantity.";
    }

    if (!is_numeric($qty) || $qty <= 0) {
        $errorMessages[] = "Enter the quantity.";
    }

    $response['errors'] = $errorMessages;

    if (empty($response['errors'])) {
        if (isset($_POST['btnCreateDor'])) {
            handleCreateDor($dorDate, $shift, $dorTypeId, $modelName, $qty, $response);
        } elseif (isset($_POST['btnSearchDor'])) {
            handleSearchDor($dorDate, $shift, $dorTypeId, $modelName, $qty, $response);
        }
    } else {
    }

    echo json_encode($response);
    exit;
}

function handleCreateDor($dorDate, $shift, $dorTypeId, $modelName, $qty, &$response)
{
    global $db1;

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
        $_SESSION["dorModelName"] = $modelName;
        $_SESSION["dorTypeId"] = $dorTypeId;

        $selProcessTab = "SELECT ISNULL(MP, 0) AS 'MP' FROM dbo.GenModel WHERE ITEM_ID = ?";
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

        // $response['tabQty'] = $_SESSION["tabQty"]; // Uncomment to debug process MP
        $response['success'] = true;
        $response['redirectUrl'] = "dor-form.php";
    }
}

function handleSearchDor($dorDate, $shift, $dorTypeId, $modelName, $qty, &$response)
{
    $response['success'] = true;
    // add redirect URL for search DOR
}

?>

<style>
    #qrScannerModal video {
        background-color: black;
    }
</style>

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
                value="<?php echo $_POST["txtModelName"] ?? '7L0113-7021C'; ?>">
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
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">Form Submission Error</h5>
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

        function getCameraConstraints() {
            return {
                video: {
                    facingMode: {
                        ideal: "environment"
                    } // Prefer rear camera, fallback to front
                }
            };
        }

        function startScanning() {
            scannerModal.show();

            const constraints = getCameraConstraints();

            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    setupVideoStream(stream);
                })
                .catch(function(err) {
                    console.warn("Rear camera failed, trying front camera...", err);

                    // Try front camera as fallback
                    return navigator.mediaDevices.getUserMedia({
                            video: {
                                facingMode: "user"
                            }
                        })
                        .then(function(stream) {
                            setupVideoStream(stream);
                        })
                        .catch(function(error) {
                            console.error("Camera access denied or unavailable.", error);
                            alert("Unable to access camera. Please check permissions or try a different browser.");
                        });
                });

            function setupVideoStream(stream) {
                console.log("Stream received", stream);
                video.srcObject = stream;
                video.setAttribute("playsinline", true);
                video.setAttribute("autoplay", true);
                video.style.width = "100%";
                video.style.height = "auto";
                video.muted = true;
                video.play()
                    .then(() => console.log("Video playing"))
                    .catch(err => console.error("Video failed to play", err));

                scanning = true;
                scanQRCode();
            }
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

        modelInput.addEventListener("click", async function() {
            const accessGranted = await checkCameraAccessBeforeScanning();
            if (accessGranted) {
                scannerModal.show();
                startScanning(); // Call your existing scanning logic here
            }
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

        let clickedButton = null;

        // Track which submit button was clicked
        document.querySelectorAll("button[type='submit']").forEach(button => {
            button.addEventListener("click", function() {
                clickedButton = this;
            });
        });

        form.addEventListener("submit", function(e) {
            e.preventDefault();
            let errors = [];

            if (document.getElementById("dtpDate").value.trim() === "") errors.push("Select a date.");
            if (!document.querySelector("input[name='rdShift']:checked")) errors.push("Select a shift.");
            if (document.getElementById("cmbDorType").value === "0") errors.push("Select a DOR type.");
            if (modelInput.value.trim() === "") errors.push("Enter the model name.");
            if (qtyInput.value.trim() === "" || qtyInput.value == 0) errors.push("Enter the quantity.");

            if (errors.length > 0) {
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
                return;
            }

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
                    if (!data.isValidModel) {
                        modalErrorMessage.innerHTML = "<ul><li>Invalid model name.</li></ul>";
                        errorModal.show();
                        return;
                    }

                    // Uncomment to debug process MP
                    // if (data.tabQty !== undefined) {
                    //     console.log("Tab Quantity:", data.tabQty); // Log tabQty specifically
                    // }

                    if (data.success) {
                        if (clickedButton.name === "btnCreateDor") {
                            // alert(data.tabQty); // Uncomment to debug process MP
                            window.location.href = data.redirectUrl;
                            return;
                        }
                        if (clickedButton.name === "btnSearchDor") {
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
</script>

<?php
$content = ob_get_clean();
include('../config/master.php');
?>