<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/dbop.php';

class DorDowntime
{
    private $db;

    public function __construct()
    {
        $this->db = new DbOp(1);
    }

    public function AtoDor()
    {
        return $this->db->execute("SELECT * FROM AtoDorDetail");
    }

    public function getDowntimeList()
    {
        return $this->db->execute("SELECT DowntimeId, DowntimeCategoryId, DowntimeCode, DowntimeName FROM GenDorDowntime");
    }

    public function getActionList()
    {
        return $this->db->execute("SELECT ActionTakenId, ActionTakenCode, ActionTakenName FROM GenDorActionTaken");
    }

    public function getRemarksList()
    {
        return $this->db->execute("SELECT RemarksId, RemarksCode, RemarksName FROM GenDorRemarks");
    }

    public function saveDowntime($recordHeaderId, $data)
    {
        // Fetch operator codes from the latest row
        $opQuery = "SELECT TOP 1 OperatorCode1, OperatorCode2, OperatorCode3, OperatorCode4
                FROM AtoDorDetail
                WHERE RecordHeaderId = ?
                ORDER BY RecordDetailId DESC";

        $operatorSet = $this->db->execute($opQuery, [$recordHeaderId]);

        $op1 = $operatorSet[0]['OperatorCode1'] ?? null;
        $op2 = $operatorSet[0]['OperatorCode2'] ?? null;
        $op3 = $operatorSet[0]['OperatorCode3'] ?? null;
        $op4 = $operatorSet[0]['OperatorCode4'] ?? null;

        // Check if a null/placeholder row exists (no DowntimeId)
        $checkQuery = "SELECT TOP 1 RecordDetailId FROM AtoDorDetail
                   WHERE RecordHeaderId = ? AND DowntimeId IS NULL
                   ORDER BY RecordDetailId ASC";
        $existing = $this->db->execute($checkQuery, [$recordHeaderId]);

        if (!empty($existing)) {
            // ✅ Update existing null row
            $recordDetailId = $existing[0]['RecordDetailId'];

            $updateQuery = "UPDATE AtoDorDetail SET
            DowntimeId = ?,
            ActionTakenId = ?,
            TimeStart = ?,
            TimeEnd = ?,
            Duration = ?,
            Pic = ?,
            RemarksId = ?
        WHERE RecordDetailId = ?";

            $params = [
                $data['DowntimeId'],
                $data['ActionTakenId'],
                $data['TimeStart'],
                $data['TimeEnd'],
                $data['Duration'],
                $data['Pic'],
                $data['RemarksId'],
                $recordDetailId
            ];

            return $this->db->execute($updateQuery, $params)
                ? ['success' => true, 'updated' => true]
                : ['success' => false, 'message' => 'Update failed'];
        } else {
            // Insert new row if no null row exists
            $insertQuery = "INSERT INTO AtoDorDetail (
            RecordHeaderId, OperatorCode1, OperatorCode2, OperatorCode3, OperatorCode4,
            DowntimeId, ActionTakenId, TimeStart, TimeEnd, Duration, Pic, RemarksId
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $recordHeaderId,
                $op1,
                $op2,
                $op3,
                $op4,
                $data['DowntimeId'],
                $data['ActionTakenId'],
                $data['TimeStart'],
                $data['TimeEnd'],
                $data['Duration'],
                $data['Pic'],
                $data['RemarksId']
            ];

            return $this->db->execute($insertQuery, $params)
                ? ['success' => true, 'inserted' => true]
                : ['success' => false, 'message' => 'Insert failed'];
        }
    }
}

// Instantiate controller and fetch dropdown data
$controller = new DorDowntime();
$downtimeOptions = $controller->getDowntimeList();
$actionTakenOptions = $controller->getActionList();
$remarksOptions = $controller->getRemarksList();
$atoDorDetails = $controller->AtoDor();

//Handle POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Try JSON input first
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // Fallback to $_POST for form-based submission
    if (!is_array($data)) {
        $data = $_POST;
    }

    // Determine type
    $type = $data['type'] ?? ($_POST['btnSubmit'] ?? null);
    switch ($type) {
        case 'saveDowntime':
            if (!isset($data['recordHeaderId']) || !isset($data['downtimeData'])) {
                error_log("Missing parameters. Received: " . print_r($data, true));
                echo json_encode([
                    'success' => false,
                    'message' => 'recordHeaderId or downtimeData not provided',
                    'received_data' => $data
                ]);
                exit;
            }

            $recordHeaderId = $data['recordHeaderId'];
            $downtimeData = $data['downtimeData'];

            // Validate required fields
            $required = ['DowntimeId', 'ActionTakenId', 'TimeStart', 'TimeEnd'];

            foreach ($required as $field) {
                if (
                    !isset($downtimeData[$field]) ||
                    trim((string)$downtimeData[$field]) === '' ||
                    $downtimeData[$field] === '0'
                ) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Downtime not saved: missing or empty field '$field'."
                    ]);
                    exit;
                }
            }

            // Format Time fields
            $today = date('Y-m-d');
            $downtimeData['TimeStart'] = $today . ' ' . $downtimeData['TimeStart'] . ':00';
            $downtimeData['TimeEnd'] = $today . ' ' . $downtimeData['TimeEnd'] . ':00';

            // Convert duration to minutes
            if (strpos($downtimeData['Duration'], ':') !== false) {
                list($h, $m) = explode(':', $downtimeData['Duration']);
                $downtimeData['Duration'] = ((int)$h * 60) + (int)$m;
            } else {
                $downtimeData['Duration'] = (int) $downtimeData['Duration'];
            }

            // Save to DB
            $result = $controller->saveDowntime($recordHeaderId, $downtimeData);
            echo json_encode($result);
            exit;

        case 'submitForm':
            // Final tab form submission handler
            echo json_encode([
                'success' => true,
                'message' => 'Form submitted successfully.'
            ]);
            exit;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid request type',
                'received_type' => $type,
                'received_data' => $data
            ]);
            exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['type'] ?? '') === 'fetchSingle') {
    header('Content-Type: application/json');

    $recordHeaderId = (int) ($_GET['recordHeaderId'] ?? 0);
    $details = $controller->AtoDor();

    foreach ($details as $detail) {
        if ((int)$detail['RecordHeaderId'] === $recordHeaderId) {
            // Add code for display
            $downtimeList = $controller->getDowntimeList();
            $actionList = $controller->getActionList();

            $downtimeMap = [];
            foreach ($downtimeList as $d) {
                $downtimeMap[$d['DowntimeId']] = $d;
            }

            $actionMap = [];
            foreach ($actionList as $a) {
                $actionMap[$a['ActionTakenId']] = $a;
            }

            $detail['DowntimeCode'] = $downtimeMap[$detail['DowntimeId']]['DowntimeCode'] ?? null;
            $detail['ActionTakenName'] = $actionMap[$detail['ActionTakenId']]['ActionTakenName'] ?? 'No Action';

            echo json_encode(['success' => true, 'detail' => $detail]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Record not found']);
    exit;
}
