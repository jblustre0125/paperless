<?php
$title = "DOR";
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

  // Regular form submit handler (btnProceed)
  if (empty($response['errors'])) {
    if (isset($_POST['btnProceed'])) {
      $recordId = $_SESSION['dorRecordId'] ?? 0;
      if ($recordId > 0) {
        foreach ($_POST as $key => $value) {
          // if (preg_match('/^Process(\d+)_(\d+)$/', $key, $matches)) {
          //   $processIndex = (int)$matches[1];
          //   $sequenceId = (int)$matches[2];

          //   $meta = $_POST['meta'][$key] ?? [];
          //   $checkpointId = $meta['checkpointId'] ?? null;
          //   $employeeCode = $_POST["userCode{$processIndex}"] ?? '';

          //   if ($checkpointId && $employeeCode !== '') {
          //     $insSp = "EXEC InsAtoDorCheckpointDefinition @RecordId=?, @ProcessIndex=?, @EmployeeCode=?, @CheckpointId=?, @CheckpointResponse=?";
          //     $db1->execute($insSp, [
          //       $recordId,
          //       $processIndex,
          //       $employeeCode,
          //       $checkpointId,
          //       $value
          //     ]);
          //   }
          // }
        }
      }

      $response['success'] = true;
      $response['redirectUrl'] = "dor-home.php";
    } else {
      $response['success'] = false;
      $response['errors'][] = "Error.";
    }
  }

  echo json_encode($response);
  exit;
}

// Check if required session variables exist
if (!isset($_SESSION['dorTypeId']) || !isset($_SESSION['dorModelId'])) {
  header("Location: dor-home.php");
  exit;
}

$employeeCode = isset($_SESSION['employeeCode']) ? $_SESSION['employeeCode'] : 'Unknown Operator';

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
  <title><?php echo htmlspecialchars($title ?? ''); ?></title>
  <link href="../css/bootstrap.min.css" rel="stylesheet">
  <link href="../css/bootstrap-icons.css" rel="stylesheet">
  <link href="../css/dor-dor.css" rel="stylesheet">
  <link href="../css/dor-navbar.css" rel="stylesheet">
  <link href="../css/dor-pip-viewer.css" rel="stylesheet">
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
          <button type="submit" class="btn btn-primary btn-lg nav-btn-group" id="btnProceed" name="btnProceed">
            <span class="short-label">Save DOR</span>
            <span class="long-label">Save DOR</span>
          </button>
        </div>
      </div>
    </div>
  </nav>

  <form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
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
              <th>Time Start</th>
              <th>Time End</th>
              <th>Operator</th>
              <th>Downtime</th>
              <th>Action</th>
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
              <tr data-row-id="<?= $i ?>">
                <td class="clickable-row text-center align-middle" style="cursor: pointer; width: 60px;">
                  <?= $i ?>
                  <i class="bi bi-qr-code-scan ms-1"></i>
                </td>
                <td class="box-no-column">
                  <input type="text" class="form-control scan-box-no box-no-input text-center" id="boxNo<?= $i ?>" name="boxNo<?= $i ?>" disabled>
                  <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                  <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
                </td>
                <td class="time-column">
                  <input type="text" class="form-control text-center time-input" id="timeStart<?= $i ?>">
                </td>
                <td class="time-column">
                  <input type="text" class="form-control scan-box-no text-center time-input" id="timeEnd<?= $i ?>" disabled>
                </td>
                <td class="align-middle text-center">
                  <div class="action-container">
                    <button type="button" class="btn btn-outline-primary btn-sm btn-operator" id="operator<?= $i ?>">
                      <i class="bi bi-person-plus"></i> Manage Operators
                    </button>
                    <div class="operator-codes" id="operatorList<?= $i ?>">
                      <?php if ($i % 2 === 0): // Even rows show 4 codes 
                      ?>
                        <small class="badge bg-light text-dark border"><?= $employeeCode ?></small>
                      <?php else: // Odd rows show 3 codes 
                      ?>
                        <small class="badge bg-light text-dark border"><?= $employeeCode ?></small>
                      <?php endif; ?>
                    </div>
                  </div>
                  <input type="hidden" id="operators<?= $i ?>" name="operators<?= $i ?>" value="<?= $employeeCode ?>">
                </td>
                <td class="align-middle text-center">
                  <div class="action-container">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="downtime<?= $i ?>">
                      <i class="bi bi-clock-history"></i> Manage Downtime
                    </button>
                    <div class="downtime-info small text-muted" id="downtimeInfo<?= $i ?>"></div>
                  </div>
                </td>
                <td class="align-middle text-center">
                  <button type="button" class="btn btn-outline-danger btn-sm delete-row" data-row-id="<?= $i ?>" title="Delete Row">
                    <span style="font-size: 1.2rem; font-weight: bold;">×</span>
                  </button>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>

  <!-- <button type="button" class="btn btn-primary floating-legends-btn drawer-style" data-bs-toggle="modal" data-bs-target="#legendsModal">
    <i class="bi bi-info-circle"></i>
  </button>

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
  </div> -->

  <!-- QR Code Scanner Modal -->
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
  </form>

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

  <!-- To fix `Uncaught ReferenceError: Modal is not defined` -->
  <script src="../js/bootstrap.bundle.min.js"></script>

  <script src="../js/jsQR.min.js"></script>
  <script>
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
      const video = document.getElementById("qr-video");
      let canvas = document.createElement("canvas");
      let ctx = canvas.getContext("2d", {
        willReadFrequently: true
      });
      let scanning = false;
      let activeRowId = null;

      function getCameraConstraints() {
        return {
          video: {
            facingMode: {
              ideal: "environment"
            }
          }
        };
      }

      function startScanning(rowId) {
        activeRowId = rowId;
        scannerModal.show();
        const constraints = getCameraConstraints();

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
            const modelName = <?php echo json_encode($_SESSION['modelName'] ?? ''); ?>;

            if (parts.length === 1) {
              // Single part - Check if it matches model name
              if (parts[0] === modelName) {
                // If model name matches and time start exists, set time end
                if (activeRowId) {
                  const timeStartInput = document.getElementById(`timeStart${activeRowId}`);
                  const timeEndInput = document.getElementById(`timeEnd${activeRowId}`);

                  if (timeStartInput && timeStartInput.value) {
                    // Set current time in 24-hour format for time end
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    timeEndInput.value = `${hours}:${minutes}:${seconds}`;
                    timeEndInput.dispatchEvent(new Event('change'));
                    indicateScanSuccess();
                  } else {
                    showErrorModal("Please set time start first");
                  }
                }
                stopScanning();
                return;
              }

              // If not matching model name, use as box number
              if (activeRowId) {
                const boxNoInput = document.getElementById(`boxNo${activeRowId}`);
                const timeStartInput = document.getElementById(`timeStart${activeRowId}`);

                if (boxNoInput) {
                  boxNoInput.value = parts[0];
                  boxNoInput.dispatchEvent(new Event('change'));

                  // Set current time in 24-hour format for time start
                  const now = new Date();
                  const hours = String(now.getHours()).padStart(2, '0');
                  const minutes = String(now.getMinutes()).padStart(2, '0');
                  const seconds = String(now.getSeconds()).padStart(2, '0');
                  timeStartInput.value = `${hours}:${minutes}:${seconds}`;
                  timeStartInput.dispatchEvent(new Event('change'));

                  indicateScanSuccess();
                }
              }
              stopScanning();
            } else if (parts.length === 3) {
              // Three parts - Model, Quantity, Lot/Box Number
              const scannedModel = parts[0];
              const qty = parts[1];
              const lotNumber = parts[2];

              if (scannedModel === modelName) {
                if (activeRowId) {
                  const boxNoInput = document.getElementById(`boxNo${activeRowId}`);
                  const timeStartInput = document.getElementById(`timeStart${activeRowId}`);
                  const timeEndInput = document.getElementById(`timeEnd${activeRowId}`);

                  if (boxNoInput && timeStartInput && timeEndInput) {
                    // Set box number
                    boxNoInput.value = lotNumber;
                    boxNoInput.dispatchEvent(new Event('change'));

                    // Check if time start already has a value
                    if (timeStartInput.value) {
                      // Set current time in 24-hour format for time end
                      const now = new Date();
                      const hours = String(now.getHours()).padStart(2, '0');
                      const minutes = String(now.getMinutes()).padStart(2, '0');
                      const seconds = String(now.getSeconds()).padStart(2, '0');
                      timeEndInput.value = `${hours}:${minutes}:${seconds}`;
                      timeEndInput.dispatchEvent(new Event('change'));
                    } else {
                      // Set current time in 24-hour format for time start
                      const now = new Date();
                      const hours = String(now.getHours()).padStart(2, '0');
                      const minutes = String(now.getMinutes()).padStart(2, '0');
                      const seconds = String(now.getSeconds()).padStart(2, '0');
                      timeStartInput.value = `${hours}:${minutes}:${seconds}`;
                      timeStartInput.dispatchEvent(new Event('change'));
                    }

                    indicateScanSuccess();
                  }
                }
                stopScanning();
              } else {
                showErrorModal("Invalid QR code: Model name mismatch");
                stopScanning();
              }
            } else {
              showErrorModal("Invalid QR code format");
              stopScanning();
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
        activeRowId = null;
      }

      // Add click event listeners to row numbers
      document.querySelectorAll('.clickable-row').forEach(cell => {
        cell.addEventListener('click', async function() {
          const rowId = this.closest('tr').getAttribute('data-row-id');
          const accessGranted = await navigator.mediaDevices.getUserMedia({
            video: true
          }).then(stream => {
            stream.getTracks().forEach(track => track.stop());
            return true;
          }).catch(() => false);

          if (accessGranted) {
            startScanning(rowId);
          } else {
            alert("Camera access denied");
          }
        });
      });

      document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
    });
  </script>

  <script>
    let isMinimized = false;
    const workInstructFile = <?php echo json_encode($workInstructFile); ?>;
    const preCardFile = <?php echo json_encode($preCardFile); ?>;

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
        if (preCardFile !== "") {
          openPiPViewer(preCardFile, 'pdf');
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

        // Only run validation if btnProceed triggered this
        if (!clickedButton || clickedButton.id !== "btnProceed") {
          return; // Skip validation if other buttons are clicked (e.g., Drawing, WI)
        }

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
      window.location.href = "dor-refresh.php";
    }

    // Form submission handling
    const form = document.querySelector("#myForm");
    const errorModal = new bootstrap.Modal(document.getElementById("errorModal"));
    const modalErrorMessage = document.getElementById("modalErrorMessage");

    let clickedButton = null;

    // Track which submit button was clicked
    document.querySelectorAll("button[type='submit']").forEach(button => {
      button.addEventListener("click", function(e) {
        e.preventDefault(); // Prevent default form submission
        clickedButton = this;

        // If it's the proceed button, trigger form validation and submission
        if (this.id === "btnProceed") {
          form.dispatchEvent(new Event('submit'));
        }
      });
    });

    // Add delete row functionality
    document.querySelectorAll('.delete-row').forEach(button => {
      button.addEventListener('click', function() {
        const rowId = this.getAttribute('data-row-id');
        const row = document.querySelector(`tr[data-row-id="${rowId}"]`);

        // Show confirmation dialog
        if (!confirm(`Are you sure you want to clear row ${rowId}?`)) {
          return; // Stop if user cancels
        }

        // Clear all inputs in the row
        row.querySelectorAll('input').forEach(input => {
          input.value = '';
        });

        // Clear operator codes
        const operatorList = document.getElementById(`operatorList${rowId}`);
        if (operatorList) {
          operatorList.innerHTML = '';
        }

        // Clear downtime info
        const downtimeInfo = document.getElementById(`downtimeInfo${rowId}`);
        if (downtimeInfo) {
          downtimeInfo.innerHTML = '';
        }

        // Show success message
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '5';
        toast.innerHTML = `
          <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
              <strong class="me-auto">Success</strong>
              <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
              Row ${rowId} has been cleared.
            </div>
          </div>
        `;
        document.body.appendChild(toast);

        // Remove toast after 3 seconds
        setTimeout(() => {
          toast.remove();
        }, 3000);
      });
    });

    // Function to check if a row is complete
    function isRowComplete(rowId) {
      const boxNo = document.getElementById(`boxNo${rowId}`).value.trim();
      const timeStart = document.getElementById(`timeStart${rowId}`).value.trim();
      const timeEnd = document.getElementById(`timeEnd${rowId}`).value.trim();
      return boxNo && timeStart && timeEnd; // All three fields are required
    }

    // Function to set row active/inactive
    function setRowActive(rowId, active) {
      const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
      const inputs = row.querySelectorAll('input:not([type="hidden"])');
      const buttons = row.querySelectorAll('button');

      if (active) {
        row.classList.remove('row-inactive');
        row.classList.add('row-active');
        inputs.forEach(input => {
          input.disabled = false;
          if (input.id.startsWith('boxNo') || input.id.startsWith('timeStart') || input.id.startsWith('timeEnd')) {
            input.classList.add('required-field');
          }
        });
        buttons.forEach(button => button.disabled = false);
      } else {
        row.classList.remove('row-active');
        row.classList.add('row-inactive');
        inputs.forEach(input => {
          input.disabled = true;
          if (input.id.startsWith('boxNo') || input.id.startsWith('timeStart') || input.id.startsWith('timeEnd')) {
            input.classList.add('required-field');
          }
        });
        buttons.forEach(button => button.disabled = true);
      }
    }

    // Function to check and update row states
    function updateRowStates() {
      for (let i = 2; i <= 20; i++) {
        const prevRowComplete = isRowComplete(i - 1);
        setRowActive(i, prevRowComplete);
      }
    }

    // Add event listeners to required fields
    for (let i = 1; i <= 20; i++) {
      const boxNoInput = document.getElementById(`boxNo${i}`);
      const timeStartInput = document.getElementById(`timeStart${i}`);
      const timeEndInput = document.getElementById(`timeEnd${i}`);

      // Add change event listeners
      [boxNoInput, timeStartInput, timeEndInput].forEach(input => {
        if (input) {
          input.addEventListener('change', updateRowStates);
          // Add required field styling
          input.classList.add('required-field');
        }
      });
    }

    // Initialize row states
    setRowActive(1, true); // First row is always active
    updateRowStates();
  </script>

  <form id="deleteDorForm" method="POST" action="dor-form.php">
    <input type="hidden" name="action" value="delete_dor">
  </form>

  <script src="../js/pdf.min.js"></script>
  <script src="../js/pdf.worker.min.js"></script>
  <script src="../js/hammer.min.js"></script>
  <script src="../js/dor-pip-viewer.js"></script>

</body>

</html>