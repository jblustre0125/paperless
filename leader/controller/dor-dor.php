<?php
require_once '../../config/dbop.php';

class DorDor
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = new DbOp(1);
    }

    public function getHeaders($hostnameId = null)
    {
        $sql = "
        SELECT
            h.RecordHeaderId,
            h.RecordId,
            h.BoxNumber,
            h.TimeStart,
            h.TimeEnd,
            h.Duration,
            d.CreatedBy,
            m.ITEM_ID,
            m.MP
        FROM AtoDorHeader h
        INNER JOIN AtoDor d ON h.RecordId = d.RecordId
        LEFT JOIN GenModel m ON d.ModelId = m.MODEL_ID
    ";

        $params = [];
        if ($hostnameId !== null) {
            $sql .= " WHERE d.HostnameId = ?";
            $params[] = $hostnameId;
        }
        $sql .= " ORDER BY h.RecordHeaderId ASC";

        return $this->db->execute($sql, $params);
    }


    public function getDetails()
    {
        $result = $this->db->execute("SELECT * FROM AtoDorDetail");
        $details = [];
        foreach ($result as $row) {
            if (!empty($row['RecordHeaderId'])) {
                $details[$row['RecordHeaderId']] = $row;
            }
        }
        return $details;
    }

    public function getActionTakenList()
    {
        $result = $this->db->execute("SELECT ActionTakenId, ActionTakenCode, ActionTakenName FROM AtoActionTaken");
        return is_array($result) ? array_column($result, null, 'ActionTakenId') : [];
    }

    public function getDowntimeList()
    {
        $result = $this->db->execute("SELECT DowntimeId, DowntimeCategoryId, DowntimeCode, DowntimeName FROM GenDorDowntime");
        return is_array($result) ? array_column($result, null, 'DowntimeId') : [];
    }

    public function getOperatorMap()
    {
        $result = $this->db->execute("SELECT ProductionCode, EmployeeCode, EmployeeName FROM GenOperator WHERE IsActive = 1");
        return is_array($result) ? array_column($result, 'EmployeeName', 'ProductionCode') : [];
    }

    public function getMPByRecordHeaderId($recordHeaderId)
    {
        $sql = "
        SELECT gm.MP
        FROM AtoDorHeader h
        JOIN AtoDor d ON h.RecordId = d.RecordId
        JOIN GenModel gm ON d.ModelId = gm.MODEL_ID
        WHERE h.RecordHeaderId = ?
    ";
        $result = $this->db->execute($sql, [$recordHeaderId]);

        file_put_contents("php://stderr", print_r([
            'recordHeaderId' => $recordHeaderId,
            'queryResult' => $result
        ], true));

        return $result[0]['MP'] ?? null;
    }


    public function saveOperators($recordHeaderId, $employeeCodes)
    {
        if (empty($recordHeaderId) || !is_array($employeeCodes)) return false;
        $employeeCodes = array_values(array_unique(array_filter($employeeCodes)));
        $mpRequired = $this->getMPByRecordHeaderId($recordHeaderId);
        if ($mpRequired !== null && count($employeeCodes) < (int)$mpRequired) return false;

        $fields = [
            'OperatorCode1' => $employeeCodes[0] ?? null,
            'OperatorCode2' => $employeeCodes[1] ?? null,
            'OperatorCode3' => $employeeCodes[2] ?? null,
            'OperatorCode4' => $employeeCodes[3] ?? null,
            'TimeStart'     => $header['TimeStart'] ?? null,
            'TimeEnd'       => $header['TimeEnd'] ?? null,
            'Duration'      => $header['Duration'] ?? null,
        ];

        $setClause = implode(", ", array_map(fn($k) => "$k = ?", array_keys($fields)));
        $values = array_values($fields);
        $values[] = $recordHeaderId;

        $sql = "UPDATE AtoDorDetail SET $setClause WHERE RecordHeaderId = ?";
        $result = $this->db->execute($sql, $values);

        $record = $this->db->execute("SELECT RecordId FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? null;
        if ($record) {
            foreach (array_reverse($employeeCodes) as $code) {
                if (!empty($code)) {
                    $this->db->execute("UPDATE AtoDor SET CreatedBy = ? WHERE RecordId = ?", [$code, $record['RecordId']]);
                    break;
                }
            }
        }
        return true;
    }

    public function deleteHeader($recordHeaderId)
    {
        if (!$recordHeaderId) return false;
        // Delete related details first
        $sqlDetail = "DELETE FROM AtoDorDetail WHERE RecordHeaderId = ?";
        $this->db->execute($sqlDetail, [$recordHeaderId]);
        // Then delete header
        $sqlHeader = "DELETE FROM AtoDorHeader WHERE RecordHeaderId = ?";
        $this->db->execute($sqlHeader, [$recordHeaderId]);
        return true;
    }
}

// ======= Main Request Handling =======
$controller = new DorDor();
$hostnameId = $_GET['hostname_id'] ?? null;

$headers = $controller->getHeaders($hostnameId);
$details = $controller->getDetails();
$downtimeOptions = $controller->getDowntimeList();
$operatorMap = $controller->getOperatorMap();
$actionTakenOptions = $controller->getActionTakenList();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['type']) && $input['type'] === 'getAllHeaders') {
        $hostnameId = $_GET['hostname_id'] ?? null;
        $headers = $controller->getHeaders($hostnameId);
        $rows = [];
        foreach ($headers as $row) {
            // Format time as HH:mm
            $timeStart = '';
            $timeEnd = '';
            if (!empty($row['TimeStart'])) {
                if ($row['TimeStart'] instanceof DateTime) {
                    $timeStart = $row['TimeStart']->format('H:i');
                } else {
                    $ts = strtotime($row['TimeStart']);
                    $timeStart = $ts ? date('H:i', $ts) : '';
                }
            }
            if (!empty($row['TimeEnd'])) {
                if ($row['TimeEnd'] instanceof DateTime) {
                    $timeEnd = $row['TimeEnd']->format('H:i');
                } else {
                    $te = strtotime($row['TimeEnd']);
                    $timeEnd = $te ? date('H:i', $te) : '';
                }
            }
            $rows[] = [
                'recordHeaderId' => $row['RecordHeaderId'],
                'boxNo' => $row['BoxNumber'],
                'timeStart' => $timeStart,
                'timeEnd' => $timeEnd,
                'duration' => $row['Duration']
            ];
        }
        echo json_encode(['success' => true, 'rows' => $rows]);
        exit;
    }
    if (isset($input['type']) && $input['type'] === 'saveOperators') {
        $recordHeaderId = $input['recordHeaderId'] ?? null;
        $employeeCodes = $input['employeeCodes'] ?? [];

        if (!is_array($employeeCodes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid employeeCodes']);
            exit;
        }

        $mp = $controller->getMPByRecordHeaderId($recordHeaderId);
        $validCount = count(array_unique(array_filter($employeeCodes)));

        if ($mp !== null && $validCount < (int)$mp) {
            echo json_encode(['success' => false, 'message' => "At least {$mp} operator(s) required."]);
            exit;
        }

        $success = $controller->saveOperators($recordHeaderId, $employeeCodes);
        echo json_encode(['success' => $success]);
        exit;
    }
    if (isset($input['type']) && $input['type'] === 'deleteHeader') {
        $recordHeaderId = $input['recordHeaderId'] ?? null;
        if (!$recordHeaderId) {
            echo json_encode(['success' => false, 'message' => 'Missing recordHeaderId']);
            exit;
        }
        try {
            $success = $controller->deleteHeader($recordHeaderId);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
