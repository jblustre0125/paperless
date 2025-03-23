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
    <link href="../css/dor-form.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">
                    <li class="nav-item">
                        <button type="button" class="btn btn-secondary form-control btn-lg" onclick="goBack()">Back</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="btn btn-primary form-control btn-lg" onclick="submitForm()">Proceed to DOR</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <?php if (!empty($errorPrompt)) : ?>
            <div class="alert alert-danger mt-3" role="alert">
                <?php echo $errorPrompt; ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tab-container">
            <div class="tab-nav">
                <?php for ($i = 1; $i <= 4; $i++) : ?>
                    <button class="tab-button" onclick="openTab(event, 'P<?php echo $i; ?>')">Process <?php echo $i; ?></button>
                <?php endfor; ?>
            </div>

            <!-- Tab Content -->
            <?php for ($i = 1; $i <= 4; $i++) : ?>
                <div id="P<?php echo $i; ?>" class="tab-content" style="display: none;">
                    <table class="table-checkpointA">
                        <thead>
                            <tr>
                                <th colspan="12">A. Required Item and Jig Condition VS Work Instruction</th>
                            </tr>
                            <tr>
                                <th colspan="1">Incharge: Operator/Leader</th>
                                <th colspan="1">Jig No:</th>
                                <th colspan="10">
                                    <input type="number" class="form-control form-control-md" id="cmbJigNo" name="cmbJigNo">
                                </th>
                            </tr>
                            <tr>
                                <th>Checkpoint</th>
                                <th colspan="10">Criteria</th>
                                <?php for ($j = 1; $j <= 4; $j++) : ?>
                                    <th>P<?php echo $j; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tabData as $checkpointName => $rows) : ?>
                                <?php $checkpointCounts = count($rows); ?>
                                <tr>
                                    <td rowspan="<?php echo $checkpointCounts; ?>" class="checkpoint-cell">
                                        <?php echo $rows[0]['SequenceId'] . ". " . $checkpointName; ?>
                                    </td>

                                    <?php foreach ($rows as $index => $row) : ?>
                                        <?php if ($index > 0) echo "<tr>"; ?>

                                        <td colspan="2" class="criteria-cell"><?php echo $row['CriteriaGood'] ?? ''; ?></td>
                                        <td class="criteria-cell"><?php echo $row['CriteriaNotGood'] ?? ''; ?></td>

                                        <?php for ($j = 1; $j <= 4; $j++) : ?>
                                            <td>
                                                <div class='process-radio'>
                                                    <input type='radio' name='P<?php echo $j . "_" . $row['SequenceId']; ?>' value='OK'> OK
                                                    <input type='radio' name='P<?php echo $j . "_" . $row['SequenceId']; ?>' value='NG'> NG
                                                </div>
                                            </td>
                                        <?php endfor; ?>

                                        <?php if ($index == $checkpointCounts - 1) : ?>
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
        // Set default tab
        let firstTab = document.querySelector(".tab-content");
        if (firstTab) firstTab.style.display = "block";

        let firstTabButton = document.querySelector(".tab-button");
        if (firstTabButton) firstTabButton.classList.add("active");
    });

    function openTab(evt, processName) {
        let tabcontent = document.getElementsByClassName("tab-content");
        for (let i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        let tabbuttons = document.getElementsByClassName("tab-button");
        for (let i = 0; i < tabbuttons.length; i++) {
            tabbuttons[i].classList.remove("active");
        }
        document.getElementById(processName).style.display = "block";
        evt.currentTarget.classList.add("active");
    }

    function goBack() {
        window.history.back();
    }

    function submitForm() {
        document.getElementById("myForm").submit();
    }
</script>

<script src="../js/bootstrap.bundle.min.js"></script>

</html>