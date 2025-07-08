<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../config/dbop.php';

$db = new DbOp(1);
// Get Hostname ID from query string
$hostname_id = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id']
    : (isset($_POST['hostname_id']) ? (int)$_POST['hostname_id'] : 0);

if ($hostname_id <= 0) {
    echo "Invalid Hostname ID.";
    exit;
}

// Get latest AtoDor record for the given Hostname ID
$sql = "SELECT TOP 1 RecordId, DorTypeId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
$result = $db->execute($sql, [$hostname_id]);

if (!is_array($result) || count($result) === 0) {
    echo "No record found for HostnameId = $hostname_id";
    exit;
}

$recordId = $result[0]['RecordId'];
$dorTypeId = $result[0]['DorTypeId'];

// Get visual checkpoints and their existing responses
$sql = "
    SELECT 
        def.CheckpointId,
        def.SequenceId,
        def.CheckpointName,
        def.CriteriaGood,
        def.CriteriaNotGood,
        def.CheckpointTypeId,
        vis.RecordDetailId,
        vis.Hatsumono,
        vis.Nakamono,
        vis.Owarimono
    FROM GenDorCheckpointVisual def
    LEFT JOIN AtoDorCheckpointVisual vis
        ON def.CheckpointId = vis.CheckpointId AND vis.RecordId = ?
    WHERE def.DorTypeId = ? AND def.IsActive = 1
    ORDER BY def.SequenceId, def.CheckpointId
";

$visualCheckpoints = $db->execute($sql, [$recordId, $dorTypeId]);

// Map for input control types
$checkpointControlMap = [
    1 => ['OK', 'NG'],
    2 => ['text'],
    3 => ['OK', 'NG'],
    4 => ['OK', 'NG'],
    5 => ['WF', 'WOF']
];

function getShiftTime()
{
    $hour = (int)date('H');
    if ($hour >= 6 && $hour < 14) return 'Morning';
    if ($hour >= 14 && $hour < 22) return 'Afternoon';
    return 'Night';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnProceed'])) {
    $recordId = $_POST['record_id'] ?? 0;
    $productionCode = $_SESSION['production_code'] ?? null;
    if (!$productionCode) {
        die("Leader employee code is required from session.");
    }

    if (!$recordId || !is_numeric($recordId)) {
        echo "<div class='alert alert-danger'>Missing or invalid Record ID.</div>";
        exit;
    }

    $shift = getShiftTime();
    $errors = [];

    if (isset($_POST['visual']) && is_array($_POST['visual'])) {
        foreach ($_POST['visual'] as $checkpointId => $tabData) {
            $checkpointId = (int)$checkpointId;

            $hatsumono = trim($tabData['Hatsumono'] ?? '');
            $nakamono  = trim($tabData['Nakamono'] ?? '');
            $owarimono = trim($tabData['Owarimono'] ?? '');

            // Validation
            if ($shift === 'Morning' && $hatsumono === '') {
                $errors[] = "Hatsumono is required for checkpoint $checkpointId during Morning shift.";
                continue;
            }
            if ($shift === 'Night' && $owarimono === '') {
                $errors[] = "Owarimono is required for checkpoint $checkpointId during Night shift.";
                continue;
            }

            // Check if the record exists
            $sqlCheck = "SELECT RecordDetailId FROM AtoDorCheckpointVisual WHERE RecordId = ? AND CheckpointId = ?";
            $exists = $db->execute($sqlCheck, [$recordId, $checkpointId]);

            if (!empty($exists)) {
                // Update
                $sqlUpdate = "UPDATE AtoDorCheckpointVisual 
                              SET Hatsumono = ?, Nakamono = ?, Owarimono = ? 
                              WHERE RecordDetailId = ?";
                $db->execute($sqlUpdate, [$hatsumono, $nakamono, $owarimono, $exists[0]['RecordDetailId']]);
            } else {
                // Insert
                $sqlInsert = "INSERT INTO AtoDorCheckpointVisual 
                              (RecordId, CheckpointId, Hatsumono, Nakamono, Owarimono)
                              VALUES (?, ?, ?, ?, ?)";
                $db->execute($sqlInsert, [$recordId, $checkpointId, $hatsumono, $nakamono, $owarimono]);
            }
        }
    }

    // Show result
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(', ', $errors);
    } else {
        $_SESSION['flash_success'] = "Visual checkpoints saved successfully.";
    }

    // Stay on the same tab (do not increment)
    $currentTab = (int)($_POST['current_tab_index'] ?? 0);

    header("Location: ?hostname_id={$hostname_id}&tab={$currentTab}");
    exit;
}
