<?php
require_once '../../config/dbop.php';
$db = new dbOp(1);


//Get hostname_id
$hostname_id = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : 0;
if ($hostname_id <= 0) {
    echo "Invalid HostnameId.";
    exit;
}

//Get latest AtoDor record
$sql = "SELECT TOP 1 RecordId, DorTypeId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
$result = $db->execute($sql, [$hostname_id]);

if (!is_array($result) || count($result) === 0) {
    echo "No record found for HostnameId = $hostname_id";
    exit;
}

$recordId = $result[0]['RecordId'];
$dorTypeId = $result[0]['DorTypeId'];

//Get checkpoint definitions
$sql = "SELECT CheckpointId, SequenceId, CheckpointName, CriteriaNotGood, CriteriaGood
        FROM GenDorCheckpointDefinition
        WHERE DorTypeId = ? AND IsActive = 1
        ORDER BY SequenceId";
$checkpoints = $db->execute($sql, [$dorTypeId]);

// Get operator responses (grouped by ProcessIndex)
$sql = "SELECT EmployeeCode, CheckpointId, CheckpointResponse, ProcessIndex
        FROM AtoDorCheckpointDefinition
        WHERE RecordId = ? AND IsLeader = 0
        ORDER BY ProcessIndex, CheckpointId";
$operatorRaw = $db->execute($sql, [$recordId]);

$operatorResponses = []; // [checkpointId][processIndex] = response
$processIndexes = [];    // To track which indexes are used
foreach ($operatorRaw as $row) {
    $cpId = $row['CheckpointId'];
    $procIdx = $row['ProcessIndex'];
    $resp = $row['CheckpointResponse'];
    $operatorResponses[$cpId][$procIdx] = $resp;

    if (!in_array($procIdx, $processIndexes)) {
        $processIndexes[] = $procIdx;
    }
}
sort($processIndexes); // Ensure consistent display order

//Get leader responses
$sql = "SELECT CheckpointId, CheckpointResponse
        FROM AtoDorCheckpointDefinition
        WHERE RecordId = ? AND IsLeader = 1 AND ProcessIndex = 5";
$leaderRaw = $db->execute($sql, [$recordId]);
$leaderResponses = [];
foreach ($leaderRaw as $row) {
    $leaderResponses[$row['CheckpointId']] = $row['CheckpointResponse'];
}

function saveLeaderCheckpointResponses($db, $recordId, $employeeCode, $responses): bool
{
    if (!$recordId || !$employeeCode || empty($responses)) {
        return false;
    }

    foreach ($responses as $checkpointId => $response) {
        // Normalize and validate response
        $response = strtoupper(trim($response));
        if (!in_array($response, ['OK', 'NA', 'NG'])) {
            continue; // Skip invalid
        }

        // Check if a leader response already exists
        $exists = $db->execute(
            "SELECT COUNT(*) AS cnt FROM AtoDorCheckpointDefinition WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1",
            [$recordId, $checkpointId]
        );

        if (!empty($exists[0]['cnt'])) {
            // Update existing
            $db->execute(
                "UPDATE AtoDorCheckpointDefinition SET CheckpointResponse = ? WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1",
                [$response, $recordId, $checkpointId]
            );
        } else {
            // Insert new
            $db->execute(
                "INSERT INTO AtoDorCheckpointDefinition (RecordId, CheckpointId, ProcessIndex, EmployeeCode, CheckpointResponse, IsLeader)
                 VALUES (?, ?, 5, ?, ?, 1)",
                [$recordId, $checkpointId, $employeeCode, $response]
            );
        }
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnVisual'])) {
    $leaderResponses = $_POST['leader'] ?? [];
    $recordId = $_POST['record_id'];
    $employeeCode = $_SESSION['employee_code'] ?? null;

    if (!$employeeCode) {
        echo "Missing employee code for leader.";
        exit;
    }

    foreach ($leaderResponses as $checkpointId => $response) {
        $exists = $db->execute(
            "SELECT COUNT(*) AS cnt FROM AtoDorCheckpointDefinition WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1",
            [$recordId, $checkpointId]
        );

        if (!empty($exists[0]['cnt'])) {
            $db->execute(
                "UPDATE AtoDorCheckpointDefinition SET CheckpointResponse = ? WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1",
                [$response, $recordId, $checkpointId]
            );
        } else {
            $db->execute(
                "INSERT INTO AtoDorCheckpointDefinition (RecordId, CheckpointId, ProcessIndex, EmployeeCode, CheckpointResponse, IsLeader)
                 VALUES (?, ?, 5, ?, ?, 1)",
                [$recordId, $checkpointId, $employeeCode, $response]
            );
        }
    }


    $nextTab = (int)($_POST['current_tab_index'] ?? 0) + 1;
    header("Location: ?hostname_id={$hostname_id}&tab={$nextTab}");
    exit;
}
