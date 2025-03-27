<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DOR System</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/jsQR.min.js"></script>
    <style>
      #qr-video {
        width: 100%;
        border-radius: 10px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
      }
    </style>
  </head>
  <body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <button class="btn btn-secondary btn-lg" onclick="goBack()">Back</button>
        <div class="d-flex flex-column align-items-center">
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-secondary btn-lg" onclick="showDrawing()">Drawing</button>
            <button class="btn btn-secondary btn-lg">Work Instructions</button>
            <button class="btn btn-secondary btn-lg">Guidelines</button>
            <button class="btn btn-secondary btn-lg">Prep Card</button>
          </div>
        </div>
        <button class="btn btn-primary btn-lg" onclick="next()">Proceed to DOR</button>
      </div>
    </nav>
    <!-- QR Code Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Scan ID Tag</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <video id="qr-video" autoplay></video>
            <p class="text-muted mt-2">Align the QR code within the frame.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="enterManually">Enter Manually</button>
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>No.</th>
          <th>Box No.</th>
          <th>TIME START</th>
          <th>TIME END</th>
          <th>Operator</th>
          <th>Downtime/Abnormality/Defect Details</th>
          <th>Action Taken</th>
          <th>TIME START</th>
          <th>TIME END</th>
          <th>PIC</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody> <?php for ($i = 1; $i <= 20; $i++) { ?> <tr>
          <td> <?= $i ?> </td>
          <td>
            <input type="text" class="form-control scan-box-no" id="boxNo
										<?= $i ?>" placeholder="Scan QR" <?= $i === 1 ? '' : 'disabled' ?>>
          </td>
          <td>
            <input type="text" class="form-control" id="timeStart
											<?= $i ?>" <?= $i === 1 ? '' : 'disabled' ?>>
          </td>
          <td>
            <input type="text" class="form-control scan-box-no time-end" id="timeEnd
												<?= $i ?>" placeholder="Scan QR" <?= $i === 1 ? '' : 'disabled' ?>>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
          <td>
            <input type="text" class="form-control" disabled>
          </td>
        </tr> <?php } ?> </tbody>
    </table>

<script src="../js/jsQR.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    let tableBody = document.querySelector("tbody");
    let video = document.getElementById("qr-video");
    let canvas = document.createElement("canvas");
    let ctx = canvas.getContext("2d", { willReadFrequently: true }); // Optimize for QR reading
    let scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
    let scanning = false;
    let activeInput = null;
    let lastScannedCode = "";

    // Enable first row at the start
    enableRow(0);

    document.addEventListener("input", function (e) {
        if (e.target.classList.contains("scan-box-no") && e.target.id.startsWith("timeEnd")) {
            let currentRow = e.target.closest("tr");
            let timeEndValue = e.target.value.trim();

            if (timeEndValue !== "") {
                let nextRow = currentRow.nextElementSibling;
                if (nextRow) {
                    enableRow([...tableBody.querySelectorAll("tr")].indexOf(nextRow));
                }
            }
        }
    });

    function enableRow(index) {
        let tableRows = tableBody.querySelectorAll("tr"); // Ensure updated row list
        if (tableRows[index]) {
            let inputs = tableRows[index].querySelectorAll("input");
            inputs.forEach(input => {
                if (!input.hasAttribute("readonly")) { // Prevent enabling readonly fields
                    input.disabled = false;
                }
            });
            console.log(`⚡ Enabling row ${index + 1}`);
        }
    }

    document.querySelectorAll(".scan-box-no").forEach(input => {
        input.addEventListener("click", function () {
            activeInput = this;
            startScanning();
        });
    });

    function startScanning() {
        scannerModal.show();
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
            .then(function (stream) {
                video.srcObject = stream;
                video.setAttribute("playsinline", true);
                video.play();
                scanning = true;
                scanQRCode();
            })
            .catch(function (err) {
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
                if (scannedText === lastScannedCode) return; // Prevent duplicate scans
                lastScannedCode = scannedText;

                let parts = scannedText.split(/\s+/);
                if (parts.length >= 3) {
                    let boxNo = parts[parts.length - 1]; // Extract last element (e.g., 4025)
                    console.log("📦 Scanned Box No.:", boxNo);

                    let now = new Date();
                    let hours = now.getHours();
                    let minutes = now.getMinutes().toString().padStart(2, '0');
                    let amPm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12 || 12;
                    let currentTime = `${hours}:${minutes} ${amPm}`;

                    let tableRows = tableBody.querySelectorAll("tr");
                    let found = false;
                    let emptyRow = null;

                    tableRows.forEach(row => {
                        let boxNoInput = row.cells[1].querySelector("input");
                        let timeStartInput = row.cells[2].querySelector("input");
                        let timeEndInput = row.cells[3].querySelector("input");

                        if (boxNoInput) {
                            if (boxNoInput.value.trim() === boxNo) {
                                if (timeEndInput.value.trim() === "") {
                                    timeEndInput.value = currentTime;
                                    console.log(`✅ Updated Time End for Box No.: ${boxNo}`);
                                    timeEndInput.disabled = true;

                                    // Enable the next row after filling Time End
                                    let nextRow = row.nextElementSibling;
                                    if (nextRow) enableRow([...tableRows].indexOf(nextRow));
                                }
                                found = true;
                            } else if (boxNoInput.value.trim() === "" && !emptyRow) {
                                emptyRow = row;
                            }
                        }
                    });

                    if (!found) {
                        if (emptyRow) {
                            emptyRow.cells[1].querySelector("input").value = boxNo;
                            emptyRow.cells[2].querySelector("input").value = currentTime;
                            emptyRow.cells[3].querySelector("input").disabled = false;
                            emptyRow.cells[1].querySelector("input").disabled = true;
                            emptyRow.cells[2].querySelector("input").disabled = true;
                            console.log(`➕ Assigned Box No. ${boxNo} to an existing row.`);
                            enableRow([...tableRows].indexOf(emptyRow));
                        } else {
                            let newRow = document.createElement("tr");
                            newRow.innerHTML = `
                                <td><input type="text" value="${tableRows.length + 1}" disabled></td>
                                <td><input type="text" value="${boxNo}" disabled></td>
                                <td><input type="text" value="${currentTime}" disabled></td>
                                <td><input type="text" value="" ></td>
                            `;
                            tableBody.appendChild(newRow);
                            console.log(`➕ Added new row for Box No.: ${boxNo}`);
                            enableRow([...tableBody.querySelectorAll("tr")].length - 1);
                        }
                    }

                    stopScanning();
                } else {
                    alert("⚠️ Invalid QR format. Please scan a valid code.");
                }
            }
        }

        requestAnimationFrame(scanQRCode);
    }

    function stopScanning() {
        scanning = false;
        let tracks = video.srcObject?.getTracks();
        if (tracks) tracks.forEach(track => track.stop());
        setTimeout(() => { lastScannedCode = ""; }, 1000);
        scannerModal.hide();
    }

    document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
    document.getElementById("enterManually").addEventListener("click", function () {
        stopScanning();
        scannerModal.hide();
        if (activeInput) activeInput.focus();
    });
});


</script>


</body>
</html>