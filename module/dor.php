<?php

$title = "Home";
ob_start();

require_once "../config/dbop.php";
require_once "../config/header.php";

$db1 = new DbOp(1);
$today = date('Y-m-d');
$errorMessages = [];

$currentHour = date('H');
$selectedShift = ($currentHour >= 7 && $currentHour < 19) ? "DS" : "NS";

?>

<form id="myForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
    <div class="container-fluid">
        <div class="mb-3">
            <label for="dtpDate" class="form-label-lg fw-bold">Date</label>
            <input type="date" class="form-control form-control-lg" id="dtpDate" name="dtpDate"
                value="<?php echo $_POST["dtpDate"] ?? $today; ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label-lg fw-bold">Shift</label>
            <div class="row">
                <div class="col">
                    <div class="input-group-text form-control-lg">
                        <input class="form-check-input me-2" type="radio" name="rdShift" id="rdDayShift" value="DS"
                            <?php echo ($_POST['rdShift'] ?? $selectedShift) === "DS" ? "checked" : ""; ?>>
                        <label class="form-check-label" for="rdDayShift">Day Shift</label>
                    </div>
                </div>
                <div class="col">
                    <div class="input-group-text form-control-lg">
                        <input class="form-check-input me-2" type="radio" name="rdShift" id="rdNightShift" value="NS"
                            <?php echo ($_POST['rdShift'] ?? $selectedShift) === "NS" ? "checked" : ""; ?>>
                        <label class="form-check-label" for="rdNightShift">Night Shift</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="cmbLeader" class="form-label-lg fw-bold">Leader</label>
            <select class="form-select form-select-lg" id="cmbLeader" name="cmbLeader">
                <option value="0" selected>Select Leader</option>
                <?php
                $opsLeader = loadLeader($_SESSION['processId'], 1);
                foreach ($opsLeader as $key => $value) {
                    $selected = ($_POST['cmbLeader'] ?? '') == $key ? 'selected' : '';
                    echo "<option value='$key' $selected>$value</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="cmbDorType" class="form-label-lg fw-bold">DOR Type</label>
            <select class="form-select form-select-lg" id="cmbDorType" name="cmbDorType">
                <option value="0" selected>Select DOR Type</option>
                <?php
                $opsDor = loadDorType($options);
                foreach ($opsDor as $key => $value) {
                    $selected = ($_POST['cmbDorType'] ?? '') == $key ? 'selected' : '';
                    echo "<option value='$key' $selected>$value</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="txtModelName" class="form-label-lg fw-bold">Model</label>
            <input type="text" class="form-control form-control-lg" id="txtModelName" name="txtModelName"
                placeholder="Scan or type product model"
                value="<?php echo $_POST["txtModelName"] ?? '7R0122-7020A'; ?>" required>
        </div>

        <div class="mb-3">
            <label for="txtQty" class="form-label-lg fw-bold">Quantity</label>
            <input type="number" class="form-control form-control-lg" id="txtQty" name="txtQty"
                placeholder="Scan or type quantity"
                value="<?php echo $_POST["txtQty"] ?? '100'; ?>" required>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg" id="btnSubmit" name="btnCreateDor">Create DOR</button>
            <button type="submit" class="btn btn-secondary btn-lg" id="btnSearch" name="btnSearchDor">Search DOR</button>
        </div>

        <?php
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $dorDate = testInput($_POST["dtpDate"]);
            $shift = testInput($_POST['rdShift']);
            $leaderId = testInput($_POST["cmbLeader"]);
            $dorTypeId = testInput($_POST["cmbDorType"]);
            $modelName = testInput($_POST["txtModelName"]);
            $qty = testInput($_POST["txtQty"]);

            if (empty($dorDate)) {
                $errorMessages[] = "- Date is required. Select a date.";
            }

            if (empty($shift)) {
                $errorMessages[] = "- Shift is required. Select a shift.";
            }

            if ($leaderId === "0") {
                $errorMessages[] = "- Select a leader from the list.";
            }

            if ($dorTypeId === "0") {
                $errorMessages[] = "- Select the type of DOR from the list.";
            }
            if (empty($modelName)) {
                $errorMessages[] = "- Scan or type the product model.";
            } else {
                if (!isValidModel($modelName)) {
                    $errorMessages[] = "- The model is not registered.";
                }
            }

            if (empty($qty) || $qty === '0') {
                $errorMessages[] = "- Enter the production quantity.";
            }

            // If there are errors, display them
            if (!empty($errorMessages)) {
                $errorPrompt = implode("<br>", $errorMessages); // Join errors with line breaks
            } else {
                if (isset($_POST['btnCreateDor'])) {
                    $shiftId = $lineId = $modelId = 0;

                    $selQry = "EXEC RdGenShift @ShiftCode=?";
                    $res = $db1->execute($selQry, [$shift], 1);

                    if ($res !== false) {
                        foreach ($res as $row) {
                            $shiftId = $row['ShiftId'];
                        }
                    }

                    $selQry = "EXEC RdAtoLine @LineId=?";
                    $res = $db1->execute($selQry, [$_SESSION["deviceName"]], 1);

                    if (!empty($res)) {
                        foreach ($res as $row) {
                            $lineId = $row['LineId'];
                        }
                    }

                    $selQry = "EXEC RdGenModel @IsActive=?, @ITEM_ID=?";
                    $res = $db1->execute($selQry, [1, $modelName], 1);

                    if ($res !== false) {
                        foreach ($res as $row) {
                            $modelId = $row['ITEM_ID'];
                        }
                    }

                    if (isExistDor($dorDate, $shiftId, $lineId, $modelId, $dorTypeId) === true) {
                        $errorPrompt = "DOR already exists.";
                        alertMsg(1);
                    } else {
                        $_SESSION["dorDate"] = $dorDate;
                        $_SESSION["dorShift"] = $shift;
                        $_SESSION["dorLineId"] = $lineId;
                        $_SESSION["dorQty"] = $qty;
                        $_SESSION["dorModelId"] = $modelId;
                        $_SESSION["dorLeaderId"] = $leaderId;
                        $_SESSION["dorTypeId"] = $dorTypeId;

                        header('Location: dor-form.php');
                    }
                }
            }
        }
        ?>

    </div>
</form>

<!-- Bootstrap Modal for Error Messages -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalErrorMessage"></div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('#myForm');
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        const modalErrorMessage = document.getElementById('modalErrorMessage');

        form.addEventListener('submit', function(e) {
            let errors = [];

            // Validate Fields
            if (document.getElementById('dtpDate').value === '') {
                errors.push("Select a date.");
            }
            if (!document.querySelector('input[name="rdShift"]:checked')) {
                errors.push("Select a shift.");
            }
            if (document.getElementById('cmbLeader').value === "0") {
                errors.push("Select a leader.");
            }
            if (document.getElementById('cmbDorType').value === "0") {
                errors.push("Select a DOR type.");
            }
            if (document.getElementById('txtModelName').value.trim() === '') {
                errors.push("Enter the model name.");
            }
            if (document.getElementById('txtQty').value.trim() === '' || document.getElementById('txtQty').value == 0) {
                errors.push("Enter the quantity.");
            }

            // If Errors Exist, Show Modal
            if (errors.length > 0) {
                e.preventDefault(); // Stop form submission
                modalErrorMessage.innerHTML = "<ul><li>" + errors.join("</li><li>") + "</li></ul>";
                errorModal.show();
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
include('../config/master.php');
?>