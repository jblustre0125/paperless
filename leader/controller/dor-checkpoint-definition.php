<?php
require_once '../../config/dbop.php';


// Initialize database connection
$db = new dbOp(1);

// Get hostname_id from URL
$hostname_id = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : 0;
if ($hostname_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid HostnameId']);
    exit;
}

// Get latest AtoDor record
$sql = "SELECT TOP 1 RecordId, DorTypeId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
$result = $db->execute($sql, [$hostname_id]);

if (!is_array($result) || count($result) === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "No record found for HostnameId = $hostname_id"]);
    exit;
}

$recordId = $result[0]['RecordId'];
$dorTypeId = $result[0]['DorTypeId'];

// Function to save leader responses
function saveLeaderResponses($db, $recordId, $employeeCode, $responses) {
    if (!$recordId || !$employeeCode) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }

    try {
        foreach ($responses as $checkpointId => $response) {
            $response = strtoupper(trim($response));
            if (!in_array($response, ['OK', 'NA', 'NG'])) continue;

            $exists = $db->execute(
                "SELECT COUNT(*) AS cnt FROM AtoDorCheckpointDefinition 
                 WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1",
                [$recordId, $checkpointId]
            );

            if (!empty($exists[0]['cnt'])) {
                $db->execute(
                    "UPDATE AtoDorCheckpointDefinition SET CheckpointResponse = ? 
                     WHERE RecordId = ? AND CheckpointId = ? AND IsLeader = 1",
                    [$response, $recordId, $checkpointId]
                );
            } else {
                $db->execute(
                    "INSERT INTO AtoDorCheckpointDefinition 
                     (RecordId, CheckpointId, ProcessIndex, EmployeeCode, CheckpointResponse, IsLeader)
                     VALUES (?, ?, 5, ?, ?, 1)",
                    [$recordId, $checkpointId, $employeeCode, $response]
                );
            }
        }
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Handle AJAX save request for tab-0
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_tab0'])) {
    header('Content-Type: application/json');
    
    $employeeCode = $_SESSION['production_code'] ?? null;
    if (!$employeeCode) {
        echo json_encode(['success' => false, 'message' => 'Missing employee code']);
        exit;
    }

    $responses = $_POST['leader'] ?? [];
    $result = saveLeaderResponses($db, $recordId, $employeeCode, $responses);
    
    echo json_encode($result);
    exit;
}

// Handle full form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSubmit'])) {
    $employeeCode = $_SESSION['production_code'] ?? null;
    if (!$employeeCode) {
        die("Missing employee code for leader.");
    }

    $responses = $_POST['leader'] ?? [];
    $saveResult = saveLeaderResponses($db, $recordId, $employeeCode, $responses);
    
    if (!$saveResult['success']) {
        die("Failed to save: " . $saveResult['message']);
    }

    // Redirect to next tab or completion page
    $nextTab = (int)($_POST['current_tab_index'] ?? 0) + 1;
    if ($nextTab >= (int)($_POST['total_tabs'] ?? 1)) {
        header("Location: dor-leader-dashboard.php");
    } else {
        header("Location: ?hostname_id={$hostname_id}&tab={$nextTab}");
    }
    exit;
}

// Get checkpoint definitions for display
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
$operatorCodesByProcess = [];
foreach ($operatorRaw as $row) {
    $cpId = $row['CheckpointId'];
    $procIdx = $row['ProcessIndex'];
    $operatorResponses[$cpId][$procIdx] = $row['CheckpointResponse'];
    if (!in_array($procIdx, $processIndexes)) {
        $processIndexes[] = $procIdx;
    }
    $operatorCodesByProcess[$procIdx][] = $row['EmployeeCode'];
}
sort($processIndexes);

// Get existing leader responses
$sql = "SELECT CheckpointId, CheckpointResponse
        FROM AtoDorCheckpointDefinition
        WHERE RecordId = ? AND IsLeader = 1 AND ProcessIndex = 5";
$leaderRaw = $db->execute($sql, [$recordId]);
$leaderResponses = [];
foreach ($leaderRaw as $row) {
    $leaderResponses[$row['CheckpointId']] = $row['CheckpointResponse'];
}