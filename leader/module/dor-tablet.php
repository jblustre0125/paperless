<?php
ob_start();
session_start();
$title = "DOR Dashboard";
require_once '../controller/dor-checkpoint-definition.php';
require_once '../controller/dor-visual-checkpoint.php';
require_once '../controller/dor-dimension-checkpoint.php';
require_once '../controller/dor-dor.php';
require_once '../controller/dor-leader-method.php';

$isSubmitted = isset($_GET['submitted']) && $_GET['submitted'] == 1;

// Make sure $recordId is defined at this point
$dimChecks = $db->execute("SELECT * FROM AtoDimensionCheck WHERE RecordId = ? ORDER BY DimCheckId ASC", [$recordId]);

$indexedDimChecks = [];
$counter = 1;
foreach ($dimChecks as $row) {
    $indexedDimChecks[$counter++] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link href="../css/leader-dashboard.css" rel="stylesheet">
    <link href="../../css/dor-navbar.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/dor-tablet.css">

    <style>
        .operator-grid {
            display: flex;
            flex-wrap: wrap;
            max-width: 220px;
        }

        .operator-tile {
            width: 90px;
            height: 42px;
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .operator-tile .remove-operator {
            position: absolute;
            top: -6px;
            right: -6px;
            background-color: #dc3545;
            color: white;
            font-size: 0.7rem;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            cursor: pointer;
        }

        .operator-tile.text-muted {
            background-color: #f8f9fa;
            color: #6c757d;
            font-style: italic;
        }


        .scrollable-body {
            max-height: 300px;
            /* or whatever you prefer */
            overflow-y: auto;
        }

        .scrollable-body table {
            margin: 0;
            table-layout: fixed;
        }
         .table-container {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }
    </style>
</head>

<body class="fs-6" style="margin-left: 0; margin-right: 0; padding: 0;">
    <form method="POST">
        <nav class="navbar navbar-expand navbar-light bg-light shadow-sm fixed-top">
            <div class="container-fluid py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
                    <!-- Left-aligned group -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group btn-nav-fixed" id="btnDrawing">Drawing</button>
                        <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group" id="btnWorkInstruction" data-file="<?php echo htmlspecialchars($workInstructFile); ?>">
                            <span class="short-label">WI</span>
                            <span class="long-label">Work Instruction</span>
                        </button>
                        <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group btn-nav-fixed" id="btnPrepCard">
                            <span class="short-label">Prep Card</span>
                            <span class="long-label">Preparation Card</span>
                        </button>
                        <button class="btn btn-secondary btn-lg nav-btn-lg btn-nav-group btn-nav-fixed" onclick="window.location.href='dor-leader-dashboard.php'" id="btnBackToTab">
                            <span class="short-label">Opt Tab</span>
                            <span class="long-label">Operator Tablets</span>
                        </button>
                    </div>
                    <input type="hidden" name="record_id" value="<?= $recordId ?>">
                    <input type="hidden" name="current_tab_index" id="currentTabInput" value="<?= isset($_GET['tab']) ? (int)$_GET['tab'] : 0 ?>">

                    <!-- Right-aligned group -->
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-secondary btn-lg nav-btn-group btn-nav-fixed" id="btnBack">Back</button>
                        <button type="submit" class="btn btn-primary btn-lg nav-btn-group btn-nav-fixed" name="btnNext" id="btnNext">
                            <span class="short-label">Next</span>
                            <span class="long-label">Proceed to Next Checkpoint</span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid py-0 m-0">
            <!-- CheckpointA -->
            <div class="tab-content fixed-top" style="margin-top: 30px; display:none;" id="dorTabContent">
                <div class="tab-pane fade show active" id="tab-0" role="tabpanel">
                    <div class="table-container" style="max-height:90vh; margin-top: 10px; position: relative;">
                        <!-- Invisible sizing table to establish column widths -->
                        <table class="table table-bordered mb-0" style="visibility: hidden;">
                            <thead>
                                <tr>
                                    <th style="width: 25%;"></th>
                                    <th style="width: 15%;"></th>
                                    <th style="width: 15%;"></th>
                                    <?php foreach ($processIndexes as $index): ?>
                                        <th style="width: <?= floor(30 / count($processIndexes)) ?>%;"></th>
                                    <?php endforeach; ?>
                                    <th style="width: 15%;"></th>
                                </tr>
                            </thead>
                        </table>

                        <!-- Sticky header clone -->
                        <div class="sticky-header" style="position: sticky; top: 0; z-index: 10; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <table class="table table-bordered mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th class="fs-6" colspan="<?= 6 + count($processIndexes) ?>">A. Required Item and Jig Condition VS Work Instruction</th>
                                    </tr>
                                    <tr>
                                        <th class="fs-6 text-start" rowspan="2" style="width: 25%;">Checkpoints</th>
                                        <th class="fs-6" colspan="2" rowspan="2" style="width: 30%;">Criteria</th>
                                        <th class="fs-6" colspan="<?= count($processIndexes) ?>" style="width: 30%;">Operator</th>
                                        <th class="fs-6" rowspan="2" style="width: 15%;">Leader</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($processIndexes as $index): ?>
                                            <th style="width: <?= floor(30 / count($processIndexes)) ?>%;">P<?= $index ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        <!-- Scrollable body -->
                        <div style="overflow-y: auto; max-height: calc(90vh - 120px);">
                            <table class="table table-bordered text-center align-middle w-100">
                                <tbody>
                                    <?php
                                    $grouped = [];
                                    foreach ($checkpoints as $cp) {
                                        $grouped[$cp['SequenceId']][] = $cp;
                                    }

                                    foreach ($grouped as $sequenceId => $group):
                                        foreach ($group as $index => $cp):
                                            $checkpointId = $cp['CheckpointId'];
                                            $good = trim($cp['CriteriaGood']);
                                            $notGood = trim($cp['CriteriaNotGood']);
                                            $colspanGood = empty($notGood) ? 2 : 1;
                                            $leaderDefault = $leaderResponses[$checkpointId] ?? 'OK';
                                    ?>
                                            <tr>
                                                <?php if ($index === 0): ?>
                                                    <td class="text-start fs-6" rowspan="<?= count($group) ?>" style="width: 25%;">
                                                        <?= $cp['SequenceId']; ?>. <?= htmlspecialchars($cp['CheckpointName']); ?>
                                                    </td>
                                                <?php endif; ?>

                                                <td class="fs-6" colspan="<?= $colspanGood; ?>" style="width: <?= $colspanGood === 2 ? '30%' : '15%' ?>;"><?= htmlspecialchars($good); ?></td>
                                                <?php if (!empty($notGood)): ?>
                                                    <td class="fs-6" style="width: 15%;"><?= htmlspecialchars($notGood); ?></td>
                                                <?php endif; ?>

                                                <?php if (!empty($processIndexes)): ?>
                                                    <?php foreach ($processIndexes as $procIdx): ?>
                                                        <td class="fs-6" style="width: <?= floor(30 / count($processIndexes)) ?>%;"><?= htmlspecialchars($operatorResponses[$checkpointId][$procIdx] ?? '-') ?></td>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <td class="fs-6" style="width: 30%;">No operator data available</td>
                                                <?php endif; ?>

                                                <td class="py-2 px-3 fs-6" style="width: 15%;">
                                                    <?php
                                                    $radioName = "leader[$checkpointId]";
                                                    $hasResponse = isset($leaderResponses[$checkpointId]);
                                                    $leaderDefault = $leaderResponses[$checkpointId] ?? 'OK';
                                                    ?>

                                                    <?php if ($hasResponse): ?>
                                                        <?php
                                                        $badgeClass = match ($leaderDefault) {
                                                            'OK' => 'text-success fw-bold',
                                                            'NG' => 'text-danger fw-bold',
                                                            'NA' => 'text-secondary fw-bold',
                                                            default => 'text-muted',
                                                        };
                                                        ?>
                                                        <div class="text-center">
                                                            <span class="<?= $badgeClass ?>"><?= htmlspecialchars($leaderDefault) ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex justify-content-center align-items-center gap-3 flex-nowrap overflow-auto" style="white-space: nowrap;">
                                                            <?php foreach (['OK', 'NA', 'NG'] as $val): ?>
                                                                <div class="form-check d-flex align-items-center m-0" style="gap: 4px;">
                                                                    <input
                                                                        type="radio"
                                                                        name="<?= $radioName ?>"
                                                                        id="leader-<?= $checkpointId ?>-<?= strtolower($val) ?>"
                                                                        class="form-check-input"
                                                                        value="<?= $val ?>"
                                                                        <?= ($leaderDefault === $val) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label m-0" for="leader-<?= $checkpointId ?>-<?= strtolower($val) ?>">
                                                                        <?= $val ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" name="btnSubmit" id="hiddenVisual" style="display: none;">Submit</button>
                </div>


                <div class="tab-pane fade" id="tab-1" role="tabpanel">
                    <div class="container-fluid mt-5">
                        <?php $tabNames = ['Hatsumono', 'Nakamono', 'Owarimono']; ?>

                        <!-- Sub-tabs -->
                        <ul class="nav nav-tabs" id="visualCheckpointTab" role="tablist">
                            <?php foreach ($tabNames as $index => $tab):
                                $tabId = strtolower($tab); ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?= $index === 0 ? 'active' : '' ?>"
                                        id="<?= $tabId ?>-tab"
                                        data-bs-toggle="tab"
                                        data-bs-target="#<?= $tabId ?>"
                                        type="button"
                                        role="tab"
                                        aria-controls="<?= $tabId ?>"
                                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                                        <?= $tab ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tab-content mt-4" id="visualCheckpointTabContent">
                            <?php foreach ($tabNames as $index => $tab):
                                $tabId = strtolower($tab); ?>
                                <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>"
                                    id="<?= $tabId ?>"
                                    role="tabpanel"
                                    aria-labelledby="<?= $tabId ?>-tab">
                                    <input type="hidden" name="record_id" value="<?= htmlspecialchars($recordId) ?>">
                                    <input type="hidden" name="employee_code" value="<?= htmlspecialchars($_SESSION['employee_code']) ?>">
                                    <div class="table-wrapper mt-3">
                                        <table class="table table-bordered text-center align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th colspan="4" class="fs-6">B. Visual Inspection Checkpoint</th>
                                                </tr>
                                                <tr>
                                                    <th class="fs-6">Checkpoints</th>
                                                    <th class="fs-6" colspan="2">Criteria</th>
                                                    <th class="fs-6"><?= $tab ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $groupedVisuals = [];
                                                foreach ($visualCheckpoints as $v) {
                                                    $groupedVisuals[$v['SequenceId']][] = $v;
                                                }

                                                foreach ($groupedVisuals as $sequenceId => $group):
                                                    foreach ($group as $index => $v):
                                                        $checkpointId = $v['CheckpointId'];
                                                        $typeId = $v['CheckpointTypeId'];
                                                        $options = $checkpointControlMap[$typeId] ?? ['text'];
                                                        $value = $v[$tab] ?? '';
                                                        $isRadio = $options !== ['text'];
                                                ?>
                                                        <tr>
                                                            <?php if ($index === 0): ?>
                                                                <td class="text-start fs-6" rowspan="<?= count($group) ?>" style="min-width: 110px;">
                                                                    <?= $v['SequenceId'] ?>. <?= htmlspecialchars($v['CheckpointName']) ?>
                                                                </td>
                                                            <?php endif; ?>

                                                            <td class="fs-6" colspan="<?= empty($v['CriteriaNotGood']) ? 2 : 1 ?>" style="min-width: 100px;">
                                                                <?= htmlspecialchars($v['CriteriaGood']) ?>
                                                            </td>

                                                            <?php if (!empty($v['CriteriaNotGood'])): ?>
                                                                <td class="fs-6" style="min-width: 100px;">
                                                                    <?= htmlspecialchars($v['CriteriaNotGood']) ?>
                                                                </td>
                                                            <?php endif; ?>
                                                            <td class="fs-6" style="min-width: 220px;">
                                                                <?php $name = "visual[$checkpointId][$tab]"; ?>
                                                                <?php if ($isRadio): ?>
                                                                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                                                                        <?php foreach ($options as $opt): ?>
                                                                            <div class="form-check form-check-inline">
                                                                                <input type="radio" class="form-check-input"
                                                                                    name="<?= $name ?>"
                                                                                    value="<?= $opt ?>"
                                                                                    id="<?= $name ?>_<?= strtolower(str_replace(' ', '', $opt)) ?>"
                                                                                    <?= $value === $opt ? 'checked' : '' ?>>
                                                                                <label class="form-check-label"
                                                                                    for="<?= $name ?>_<?= strtolower(str_replace(' ', '', $opt)) ?>">
                                                                                    <?= $opt ?>
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <input type="text" name="<?= $name ?>" class="form-control" value="<?= htmlspecialchars($value) ?>">
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <button type="submit" name="btnProceed" id="btnProceed" class="btn btn-primary float-end">Submit</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-2" role="tabpanel">
                    <div class="dimension-check-container" style="max-height: 90vh; margin-top: 10px; position: relative;">
                        <!-- Invisible sizing table to establish column widths -->
                        <table class="table table-bordered mb-0" style="visibility: hidden;">
                            <thead>
                                <tr>
                                    <th style="width: 8%;"></th> <!-- No. column -->
                                    <?php foreach (['Hatsumono', 'Nakamono', 'Owarimono'] as $section): ?>
                                        <th style="width: 30.666%;"></th> <!-- Each section gets equal width -->
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                        </table>

                        <!-- Sticky header clone -->
                        <div class="sticky-header" style="position: sticky; top: 0; z-index: 10; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <table class="table table-bordered mb-0 text-center">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fs-6" colspan="10">C. Dimension Check</th>
                                    </tr>
                                    <tr>
                                        <th class="fs-6" rowspan="2" style="width: 8%; min-width: 50px;">No.</th>
                                        <?php foreach (['Hatsumono', 'Nakamono', 'Owarimono'] as $section): ?>
                                            <th class="fs-6" colspan="3" style="width: 30.666%;"><?= $section ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php for ($i = 1; $i <= 9; $i++): ?>
                                            <th class="fs-6" style="width: 10.222%;"><?= (($i - 1) % 3) + 1 ?></th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        <!-- Scrollable body -->
                        <div style="overflow-y: auto; max-height: calc(90vh - 120px);">
                            <table class="table table-bordered text-center align-middle w-100">
                                <tbody>
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                        <?php $row = $indexedDimChecks[$i] ?? []; ?>
                                        <tr>
                                            <td class="fs-6" style="width: 8%; min-width: 50px;"><?= $i ?></td>

                                            <input type="hidden" name="dim_check_id[<?= $i ?>]" value="<?= $row['DimCheckId'] ?? '' ?>">

                                            <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                                                <?php for ($j = 1; $j <= 3; $j++): ?>
                                                    <td class="text-center fs-6" style="width: 10.222%;">
                                                        <?php
                                                        $key = ucfirst($section) . $j;
                                                        $value = $row[$key] ?? ($_POST["{$section}_value_{$j}"][$i] ?? '');
                                                        ?>
                                                        <input type="number" class="form-control"
                                                            name="<?= $section ?>_value_<?= $j ?>[<?= $i ?>]"
                                                            value="<?= htmlspecialchars($value) ?>" />
                                                    </td>
                                                <?php endfor; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endfor; ?>

                                    <!-- Judge row -->
                                    <tr>
                                        <td class="fw-bold text-center fs-6" style="width: 8%;">Judge</td>
                                        <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                                <td class="fs-6" style="width: 10.222%;">
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <?php foreach (['OK', 'NA', 'NG'] as $opt): ?>
                                                            <label class="form-check-label small w-100 text-center">
                                                                <input type="radio" class="form-check-input me-1"
                                                                    name="judge_<?= $section ?>_<?= $i ?>"
                                                                    value="<?= $opt ?>"
                                                                    <?= (($_POST["judge_{$section}_{$i}"] ?? '') === $opt) ? 'checked' : '' ?>>
                                                                <?= $opt ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <input type="hidden" name="checkby_<?= $section . $i ?>"
                                                        value="<?= htmlspecialchars($_SESSION['employee_code'] ?? '') ?>">
                                                </td>
                                            <?php endfor; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" name="btnSubmit" id="hiddenSubmit" style="display: none;">Submit</button>
                </div>

                <!-- Tab Pane -->
                    <div class="tab-pane fade" id="tab-3" role="tabpanel">
                        <div class="dor-table-container" style="max-height: 90vh; margin-top: 10px; position: relative;">
                            <!-- Invisible sizing table to establish column widths -->
                            <table class="table table-bordered mb-0" style="visibility: hidden;">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;"></th> <!-- # -->
                                        <th style="width: 15%;"></th> <!-- Box No. -->
                                        <th style="width: 12%;"></th> <!-- Start Time -->
                                        <th style="width: 12%;"></th> <!-- End Time -->
                                        <th style="width: 12%;"></th> <!-- Duration -->
                                        <th style="width: 22%;"></th> <!-- Operator -->
                                        <th style="width: 17%;"></th> <!-- Downtime -->
                                        <th style="width: 5%;"></th> <!-- * -->
                                    </tr>
                                </thead>
                            </table>

                            <!-- Sticky header clone -->
                            <div class="sticky-header" style="position: sticky; top: 0; z-index: 10; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <table class="table table-bordered table-dor mb-0 w-100">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th colspan="8">Daily Operation Record</th>
                                        </tr>
                                        <tr>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 15%;">Box No.</th>
                                            <th style="width: 12%;">Start Time</th>
                                            <th style="width: 12%;">End Time</th>
                                            <th style="width: 12%;">Duration</th>
                                            <th style="width: 22%;">Operator</th>
                                            <th style="width: 17%;">Downtime</th>
                                            <th style="width: 5%;">*</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                            <!-- Scrollable body -->
                            <div style="overflow-y: auto; max-height: calc(90vh - 120px);">
                                <table class="table table-bordered table-dor align-middle text-nowrap w-100">      
                                    
 
                                    <tbody>
                                        <?php
                                        $modals = [];

                                        // Fallback operator logic (session or hardcoded)
                                        $fallbackEmployeeCodes = [];
                                        for ($j = 1; $j <= 4; $j++) {
                                            if (!empty($_SESSION["userCode$j"])) {
                                                $fallbackEmployeeCodes[] = strtoupper(trim($_SESSION["userCode$j"]));
                                            }
                                        }
                                        $sharedEmployeeCodes = [];

                                        for ($i = 1; $i <= 20; $i++) {
                                            $header = $headers[$i - 1] ?? [];
                                            $recordHeaderId = $header['RecordHeaderId'] ?? 'unknown_' . $i;
                                            $recordHeaderIdSafe = htmlentities($recordHeaderId);
                                            $employeeCodes = !empty($sharedEmployeeCodes) ? $sharedEmployeeCodes : $fallbackEmployeeCodes;
                                            $modalId = "operatorModal_" . htmlspecialchars($recordHeaderId);
                                        ?>
                                            <tr data-row-id="<?= $i ?>">
                                                <td class="text-center align-middle" style="width: 5%;">
                                                    <?= $i ?> <i class="bi bi-qr-code-scan ms-1"></i>
                                                </td>
                                                <td style="width: 15%;">
                                                    <input type="text" class="form-control text-center scan-box-no"
                                                        id="boxNo<?= $i ?>" name="boxNo<?= $i ?>"
                                                        value="<?= htmlspecialchars($header['BoxNumber'] ?? '') ?>" disabled>
                                                    <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                                                    <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
                                                </td>
                                                <td style="width: 12%;">
                                                    <input type="text" class="form-control text-center time-input"
                                                        id="timeStart<?= $i ?>" name="timeStart<?= $i ?>"
                                                        value="<?= isset($header['TimeStart']) ? date('H:i', $header['TimeStart'] instanceof \DateTime ? $header['TimeStart']->getTimestamp() : strtotime($header['TimeStart'])) : '' ?>"
                                                        placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" disabled>
                                                </td>
                                                <td style="width: 12%;">
                                                    <input type="text" class="form-control text-center time-input"
                                                        id="timeEnd<?= $i ?>" name="timeEnd<?= $i ?>"
                                                        value="<?= isset($header['TimeEnd']) ? date('H:i', $header['TimeEnd'] instanceof \DateTime ? $header['TimeEnd']->getTimestamp() : strtotime($header['TimeEnd'])) : '' ?>"
                                                        placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" disabled>
                                                </td>
                                                <td class="text-center align-middle" style="width: 12%;">
                                                    <span id="duration<?= $i ?>" class="duration-value">
                                                        <?= htmlspecialchars($header['Duration'] ?? '') ?>
                                                    </span>
                                                </td>

                                                <!-- Operator -->
                                                <td class="text-center align-middle" style="width: 22%;">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <button type="button" 
                                                        class="btn btn-outline-primary btn-sm btn-operator mb-1"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#operatorModal<?= $recordHeaderId ?>" 
                                                        data-record-id="operator<?= $recordHeaderId ?>">
                                                            <i class="bi bi-person-plus"></i> View Operators
                                                        </button>

                                                        <!-- Badge container -->
                                                        <div class="operator-codes d-flex flex-wrap justify-content-center" id="operatorList<?= $recordHeaderId ?>">
                                                            <?php foreach (array_unique($employeeCodes) as $code):
                                                                $name = $operatorMap[$code] ?? 'No operators'; ?>
                                                                <small class="badge bg-light text-dark border me-1 mb-1"
                                                                    title="<?= htmlspecialchars($name) ?>">
                                                                    <?= htmlspecialchars($code) ?>
                                                                </small>
                                                            <?php endforeach; ?>
                                                        </div>

                                                        <input type="hidden" id="operators<?= htmlspecialchars($recordHeaderId) ?>"
                                                            value="<?= htmlspecialchars(implode(',', $employeeCodes)) ?>">
                                                    </div>
                                                </td>

                                                <!-- Downtime -->
                                                <?php if ($recordHeaderId): ?>
                                                    
                                                    <td class="text-center align-middle" style="width: 17%;">
                                                        <div class="d-flex flex-column align-items-center mt-md-4">
                                                            <button type="button"
                                                                class="btn btn-outline-secondary btn-sm mb-1 downtime-trigger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#downtimeModal<?= $recordHeaderIdSafe ?>"
                                                                data-record-id="<?= $recordHeaderIdSafe ?>">
                                                                <i class="bi bi-clock-history"></i> View Downtime
                                                            </button>

                                                            <div class="downtime-info d-flex flex-wrap justify-content-center"
                                                                id="downtimeInfo<?= $recordHeaderIdSafe ?>">
                                                                <?php
                                                                $detail = $details[$recordHeaderId] ?? null;
                                                                $downtimeId = $detail['DowntimeId'] ?? null;
                                                                $actionTakenId = $detail['ActionTakenId'] ?? null;

                                                                $downtimeCode = $downtimeMap[$downtimeId]['DowntimeCode'] ?? null;
                                                                $actionTakenTitle = $actionTakenMap[$actionTakenId]['ActionDescription'] ?? 'No Description';
                                                                ?>

                                                                <?php if ($downtimeCode): ?>
                                                                    <small class="badge bg-danger text-white me-1 mb-1"
                                                                        title="<?= htmlspecialchars($actionTakenTitle) ?>">
                                                                        <?= htmlspecialchars($downtimeCode) ?>
                                                                    </small>
                                                                <?php else: ?>
                                                                    <small class="badge bg-secondary text-white me-1 mb-1">No Downtime</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>

                                                <!-- Delete -->
                                                <td class="text-center align-middle" style="width: 5%;">
                                                    <button type="button" class="btn btn-outline-danger btn-sm delete-row"
                                                        data-row-id="<?= $i ?>" title="Delete Row">
                                                        <span style="font-size: 1.2rem; font-weight: bold;">×</span>
                                                    </button>
                                                </td>
                                            </tr>

                                            <?php
                                            // Operator Modal
                                            ob_start(); ?>
                                            <div class="modal fade operator-modal"
                                                id="operatorModal<?= $header['RecordHeaderId'] ?>"
                                                tabindex="-1"
                                                aria-labelledby="operatorModalLabel<?= $header['RecordHeaderId'] ?>"
                                                aria-hidden="true"
                                                data-record-id="<?= $header['RecordHeaderId'] ?>">

                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <div class="modal-content shadow">

                                                        <!-- Modal Header -->
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="operatorModalLabel<?= $header['RecordHeaderId'] ?>">
                                                                Manage Operators for Row #<?= htmlspecialchars($i) ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>

                                                        <!-- Modal Body -->
                                                        <div class="modal-body">
                                                            <!-- Search Box -->
                                                            <div class="mb-3">
                                                                <input type="text" class="form-control operator-search" placeholder="Search operator name or code...">
                                                                <div class="search-results mt-2"></div>
                                                            </div>

                                                            <!-- Current Selected Operators -->
                                                            <div class="current-operators d-flex flex-wrap gap-3 justify-content-start">
                                                                <?php foreach (array_unique($employeeCodes) as $code):
                                                                    $name = $operatorMap[$code] ?? 'No operator'; ?>
                                                                    <div class="card border-primary operator-card" data-code="<?= htmlspecialchars($code) ?>" style="min-width: 160px;">
                                                                        <div class="card-body text-center p-2">
                                                                            <h6 class="card-title mb-1"><?= htmlspecialchars($name) ?></h6>
                                                                            <small class="text-muted"><?= htmlspecialchars($code) ?></small>
                                                                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-remove-operator">
                                                                                <i class="bi bi-x-circle"></i> Remove
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>

                                                            <!-- Hidden Inputs -->
                                                            <input type="hidden"
                                                                class="updated-operators"
                                                                id="operatorsHidden<?= htmlspecialchars($header['RecordHeaderId']) ?>"
                                                                value="<?= htmlspecialchars(implode(',', $employeeCodes)) ?>">
                                                        </div>

                                                        <!-- Modal Footer -->
                                                        <div class="modal-footer">
                                                            <button type="button"
                                                                class="btn btn-success btn-save-operators"
                                                                data-row-id="<?= $i ?>">
                                                                Save Changes
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Downtime Modal -->
                                            <div class="modal fade"
                                                id="downtimeModal<?= $header['RecordHeaderId'] ?>"
                                                tabindex="-1"
                                                aria-labelledby="downtimeModalLabel<?= $header['RecordHeaderId'] ?>"
                                                aria-hidden="true"
                                                data-record-id="<?= $header['RecordHeaderId'] ?>">

                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content shadow">
                                                        <!-- Modal Header -->
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title" id="downtimeModalLabel<?= $header['RecordHeaderId'] ?>">
                                                                Manage Downtime for Row #<?= htmlspecialchars($i) ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>

                                                        <!-- Modal Body -->
                                                        <div class="modal-body">
                                                            <!-- Downtime Reason -->
                                                            <div class="mb-3">
                                                                <label for="downtimeSelect<?= $header['RecordHeaderId'] ?>" class="form-label">Downtime Reason</label>
                                                                <select id="downtimeSelect<?= $header['RecordHeaderId'] ?>" class="form-select downtime-select">
                                                                    <option value="">-- Select Downtime --</option>
                                                                    <?php foreach ($downtimeOptions as $downtime): ?>
                                                                        <option value="<?= htmlspecialchars($downtime['DowntimeId']) ?>">
                                                                            <?= htmlspecialchars($downtime['DowntimeCode'] . ' - ' . $downtime['DowntimeName']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <!-- Action Taken -->
                                                            <div class="mb-3">
                                                                <label for="actionTakenSelect<?= $header['RecordHeaderId'] ?>" class="form-label">Action Taken</label>
                                                                <select id="actionTakenSelect<?= $header['RecordHeaderId'] ?>" class="form-select action-select">
                                                                    <option value="">-- Select Action Taken --</option>
                                                                    <?php foreach ($actionTakenOptions as $option): ?>
                                                                        <option value="<?= htmlspecialchars($option['ActionTakenId']) ?>">
                                                                            <?= htmlspecialchars($option['ActionTakenCode'] . ' - ' . $option['ActionTakenName']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>

                                                            <!-- PIC Input -->
                                                            <div class="mb-3">
                                                                <label for="picInput<?= $header['RecordHeaderId'] ?>" class="form-label">PIC (Person In Charge)</label>
                                                                <input type="text" id="picInput<?= $header['RecordHeaderId'] ?>" class="form-control pic-input" placeholder="Enter PIC name">
                                                            </div>

                                                            <!-- Hidden Record Header ID -->
                                                            <input type="hidden" class="downtimeTargetRow" value="<?= $header['RecordHeaderId'] ?>">
                                                        </div>

                                                        <!-- Modal Footer -->
                                                        <div class="modal-footer">
                                                            <button type="button"
                                                                class="btn btn-danger btn-save-downtime"
                                                                data-record-id="<?= $header['RecordHeaderId'] ?>">
                                                                Save Downtime
                                                            </button>
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                            $modals[] = ob_get_clean();
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <button type="submit" name="btnSubmit" id="hiddenSubmit" style="display: none;">Submit</button>
                    </div>
            </div>
        </div>
    </form>

    <?php
    echo implode("\n", $modals);
    ?>
    <?php
    $downtimeMap = $downtimeMap ?? [];
    $actionTakenMap = $actionTakenMap ?? [];
    ?>
    <script>
        window.downtimeMap = <?= json_encode($downtimeMap, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
        window.actionTakenMap = <?= json_encode($actionTakenMap, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
        window.operatorMap = <?php echo json_encode($operatorMap); ?> || {};
        console.log("Loaded downtimeMap:", window.downtimeMap);
    </script>



    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script src="../js/dor-downtimeTaken.js"></script>
    <script src="../js/dor-tab.js"></script>
</body>

</html>