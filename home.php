<?php

$title = "Create DOR";
ob_start(); // start output buffering

require "config/dbcon.php";
require "header.php";
require "config/method.php";

$msgPrompt = '';

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
</style>

<form id="myForm" class="p-1 row" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
    <div class="container-fluid">
        <h3 class="text-center">Sub-Assembly Daily Checksheet</h3>
        <div class="row">
            <div class=" col-sm input-group p-1">
                <span for="dtpDate" class="input-group-text col-2 form-label-lg">Date</span>
                <input type="date" class="form-control form-control-lg" id="dtpDate" name="dtpDate" value="<?php echo isset($_POST["dtpDate"]) ? $_POST["dtpDate"] : $today; ?>" required>
            </div>
            <div class="col-sm input-group p-1 shift-group">
                <span class="input-group-text col-2 form-label-lg">Shift</span>
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
                <input type="text" class="form-control form-control-lg has-validation" id="txtLineNumber" name="txtLineNumber" value="<?php echo isset($_POST["txtLineNumber"]) ? $_POST["txtLineNumber"] : ''; ?>" required>
                <div class="invalid-feedback">
                    Please provide line number.
                </div>
                <span for="txtQty" class="input-group-text col-2 form-label-lg has-validation">Qty</span>
                <input type="text" class="form-control form-control-lg has-validation" id="txtQty" name="txtQty" value="<?php echo isset($_POST["txtQty"]) ? $_POST["txtQty"] : ''; ?>" required>
            </div>
        </div>
        <div class="row">
            <div class="col-sm input-group p-1">
                <span for="txtModelName" class="input-group-text col-2 form-label-lg">Model</span>
                <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName" value="<?php echo isset($_POST["txtModelName"]) ? $_POST["txtModelName"] : ''; ?>">
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
            <button type="submit" class="btn btn-primary form-control btn-lg" name="btnCreateDor" value="btnCreateDor">Create DOR</button>
        </div>
        <div class="row p-1">
            <button type="submit" class="btn btn-secondary form-control btn-lg" name="btnSearchDor" value="btnSearchDor">Search DOR</button>
        </div>
        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $dorDate = testInput($_POST["dtpDate"]);

            if (empty($dorDate) or is_null($dorDate)) {
                $msgPrompt = "Please select date.";
            }

            if (empty($_POST["rdShift"])) {
                $msgPrompt = "Please select shift.";
            } else {
                $shift = testInput($_POST["rdShift"]);
            }

            if (empty($shift)) {
                $msgPrompt = "Please select shift.";
            }

            $lineNumber = testInput($_POST["txtLineNumber"]);

            if (empty($lineNumber) or is_null($lineNumber)) {
                $msgPrompt = "Please provide line number.";
            }

            if (empty($lineNumber) === 0) {
                $msgPrompt = "Line number cannot be zero.";
            }

            if (isValidLine($lineNumber) === false) {
                $msgPrompt = "Line number is invalid or already logged in.";
            }

            $qty = testInput($_POST["txtQty"]);

            if (empty($qty) or is_null($qty)) {
                $msgPrompt = "Please provide quantity.";
            }

            if (empty($qty) === 0) {
                $msgPrompt = "Quantity cannot be zero.";
            }

            $modelName = testInput($_POST["txtModelName"]);

            if (isValidModel($modelName) === false) {
                $msgPrompt = "Model name is invalid or not registered.";
            }

            if (empty($modelName) or is_null($modelName)) {
                $msgPrompt = "Please provide model name.";
            }

            $leaderId = testInput($_POST["cmbLeader"]);

            if ($leaderId === "0") {
                $msgPrompt = "Please select leader.";
            }

            $dorTypeId = testInput($_POST["cmbDorType"]);

            if ($dorTypeId === "0") {
                $msgPrompt = "Please select DOR type.";
            }

            if (empty($msgPrompt)) {
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
                        $msgPrompt = "DOR already exists.";
                    }

                    // echo 123;
                }
            }
        }
        ?>
        <?php if ($msgPrompt) : ?>
            <div class="alert alert-danger mt-3" role="alert">
                <?php echo $msgPrompt; ?>
            </div>
        <?php endif; ?>
    </div>


    <div class="container-fluid">

    </div>
</form>



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
</script>


<?php
$content = ob_get_clean(); // capture the buffer into a variable and clean the buffer
include('master.php');
?>