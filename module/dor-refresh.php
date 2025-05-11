<?php
$response = ['success' => false, 'errors' => []];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    if (empty($response['errors'])) {
        if (isset($_POST['btnProceed'])) {
            $response['success'] = true;
            $response['redirectUrl'] = "dor-refresh.php";
        } else {
            $response['success'] = false;
            $response['errors'][] = "Sample error.";
        }
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Refreshment Checkpoint'); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/dor-form.css" rel="stylesheet">
</head>

<body>
    <?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $title = "DOR Form A";
    session_start(); // Start the session
    ob_start(); // start output buffering

    require_once "../config/dbop.php";
    require_once "../config/method.php";

    $db1 = new DbOp(1);

    $errorPrompt = '';

    // Fetch checkpoints based on DOR Type
    $procA = "EXEC RdAtoDorCheckpointRefresh";
    $resA = $db1->execute($procA, [], 1);

    // Prepare data for the tabs
    $tabData = [];
    foreach ($resA as $row) {
        $checkpointName = $row['CheckpointName'];
        if (!isset($tabData[$checkpointName])) {
            $tabData[$checkpointName] = [];
        }
        $tabData[$checkpointName][] = $row;
    }

    $drawingFile = getDrawing($_SESSION["dorModelName"]);
    ?>

    <form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
        <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarContent">
                    <div class="d-flex flex-column flex-lg-row align-items-center justify-content-between w-100">
                        <button type="button" class="btn btn-secondary btn-lg mb-2 mb-lg-0" onclick="goBack()" aria-label="Go Back">Back</button>

                        <div class="d-flex flex-wrap gap-3 mt-2 mt-lg-0 justify-content-center">
                            <button type="button" class="btn btn-secondary btn-lg" id="btnDrawing" aria-label="Open Drawing">Drawing</button>
                            <button type="button" class="btn btn-secondary btn-lg" id="btnWorkInstruction" aria-label="Open Work Instruction">Work Instruction</button>
                            <button type="button" class="btn btn-secondary btn-lg" id="btnGuideline" aria-label="Open Guideline">Guideline</button>
                            <button type="button" class="btn btn-secondary btn-lg" id="btnPrepCard" aria-label="Open Preparation Card">Preparation Card</button>
                        </div>

                        <button class="btn btn-primary btn-lg mt-2 mt-lg-0" type="submit" id="btnProceed" name="btnProceed" aria-label="Proceed to DOR">Proceed to DOR</button>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <?php if (!empty($errorPrompt)) : ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $errorPrompt; ?>
                </div>
            <?php endif; ?>

            <div class="tab-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0" style="font-size: 1rem; max-width: 60%; word-wrap: break-word;">
                        Refreshment Checkpoint
                    </h6>
                </div>

                <table class="table-checkpointA table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Checkpoint</th>
                            <th colspan="2">Criteria</th>
                            <th class="col-auto text-nowrap">Selection</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tabData as $checkpointName => $rows) : ?>
                            <tr>
                                <td rowspan="<?php echo count($rows); ?>" class="checkpoint-cell">
                                    <?php echo $rows[0]['SequenceId'] . ". " . $checkpointName; ?>
                                </td>
                                <?php foreach ($rows as $index => $row) : ?>
                                    <?php if ($index > 0) echo "<tr>"; ?>

                                    <?php
                                    $criteriaTypeId = $row['CheckpointTypeId'];
                                    $criteriaGood = $row['CriteriaGood'] ?? '';
                                    $criteriaNotGood = $row['CriteriaNotGood'] ?? '';
                                    ?>

                                    <?php if (empty($criteriaNotGood)) : ?>
                                        <td class="criteria-cell" colspan="2"><?php echo $criteriaGood; ?></td>
                                    <?php else : ?>
                                        <td class="criteria-cell"><?php echo $criteriaGood; ?></td>
                                        <td class="criteria-cell"><?php echo $criteriaNotGood; ?></td>
                                    <?php endif; ?>

                                    <td class="selection-cell">
                                        <?php if ($criteriaTypeId == 1) : ?>
                                            <!-- Type 1: OK, NG, NA -->
                                            <div class='process-radio'>
                                                <label><input type='radio' name='Process<?php echo $i . "_" . $row['SequenceId']; ?>' value='OK'> OK</label>
                                                <label><input type='radio' name='Process<?php echo $i . "_" . $row['SequenceId']; ?>' value='NG'> NG</label>
                                                <label><input type='radio' name='Process<?php echo $i . "_" . $row['SequenceId']; ?>' value='NA'> NA</label>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <?php if ($index == count($rows) - 1) : ?>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="pipModal" role="dialog" aria-labelledby="pipTitle" aria-hidden="true">
            <div id="pipHeader" class="pip-header d-flex justify-content-end align-items-center bg-light px-2 py-1 border-bottom rounded-top">
                <div class="pip-controls">
                    <button type="button" class="btn btn-sm btn-outline-secondary pip-minimize-btn" id="pip-minimize" onclick="minimizePiP()" aria-label="Minimize">_</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary pip-maximize-btn" id="pip-maximize" onclick="maximizePiP()" aria-label="Maximize">+</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary pip-close-btn" id="pip-close" onclick="closePiP()" aria-label="Close">×</button>
                </div>
            </div>
            <div id="pipContent" class="pip-body p-2"></div>
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

    <!-- To fix `Uncaught ReferenceError: Modal is not defined` -->
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        let isMinimized = false;

        document.addEventListener("DOMContentLoaded", function() {
            // Attach event listeners to buttons
            document.getElementById("btnDrawing").addEventListener("click", function() {
                openPiP('image', "<?php echo $drawingFile; ?>");
            });

            document.getElementById("btnWorkInstruction").addEventListener("click", function() {
                openPiP('pdf', '../img/wi/<?php echo $_SESSION['dorModelName']; ?>.pdf');
            });

            document.getElementById("btnGuideline").addEventListener("click", function() {
                openPiP('pdf', '../img/guideline/<?php echo $_SESSION['dorModelName']; ?>.pdf');
            });

            document.getElementById("btnPrepCard").addEventListener("click", function() {
                openPiP('pdf', '../img/prepcard/<?php echo $_SESSION['dorModelName']; ?>.pdf');
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

            let isDragging = false;
            let dragOffsetX = 0;
            let dragOffsetY = 0;

            // Dragging functionality when minimized
            const pipModal = document.getElementById("pipModal");
            const pipHeader = document.getElementById("pipHeader");

            if (pipHeader) {
                pipHeader.addEventListener("mousedown", (e) => {
                    if (!pipModal.classList.contains("minimized")) return; // Only allow dragging in minimized mode
                    isDragging = true;
                    const rect = pipModal.getBoundingClientRect();
                    dragOffsetX = e.clientX - rect.left;
                    dragOffsetY = e.clientY - rect.top;
                    document.body.style.userSelect = "none"; // Prevent text selection
                });
            }

            document.addEventListener("mouseup", () => {
                isDragging = false;
                document.body.style.userSelect = ""; // Allow text selection again
            });

            document.addEventListener("mousemove", (e) => {
                if (!isDragging || !pipModal.classList.contains("minimized")) return;
                pipModal.style.left = `${e.clientX - dragOffsetX}px`;
                pipModal.style.top = `${e.clientY - dragOffsetY}px`;
                pipModal.style.right = "auto";
                pipModal.style.bottom = "auto";
            });

            function openPiP(type, src) {
                const modal = document.getElementById("pipModal"); // Define the modal element
                const content = document.getElementById("pipContent"); // Define the content element

                // Reset modal styles to maximized state
                setModalStyles(modal, {
                    top: "50%",
                    left: "50%",
                    transform: "translate(-50%, -50%)",
                    bottom: "",
                    right: "",
                    width: "80vw",
                    height: "80vh",
                    zIndex: "",
                    position: "fixed"
                });

                // Ensure modal is maximized
                modal.classList.add("maximized");
                modal.classList.remove("minimized");
                modal.style.display = "block";

                content.innerHTML = ""; // Clear previous content
                modal.setAttribute("tabindex", "-1"); // Make modal focusable
                modal.focus(); // Set focus to the modal

                if (type === 'image') {
                    openImageInPiP(content, src);
                } else if (type === 'pdf') {
                    openPdfInPiP(content, src)
                }
            }

            function openImageInPiP(content, src) {
                const img = document.createElement("img");
                img.src = src;
                img.style.maxWidth = "100%";
                img.style.maxHeight = "100%";
                img.style.objectFit = "contain";
                img.style.display = "block";

                // Remove previous content
                content.innerHTML = "";
                content.style.position = "relative"; // Needed for absolute positioning of image
                content.appendChild(img);

                // Optional: support pinch zoom
                const hammer = new Hammer.Manager(img);
                hammer.add(new Hammer.Pinch());

                let currentScale = 1;
                let lastScale = 1;

                hammer.on("pinchstart", function() {
                    lastScale = currentScale;
                });

                hammer.on("pinch", function(ev) {
                    currentScale = lastScale * ev.scale;
                    currentScale = Math.min(Math.max(1, currentScale), 3); // Clamp between 1x and 3x
                    img.style.transform = `scale(${currentScale})`;
                    img.style.transformOrigin = "center center";
                });

                hammer.on("doubletap", function() {
                    currentScale = currentScale === 1 ? 2 : 1;
                    img.style.transform = `scale(${currentScale})`;
                    img.style.transformOrigin = "center center";
                });
            }

            function openPdfInPiP(content, src) {
                const canvas = document.createElement("canvas");
                content.innerHTML = "<div class='text-center w-100'>Loading PDF...</div>";
                content.appendChild(canvas);

                pdfjsLib.getDocument(src).promise.then(pdf => {
                    content.innerHTML = ""; // Remove spinner once loaded

                    for (let i = 1; i <= pdf.numPages; i++) {
                        pdf.getPage(i).then(page => {
                            const viewport = page.getViewport({
                                scale: 1.5
                            });
                            const canvas = document.createElement("canvas");
                            const context = canvas.getContext("2d");

                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            content.appendChild(canvas);

                            page.render({
                                canvasContext: context,
                                viewport: viewport
                            });
                        });
                    }
                });
            }

            form.addEventListener("submit", function(e) {
                e.preventDefault();
                let errors = [];

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

        // Modal-related functions
        function setModalStyles(modal, styles) {
            Object.assign(modal.style, styles);
        }

        function maximizePiP() {
            const modal = document.getElementById("pipModal");

            setModalStyles(modal, {
                top: "50%",
                left: "50%",
                transform: "translate(-50%, -50%)",
                bottom: "",
                right: "",
                width: "",
                height: "",
                zIndex: "",
                position: "fixed"
            });

            modal.classList.add("maximized");
            modal.classList.remove("minimized");
        }

        function minimizePiP() {
            const modal = document.getElementById("pipModal");

            setModalStyles(modal, {
                position: "fixed",
                bottom: "20px",
                right: "20px",
                width: "300px",
                height: "300px",
                top: "auto",
                left: "auto",
                transform: "none",
                zIndex: "9999"
            });

            modal.classList.add("minimized");
            modal.classList.remove("maximized");
        }

        function closePiP() {
            const modal = document.getElementById("pipModal");
            modal.style.display = "none";
            modal.classList.remove("maximized");
            modal.classList.remove("minimized");
            document.body.style.overflow = "";
        }

        function goBack() {
            // Get all inputs inside the tab-container
            const tabContainer = document.querySelector(".tab-container");
            const inputs = tabContainer.querySelectorAll("input[type='text'], input[type='radio']:checked");

            // Check if at least one input is filled
            let isFilled = false;
            inputs.forEach(input => {
                if (input.type === "text" && input.value.trim() !== "") {
                    isFilled = true;
                } else if (input.type === "radio") {
                    isFilled = true;
                }
            });

            // If at least one input is filled, show a confirmation dialog
            if (isFilled) {
                const confirmLeave = confirm("Are you sure you want to go back?");
                if (!confirmLeave) {
                    return; // Stop navigation if the user cancels
                }
            }

            // Navigate back to dor-home.php
            window.location.href = "dor-form.php";
        }

        // Form submission handling
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
    </script>
    <script src="../js/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = "../js/pdf.worker.min.js";
    </script>
</body>

</html>