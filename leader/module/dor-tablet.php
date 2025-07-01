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
                        <button type="submit    " class="btn btn-primary btn-lg nav-btn-group btn-nav-fixed" name="btnNext" id="btnNext">
                            <span class="short-label">Next</span>
                            <span class="long-label">Proceed to Next Checkpoint</span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
        <div class="container-fluid py-0 m-0">
            <!-- CheckpointA -->
            <div class="tab-content fixed-top" style="margin-top: 50px; display:none;" id="dorTabContent">
                <div class="tab-pane fade show active" id="tab-0" role="tabpanel">
                    <div class="table-responsive" style="max-height:90vh; margin-top: 10px; overflow: auto;">
                        <table class=" table table-bordered text-center align-middle w-100 h-100">
                            <thead class="table-light">
                                <tr>
                                    <th class="fs-6" colspan="6">A. Required Item and Jig Condition VS Work Instruction</th>
                                </tr>
                                <tr>
                                    <th class="fs-6" rowspan="2">Checkpoints</th>
                                    <th class="fs-6" colspan="2" rowspan="2">Criteria</th>
                                    <th class="fs-6" colspan="<?= count($processIndexes) ?>">Operator</th>
                                    <th class="fs-6" rowspan="2">Leader</th>
                                </tr>
                                <tr>
                                    <?php foreach ($processIndexes as $index): ?>
                                        <th>P<?= $index ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
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
                                                <td class="text-start fs-6" rowspan="<?= count($group) ?>">
                                                    <?= $cp['SequenceId']; ?>. <?= htmlspecialchars($cp['CheckpointName']); ?>
                                                </td>
                                            <?php endif; ?>

                                            <td class="fs-6" colspan="<?= $colspanGood; ?>"><?= htmlspecialchars($good); ?></td>
                                            <?php if (!empty($notGood)): ?>
                                                <td class="fs-6"><?= htmlspecialchars($notGood); ?></td>
                                            <?php endif; ?>

                                            <?php if (!empty($processIndexes)): ?>
                                                <?php foreach ($processIndexes as $procIdx): ?>
                                                    <td class="fs-6"><?= htmlspecialchars($operatorResponses[$checkpointId][$procIdx] ?? '-') ?></td>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <td class="fs-6">No operator data available</td>
                                            <?php endif; ?>

                                            <td class="py-2 px-3 fs-6" style="min-width: 150px; max-width: 200px;">
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
                        <button type="submit" name="btnSubmit" id="hiddenVisual" style="display: none;">Submit</button>
                    </div>
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
                    <div class="table-wrapper" style="max-height: 90vh; margin-top: 10px; overflow:auto;">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="fs-6" colspan="10">C. Dimension Check</th>
                                </tr>
                                <tr>
                                    <th rowspan="2" style="min-width: 50px;">No.</th>
                                    <?php foreach (['Hatsumono', 'Nakamono', 'Owarimono'] as $section): ?>
                                        <th class="fs-6" colspan="3"><?= $section ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <?php for ($i = 1; $i <= 9; $i++): ?>
                                        <th class="fs-6"><?= (($i - 1) % 3) + 1 ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <?php $row = $indexedDimChecks[$i] ?? []; ?>
                                    <tr>
                                        <td class="fs-6" style="min-width: 100px;"><?= $i ?></td>

                                        <input type="hidden" name="dim_check_id[<?= $i ?>]" value="<?= $row['DimCheckId'] ?? '' ?>">

                                        <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                                            <?php for ($j = 1; $j <= 3; $j++): ?>
                                                <td class="text-center fs-6">
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

                                <!-- Judge row (same as your previous logic) -->
                                <tr>
                                    <td class="fw-bold text-center fs-6">Judge</td>
                                    <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <td class="fs-6">
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
                        <button type="submit" name="btnSubmit" id="hiddenSubmit" style="display: none;">Submit</button>
                    </div>
                </div>
                <!-- Tab Pane -->
                <div class="tab-pane fade" id="tab-3" role="tabpanel">
                    <div class="container-fluid py-2">
                        <div class="table-wrapper" style="margin-top:10px; max-height: 90vh; overflow: auto;">
                            <table class="table table-bordered table-dor align-middle text-nowrap w-100">
                                <thead class="table-light position-sticky top-0 text-center" style="z-index: 1;">
                                    <tr>
                                        <th colspan="8">Daily Operation Record</th>
                                    </tr>
                                    <tr>
                                        <th>#</th>
                                        <th>Box No.</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Operator</th>
                                        <th>Downtime</th>
                                        <th>*</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Prepare operator details
                                    $modals = [];
                                    $detailsById = [];
                                    foreach ($details as $d) {
                                        if (isset($d['RecordHeaderId'])) {
                                            $detailsById[$d['RecordHeaderId']] = $d;
                                        }
                                    }

                                    // Fallback operator logic (session or hardcoded)
                                    $fallbackEmployeeCodes = [];
                                    for ($j = 1; $j <= 4; $j++) {
                                        if (!empty($_SESSION["userCode$j"])) {
                                            $fallbackEmployeeCodes[] = strtoupper(trim($_SESSION["userCode$j"]));
                                        }
                                    }

                                    $firstRowHeader = $headers[0] ?? [];
                                    $firstRowRecordId = $firstRowHeader['RecordHeaderId'] ?? null;
                                    $sharedEmployeeCodes = [];

                                    if ($firstRowHeader && isset($detailsById[$firstRowRecordId])) {
                                        $detail = $detailsById[$firstRowRecordId];
                                        for ($j = 1; $j <= 4; $j++) {
                                            $key = "OperatorCode$j";
                                            if (!empty($detail[$key])) {
                                                $sharedEmployeeCodes[] = strtoupper(trim($detail[$key]));
                                            }
                                        }
                                    }

                                    for ($i = 1; $i <= 20; $i++) {
                                        $header = $headers[$i - 1] ?? [];
                                        $recordHeaderId = $header['RecordHeaderId'] ?? 'unknown_' . $i;

                                        // Get employee codes
                                        $employeeCodes = $sharedEmployeeCodes;

                                        $modalId = "operatorModal_" . htmlspecialchars($recordHeaderId);
                                    ?>
                                        <tr data-row-id="<?= $i ?>">
                                            <td class="text-center align-middle">
                                                <?= $i ?> <i class="bi bi-qr-code-scan ms-1"></i>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control text-center scan-box-no"
                                                    id="boxNo<?= $i ?>" name="boxNo<?= $i ?>"
                                                    value="<?= htmlspecialchars($header['BoxNumber'] ?? '') ?>" disabled>
                                                <input type="hidden" id="modelName<?= $i ?>" name="modelName<?= $i ?>">
                                                <input type="hidden" id="lotNumber<?= $i ?>" name="lotNumber<?= $i ?>">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control text-center time-input"
                                                    id="timeStart<?= $i ?>" name="timeStart<?= $i ?>"
                                                    value="<?= isset($header['TimeStart']) ? date('H:i', $header['TimeStart'] instanceof \DateTime ? $header['TimeStart']->getTimestamp() : strtotime($header['TimeStart'])) : '' ?>"
                                                    placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" disabled>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control text-center time-input"
                                                    id="timeEnd<?= $i ?>" name="timeEnd<?= $i ?>"
                                                    value="<?= isset($header['TimeEnd']) ? date('H:i', $header['TimeEnd'] instanceof \DateTime ? $header['TimeEnd']->getTimestamp() : strtotime($header['TimeEnd'])) : '' ?>"
                                                    placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" disabled>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span id="duration<?= $i ?>" class="duration-value">
                                                    <?= htmlspecialchars($header['Duration'] ?? '') ?>
                                                </span>
                                            </td>

                                            <!-- Operator -->
                                            <td class="text-center align-middle">
                                                <div class="d-flex flex-column align-items-center">
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-operator mb-1"
                                                        data-bs-toggle="modal" data-bs-target="#operatorModal<?= $recordHeaderId ?>" id="operator<?= $recordHeaderId ?>">
                                                        <i class="bi bi-person-plus"></i> View Operators
                                                    </button>

                                                    <!-- Badge container with unique ID -->
                                                    <div class="operator-codes d-flex flex-wrap justify-content-center" id="operatorList<?= $recordHeaderId ?>">
                                                        <?php foreach (array_unique($employeeCodes) as $code):
                                                            $name = $operatorMap[$code] ?? 'No operators'; ?>
                                                            <small class="badge bg-light text-dark border me-1 mb-1"
                                                                title="<?= htmlspecialchars($name) ?>">
                                                                <?= htmlspecialchars($code) ?>
                                                            </small>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <!-- Hidden input to sync operator values (MUST be closed properly) -->
                                                    <input type="hidden" id="operators<?= htmlspecialchars($recordHeaderId) ?>"
                                                        value="<?= htmlspecialchars(implode(',', $employeeCodes)) ?>">
                                                </div>
                                            </td>


                                          <!-- Downtime -->
<td class="text-center align-middle">
    <?php $recordHeaderId = $header['RecordHeaderId'] ?? null; ?>

    <div class="d-flex flex-column align-items-center mt-md-4">
        <!-- View Downtime Button -->
        <button type="button"
                class="btn btn-outline-secondary btn-sm mb-1 downtime-trigger"
                data-bs-toggle="modal"
                data-bs-target="#downtimeModal"
                data-record-id="<?= htmlspecialchars($recordHeaderId) ?>">
            <i class="bi bi-clock-history"></i> View Downtime
        </button>

        <!-- Downtime Info Badges -->
        <div class="downtime-info d-flex flex-wrap justify-content-center"
             id="downtimeInfo<?= htmlspecialchars($recordHeaderId) ?>">
            <?php if (!empty($details[$recordHeaderId]) && is_array($details[$recordHeaderId])): ?>
                <?php foreach ($details[$recordHeaderId] as $detail): ?>
                    <?php
                        $downtimeId = $detail['DowntimeId'] ?? null;
                        $actionTakenId = $detail['ActionTakenId'] ?? null;

                        $downtimeCode = $downtimeId && isset($downtimeMap[$downtimeId])
                            ? $downtimeMap[$downtimeId]['DowntimeCode']
                            : null;

                        $actionTakenTitle = $actionTakenId && isset($actionTakenMap[$actionTakenId])
                            ? $actionTakenMap[$actionTakenId]['ActionDescription']
                            : 'No Description';
                    ?>

                    <?php if (!empty($downtimeCode)): ?>
                        <small class="badge bg-danger text-white me-1 mb-1"
                               title="<?= htmlspecialchars($actionTakenTitle) ?>">
                            <?= htmlspecialchars($downtimeCode) ?>
                        </small>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <small class="badge bg-secondary text-white me-1 mb-1">No Downtime</small>
            <?php endif; ?>
        </div>
    </div>
</td>


                                            <!-- Delete -->
                                            <td class="text-center align-middle">
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
                                            data-record-id="<?= $header['RecordHeaderId'] ?>"
                                            data-record-detail-id="<?= $header['RecordDetailId'] ?>"> <!-- add RecordDetailId -->

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

                                                        <input type="hidden"
                                                            class="form-control"
                                                            id="operators<?= htmlspecialchars($header['RecordHeaderId']) ?>"
                                                            value="<?= htmlspecialchars(implode(',', $employeeCodes)) ?>">

                                                        <!-- Optional: Output area for badges outside modal -->
                                                        <!-- <div id="operatorList<?= htmlspecialchars($header['RecordHeaderId']) ?>" class="mt-3">
                    <?php foreach ($employeeCodes as $code):
                                            $name = $operatorMap[$code] ?? 'Unknown'; ?>
                        <small class="badge bg-light text-dark border me-1 mb-1" title="<?= htmlspecialchars($name) ?>">
                            <?= htmlspecialchars($code) ?>
                        </small>
                    <?php endforeach; ?>
                </div> -->
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


                                        <div class="modal fade" id="downtimeModal" tabindex="-1" aria-labelledby="downtimeModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content shadow">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title" id="downtimeModalLabel">Manage Downtime</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <!-- Downtime Reason -->
                                                        <div class="mb-3">
                                                            <label for="downtimeSelect" class="form-label">Downtime Reason</label>
                                                            <select id="downtimeSelect" class="form-select">
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
                                                            <label for="actionTakenSelect" class="form-label">Action Taken</label>
                                                            <select id="actionTakenSelect" class="form-select">
                                                                <option value="">-- Select Action Taken --</option>
                                                                <?php foreach ($actionTakenOptions as $option): ?>
                                                                    <option value="<?= htmlspecialchars($option['ActionTakenId']) ?>">
                                                                        <?= htmlspecialchars($option['ActionTakenCode'] . ' - ' . $option['ActionTakenName']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                                <pre><?php print_r($details[$recordHeaderId]); ?></pre>

                                                            </select>
                                                        </div>

                                                        <!-- PIC Input -->
                                                        <div class="mb-3">
                                                            <label for="picInput" class="form-label">PIC (Person In Charge)</label>
                                                            <input type="text" id="picInput" class="form-control" placeholder="Enter PIC name">
                                                        </div>

                                                        <!-- Hidden Target Row ID -->
                                                        <input type="hidden" id="downtimeTargetRow">
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-danger" id="saveDowntime">Save Downtime</button>
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
                </div>

            </div>
        </div>


        <?php
        echo implode("\n", $modals);
        ?>
    </form>
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script>
        window.downtimeMap = <?= json_encode($downtimeOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        window.actionTakenMap = <?= json_encode($actionTakenOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab Handling
            const tabPanes = Array.from(document.querySelectorAll('#dorTabContent > .tab-pane')).slice(0, 4);
            const tabInput = document.getElementById("currentTabInput");
            const form = document.querySelector('form');
            const operatorMap = <?= json_encode($operatorMap) ?>;

            let urlTabParam = new URLSearchParams(window.location.search).get("tab");
            let parsedIndex = parseInt(urlTabParam);
            if (isNaN(parsedIndex)) parsedIndex = parseInt(tabInput.value) || 0;
            parsedIndex = Math.max(0, Math.min(parsedIndex, tabPanes.length - 1));
            let currentTabIndex = parsedIndex;

            function showTab(index) {
                if (index < 0 || index >= tabPanes.length) return;

                tabPanes.forEach(tab => tab.classList.remove('show', 'active'));
                tabPanes[index].classList.add('show', 'active');
                tabInput.value = index;

                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('tab', index);
                window.history.replaceState({}, '', newUrl);

                const nestedNav = tabPanes[index].querySelector('.nav-tabs');
                const nestedContent = tabPanes[index].querySelector('.tab-content');
                if (nestedNav && nestedContent) {
                    const activeBtn = nestedNav.querySelector('.nav-link.active') || nestedNav.querySelector('.nav-link');
                    const targetSelector = activeBtn?.getAttribute('data-bs-target');

                    nestedNav.querySelectorAll('.nav-link').forEach(btn => btn.classList.remove('active'));
                    nestedContent.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('show', 'active'));

                    activeBtn?.classList.add('active');
                    if (targetSelector) {
                        const targetContent = nestedContent.querySelector(targetSelector);
                        targetContent?.classList.add('show', 'active');
                    }
                }

                const btnNext = document.getElementById('btnNext');
                if (btnNext) {
                    btnNext.textContent = (index === tabPanes.length - 1) ? 'Submit' : 'Next';
                }
            }

            // Tab Navigation
            document.getElementById('btnNext').addEventListener('click', function(e) {
                e.preventDefault();
                const currentTab = tabPanes[currentTabIndex];

                if (currentTabIndex === 0 && form) {
                    const tabInputs = currentTab.querySelectorAll('input, select, textarea');
                    const tabData = new FormData();

                    tabInputs.forEach(input => {
                        if (!input.name) return;
                        if ((input.type === 'radio' || input.type === 'checkbox')) {
                            if (input.checked) tabData.append(input.name, input.value);
                        } else {
                            tabData.append(input.name, input.value);
                        }
                    });

                    tabData.append('tab', '0');
                    tabData.append('btnVisual', '1');
                    tabData.append('record_id', form.querySelector('input[name="record_id"]').value);
                    tabData.append('current_tab_index', currentTabIndex);

                    fetch(form.action, {
                            method: 'POST',
                            body: tabData
                        })
                        .then(res => {
                            if (!res.ok) throw new Error('Failed to save Tab 0');
                            return res.text();
                        })
                        .then(() => {
                            currentTabIndex++;
                            showTab(currentTabIndex);
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Could not save Tab 0. Please try again.');
                        });

                    return;
                }

                if (currentTabIndex < tabPanes.length - 1) {
                    currentTabIndex++;
                    showTab(currentTabIndex);
                } else {
                    window.location.href = 'dor-leader-dashboard.php';
                }
            });

            document.getElementById('btnBack').addEventListener('click', function(e) {
                e.preventDefault();
                if (currentTabIndex > 0) {
                    currentTabIndex--;
                    showTab(currentTabIndex);
                }
            });

            showTab(currentTabIndex);
            document.getElementById('dorTabContent').style.display = 'block';

            // === Operator Modal Logic ===
            document.querySelectorAll('.operator-modal').forEach(modal => {
                const recordHeaderId = modal.dataset.recordId;
                const hiddenInput = document.getElementById(`operatorsHidden${recordHeaderId}`);
                const visibleInput = document.getElementById(`operators${recordHeaderId}`);
                const badgeList = document.getElementById(`operatorList${recordHeaderId}`);
                const modalBody = modal.querySelector('.current-operators');

                function updateDisplay(codes) {
                    const html = codes.map(code => {
                        const name = operatorMap[code] || 'Unknown';
                        return `<small class="badge bg-light text-dark border me-1 mb-1" title="${name}">${code}</small>`;
                    }).join('');
                    badgeList.innerHTML = html;
                }

                function syncCode(code, action = 'add') {
                    let codes = hiddenInput.value.split(',').map(c => c.trim()).filter(Boolean);
                    const codeSet = new Set(codes);

                    if (action === 'add') codeSet.add(code);
                    else if (action === 'remove') codeSet.delete(code);

                    const updatedCodes = Array.from(codeSet);
                    hiddenInput.value = visibleInput.value = updatedCodes.join(',');
                    updateDisplay(updatedCodes);

                    if (action === 'add' && !modalBody.querySelector(`[data-code="${code}"]`)) {
                        const card = document.createElement('div');
                        card.className = 'card border-primary operator-card';
                        card.dataset.code = code;
                        card.innerHTML = `
                    <div class="card-body text-center p-2">
                        <h6 class="card-title mb-1">${operatorMap[code] || 'No operator'}</h6>
                        <small class="text-muted">${code}</small>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-remove-operator">
                            <i class="bi bi-x-circle"></i> Remove
                        </button>
                    </div>`;
                        modalBody.appendChild(card);
                    } else if (action === 'remove') {
                        modalBody.querySelectorAll(`[data-code="${code}"]`).forEach(el => el.remove());
                    }
                }

                modal.addEventListener('click', e => {
                    const btn = e.target.closest('.btn-remove-operator');
                    if (btn) {
                        const card = btn.closest('.operator-card');
                        if (card?.dataset.code) {
                            syncCode(card.dataset.code, 'remove');
                        }
                    }
                });

                modal.querySelector('.operator-search')?.addEventListener('input', function() {
                    const val = this.value.toLowerCase().trim();
                    const resultsBox = modal.querySelector('.search-results');
                    resultsBox.innerHTML = '';

                    if (!val) return;

                    let count = 0;
                    for (const [code, name] of Object.entries(operatorMap)) {
                        if (code.toLowerCase().includes(val) || name.toLowerCase().includes(val)) {
                            if (count++ >= 10) break;

                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-outline-primary btn-sm me-2 mb-2';
                            btn.textContent = `${name} (${code})`;
                            btn.addEventListener('click', () => {
                                syncCode(code, 'add');
                                this.value = '';
                                resultsBox.innerHTML = '';
                            });

                            resultsBox.appendChild(btn);
                        }
                    }

                    if (count === 0) {
                        resultsBox.innerHTML = '<small class="text-muted">No matches found.</small>';
                    }
                });
            });

            // === Save Operators
            // === Save Operators ===
            document.querySelectorAll('.btn-save-operators').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modal = this.closest('.operator-modal');
                    const recordHeaderId = modal.dataset.recordId;

                    const operatorCards = modal.querySelectorAll('.operator-card');
                    const selectedCodes = [...new Set(Array.from(operatorCards).map(card => card.dataset.code).filter(Boolean))];

                    if (selectedCodes.length === 0) {
                        alert('No operator codes selected.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = 'Saving...';

                    fetch('../controller/dor-dor.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                type: 'saveOperators',
                                recordHeaderId,
                                employeeCodes: selectedCodes
                            })
                        })
                        .then(res => res.json())
                        .then(json => {
                            btn.disabled = false;
                            btn.innerHTML = 'Save Changes';

                            if (json.success) {
                                alert('Operators saved.');
                                location.reload();

                                // === Update the operator badges on the row
                                const operatorContainer = document.getElementById(`operatorList${recordHeaderId}`);
                                const operatorInput = document.getElementById(`operators${recordHeaderId}`);
                                operatorInput.value = selectedCodes.join(',');

                                operatorContainer.innerHTML = '';

                                if (selectedCodes.length === 0) {
                                    operatorContainer.innerHTML = '<small class="badge bg-secondary text-white">No Operators</small>';
                                } else {
                                    selectedCodes.forEach(code => {
                                        // Optional: Add tooltip with name if available from a global map
                                        const name = window.operatorMap?.[code] || '';
                                        operatorContainer.innerHTML += `
                            <small class="badge bg-light text-dark border me-1 mb-1" title="${name}">
                                ${code}
                            </small>`;
                                    });
                                }

                                const modalInstance = bootstrap.Modal.getInstance(modal);
                                if (modalInstance) modalInstance.hide();
                            } else {
                                alert(json.message || 'Save failed.');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            btn.disabled = false;
                            btn.innerHTML = 'Save Changes';
                            alert('Server error while saving.');
                        });
                });
            });


            // === Downtime Modal ===
            const downtimeSelect = document.getElementById('downtimeSelect');
            const actionSelect = document.getElementById('actionTakenSelect');
            const picInput = document.getElementById('picInput');
            let selectedRecordId = null;
            let selectedPic = null;

            // Handle trigger button click
            document.querySelectorAll('.downtime-trigger').forEach(button => {
                button.addEventListener('click', function() {
                    selectedRecordId = this.dataset.recordId || null;
                    selectedPic = this.dataset.pic || document.getElementById('picField')?.value || '';

                    // Clear previous values in modal
                    downtimeSelect.value = '';
                    actionSelect.value = '';
                    if (picInput) {
                        picInput.value = selectedPic;
                    }

                    if (!selectedRecordId) {
                        console.error("Missing data-record-id on downtime trigger.");
                    }
                });
            });

            // Handle Save button click
            document.getElementById("saveDowntime").addEventListener("click", function() {
                const downtimeId = downtimeSelect.value;
                const actionTakenId = actionSelect.value;
                selectedPic = picInput?.value?.trim();

                if (!selectedRecordId || !downtimeId || !actionTakenId || !selectedPic) {
                    alert("Please fill out all fields, including PIC.");
                    return;
                }

                console.log("Saving downtime with data:", {
                    selectedRecordId,
                    downtimeId,
                    actionTakenId,
                    selectedPic
                });

                fetch("../controller/dor-dor.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            type: "saveActionDowntime",
                            recordHeaderId: selectedRecordId,
                            downtimeId,
                            actionTakenId,
                            pic: selectedPic
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert("Downtime saved successfully!");

                            const container = document.getElementById(`downtimeInfo${selectedRecordId}`);
                            if (container) {
                                const downtimeCode = window.downtimeMap?.[downtimeId]?.DowntimeCode || '???';
                                const actionDesc = window.actionTakenMap?.[actionTakenId]?.ActionTakenName || 'Action info missing';

                                const newBadge = document.createElement('small');
newBadge.className = 'badge bg-danger text-white me-1 mb-1';
newBadge.title = actionDesc;
newBadge.textContent = downtimeCode;

container.appendChild(newBadge);

                            }

                            // Hide modal
                            const modalElement = document.getElementById("downtimeModal");
                            if (modalElement) {
                                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                                modalInstance?.hide();
                            }

                            // Reset variables
                            selectedRecordId = null;
                            selectedPic = null;
                            downtimeSelect.value = '';
                            actionSelect.value = '';
                            if (picInput) picInput.value = '';
                        } else {
                            alert("Failed to save downtime: " + (data.message || "Unknown error."));
                        }
                    })
                    .catch(err => {
                        console.error("Fetch error:", err);
                        alert("An error occurred while saving downtime.");
                    });
            });



        });
    </script>







</body>

</html>