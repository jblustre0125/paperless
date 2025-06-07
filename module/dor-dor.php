<?php
session_start();
ob_start();

require_once "../config/dbop.php";
require_once "../config/method.php";

// Check if required session variables exist
if (!isset($_SESSION['dorTypeId']) || !isset($_SESSION['dorModelId'])) {
  header("Location: dor-home.php");
  exit;
}

$productionCode = isset($_SESSION['productionCode']) ? $_SESSION['productionCode'] : 'Unknown Operator';

// Get the file paths
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
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>DOR System</title>
  <link href="../css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/dor-dor.css" rel="stylesheet">
  <link href="../css/dor-navbar.css" rel="stylesheet">
  <link href="../css/dor-pip-viewer.css" rel="stylesheet">
  <script src="../js/bootstrap.bundle.min.js"></script>
  <script src="../js/jsQR.min.js"></script>
  <script src="../js/hammer.min.js"></script>
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
          <button type="submit" class="btn btn-primary btn-lg nav-btn-group" id="btnProceed" name="btnProceed">Save</button>
        </div>
      </div>
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

  <!-- Floating Legends Button -->
  <button type="button" class="btn btn-primary floating-legends-btn drawer-style" data-bs-toggle="modal" data-bs-target="#legendsModal">
    <i class="bi bi-info-circle-fill"></i>
  </button>

  <!-- Legends Modal -->
  <div class="modal fade" id="legendsModal" tabindex="-1" aria-labelledby="legendsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header" id="legendsModalHeader">
          <h5 class="modal-title" id="legendsModalLabel">LEGENDS</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="legendsModalBody">
          <div class="legend-content">
            <div class="legend-row">
              <div class="legend-group">
                <div><strong>A. Jig Alarm</strong></div>
                <div><span class="legend-code">TS</span> - Tape Sensor</div>
                <div><span class="legend-code">TC</span> - Toggle Clamp</div>
                <div><span class="legend-code">W1</span> - Wire 1 LED not ON</div>
                <div><span class="legend-code">W2</span> - Wire 2 LED not ON</div>
                <div><span class="legend-code">ST</span> - Stop tape trigger</div>
                <div><span class="legend-code">CT</span> - Connector trigger</div>
              </div>
              <div class="legend-group">
                <div><strong>B. Abnormality</strong></div>
                <div><span class="legend-code">WS</span> - Wrong Setting</div>
                <div><span class="legend-code">CC</span> - Counter complete</div>
                <div><span class="legend-code">DP</span> - Drop Parts</div>
                <div><span class="legend-code">E</span> - Excess parts</div>
                <div><span class="legend-code">L</span> - Lacking parts</div>
                <div><span class="legend-code">IN</span> - Incoming NG parts</div>
                <div><span class="legend-code">IP</span> - In process defects</div>
                <div><span class="legend-code">FOC</span> - For confirmation harness</div>
              </div>
              <div class="legend-group">
                <div><strong>C. Remarks</strong></div>
                <div><span class="legend-code">FC</span> - For Continue</div>
                <div><span class="legend-code">C</span> - Continuation</div>
                <div><span class="legend-code">AM</span> - Affected of AM</div>
              </div>
              <div class="legend-group">
                <div><strong>D. Action Taken</strong></div>
                <div><span class="legend-code">RJ</span> - Reset jig counter</div>
                <div><span class="legend-code">RH</span> - Reset jig then recheck Spcs.</div>
                <div><span class="legend-code">RP</span> - Check affected drop parts</div>
                <div><span class="legend-code">RL</span> - Return excess parts</div>
                <div><span class="legend-code">RLS</span> - Return excess then sort</div>
                <div><span class="legend-code">CP</span> - Change part</div>
                <div><span class="legend-code">CPS</span> - Change part then sort</div>
                <div><span class="legend-code">HP</span> - Hold affected part</div>
                <div><span class="legend-code">HB</span> - Hold affected box</div>
                <div><span class="legend-code">HL</span> - Hold affected lot</div>
                <div><span class="legend-code">RC</span> - Rework then check</div>
                <div><span class="legend-code">RM</span> - Request rework check</div>
                <div><span class="legend-code">COG</span> - Confirm as GOOD</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="sticky-dor-bar">
    <div class="container-fluid">
      <div class="sticky-table-header">
        <!-- Remove the old legend section -->
      </div>
    </div>
    <!-- Match EXACTLY the same container structure as the body table -->
    <div class="container-fluid">
      <div class="table-container">
        <table class="table table-bordered align-middle"></table>
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
        </table>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <div class="table-container">
      <table class="table table-bordered align-middle">
        <tbody>
          <?php for ($i = 1; $i <= 20; $i++) { ?>
            <tr>
              <td><?= $i ?></td>
              <td>
                <input type="text" class="form-control scan-box-no" id="boxNo<?= $i ?>" placeholder="Scan QR" <?= $i === 1 ? '' : 'disabled' ?> readyonly>
              </td>
              <td>
                <input type="text" class="form-control" id="timeStart<?= $i ?>" <?= $i === 1 ? '' : 'disabled' ?> readonly>
              </td>
              <td>
                <input type="text" class="form-control scan-box-no time-end" id="timeEnd<?= $i ?>" placeholder="Scan QR" <?= $i === 1 ? '' : 'disabled' ?>>
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
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Required Scripts -->
  <script src="../js/bootstrap.bundle.min.js"></script>
  <script src="../js/jsQR.min.js"></script>
  <script src="../js/pdf.min.js"></script>
  <script src="../js/pdf.worker.min.js"></script>
  <script src="../js/hammer.min.js"></script>
  <script src="../js/dor-pip-viewer.js"></script>

  <script>
    const productionCode = "<?php echo addslashes($productionCode); ?>";
    const workInstructFile = <?php echo json_encode($workInstructFile); ?>;
    const preCardFile = <?php echo json_encode($preCardFile); ?>;
    const drawingFile = <?php echo json_encode($drawingFile); ?>;

    document.addEventListener("DOMContentLoaded", function() {

      // QR Scanner Setup
      const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
      const video = document.getElementById("qr-video");
      let canvas = document.createElement("canvas");
      let ctx = canvas.getContext("2d", {
        willReadFrequently: true
      });
      let scanning = false;
      let activeInput = null;
      let lastScannedCode = "";
      let tableBody = document.querySelector("tbody");

      // Enable first row by default
      enableRow(0);

      // Handle input events for timeEnd fields
      document.addEventListener("input", function(e) {
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

      // Row enabling function
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

      // QR Scanner Functions
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

      function getCurrentTime() {
        const now = new Date();
        const hours = now.getHours() % 12 || 12;
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = now.getHours() >= 12 ? 'PM' : 'AM';
        return `${hours}:${minutes} ${ampm}`;
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
            handleScannedCode(scannedText);
          }
        }
        requestAnimationFrame(scanQRCode);
      }

      function handleScannedCode(scannedText) {
        let parts = scannedText.split(/\s+/);
        if (parts.length >= 3) {
          let boxNo = parts[parts.length - 1];
          let currentTime = getCurrentTime();
          let employeeCode = parts[0]; // Assuming first part is employee code

          if (activeInput.id.startsWith('boxNo')) {
            let row = activeInput.closest('tr');
            let timeStartInput = row.querySelector('input[id^="timeStart"]');
            let operatorInput = row.querySelector('input[disabled]'); // Operator field

            activeInput.value = boxNo;
            if (timeStartInput && !timeStartInput.value) {
              timeStartInput.value = currentTime;
            }
            if (operatorInput) {
              operatorInput.value = employeeCode;
            }
          } else if (activeInput.id.startsWith('timeEnd')) {
            activeInput.value = currentTime;
            let row = activeInput.closest('tr');
            let nextRow = row.nextElementSibling;
            if (nextRow) {
              let inputs = nextRow.querySelectorAll('input');
              inputs.forEach(input => {
                if (!input.hasAttribute('readonly')) {
                  input.disabled = false;
                }
              });
            }
          }
          stopScanning();
        } else {
          alert("⚠️ Invalid QR format. Please scan a valid code.");
        }
      }

      function stopScanning() {
        scanning = false;
        let tracks = video.srcObject?.getTracks();
        if (tracks) tracks.forEach(track => track.stop());
        setTimeout(() => {
          lastScannedCode = "";
        }, 1000);
        scannerModal.hide();
      }

      // Event Listeners
      document.querySelectorAll('.scan-box-no').forEach(input => {
        input.addEventListener("click", async function() {
          if (this.disabled) return;

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
        }, 300);
      });

      // PiP Viewer Button Event Listeners
      document.getElementById("btnDrawing").addEventListener("click", function() {
        if (drawingFile) {
          openPiPViewer(drawingFile, 'image');
        } else {
          showErrorModal("Drawing file is not available.");
        }
      });

      document.getElementById("btnWorkInstruction").addEventListener("click", function() {
        if (workInstructFile) {
          openPiPViewer(workInstructFile, 'pdf');
        } else {
          showErrorModal("Work instruction file is not available.");
        }
      });

      document.getElementById("btnPrepCard").addEventListener("click", function() {
        if (preCardFile) {
          openPiPViewer(preCardFile, 'pdf');
        } else {
          showErrorModal("Preparation card file is not available.");
        }
      });

      // Form submission handling
      const form = document.querySelector("#myForm");
      const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
      const modalErrorMessage = document.getElementById("modalErrorMessage");
      let clickedButton = null;

      // Track which submit button was clicked
      document.querySelectorAll("button[type='submit']").forEach(button => {
        button.addEventListener("click", function(e) {
          e.preventDefault();
          clickedButton = this;

          if (this.id === "btnProceed") {
            form.dispatchEvent(new Event('submit'));
          }
        });
      });

      form.addEventListener("submit", function(e) {
        e.preventDefault();

        // Only run validation if btnProceed triggered this
        if (!clickedButton || clickedButton.id !== "btnProceed") {
          return;
        }

        // Validate all rows have required fields filled
        const tableRows = document.querySelectorAll("tbody tr");
        let errors = [];
        let lastFilledRow = null;

        tableRows.forEach((row, index) => {
          const boxNo = row.querySelector('input[id^="boxNo"]')?.value.trim();
          const timeStart = row.querySelector('input[id^="timeStart"]')?.value.trim();
          const timeEnd = row.querySelector('input[id^="timeEnd"]')?.value.trim();

          if (boxNo || timeStart || timeEnd) {
            lastFilledRow = index;

            if (!boxNo) errors.push(`Row ${index + 1}: Box No. is required`);
            if (!timeStart) errors.push(`Row ${index + 1}: Time Start is required`);
            if (!timeEnd) errors.push(`Row ${index + 1}: Time End is required`);
          }
        });

        if (errors.length > 0) {
          modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
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
          .then(data => {
            if (data.success) {
              if (data.redirectUrl) {
                window.location.href = data.redirectUrl;
              }
            } else {
              modalErrorMessage.innerHTML = "<ul><li>" + (data.errors || ["An error occurred"]).join("</li><li>") + "</li></ul>";
              errorModal.show();
            }
          })
          .catch(error => {
            console.error("Error:", error);
            modalErrorMessage.innerHTML = "<ul><li>An error occurred while saving</li></ul>";
            errorModal.show();
          });
      });

      // QR Scanner Setup
    });

    function showErrorModal(message) {
      const modalErrorMessage = document.getElementById("modalErrorMessage");
      modalErrorMessage.innerText = message;
      const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
      errorModal.show();
    }

    function goBack() {
      window.location.href = "dor-refresh.php";
    }
  </script>
</body>

</html>