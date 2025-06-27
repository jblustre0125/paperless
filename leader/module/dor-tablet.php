<?php
ob_start();
session_start();
$title = "DOR Dashboard";
require_once '../controller/dor-checkpoint-definition.php';
require_once '../controller/dor-visual-checkpoint.php';
require_once '../controller/dor-dimension-checkpoint.php';
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
    <!-- <link rel="stylesheet" href="../../css/dor-form.css"> -->


</head>

<body>
    <form method="POST">
        <nav class="navbar navbar-expand navbar-light bg-light shadow-sm fixed-top">
            <div class="container-fluid px-2 py-2">
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
        <div class="container-fluid">
            <!-- CheckpointA -->
            <div class="tab-content fixed-top" style="margin-top: 50px; display:none;" id="dorTabContent">
                <div class="tab-pane fade show active" id="tab-0" role="tabpanel">
                    <div class="table-responsive" style="max-height: 90vh; margin-top: 10px; overflow: auto;">
                        <table class=" table table-bordered text-center align-middle w-100 h-100">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="6">A. Required Item and Jig Condition VS Work Instruction</th>
                                </tr>
                                <tr>
                                    <th rowspan="2">Checkpoints</th>
                                    <th colspan="2" rowspan="2">Criteria</th>
                                    <th colspan="<?= count($processIndexes) ?>">Operator</th>
                                    <th rowspan="2">Leader</th>
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
                                                <td class="text-start" rowspan="<?= count($group) ?>">
                                                    <?= $cp['SequenceId']; ?>. <?= htmlspecialchars($cp['CheckpointName']); ?>
                                                </td>
                                            <?php endif; ?>

                                            <td colspan="<?= $colspanGood; ?>"><?= htmlspecialchars($good); ?></td>
                                            <?php if (!empty($notGood)): ?>
                                                <td><?= htmlspecialchars($notGood); ?></td>
                                            <?php endif; ?>

                                            <?php if (!empty($processIndexes)): ?>
                                                <?php foreach ($processIndexes as $procIdx): ?>
                                                    <td class="operator-cell"><?= htmlspecialchars($operatorResponses[$checkpointId][$procIdx] ?? '-') ?></td>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <td>No operator data available</td>
                                            <?php endif; ?>

                                            <td class="py-2 px-3" style="min-width: 150px; max-width: 200px;">
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
                                        <table class="table table-bordered text-center align-middle" style="min-width: 250px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th colspan="4">B. Visual Inspection Checkpoint</th>
                                                </tr>
                                                <tr>
                                                    <th>Checkpoints</th>
                                                    <th colspan="2">Criteria</th>
                                                    <th><?= $tab ?></th>
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
                                                                <td class="text-start" rowspan="<?= count($group) ?>" style="min-width: 100px;">
                                                                    <?= $v['SequenceId'] ?>. <?= htmlspecialchars($v['CheckpointName']) ?>
                                                                </td>
                                                            <?php endif; ?>

                                                            <td colspan="<?= empty($v['CriteriaNotGood']) ? 2 : 1 ?>" style="min-width: 100px;">
                                                                <?= htmlspecialchars($v['CriteriaGood']) ?>
                                                            </td>

                                                            <?php if (!empty($v['CriteriaNotGood'])): ?>
                                                                <td style="min-width: 100px;">
                                                                    <?= htmlspecialchars($v['CriteriaNotGood']) ?>
                                                                </td>
                                                            <?php endif; ?>

                                                            <td style="min-width: 250px;">
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
                                    <th colspan="10">C. Dimension Check</th>
                                </tr>
                                <tr>
                                    <th rowspan="2" style="min-width: 50px;">No.</th>
                                    <?php foreach (['Hatsumono', 'Nakamono', 'Owarimono'] as $section): ?>
                                        <th colspan="3"><?= $section ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <?php for ($i = 1; $i <= 9; $i++): ?>
                                        <th><?= (($i - 1) % 3) + 1 ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 20; $i++): ?>
                                    <?php $row = $indexedDimChecks[$i] ?? []; ?>
                                    <tr>
                                        <td style="min-width: 100px;"><?= $i ?></td>

                                        <input type="hidden" name="dim_check_id[<?= $i ?>]" value="<?= $row['DimCheckId'] ?? '' ?>">

                                        <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                                            <?php for ($j = 1; $j <= 3; $j++): ?>
                                                <td class="text-center">
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
                                    <td class="fw-bold text-center">Judge</td>
                                    <?php foreach (['hatsumono', 'nakamono', 'owarimono'] as $section): ?>
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <td>
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
            </div>
        </div>



    </form>
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabPanes = Array.from(document.querySelectorAll('#dorTabContent > .tab-pane')).slice(0, 4);
            const tabInput = document.getElementById("currentTabInput");

            // Correct tab index parsing and clamping
            let urlTabParam = new URLSearchParams(window.location.search).get("tab");
            let parsedIndex = parseInt(urlTabParam);
            if (isNaN(parsedIndex)) {
                parsedIndex = parseInt(tabInput.value) || 0;
            }
            if (parsedIndex < 0) parsedIndex = 0;
            if (parsedIndex >= tabPanes.length) parsedIndex = tabPanes.length - 1;

            let currentTabIndex = parsedIndex;

            function showTab(index) {
                if (index < 0 || index >= tabPanes.length) return;

                tabPanes.forEach(tab => tab.classList.remove('show', 'active'));
                tabPanes[index].classList.add('show', 'active');
                tabInput.value = index;

                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('tab', index);
                window.history.replaceState({}, '', newUrl);

                // Handle nested visual tab activation
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
            }

            // Next button
            // Next button
            document.getElementById('btnNext').addEventListener('click', function(e) {
                e.preventDefault();

                const currentTab = tabPanes[currentTabIndex];
                const form = document.querySelector('form');

                // Validate radios (optional)
                let radios = [...currentTab.querySelectorAll('input[type="radio"]')];
                const radioGroups = Array.from(new Set(radios.map(r => r.name)));
                const allAnswered = radioGroups.every(name =>
                    currentTab.querySelector(`input[name="${name}"]:checked`)
                );

                if (currentTabIndex === 0 && form) {
                    // Only collect inputs from current tab
                    const tabInputs = currentTab.querySelectorAll('input, select, textarea');
                    const tabData = new FormData();

                    tabInputs.forEach(input => {
                        if (!input.name) return;

                        if ((input.type === 'radio' || input.type === 'checkbox')) {
                            if (input.checked) {
                                tabData.append(input.name, input.value);
                            }
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
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to save Tab 0');
                            return response.text();
                        })
                        .then(data => {
                            console.log("✅ Tab 0 saved:", data);
                            currentTabIndex++;
                            showTab(currentTabIndex);
                            tabInput.value = currentTabIndex;
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Could not save Tab 0. Please try again.');
                        });

                    return;
                }

                // ✅ Normal next tab for Tab 1, 2
                if (currentTabIndex < tabPanes.length - 1) {
                    currentTabIndex++;
                    showTab(currentTabIndex);
                    tabInput.value = currentTabIndex;
                } else {
                    // ✅ Final full submission
                    if (form) {
                        this.disabled = true;
                        this.innerHTML = 'Submitting...';

                        const submitBtn = document.getElementById('hiddenSubmit');
                        if (submitBtn) {
                            submitBtn.click();
                        } else {
                            form.submit();
                        }
                    }
                }
            });



            // Back button
            document.getElementById('btnBack').addEventListener('click', function(e) {
                e.preventDefault();
                if (currentTabIndex > 0) {
                    currentTabIndex--;
                    showTab(currentTabIndex);
                }
            });

            showTab(currentTabIndex);
            document.getElementById('dorTabContent').style.display = 'block';
        });
    </script>



</body>

</html>