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
      #imageViewer {
        display: none;
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 320px;
        height: 500px;
        background: #1e1e1e;
        border-radius: 12px;
        box-shadow: 0px 6px 15px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 10px;
      }
      
      #pipControls {
        display: flex;
        justify-content: space-between;
        padding: 5px 10px;
      }
      
      .pip-button {
        background: #dc3545;
        color: white;
        border: none;
        cursor: pointer;
        font-size: 16px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        transition: 0.2s ease-in-out;
      }
      
      .pip-button:hover {
        background: #a71d2a;
      }
      
      #fullImage {
        max-width: 100%;
        max-height: 180px;
        border-bottom: 2px solid #444;
        padding-bottom: 5px;
        border-radius: 8px;
      }
      
      #imageList {
        flex-grow: 1;
        overflow-y: auto;
        padding: 10px;
        max-height: 300px;
      }
      
      .thumbnail {
        width: 100%;
        margin-bottom: 10px;
        cursor: pointer;
        border-radius: 8px;
        transition: transform 0.2s, opacity 0.2s;
        box-shadow: 0px 3px 8px rgba(0, 0, 0, 0.2);
      }
      
      .thumbnail:hover {
        transform: scale(1.05);
        opacity: 0.9;
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
    
<!-- Image Viewer -->
<div id="imageViewer">
  <div id="pipControls">
    <button class="pip-button" onclick="minimizePiP()">−</button>
    <button class="pip-button" onclick="closePiP()">×</button>
  </div>
  <img id="fullImage" src="" alt="Drawing">
  <div id="imageList"></div> <!-- Scrollable list of images -->
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
										<?= $i ?>" placeholder="Scan QR" <?= $i === 1 ? '' : 'disabled' ?> readyonly>
          </td>
          <td>
            <input type="text" class="form-control" id="timeStart <?= $i ?>" <?= $i === 1 ? '' : 'disabled' ?> readonly>
          </td>
          <td>
            <input type="text" class="form-control scan-box-no time-end" id="timeEnd <?= $i ?>" placeholder="Scan QR" <?= $i === 1 ? '' : 'disabled' ?>>
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
        let imageFolder = "drawings/pre-assy/";
        let imageFiles = ["7L0030-7024.png", "7L0031-7024.png", "7L0032-7024.png"];
        let imageList = document.getElementById("imageList");

        imageFiles.forEach(file => {
          let img = document.createElement("img");
          img.src = imageFolder + file;
          img.alt = file;
          img.classList.add("thumbnail");
          img.onclick = function () {
            showImage(imageFolder + file);
          };
          imageList.appendChild(img);
        });
      });
      
      function showDrawing() {
        document.getElementById("imageViewer").style.display = "flex";
      }
      
      function showImage(src) {
        document.getElementById("fullImage").src = src;
        document.getElementById("imageViewer").style.display = "flex";
      }
      
      function closePiP() {
        document.getElementById("imageViewer").style.display = "none";
      }

  document.addEventListener("DOMContentLoaded", function () {
      let tableBody = document.querySelector("tbody");
      let video = document.getElementById("qr-video");
      let canvas = document.createElement("canvas");
      let ctx = canvas.getContext("2d", { willReadFrequently: true });
      let scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
      let scanning = false;
      let activeInput = null;
      let lastScannedCode = "";

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
          let tableRows = tableBody.querySelectorAll("tr");
          if (tableRows[index]) {
              let inputs = tableRows[index].querySelectorAll("input");
              inputs.forEach(input => {
                  if (!input.hasAttribute("readonly")) {
                      input.disabled = false;
                  }
              });
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
                  if (scannedText === lastScannedCode) return;
                  lastScannedCode = scannedText;

                  let parts = scannedText.split(/\s+/);
                  if (parts.length >= 3) {
                      let boxNo = parts[parts.length - 1];

                      let now = new Date();
                      let hours = now.getHours();
                      let minutes = now.getMinutes().toString().padStart(2, '0');
                      let amPm = hours >= 12 ? 'PM' : 'AM';
                      hours = hours % 12 || 12;
                      let currentTime = `${hours}:${minutes} ${amPm}`;

                      let tableRows = tableBody.querySelectorAll("tr");
                      let found = false;
                      let emptyRow = null;
                      let existingBoxWithNoEnd = null;

                      for (let row of tableRows) {
                          let boxNoInput = row.cells[1].querySelector("input");
                          let timeEndInput = row.cells[3].querySelector("input");

                          if (boxNoInput.value.trim() !== "" && timeEndInput.value.trim() === "") {
                              existingBoxWithNoEnd = boxNoInput.value.trim();
                              if (boxNo !== existingBoxWithNoEnd) {
                                  alert(`⚠️ Complete the Time End for Box No. ${existingBoxWithNoEnd} before scanning a new box.`);
                                  return;
                              }
                          }
                      }

                      tableRows.forEach(row => {
                          let boxNoInput = row.cells[1].querySelector("input");
                          let timeStartInput = row.cells[2].querySelector("input");
                          let timeEndInput = row.cells[3].querySelector("input");

                          if (boxNoInput) {
                              if (boxNoInput.value.trim() === boxNo) {
                                  if (timeEndInput.value.trim() === "") {
                                      timeEndInput.value = currentTime;
                                      timeStartInput.disabled = true;
                                      timeEndInput.disabled = true;
                                      boxNoInput.disabled = true;

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
                              enableRow([...tableRows].indexOf(emptyRow));
                          } else {
                              let newRow = document.createElement("tr");
                              newRow.innerHTML = `
                                  <td><input type="text" value="${tableRows.length + 1}" disabled></td>
                                  <td><input type="text" value="${boxNo}" disabled></td>
                                  <td><input type="text" value="${currentTime}" disabled></td>
                                  <td><input type="text" value=""></td>
                              `;
                              tableBody.appendChild(newRow);
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

  var productionCode = "<?php echo isset($_SESSION['productionCode']) ? $_SESSION['productionCode'] : ''; ?>";
</script>


</body>
</html>
  