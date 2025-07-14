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

    private function generateCode($prefix, $table, $column)
    {
        $sql = "SELECT MAX($column) AS MaxCode FROM $table WHERE $column LIKE ?";
        $result = $this->db->execute($sql, ["$prefix-%"]);

        $max = 0;
        if (!empty($result[0]['MaxCode'])) {
            $lastPart = explode('-', $result[0]['MaxCode'])[1] ?? '0';
            $max = (int)$lastPart;
        }

        return $prefix . '-' . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
    }

    private function insertDowntime($name)
    {
        $code = $this->generateCode('CU', 'GenDorDowntime', 'DowntimeCode');
        $categoryId = 3;

        try {
            $success = $this->db->execute(
                "INSERT INTO GenDorDowntime (DowntimeCategoryId, DowntimeCode, DowntimeName) VALUES (?, ?, ?)",
                [$categoryId, $code, $name]
            );

            if ($success) {
                $id = $this->db->lastInsertId();
                if (is_numeric($id)) {
                    return (int)$id;
                }

                // fallback
                $row = $this->db->execute("SELECT TOP 1 DowntimeId FROM GenDorDowntime WHERE DowntimeCode = ? ORDER BY DowntimeId DESC", [$code]);
                return $row[0]['DowntimeId'] ?? true;
            }

            return null;
        } catch (Exception $e) {
            error_log("Insert Downtime ERROR: " . $e->getMessage());
            return null;
        }
    }

    private function insertAction($name)
    {
        $code = $this->generateCode('CU', 'GenDorActionTaken', 'ActionTakenCode');

        try {
            $success = $this->db->execute(
                "INSERT INTO GenDorActionTaken (ActionTakenCode, ActionTakenName) VALUES (?, ?)",
                [$code, $name]
            );

            if ($success) {
                $id = $this->db->lastInsertId();
                if (is_numeric($id)) {
                    return (int)$id;
                }

                $row = $this->db->execute("SELECT TOP 1 ActionTakenId FROM GenDorActionTaken WHERE ActionTakenCode = ? ORDER BY ActionTakenId DESC", [$code]);
                return $row[0]['ActionTakenId'] ?? true;
            }

            return null;
        } catch (Exception $e) {
            error_log("Insert Action ERROR: " . $e->getMessage());
            return null;
        }
    }

    private function insertRemarks($name)
    {
        $code = $this->generateCode('CU', 'GenDorRemarks', 'RemarksCode');

        try {
            $success = $this->db->execute(
                "INSERT INTO GenDorRemarks (RemarksCode, RemarksName) VALUES (?, ?)",
                [$code, $name]
            );

            if ($success) {
                $id = $this->db->lastInsertId();
                if (is_numeric($id)) {
                    return (int)$id;
                }

                $row = $this->db->execute("SELECT TOP 1 RemarksId FROM GenDorRemarks WHERE RemarksCode = ? ORDER BY RemarksId DESC", [$code]);
                return $row[0]['RemarksId'] ?? true;
            }

            return null;
        } catch (Exception $e) {
            error_log("Insert Remarks ERROR: " . $e->getMessage());
            return null;
        }
    }

    public function saveDowntime($recordHeaderId, $data)
    {
        $messages = [];

        if ($data['DowntimeId'] === 'custom' && !empty($data['CustomDowntimeName'])) {
            $newId = $this->insertDowntime($data['CustomDowntimeName']);
            if ($newId) {
                $data['DowntimeId'] = is_numeric($newId) ? (int)$newId : $newId;
            } else {
                $messages[] = 'Failed to insert custom Downtime';
            }
        }

        if ($data['ActionTakenId'] === 'custom' && !empty($data['CustomActionName'])) {
            $newId = $this->insertAction($data['CustomActionName']);
            if ($newId) {
                $data['ActionTakenId'] = is_numeric($newId) ? (int)$newId : $newId;
            } else {
                $messages[] = 'Failed to insert custom ActionTaken';
            }
        }

        if ($data['RemarksId'] === 'custom' && !empty($data['CustomRemarksName'])) {
            $newId = $this->insertRemarks($data['CustomRemarksName']);
            if ($newId) {
                $data['RemarksId'] = is_numeric($newId) ? (int)$newId : $newId;
            } else {
                $messages[] = 'Failed to insert custom Remarks';
            }
        }

        if (!empty($messages)) {
            return ['success' => false, 'message' => implode('; ', $messages)];
        }

        $data['DowntimeId'] = (int)$data['DowntimeId'];
        $data['ActionTakenId'] = (int)$data['ActionTakenId'];
        $data['RemarksId'] = isset($data['RemarksId']) ? (int)$data['RemarksId'] : null;

        $ops = $this->db->execute(
            "SELECT TOP 1 OperatorCode1, OperatorCode2, OperatorCode3, OperatorCode4
             FROM AtoDorDetail WHERE RecordHeaderId = ? ORDER BY RecordDetailId DESC",
            [$recordHeaderId]
        );

        $op1 = $ops[0]['OperatorCode1'] ?? null;
        $op2 = $ops[0]['OperatorCode2'] ?? null;
        $op3 = $ops[0]['OperatorCode3'] ?? null;
        $op4 = $ops[0]['OperatorCode4'] ?? null;

        $existing = $this->db->execute(
            "SELECT TOP 1 RecordDetailId FROM AtoDorDetail
             WHERE RecordHeaderId = ? AND DowntimeId IS NULL
             ORDER BY RecordDetailId ASC",
            [$recordHeaderId]
        );

        if (!empty($existing)) {
            $recordDetailId = $existing[0]['RecordDetailId'];

            $updated = $this->db->execute(
                "UPDATE AtoDorDetail
                 SET DowntimeId = ?, ActionTakenId = ?, TimeStart = ?, TimeEnd = ?, Duration = ?, Pic = ?, RemarksId = ?
                 WHERE RecordDetailId = ?",
                [
                    $data['DowntimeId'],
                    $data['ActionTakenId'],
                    $data['TimeStart'],
                    $data['TimeEnd'],
                    $data['Duration'],
                    $data['Pic'],
                    $data['RemarksId'],
                    $recordDetailId
                ]
            );

            return $updated ? ['success' => true, 'updated' => true] : ['success' => false, 'message' => 'Update failed'];
        }

        $inserted = $this->db->execute(
            "INSERT INTO AtoDorDetail
             (RecordHeaderId, OperatorCode1, OperatorCode2, OperatorCode3, OperatorCode4,
              DowntimeId, ActionTakenId, TimeStart, TimeEnd, Duration, Pic, RemarksId)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
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
            ]
        );

        return $inserted ? ['success' => true, 'inserted' => true] : ['success' => false, 'message' => 'Insert failed'];
    }
}

$controller = new DorDowntime();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) $data = $_POST;

    $type = $data['type'] ?? ($_POST['btnSubmit'] ?? null);

    switch ($type) {
        case 'saveDowntime':
            if (!isset($data['recordHeaderId'], $data['downtimeData'])) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit;
            }

            $recordHeaderId = $data['recordHeaderId'];
            $downtimeData = $data['downtimeData'];

            foreach (['DowntimeId', 'ActionTakenId', 'TimeStart', 'TimeEnd'] as $field) {
                if (empty($downtimeData[$field]) || $downtimeData[$field] === '0') {
                    echo json_encode(['success' => false, 'message' => "Missing or invalid field '$field'"]);
                    exit;
                }
            }

            $today = date('Y-m-d');
            $downtimeData['TimeStart'] = $today . ' ' . $downtimeData['TimeStart'] . ':00';
            $downtimeData['TimeEnd'] = $today . ' ' . $downtimeData['TimeEnd'] . ':00';

            if (strpos($downtimeData['Duration'], ':') !== false) {
                [$h, $m] = explode(':', $downtimeData['Duration']);
                $downtimeData['Duration'] = ((int)$h * 60) + (int)$m;
            } else {
                $downtimeData['Duration'] = (int)$downtimeData['Duration'];
            }

            echo json_encode($controller->saveDowntime($recordHeaderId, $downtimeData));
            exit;

        case 'submitForm':
            echo json_encode(['success' => true, 'message' => 'Form submitted']);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid request type']);
            exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['type'] ?? '') === 'fetchSingle') {
    header('Content-Type: application/json');
    $recordHeaderId = (int)($_GET['recordHeaderId'] ?? 0);
    $details = $controller->AtoDor();

    foreach ($details as $detail) {
        if ((int)$detail['RecordHeaderId'] === $recordHeaderId) {
            $downtimeMap = array_column($controller->getDowntimeList(), null, 'DowntimeId');
            $actionMap = array_column($controller->getActionList(), null, 'ActionTakenId');

            $detail['DowntimeCode'] = $downtimeMap[$detail['DowntimeId']]['DowntimeCode'] ?? null;
            $detail['ActionTakenName'] = $actionMap[$detail['ActionTakenId']]['ActionTakenName'] ?? 'No Action';

            echo json_encode(['success' => true, 'detail' => $detail]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Record not found']);
    exit;
}