<?php
require_once '../../config/dbop.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('LEADER_PROCESS_INDEX', 5);

$db = new dbOp(1);

// Get hostname_id
$hostname_id = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : 0;
if ($hostname_id <= 0) {
    echo "Invalid HostnameId.";
    exit;
}

// Get latest AtoDor record
$sql = "SELECT TOP 1 RecordId, DorTypeId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
$result = $db->execute($sql, [$hostname_id]);

if (!is_array($result) || count($result) === 0) {
    $_SESSION['flash_message'] = "No record found for the selected tablet.";
    header("Location: dor-leader-dashboard.php");
    exit;
}

$recordId = $result[0]['RecordId'];
$dorTypeId = $result[0]['DorTypeId'];

// Get checkpoints
$sql = "SELECT CheckpointId, SequenceId, CheckpointName, CriteriaNotGood, CriteriaGood
        FROM GenDorCheckpointDefinition
        WHERE DorTypeId = ? AND IsActive = 1
        ORDER BY SequenceId";
$checkpoints = $db->execute($sql, [$dorTypeId]);

// Get operator responses
$sql = "SELECT EmployeeCode, CheckpointId, CheckpointResponse, ProcessIndex
        FROM AtoDorCheckpointDefinition
        WHERE RecordId = ? AND IsLeader = 0
        ORDER BY ProcessIndex, CheckpointId";
$operatorRaw = $db->execute($sql, [$recordId]);

$operatorResponses = [];
$processIndexes = [];
foreach ($operatorRaw as $row) {
    $cpId = $row['CheckpointId'];
    $procIdx = $row['ProcessIndex'];
    $operatorResponses[$cpId][$procIdx] = $row['CheckpointResponse'];
    if (!in_array($procIdx, $processIndexes)) {
        $processIndexes[] = $procIdx;
    }
}
sort($processIndexes);

// Get leader responses
$sql = "SELECT CheckpointId, CheckpointResponse
        FROM AtoDorCheckpointDefinition
        WHERE RecordId = ? AND IsLeader = 1 AND ProcessIndex = ?";
$leaderRaw = $db->execute($sql, [$recordId, LEADER_PROCESS_INDEX]);
$leaderResponses = [];
foreach ($leaderRaw as $row) {
    $leaderResponses[$row['CheckpointId']] = $row['CheckpointResponse'];
}
$isTab0Saved = count($leaderResponses) > 0;


// ========== AJAX/POST Submission Handler ========== //

function sendJsonResponse($success, $message = '')
{
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function saveLeaderCheckpointResponses($db, $recordId, $employeeCode, $responses): array
{
    if (!$recordId || !$employeeCode || empty($responses)) {
        return ['success' => false, 'message' => 'Missing data'];
    }

    foreach ($responses as $checkpointId => $response) {
        $response = strtoupper(trim($response));
        if (!in_array($response, ['OK', 'NA', 'NG'])) {
            continue;
        }

        // Check for existing response
        $exists = $db->execute(
            "SELECT COUNT(*) AS cnt FROM AtoDorCheckpointDefinition WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1 AND ProcessIndex = ?",
            [$recordId, $checkpointId, LEADER_PROCESS_INDEX]
        );

        if (!empty($exists[0]['cnt'])) {
            continue; // ❌ Do NOT update
        }

        // Insert new leader response
        $db->execute(
            "INSERT INTO AtoDorCheckpointDefinition (RecordId, CheckpointId, ProcessIndex, EmployeeCode, CheckpointResponse, IsLeader)
             VALUES (?, ?, ?, ?, ?, 1)",
            [$recordId, $checkpointId, LEADER_PROCESS_INDEX, $employeeCode, $response]
        );
    }

    return ['success' => true, 'message' => 'Leader responses saved successfully'];
}

// AJAX handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    $employeeCode = $_SESSION['production_code'] ?? null;
    $json = $_POST['leader'] ?? [];
    $responses = json_decode($json, true);

    if (!$employeeCode || !$recordId) {
        sendJsonResponse(false, 'Missing employee/session data');
    }

    $result = saveLeaderCheckpointResponses($db, $recordId, $employeeCode, $responses);
    sendJsonResponse($result['success'], $result['message']);
}

// Fallback: regular form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnVisual'])) {
    $employeeCode = $_SESSION['production_code'] ?? null;
    $responses = $_POST['leader'] ?? [];

    if (!$employeeCode || !$recordId) {
        echo "Missing data.";
        exit;
    }

    saveLeaderCheckpointResponses($db, $recordId, $employeeCode, $responses);

    $nextTab = (int)($_POST['current_tab_index'] ?? 0) + 1;
    header("Location: ?hostname_id={$hostname_id}&tab={$nextTab}");
    exit;
}
