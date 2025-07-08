<?php
session_start();
$errorPrompt = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']); // Clear after showing
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>DOR Leader Login</title>
    <link rel="icon" type="image/png" href="../../img/dor-1024.png">
    <link rel="stylesheet" href="../../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../../css/index.css" />
    <link rel="manifest" href="../../manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
</head>

<body>
    <div class="main-content">
        <div class="card login-card">
            <div class="card-header bg-primary text-center">
                <h2 class="mb-0 text-white">DOR System</h2>
            </div>
            <div class="card-body">
                <form id="myForm" method="POST" action="../controller/dor-leader-login.php">
                    <div class="mb-4">
                        <label for="codeInput" class="form-label">Production Code</label>
                        <div class="input-group">
                            <input type="text" name="production_code" id="codeInput" class="form-control py-2" placeholder="Enter your production code">
                            <button type="button" class="btn btn-outline-secondary" id="scanToggleBtn">
                                <i class="bi bi-upc-scan"></i> Scan
                            </button>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" name="btnLogin" id="btnLogin">Login</button>
                    </div>
                </form>

                <?php if (!empty($errorPrompt)) : ?>
                    <div class="alert alert-danger mt-3" role="alert">
                        <?= htmlspecialchars($errorPrompt) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once '../../config/footer.php'; ?>
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script src="../../js/jsQR.min.js"></script>

    <!-- QR Code Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scan SA Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="qr-video" style="width: 100%;" autoplay muted playsinline></video>
                    <p class="text-muted mt-2">Align the QR code within the frame.</p>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" id="enterManually">Enter Manually</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
            const video = document.getElementById("qr-video");
            const canvas = document.createElement("canvas");
            const ctx = canvas.getContext("2d", {
                willReadFrequently: true
            });
            let scanning = false;
            let activeInput = null;

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
                    .catch(() => {
                        navigator.mediaDevices.getUserMedia({
                                video: {
                                    facingMode: "user"
                                }
                            })
                            .then(setupVideoStream)
                            .catch(() => alert("Camera access denied or not available."));
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
                        const code = scannedText.split(" ")[0];

                        if (code && activeInput) {
                            activeInput.value = code;
                            stopScanning();

                            // Trigger login
                            setTimeout(() => {
                                document.getElementById("btnLogin").click();
                            }, 100);
                        }
                    }
                }
                requestAnimationFrame(scanQRCode);
            }

            function stopScanning() {
                scanning = false;
                const tracks = video.srcObject?.getTracks();
                if (tracks) tracks.forEach(track => track.stop());
                scannerModal.hide();
            }

            document.getElementById("scanToggleBtn").addEventListener("click", async () => {
                const granted = await navigator.mediaDevices.getUserMedia({
                        video: true
                    })
                    .then(stream => {
                        stream.getTracks().forEach(track => track.stop());
                        return true;
                    })
                    .catch(() => false);

                if (granted) {
                    activeInput = document.getElementById("codeInput");
                    startScanning();
                } else {
                    alert("Camera access is denied.");
                }
            });

            document.getElementById("enterManually").addEventListener("click", () => {
                stopScanning();
                setTimeout(() => document.getElementById("codeInput").focus(), 300);
            });

            document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
        });
    </script>
</body>

</html>