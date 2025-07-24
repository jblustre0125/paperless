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
        $result = $this->db->execute($sql, ["$prefix-%"], 1);

        $max = 0;
        if (!empty($result) && isset($result[0]['MaxCode']) && $result[0]['MaxCode']) {
            $parts = explode('-', $result[0]['MaxCode']);
            $lastPart = $parts[1] ?? '0';
            $max = (int)$lastPart;
        }

        return $prefix . '-' . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
    }

    private function insertDowntime($name)
    {
        $code = $this->generateCode('OTH', 'GenDorDowntime', 'DowntimeCode');
        $categoryId = 3;

        error_log("insertDowntime: Attempting to insert - Name: '$name', Code: '$code', CategoryId: $categoryId");

        try {
            // Try using OUTPUT clause to get the inserted ID directly
            $sql = "INSERT INTO GenDorDowntime (DowntimeCategoryId, DowntimeCode, DowntimeName)
                    OUTPUT INSERTED.DowntimeId
                    VALUES (?, ?, ?)";

            error_log("insertDowntime: Executing SQL with OUTPUT: $sql | Params: " . json_encode([$categoryId, $code, $name]));

            $result = $this->db->execute($sql, [$categoryId, $code, $name], 1);

            error_log("insertDowntime: OUTPUT result: " . print_r($result, true));

            if (is_array($result) && !empty($result) && isset($result[0]['DowntimeId'])) {
                $id = (int)$result[0]['DowntimeId'];
                error_log("insertDowntime: SUCCESS with OUTPUT - ID: $id");
                return $id;
            }

            // Fallback: Traditional insert then select
            error_log("insertDowntime: OUTPUT failed, trying fallback insert");

            $success = $this->db->execute(
                "INSERT INTO GenDorDowntime (DowntimeCategoryId, DowntimeCode, DowntimeName) VALUES (?, ?, ?)",
                [$categoryId, $code, $name]
            );

            error_log("insertDowntime: Fallback insert result: " . print_r($success, true));

            if ($success === false) {
                error_log("insertDowntime: Fallback insert failed for code $code, name $name");
            }

            if ($success !== false) {
                // Fallback: query by the unique code we just inserted
                $result = $this->db->execute(
                    "SELECT DowntimeId FROM GenDorDowntime WHERE DowntimeCode = ?",
                    [$code],
                    1
                );

                error_log("insertDowntime: Fallback select result for code $code: " . print_r($result, true));

                if (!empty($result) && isset($result[0]['DowntimeId'])) {
                    $id = (int)$result[0]['DowntimeId'];
                    error_log("insertDowntime: SUCCESS with fallback - ID: $id");
                    return $id;
                } else {
                    error_log("insertDowntime: Fallback select did not find inserted row for code $code");
                }
            }

            error_log("insertDowntime: FAILED - No ID returned for code $code, name $name");
            return null;
        } catch (Exception $e) {
            error_log("insertDowntime: EXCEPTION - " . $e->getMessage());
            error_log("insertDowntime: Stack trace - " . $e->getTraceAsString());
            return null;
        }
    }

    private function insertAction($name)
    {
        $code = $this->generateCode('OTH', 'GenDorActionTaken', 'ActionTakenCode');

        error_log("insertAction: Attempting to insert - Name: '$name', Code: '$code'");

        try {
            // Try using OUTPUT clause to get the inserted ID directly
            $sql = "INSERT INTO GenDorActionTaken (ActionTakenCode, ActionTakenName)
                 OUTPUT INSERTED.ActionTakenId
                 VALUES (?, ?)";

            error_log("insertAction: Executing SQL with OUTPUT: $sql | Params: " . json_encode([$code, $name]));

            $result = $this->db->execute($sql, [$code, $name], 1);

            error_log("insertAction: OUTPUT result: " . print_r($result, true));

            if (!empty($result) && isset($result[0]['ActionTakenId'])) {
                $id = (int)$result[0]['ActionTakenId'];
                error_log("insertAction: SUCCESS with OUTPUT - ID: $id");
                return $id;
            }

            // Fallback: Traditional insert then select
            error_log("insertAction: OUTPUT failed, trying fallback insert");

            $success = $this->db->execute(
                "INSERT INTO GenDorActionTaken (ActionTakenCode, ActionTakenName) VALUES (?, ?)",
                [$code, $name]
            );

            error_log("insertAction: Fallback insert result: " . print_r($success, true));

            if ($success === false) {
                error_log("insertAction: Fallback insert failed for code $code, name $name");
            }

            if ($success !== false) {
                // Fallback: query by the unique code we just inserted
                $result = $this->db->execute(
                    "SELECT ActionTakenId FROM GenDorActionTaken WHERE ActionTakenCode = ?",
                    [$code],
                    1
                );

                error_log("insertAction: Fallback select result for code $code: " . print_r($result, true));

                if (!empty($result) && isset($result[0]['ActionTakenId'])) {
                    $id = (int)$result[0]['ActionTakenId'];
                    error_log("insertAction: SUCCESS with fallback - ID: $id");
                    return $id;
                } else {
                    error_log("insertAction: Fallback select did not find inserted row for code $code");
                }
            }

            error_log("Insert Action failed: No ID returned for code $code, name $name");
            return null;
        } catch (Exception $e) {
            error_log("Insert Action ERROR: " . $e->getMessage());
            error_log("insertAction: Stack trace - " . $e->getTraceAsString());
            return null;
        }
    }

    private function insertRemarks($name)
    {
        $code = $this->generateCode('OTH', 'GenDorRemarks', 'RemarksCode');

        try {
            // Try using OUTPUT clause to get the inserted ID directly
            $result = $this->db->execute(
                "INSERT INTO GenDorRemarks (RemarksCode, RemarksName)
                 OUTPUT INSERTED.RemarksId
                 VALUES (?, ?)",
                [$code, $name],
                1 // Use com=1 to get SELECT-like results
            );

            if (!empty($result) && isset($result[0]['RemarksId'])) {
                return (int)$result[0]['RemarksId'];
            }

            // Fallback: Traditional insert then select
            $success = $this->db->execute(
                "INSERT INTO GenDorRemarks (RemarksCode, RemarksName) VALUES (?, ?)",
                [$code, $name]
            );

            if ($success !== false) {
                // Fallback: query by the unique code we just inserted
                $result = $this->db->execute(
                    "SELECT RemarksId FROM GenDorRemarks WHERE RemarksCode = ?",
                    [$code],
                    1
                );

                if (!empty($result) && isset($result[0]['RemarksId'])) {
                    return (int)$result[0]['RemarksId'];
                }
            }

            error_log("Insert Remarks failed: No ID returned for code $code");
            return null;
        } catch (Exception $e) {
            error_log("Insert Remarks ERROR: " . $e->getMessage());
            return null;
        }
    }

    public function saveDowntime($recordHeaderId, $data)
    {
        error_log("saveDowntime called with recordHeaderId: $recordHeaderId, data: " . json_encode($data));
        $messages = [];

        if ($data['DowntimeId'] === 'custom' && !empty($data['CustomDowntimeName'])) {
            error_log("Inserting custom downtime: " . $data['CustomDowntimeName']);
            $newId = $this->insertDowntime($data['CustomDowntimeName']);
            if ($newId) {
                $data['DowntimeId'] = is_numeric($newId) ? (int)$newId : $newId;
                error_log("Custom downtime inserted with ID: " . $data['DowntimeId']);
            } else {
                error_log("Failed to insert custom downtime: " . $data['CustomDowntimeName']);
                $messages[] = 'Failed to insert custom Downtime';
            }
        }

        if ($data['ActionTakenId'] === 'custom' && !empty($data['CustomActionName'])) {
            error_log("Inserting custom action: " . $data['CustomActionName']);
            $newId = $this->insertAction($data['CustomActionName']);
            if ($newId) {
                $data['ActionTakenId'] = is_numeric($newId) ? (int)$newId : $newId;
                error_log("Custom action inserted with ID: " . $data['ActionTakenId']);
            } else {
                error_log("Failed to insert custom action: " . $data['CustomActionName']);
                $messages[] = 'Failed to insert custom ActionTaken';
            }
        }

        if ($data['RemarksId'] === 'custom' && !empty($data['CustomRemarksName'])) {
            error_log("Inserting custom remarks: " . $data['CustomRemarksName']);
            $newId = $this->insertRemarks($data['CustomRemarksName']);
            if ($newId) {
                $data['RemarksId'] = is_numeric($newId) ? (int)$newId : $newId;
                error_log("Custom remarks inserted with ID: " . $data['RemarksId']);
            } else {
                error_log("Failed to insert custom remarks: " . $data['CustomRemarksName']);
                $messages[] = 'Failed to insert custom Remarks';
            }
        }

        if (!empty($messages)) {
            error_log("saveDowntime failed with messages: " . implode('; ', $messages));
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

    public function getDowntimeInfo($recordHeaderId)
    {
        $details = [];
        $atoDorDetails = $this->AtoDor();

        foreach ($atoDorDetails as $row) {
            if ($row['RecordHeaderId'] == $recordHeaderId) {
                $details[] = $row;
            }
        }

        $downtimeOptions = $this->getDowntimeList();
        $actionTakenOptions = $this->getActionList();

        $downtimeMap = [];
        foreach ($downtimeOptions as $downtime) {
            $downtimeMap[$downtime['DowntimeId']] = $downtime;
        }
        $actionTakenMap = [];
        foreach ($actionTakenOptions as $action) {
            $actionTakenMap[$action['ActionTakenId']] = [
                'ActionTakenDescription' => $action['ActionTakenName']
            ];
        }

        $html = '';
        if (empty($details)) {
            $html = '<small class="badge bg-light text-dark border me-1 mb-1">No downtime</small>';
        } else {
            $hasValidDowntime = false;
            foreach ($details as $detail) {
                $downtimeId = $detail['DowntimeId'] ?? null;
                $actionTakenId = $detail['ActionTakenId'] ?? null;

                $downtimeCode = $downtimeMap[$downtimeId]['DowntimeCode'] ?? null;
                $actionTakenTitle = $actionTakenMap[$actionTakenId]['ActionDescription'] ?? 'No description';

                if ($downtimeCode) {
                    $html .= '<small class="badge bg-light text-dark border me-1 mb-1" title="' . htmlspecialchars($actionTakenTitle) . '">'
                        . htmlspecialchars($downtimeCode) .
                        '</small>';

                    $hasValidDowntime = true;
                }
            }
            if (!$hasValidDowntime) {
                $html = '<small class="badge bg-light text-dark border me-1 mb-1">No downtime</small>';
            }
        }
        return ['success' => true, 'html' => $html];
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
        case 'getDowntimeInfo':
            if (!isset($data['recordHeaderId'])) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit;
            }
            $recordHeaderId = $data['recordHeaderId'];
            echo json_encode($controller->getDowntimeInfo($recordHeaderId));
            exit;

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
