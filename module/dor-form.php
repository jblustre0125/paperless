<?php

$title = "DOR Form";
ob_start(); // start output buffering

require_once "../config/dbop.php";
require_once "../config/method.php";

$db1 = new DbOp(1);

$today = date('Y-m-d');
$errorPrompt = '';

// Fetch checkpoints based on DOR Type
$procA = "EXEC RdAtoDorCheckpointStart @DorTypeId=?";
$resA = $db1->execute($procA, [1], 1);

// Prepare data for the tabs
$tabData = [];
foreach ($resA as $row) {
    $checkpointName = $row['CheckpointName'];
    if (!isset($tabData[$checkpointName])) {
        $tabData[$checkpointName] = [];
    }
    $tabData[$checkpointName][] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Default Title'; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 1.2rem;
            padding-top: 10px;
        }

        .tab-container {
            margin-top: 0;
        }

        .table-checkpointA th {
            text-align: center;
        }

        .table-checkpointA th,
        .table-checkpointA td {
            vertical-align: middle;
            padding: 8px;
        }

        .checkpoint-cell {
            text-align: left !important;
            white-space: normal;
            word-wrap: break-word;
        }

        .criteria-cell {
            text-align: center;
        }

        .selection-cell {
            width: 35%;
            /* Increase width for better touch accessibility */
        }

        .process-radio {
            display: flex;
            justify-content: space-evenly;
            /* Space out the radio buttons */
            gap: 20px;
            /* Increase spacing for touch-friendly selection */
        }

        .process-radio label {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .process-radio input {
            transform: scale(1.5);
            /* Make radio buttons larger */
        }

        .tab-nav {
            display: flex;
            justify-content: center;
            gap: 20px;
            /* Reduce space between buttons */
            flex-wrap: wrap;
            /* Allows wrapping on smaller screens */
            margin-bottom: 10px;
        }

        .tab-button {
            font-size: 1.2rem;
            /* Reduce font size slightly */
            padding: 10px 20px;
            /* Adjust padding */
            min-width: 120px;
            /* Reduce minimum width */
            text-align: center;
            flex: 1;
            /* Makes buttons evenly distribute space */
            max-width: 200px;
            /* Prevents overly wide buttons */
        }

        .tab-button.active {
            background-color: #007bff;
            color: white;
        }

        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
            /* Ensures it stays on top */
            background-color: #f8f9fa;
            /* Bootstrap light background */
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            padding-top: 80px;
            /* Added to prevent content overlap */
        }

        .navbar-brand {
            white-space: normal !important;
            /* Allow text wrapping */
            word-wrap: break-word;
            max-width: 80%;
            /* Limit width to prevent overflow */
            text-align: center;
            /* Center align */
            font-size: 1.2rem;
            /* Adjust font size for responsiveness */
        }
    </style>

    <style>
        /* Floating Window Styling */
        .floating-window {
            display: none;
            position: absolute;
            width: 500px;
            height: auto;
            background: white;
            border: 2px solid #555;
            box-shadow: 3px 3px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            top: 50px;
            left: 50px;
        }

        /* Header for Dragging */
        .floating-header {
            padding: 10px;
            background: #007bff;
            color: white;
            cursor: move;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Close Button */
        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            color: white;
            cursor: pointer;
        }

        .floating-body {
            padding: 10px;
            text-align: center;
        }

        .floating-body img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <button class="btn btn-secondary btn-md" onclick="goBack()">Back</button>
            <div class="d-flex flex-column align-items-center">
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-secondary btn-lg" id="btnDrawing" onclick="showDrawing()">Drawing</button>
                    <button class="btn btn-secondary btn-md" id="btnDrawing">Drawing</button>
                    <button class="btn btn-secondary btn-md">Work Instructions</button>
                    <button class="btn btn-secondary btn-md">Guidelines</button>
                    <button class="btn btn-secondary btn-md">Prep Card</button>
                </div>
            </div>
            <<<<<<< Updated upstream
                <button class="btn btn-primary btn-lg" onclick="GoDOR()">Proceed to DOR</button>
                =======
                <button class="btn btn-primary btn-md" onclick="submitForm()">Proceed to DOR</button>
                >>>>>>> Stashed changes
        </div>
    </nav>

    <div class="container-fluid">
        <?php if (!empty($errorPrompt)) : ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $errorPrompt; ?>
            </div>
        <?php endif; ?>

        <div class="tab-container">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="fw-bold">A. Required Item and Jig Condition VS Work Instruction</h5>
                <div class="tab-nav d-flex">
                    <?php for ($i = 1; $i <= 4; $i++) : ?>
                        <button class="tab-button" onclick="openTab(event, 'Process<?php echo $i; ?>')">Process <?php echo $i; ?></button>
                    <?php endfor; ?>
                </div>
            </div>

            <?php for ($i = 1; $i <= 4; $i++) : ?>
                <div id="Process<?php echo $i; ?>" class="tab-content" style="display: none;">
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
                                        <td class="criteria-cell"> <?php echo $row['CriteriaGood'] ?? ''; ?> </td>
                                        <td class="criteria-cell"> <?php echo $row['CriteriaNotGood'] ?? ''; ?> </td>
                                        <td class="selection-cell">
                                            <div class='process-radio'>
                                                <label><input type='radio' name='Process<?php echo $i . "_" . $row['SequenceId']; ?>' value='OK'> OK</label>
                                                <label><input type='radio' name='Process<?php echo $i . "_" . $row['SequenceId']; ?>' value='NG'> NG</label>
                                                <label><input type='radio' name='Process<?php echo $i . "_" . $row['SequenceId']; ?>' value='NA'> NA</label>
                                            </div>
                                        </td>
                                        <?php if ($index == count($rows) - 1) : ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Floating Window for Drawing -->
    <div id="drawingWindow" class="floating-window">
        <div id="drawingHeader" class="floating-header">
            <span>Drawing</span>
            <button onclick="closeDrawing()" class="close-btn">&times;</button>
        </div>
        <div class="floating-body">
            <img id="drawingImage" src="" class="img-fluid" alt="Drawing Image">
        </div>
    </div>
</body>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let storedTab = sessionStorage.getItem("activeTab");
        if (storedTab) {
            document.getElementById(storedTab).style.display = "block";
            document.querySelector(`[onclick="openTab(event, '${storedTab}')"]`).classList.add("active");
        } else {
            document.querySelector(".tab-content").style.display = "block";
        }
    });

    function openTab(evt, processName) {
        document.querySelectorAll(".tab-content").forEach(el => el.style.display = "none");
        document.querySelectorAll(".tab-button").forEach(el => el.classList.remove("active"));
        document.getElementById(processName).style.display = "block";
        evt.currentTarget.classList.add("active");
        sessionStorage.setItem("activeTab", processName);
    }

    function goBack() {
        window.location.href = "dor.php";
    }

    function GoDOR() {
        window.location.href = "add-mntform.php";
    }

    function submitForm() {
        document.getElementById("myForm").submit();
    }
</script>

<script>
    function showDrawing2() {
        var win = window.open('', 'win', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=no,resizable=no,width=520,height=400,left=350,top=100');

        var img = win.document.createElement('img');
        img.src = '../img/drawings/pre-assy/7L0030-7024.png';

        var button = win.document.createElement('a');
        button.href = 'javascript:window.close()';
        button.innerHTML = 'Close';

        win.document.body.appendChild(img);
        win.document.body.appendChild(label);
        win.document.body.appendChild(button);
    }

    function showDrawing() {
        const dorTypeId = $_SESSION['dorTypeId']; // Change dynamically based on the selected DOR type

        fetch(`get_drawing.php?dorTypeId=${dorTypeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.drawing) {
                    document.getElementById("drawingImage").src = data.drawing;
                    document.getElementById("drawingWindow").style.display = "block";
                } else {
                    alert(data.error);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while fetching the drawing.");
            });
    }

    // Close the floating window
    function closeDrawing() {
        document.getElementById("drawingWindow").style.display = "none";
    }

    // Make the floating window draggable
    dragElement(document.getElementById("drawingWindow"));

    function dragElement(el) {
        let pos1 = 0,
            pos2 = 0,
            pos3 = 0,
            pos4 = 0;
        const header = document.getElementById("drawingHeader");

        if (header) {
            header.onmousedown = dragMouseDown;
        }

        function dragMouseDown(e) {
            e.preventDefault();
            pos3 = e.clientX;
            pos4 = e.clientY;
            document.onmouseup = closeDragElement;
            document.onmousemove = elementDrag;
        }

        function elementDrag(e) {
            e.preventDefault();
            pos1 = pos3 - e.clientX;
            pos2 = pos4 - e.clientY;
            pos3 = e.clientX;
            pos4 = e.clientY;
            el.style.top = (el.offsetTop - pos2) + "px";
            el.style.left = (el.offsetLeft - pos1) + "px";
        }

        function closeDragElement() {
            document.onmouseup = null;
            document.onmousemove = null;
        }
    }
</script>

<script>
    const videoElement =
        document.querySelector('video');
    const enterPiPButton =
        document.getElementById('btnDrawing');
    const exitPiPButton =
        document.getElementById('btnExitDrawing');

    // Check if PiP is supported in the browser
    if (videoElement && 'pictureInPictureEnabled' in document) {
        enterPiPButton.addEventListener('click', enterPiP);
        exitPiPButton.addEventListener('click', exitPiP);

        async function enterPiP() {
            try {
                // Request PiP mode
                await videoElement.requestPictureInPicture();

                // Hide the "Enter PiP" button and show the 
                // "Exit PiP" button
                enterPiPButton.classList.add('hidden');
                exitPiPButton.classList.remove('hidden');
            } catch (error) {
                console.error('Error entering PiP:', error);
            }
        }

        async function exitPiP() {
            try {
                // Exit PiP mode
                await document.exitPictureInPicture();

                // Hide the "Exit PiP" button and show the 
                // "Enter PiP" button
                exitPiPButton.classList.add('hidden');
                enterPiPButton.classList.remove('hidden');
            } catch (error) {
                console.error('Error exiting PiP:', error);
            }
        }
    } else {
        console.error('PiP is not supported in this browser.');
        enterPiPButton.style.display = 'none';
    }
</script>

<script src="../js/bootstrap.bundle.min.js"></script>

</html>