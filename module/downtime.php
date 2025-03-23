<?php

$title = "Create DOR";
ob_start(); // start output buffering

require "config/dbcon.php";
require "header.php";
require "config/method.php";

$errorPrompt = '';

$month = date('m');
$day = date('d');
$year = date('Y');

$today = $year . '-' . $month . '-' . $day;
?>

<style>
    .shift-group {
        display: flex;
        justify-content: space-around;
    }

    .shift-option {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .model-image {
        height: 500px !important;
        width: 800px !important;
    }

    .tab-container {
        display: flex;
        flex-direction: column;
    }

    .tab-nav {
        display: flex;
        cursor: pointer;
        margin-bottom: 0;
    }

    .tab-nav button {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        background: #f2f2f2;
        border-bottom: none;
        cursor: pointer;
        text-align: center;
        font-weight: bold;
    }

    .tab-nav button.active {
        background: white;
        border-bottom: 1px solid white;
    }

    .tab-content {
        border: 1px solid #ddd;
        padding: 10px;
    }

    .table-checkpointA {
        width: 100%;
        border-collapse: collapse;
    }

    .table-checkpointA th,
    .table-checkpointA td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: center;
        vertical-align: middle;
    }

    .table-checkpointA th {
        background-color: #f2f2f2;
        font-weight: bold;
    }

    .process-radio {
        display: flex;
        justify-content: space-around;
        align-items: center;
    }

    .process-radio label {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .process-radio input[type="radio"] {
        margin-bottom: 5px;
    }

    .number-input {
        width: 100px;
        margin: auto;
        text-align: center;
        padding: 5px;
        font-size: 16px;
    }
</style>

<form id="myForm" class="p-1 row" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
    <div class="container-fluid">
        <h3 class="text-center">Downtime Report</h3>
        <div class="row">
            <div class=" col-sm input-group p-1">
                <span for="dtpDate" class="input-group-text col-2 form-label-lg">Date</span>
                <input type="date" class="form-control form-control-lg" id="dtpDate" name="dtpDate" value="<?php echo isset($_POST["dtpDate"]) ? $_POST["dtpDate"] : $today; ?>" required>
            </div>
            <div class="col-sm input-group p-1 shift-group">
                <span class="input-group-text col-2 form-label-lg">Shift</span>
                <div class="shift-option input-group-text form-control-lg">
                    <input class="form-check-input form-check-lg" type="radio" checked name="rdShift" id="rdBothShift" style="margin-right: 10px;" value="BS" <?php if (isset($_POST["rdShift"])) {
                                                                                                                                                                    echo 'checked="checked"';
                                                                                                                                                                } ?>>
                    <label class="form-check-label" for="rdDayShift">Both Shift</label>
                </div>
                <div class="shift-option input-group-text form-control-lg">
                    <input class="form-check-input form-check-lg" type="radio" checked name="rdShift" id="rdDayShift" style="margin-right: 10px;" value="DS" <?php if (isset($_POST["rdShift"])) {
                                                                                                                                                                    echo 'checked="checked"';
                                                                                                                                                                } ?>>
                    <label class="form-check-label" for="rdDayShift">Day Shift</label>
                </div>
                <div class="shift-option input-group-text">
                    <input class="form-check-input form-check-lg" type="radio" name="rdShift" id="rdNighShift" style="margin-right: 10px;" value="NS" <?php if (isset($_POST["rdShift"])) {
                                                                                                                                                            echo 'checked="checked"';
                                                                                                                                                        } ?>>
                    <label class="form-check-label" for="rdNighShift">Night Shift</label>
                </div>
            </div>
            <div class="col-sm input-group p-1">
                <span for="txtLineNumber" class="input-group-text col-2 form-label-lg has-validation">Line No</span>
                <input type="text" class="form-control form-control-lg has-validation" id="txtLineNumber" name="txtLineNumber" value="<?php echo isset($_POST["txtLineNumber"]) ? $_POST["txtLineNumber"] : '1'; ?>" required>
                <div class="invalid-feedback">
                    Please provide line number.
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm input-group p-1">
                <span for="txtModelName" class="input-group-text col-2 form-label-lg">Model</span>
                <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName" value="<?php echo isset($_POST["txtModelName"]) ? $_POST["txtModelName"] : '7R0123-7020'; ?>">
            </div>
            <div class="col-sm input-group p-1">
                <span for="cmbLeader" class="input-group-text col-2 form-label-lg">Leader</span>
                <select class="form-select form-select-lg" id="cmbLeader" name="cmbLeader">
                    <option value=0 selected>Select Leader</option>
                    <?php
                    $opsLeader = loadLeader($options);
                    foreach ($opsLeader as $key => $value) {
                    ?>
                        <option value="<?php echo $key; ?>" <?php if (isset($_POST['cmbLeader']) && $_POST['cmbLeader'] == $key)
                                                                echo 'selected= "selected"';
                                                            ?>><?php echo $value ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-sm input-group p-1">
                <span for="cmbDorType" class="input-group-text col-2 form-label-lg">Type</span>
                <select class="form-select form-select-lg" id="cmbDorType" name="cmbDorType">
                    <option value=0 selected>Select DOR Type</option>
                    <?php
                    $opsDor = loadDorType($options);
                    foreach ($opsDor as $key => $value) {
                    ?>
                        <option value="<?php echo $key; ?>" <?php if (isset($_POST['cmbDorType']) && $_POST['cmbDorType'] == $key)
                                                                echo 'selected= "selected"';
                                                            ?>><?php echo $value ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="row p-1">
            <button type="submit" class="btn btn-primary form-control btn-lg" name="btnCreateDor" value="btnCreateDor">Generate Daily Production Report</button>
        </div>

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $dorDate = testInput($_POST["dtpDate"]);

            if (empty($dorDate) or is_null($dorDate)) {
                $errorPrompt = "Please select date.";
            }

            if (empty($_POST["rdShift"])) {
                $errorPrompt = "Please select shift.";
            } else {
                $shift = testInput($_POST["rdShift"]);
            }

            if (empty($shift)) {
                $errorPrompt = "Please select shift.";
            }

            $lineNumber = testInput($_POST["txtLineNumber"]);

            if (isValidLine($lineNumber) === false) {
                $errorPrompt = "Line number is invalid or already logged in.";
            }

            $modelName = testInput($_POST["txtModelName"]);

            if (isValidModel($modelName) === false) {
                $errorPrompt = "Model name is invalid or not registered.";
            }

            $leaderId = testInput($_POST["cmbLeader"]);

            $dorTypeId = testInput($_POST["cmbDorType"]);


            if (empty($errorPrompt)) {
                if (isset($_POST['btnCreateDor'])) {
                    $lineId = $modelId = $shiftId = 0;

                    // get line id
                    $selQry = "SELECT LineId FROM dbo.AtoLine WHERE LineNumber = ?";
                    $prm = array($lineNumber);
                    $res = execQuery(1, 1, $selQry, $prm);

                    if ($res !== false) {
                        foreach ($res as $row) {
                            $lineId = $row['LineId'];
                        }
                    }

                    // get model id
                    $selQry = "SELECT MODEL_ID FROM dbo.GenModel WHERE IsActive = 1 AND ITEM_ID = ?";
                    $prm = array($modelName);
                    $res = execQuery(1, 1, $selQry, $prm);

                    if ($res !== false) {
                        foreach ($res as $row) {
                            $modelId = $row['MODEL_ID'];
                        }
                    }

                    // get shift id
                    if ($shift === "DS") {
                        $shiftId = 1;
                    } else {
                        $shiftId = 2;
                    }

                    if (isExistDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId) === true) {
                        $errorPrompt = "DOR already exists.";
                    }


                    // echo $_SESSION['isLeader'];
                }
            }
        }
        ?>

        <!-- image display section -->
        <!-- <div class="row p-1">
            <?php if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["txtModelName"])) : ?>
                <div class="col-12 text-center">
                    <img src="<?php
                                if (empty($errorPrompt)) {
                                    echo getImageUrl(testInput($_POST["cmbDorType"]), testInput($_POST["txtModelName"]));
                                }
                                ?>" class="img-fluid model-image" alt="Model Drawing">
                </div>
            <?php endif; ?>
        </div> -->

        <?php if ($errorPrompt) : ?>
            <div class="alert alert-danger mt-3" role="alert">
                <?php echo $errorPrompt; ?>
            </div>
        <?php endif; ?>
    </div>



</form>

<!-- <?php
        if (isset($_POST['btnCreateDor'])) {
            $procA = "EXEC RdCheckpointA @DorTypeId=?";
            $paramsA = [testInput($_POST["cmbDorType"])];
            $resA = execQuery(1, 2, $procA, $paramsA);

            if ($resA !== false) {
                // Prepare data for the tabs
                $tabData = [];
                foreach ($resA as $row) {
                    $checkpointName = $row['CheckpointName'];
                    if (!isset($tabData[$checkpointName])) {
                        $tabData[$checkpointName] = [];
                    }
                    $tabData[$checkpointName][] = $row;
                }

                // Render the tabs and content
                echo '<div class="tab-container">';

                // Tab navigation
                echo '<div class="tab-nav">';
                for ($i = 1; $i <= 4; $i++) {
                    echo "<button class='tab-button' onclick='openTab(event, \"P$i\")'>Process $i</button>";
                }
                echo '</div>';

                // Tab content
                for ($i = 1; $i <= 4; $i++) {
                    echo "<div id='P$i' class='tab-content' style='display: none;'>";
                    echo '<table class="table-checkpointA">
                <thead>
                    <tr>
                        <th colspan="12">A. Required Item and Jig Condition VS Work Instruction</th>
                    </tr>
                    <tr>
                        <th>Incharge: Operator/Leader</th>
                        <th colspan="1">Jig No:</th>
                        <th colspan="10"><input type="number" class="form-control form-control-md" id="cmbJigNo" name="cmbJigNo"></th>
                    </tr>
                    <tr>
                        <th>Checkpoint</th>
                        <th colspan="2">Criteria</th>';
                    for ($j = 1; $j <= 4; $j++) {
                        echo "<th>P$j</th>";
                    }
                    echo '</tr>
                </thead>
                <tbody>';

                    foreach ($tabData as $checkpointName => $rows) {
                        $checkpointCounts = count($rows);
                        echo "<tr>";

                        // Print checkpoint name cell
                        echo "<td rowspan='$checkpointCounts' class='checkpoint-cell'>" . $rows[0]['SequenceId'] . ". " . $checkpointName . "</td>";

                        foreach ($rows as $index => $row) {
                            // Print criteria columns
                            if (is_null($row['CriteriaName2'])) {
                                echo "<td colspan='2' class='criteria-cell'>" . $row['CriteriaName1'] . "</td>";
                            } else {
                                echo "<td class='criteria-cell'>" . $row['CriteriaName1'] . "</td>";
                                echo "<td class='criteria-cell'>" . $row['CriteriaName2'] . "</td>";
                            }

                            // Display options for each process (P1, P2, P3, P4)
                            for ($j = 1; $j <= 4; $j++) {
                                echo "<td>";
                                echo "<div class='process-radio'>";

                                if ($row['CriteriaTypeId'] > 3) {
                                    $procB = "EXEC RdCriteriaTypeOption @CriteriaTypeId=?";
                                    $paramsB = [testInput($row['CriteriaTypeId'])];
                                    $resB = execQuery(1, 2, $procB, $paramsB);

                                    if ($resB !== false) {
                                        foreach ($resB as $option) {
                                            echo "<label><input type='radio' class='form-check-input form-check-md' name='option_" . $row['CheckpointId'] . "_P$j' value='" . $option['CriteriaTypeOptionId'] . "'> " . $option['CriteriaTypeName'] . "</label>";
                                        }
                                    } else {
                                        echo "No options available.";
                                    }
                                } else {
                                    // Display a centered number input
                                    echo "<input type='number' class='form-control number-input' name='input_" . $row['SequenceId'] . "_P$j'>";
                                }

                                echo "</div>"; // Close process-radio div
                                echo "</td>";
                            }

                            if ($index == count($rows) - 1) {
                                echo "</tr>";
                            } else {
                                echo "<tr>";
                            }
                        }
                    }

                    echo '</tbody></table>';
                    echo "</div>"; // Close tab-content div
                }

                echo '</div>'; // Close tab-container div
            } else {
                $errorPrompt = "Error";
            }
        }
        ?> -->



<?php

// function to get image URL based on model number
function getImageUrl($dorTypeId, $modelNumber)
{
    $dorTypeName = "";

    switch ($dorTypeId) {
        case 1:
            $dorTypeName = "pre-assy";
            break;
        case 2:
            $dorTypeName = "clamp-assy";
            break;
        case 3:
            $dorTypeName = "taping";
            break;
        default:
            $dorTypeName = "pre-assy";
    }

    $imagePath = "img/drawings/" . $dorTypeName . "/"; // directory where images are stored
    $imageExtension = ".jpg"; // assuming images are in jpg format

    if (file_exists($imagePath . $modelNumber . $imageExtension)) {
        return $imagePath . $modelNumber . $imageExtension;
    } else {
        return $imagePath . "default.jpg"; // fallback image if model-specific image is not found
    }
}

?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');

        form.addEventListener('myForm', function(e) {
            e.preventDefault(); // stop form from submitting normally

            // use fetch API to submit the form data
            fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(response => response.text())
                .then(html => alert(html)) // display response
                .catch(error => console.error('Error:', error));
        });
    });

    // function openTab(evt, processName) {
    //     var i, tabcontent, tabbuttons;
    //     tabcontent = document.getElementsByClassName("tab-content");
    //     for (i = 0; i < tabcontent.length; i++) {
    //         tabcontent[i].style.display = "none";
    //     }
    //     tabbuttons = document.getElementsByClassName("tab-button");
    //     for (i = 0; i < tabbuttons.length; i++) {
    //         tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
    //     }
    //     document.getElementById(processName).style.display = "block";
    //     evt.currentTarget.className += " active";
    // }
    // // Set default tab
    // document.getElementsByClassName("tab-button")[0].click();

    function openTab(evt, processName) {
        var i, tabcontent, tabbuttons;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tabbuttons = document.getElementsByClassName("tab-button");
        for (i = 0; i < tabbuttons.length; i++) {
            tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
        }
        document.getElementById(processName).style.display = "block";
        evt.currentTarget.className += " active";
    }
    // Set default tab
    document.getElementsByClassName("tab-button")[0].click();
</script>


<?php
$content = ob_get_clean(); // capture the buffer into a variable and clean the buffer
include('master.php');
?>