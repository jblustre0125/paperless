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

    public function syncTimeFieldsToDetail()
    {
        $headers = $this->getHeaders();
        foreach ($headers as $header) {
            $recordHeaderId = $header['RecordHeaderId'];
            $timeStart = $header['TimeStart'];
            $timeEnd = $header['TimeEnd'];
            $duration = $header['Duration'];

            // Check if detail exists
            $exists = $this->db->execute(
                "SELECT COUNT(*) AS cnt FROM AtoDorDetail WHERE RecordHeaderId = ?",
                [$recordHeaderId]
            )[0]['cnt'] ?? 0;

            if ($exists == 0) {
                // Insert a new row if missing
                $this->db->execute(
                    "INSERT INTO AtoDorDetail (RecordHeaderId, TimeStart, TimeEnd, Duration) VALUES (?, ?, ?, ?)",
                    [$recordHeaderId, $timeStart, $timeEnd, $duration]
                );
            } else {
                // Update if exists
                $this->db->execute(
                    "UPDATE AtoDorDetail SET TimeStart = ?, TimeEnd = ?, Duration = ? WHERE RecordHeaderId = ?",
                    [$timeStart, $timeEnd, $duration, $recordHeaderId]
                );
            }
        }
    }

    public function syncOperatorsFromCheckpoint()
    {
        // Get all details with their RecordDetailId and RecordHeaderId
        $details = $this->db->execute("SELECT RecordDetailId, RecordHeaderId FROM AtoDorDetail");
        foreach ($details as $detail) {
            $recordDetailId = $detail['RecordDetailId'];
            $recordHeaderId = $detail['RecordHeaderId'];

            // Fetch up to 4 unique EmployeeCodes for this detail from AtoDorCheckpointDefinition
            $checkpointRows = $this->db->execute(
                "SELECT DISTINCT EmployeeCode FROM AtoDorCheckpointDefinition WHERE RecordDetailId = ? AND EmployeeCode IS NOT NULL AND EmployeeCode <> '' LIMIT 4",
                [$recordDetailId]
            );

            $employeeCodes = array_column($checkpointRows, 'EmployeeCode');
            // Pad to 4 elements
            while (count($employeeCodes) < 4) $employeeCodes[] = null;

            // Update AtoDorDetail with these codes
            $this->db->execute(
                "UPDATE AtoDorDetail SET OperatorCode1 = ?, OperatorCode2 = ?, OperatorCode3 = ?, OperatorCode4 = ? WHERE RecordDetailId = ?",
                [$employeeCodes[0], $employeeCodes[1], $employeeCodes[2], $employeeCodes[3], $recordDetailId]
            );
        }
    }

    public function getOperatorCodesFromCheckpoint($recordHeaderId)
    {
        $sql = "SELECT DISTINCT EmployeeCode
                FROM AtoDorCheckpointDefinition
                WHERE RecordHeaderId = ? AND EmployeeCode IS NOT NULL AND EmployeeCode <> ''";
        $result = $this->db->execute($sql, [$recordHeaderId]);
        if (!is_array($result)) {
            return [];
        }
        return array_column($result, 'EmployeeCode');
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

    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}