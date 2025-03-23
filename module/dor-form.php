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
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid d-flex justify-content-between">
            <button class="btn btn-secondary btn-lg" onclick="goBack()">Back</button>
            <span class="navbar-brand text-center fw-bold d-block">
                A. Required Item and Jig Condition VS Work Instruction
            </span>

            <button class="btn btn-primary btn-lg" onclick="submitForm()">Proceed to DOR</button>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <?php if (!empty($errorPrompt)) : ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $errorPrompt; ?>
            </div>
        <?php endif; ?>

        <div class="tab-container">
            <div class="tab-nav">
                <?php for ($i = 1; $i <= 4; $i++) : ?>
                    <button class="tab-button" onclick="openTab(event, 'Process<?php echo $i; ?>')">Process <?php echo $i; ?></button>
                <?php endfor; ?>
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

    function submitForm() {
        document.getElementById("myForm").submit();
    }
</script>

<script src="../js/bootstrap.bundle.min.js"></script>

</html>