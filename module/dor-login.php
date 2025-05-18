<?php require_once '../admin/authenticate.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="../css/bootstrap.min.css" />
    <link rel="stylesheet" href="../css/index.css" />
</head>

<body>
    <div class="main-content">
        <div class="card login-card">
            <div class="card-header text-center">
                <h2 class="mb-0">DOR System</h2>
            </div>
            <div class="card-body">
                <form id="myForm" action="" method="POST">
                    <div class="mb-4">
                        <label for="productionCode" class="form-label">Employee ID</label>
                        <input type="text" class="form-control form-control-lg" id="productionCode" name="txtProductionCode" required data-scan placeholder="Tap to scan ID" value="2410-016">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" name="btnlogin">Login</button>
                    </div>
                </form>

                <?php if ($errorPrompt) : ?>
                    <div class="alert alert-danger mt-3" role="alert">
                        <?php echo $errorPrompt; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once '../config/footer.php'; ?>
    <script src="../js/bootstrap.bundle.min.js"></script>

    <!-- QR Code Scanner Modal -->
    <div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scan Employee ID</h5>
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

    <script src="../js/jsQR.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const scannerModal = new bootstrap.Modal(document.getElementById("qrScannerModal"));
            const video = document.getElementById("qr-video");
            const canvas = document.createElement("canvas");
            const ctx = canvas.getContext("2d", {
                willReadFrequently: true
            });
            let scanning = false;
            let activeInput = null;

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
                const constraints = getCameraConstraints();

                navigator.mediaDevices.getUserMedia(constraints)
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
                video.play().then(() => scanQRCode());
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
                        if (activeInput) activeInput.value = scannedText;
                        stopScanning();
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

            document.querySelectorAll("input[data-scan]").forEach(input => {
                input.addEventListener("click", async function() {
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
                        alert("Camera access denied.");
                    }
                });
            });

            document.getElementById("enterManually").addEventListener("click", () => {
                stopScanning();
                setTimeout(() => {
                    if (activeInput) activeInput.focus();
                }, 300);
            });

            document.getElementById("qrScannerModal").addEventListener("hidden.bs.modal", stopScanning);
        });
    </script>

</body>

</html>