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
            SELECT h.RecordHeaderId, h.RecordId, h.BoxNumber, h.TimeStart, h.TimeEnd, h.Duration, d.CreatedBy
            FROM AtoDorHeader h
            INNER JOIN AtoDor d ON h.RecordId = d.RecordId
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
        return $result[0]['MP'] ?? null;
    }

    public function insertOrUpdateDetail($data)
    {
        $recordHeaderId = $data['RecordHeaderId'] ?? null;
        if (!$recordHeaderId) return false;

        $existing = $this->db->execute("SELECT * FROM AtoDorDetail WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? [];

        // Always fetch from header for time fields
        $header = $this->db->execute("SELECT TimeStart, TimeEnd, Duration FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? [];

        $fields = [
            'OperatorCode1' => $data['OperatorCode1'] ?? $existing['OperatorCode1'] ?? null,
            'OperatorCode2' => $data['OperatorCode2'] ?? $existing['OperatorCode2'] ?? null,
            'OperatorCode3' => $data['OperatorCode3'] ?? $existing['OperatorCode3'] ?? null,
            'OperatorCode4' => $data['OperatorCode4'] ?? $existing['OperatorCode4'] ?? null,
            'DowntimeId'    => $data['DowntimeId'] ?? $existing['DowntimeId'] ?? null,
            'ActionTakenId' => $data['ActionTakenId'] ?? $existing['ActionTakenId'] ?? null,
            'TimeStart'     => $header['TimeStart'] ?? $existing['TimeStart'] ?? null,
            'TimeEnd'       => $header['TimeEnd'] ?? $existing['TimeEnd'] ?? null,
            'Duration'      => $header['Duration'] ?? $existing['Duration'] ?? null,
            'Pic'           => $data['Pic'] ?? $existing['Pic'] ?? null,
            'RemarksId'     => $data['RemarksId'] ?? $existing['RemarksId'] ?? null,
        ];

        if ($existing) {
            $setClause = implode(", ", array_map(fn($k) => "$k = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $recordHeaderId;
            $sql = "UPDATE AtoDorDetail SET $setClause WHERE RecordHeaderId = ?";
        } else {
            $columns = implode(", ", array_keys($fields));
            $placeholders = implode(", ", array_fill(0, count($fields), '?'));
            $values = array_values($fields);
            array_unshift($values, $recordHeaderId);
            $sql = "INSERT INTO AtoDorDetail (RecordHeaderId, $columns) VALUES (?, $placeholders)";
        }

        $this->db->execute($sql, $values);

        $headerRow = $this->db->execute("SELECT RecordId FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? null;

        if ($headerRow) {
            $recordId = $headerRow['RecordId'];
            foreach (array_reverse(['OperatorCode1', 'OperatorCode2', 'OperatorCode3', 'OperatorCode4']) as $key) {
                if (!empty($fields[$key])) {
                    $this->db->execute("UPDATE AtoDor SET CreatedBy = ? WHERE RecordId = ?", [$fields[$key], $recordId]);
                    break;
                }
            }
        }

        return true;
    }

    public function getAllDowntimeDetails()
{
    $result = $this->db->execute("SELECT * FROM AtoDorDetail");
    $groupedDetails = [];

    foreach ($result as $row) {
        $recordHeaderId = $row['RecordHeaderId'] ?? null;
        if ($recordHeaderId !== null) {
            $groupedDetails[$recordHeaderId][] = $row;
        }
    }

    return $groupedDetails;
}


    public function deleteOperator($recordHeaderId, $operatorCode)
    {
        $detail = $this->db->execute("SELECT * FROM AtoDorDetail WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? null;
        if (!$detail) return false;

        $updates = [];
        foreach (['OperatorCode1', 'OperatorCode2', 'OperatorCode3', 'OperatorCode4'] as $key) {
            if ($detail[$key] === $operatorCode) {
                $updates[$key] = null;
            }
        }

        if (empty($updates)) return false;

        $setClause = implode(", ", array_map(fn($k) => "$k = NULL", array_keys($updates)));
        return $this->db->execute("UPDATE AtoDorDetail SET $setClause WHERE RecordHeaderId = ?", [$recordHeaderId]);
    }

    public function updateDowntime($recordHeaderId, $downtimeId)
    {
        return $this->db->execute("UPDATE AtoDorDetail SET DowntimeId = ? WHERE RecordHeaderId = ?", [$downtimeId, $recordHeaderId]);
    }

    public function saveOperators($recordHeaderId, $employeeCodes)
    {
        if (!$recordHeaderId || !is_array($employeeCodes)) return false;

        $employeeCodes = array_unique(array_filter($employeeCodes)); // Filter out duplicates and empty

        $mpRequired = $this->getMPByRecordHeaderId($recordHeaderId);
        if ($mpRequired !== null && count($employeeCodes) < (int)$mpRequired) {
            return false;
        }

        // Always fetch from header for time fields
        $header = $this->db->execute("SELECT TimeStart, TimeEnd, Duration FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? [];

        $fields = [
            'OperatorCode1' => $employeeCodes[0] ?? null,
            'OperatorCode2' => $employeeCodes[1] ?? null,
            'OperatorCode3' => $employeeCodes[2] ?? null,
            'OperatorCode4' => $employeeCodes[3] ?? null,
            'TimeStart'     => $header['TimeStart'] ?? null,
            'TimeEnd'       => $header['TimeEnd'] ?? null,
            'Duration'      => $header['Duration'] ?? null,
        ];

        // Check if detail exists
        $exists = $this->db->execute(
            "SELECT COUNT(*) AS cnt FROM AtoDorDetail WHERE RecordHeaderId = ?",
            [$recordHeaderId]
        )[0]['cnt'] ?? 0;

        if ($exists == 0) {
            // Insert new row
            $columns = implode(", ", array_keys($fields));
            $placeholders = implode(", ", array_fill(0, count($fields), '?'));
            $values = array_values($fields);
            array_unshift($values, $recordHeaderId);
            $this->db->execute(
                "INSERT INTO AtoDorDetail (RecordHeaderId, $columns) VALUES (?, $placeholders)",
                $values
            );
        } else {
            // Update existing row
            $setClause = implode(", ", array_map(fn($k) => "$k = ?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $recordHeaderId;
            $this->db->execute(
                "UPDATE AtoDorDetail SET $setClause WHERE RecordHeaderId = ?",
                $values
            );
        }

        $record = $this->db->execute("SELECT RecordId FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? null;

        if ($record) {
            foreach (array_reverse($fields) as $code) {
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

// ================== MAIN SCRIPT ===================

$controller = new DorDor();
$hostnameId = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : null;
    $headers = $controller->getHeaders($hostnameId);
    $details = $controller->getDetails();
    $downtimeOptions = $controller->getDowntimeList();
    $operatorMap = $controller->getOperatorMap();
    $actionTakenOptions = $controller->getActionTakenList();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['type']) && $_GET['type'] === 'getActionDowntime') {
        $recordHeaderId = $_GET['recordHeaderId'] ?? null;

        if (!$recordHeaderId) {
            echo json_encode(['success' => false, 'message' => 'Missing recordHeaderId']);
            exit;
        }

        $detail = $controller->getDetails()[$recordHeaderId] ?? null;

        echo json_encode([
            'success' => (bool) $detail,
            'downtimeId' => $detail['DowntimeId'] ?? null,
            'actionTakenId' => $detail['ActionTakenId'] ?? null,
        ]);
        exit;
    }

    
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $type = $data['type'] ?? null;

    switch ($type) {
        case 'saveOperators':
            $recordHeaderId = $data['recordHeaderId'] ?? null;
            $employeeCodes = $data['employeeCodes'] ?? [];

            $mp = $controller->getMPByRecordHeaderId($recordHeaderId);
            $validCount = count(array_unique(array_filter($employeeCodes)));

            if ($mp !== null && $validCount < (int)$mp) {
                echo json_encode(['success' => false, 'message' => "At least {$mp} operator(s) required."]);
                exit;
            }

            $success = $controller->saveOperators($recordHeaderId, $employeeCodes);
            echo json_encode(['success' => $success]);
            break;

        case 'saveMultipleOperators':
            $allOk = true;
            foreach ($data['data'] as $entry) {
                $recordHeaderId = $entry['recordHeaderId'] ?? null;
                $employeeCodes = $entry['employeeCodes'] ?? [];
                if (!$controller->saveOperators($recordHeaderId, $employeeCodes)) {
                    $allOk = false;
                }
            }
            echo json_encode(['success' => $allOk]);
            break;

        case 'saveActionDowntime':
            $success = $controller->insertOrUpdateDetail([
                'RecordHeaderId' => $data['recordHeaderId'] ?? null,
                'DowntimeId'     => $data['downtimeId'] ?? null,
                'ActionTakenId'  => $data['actionTakenId'] ?? null,
                'Pic'            => $data['pic'] ?? null,
            ]);
            echo json_encode(['success' => $success]);
            break;

        case 'getDowntimeDetails':
            $recordHeaderId = $data['recordHeaderId'] ?? null;
            if (!$recordHeaderId) {
                echo json_encode(['success' => false, 'message' => 'Missing recordHeaderId']);
                exit;
            }

            $details = $controller->getDetails();

            $downtimeMap = $controller->getDowntimeList();
            $actionTakenMap = $controller->getActionTakenList();

            $badges = [];

            foreach ($details as $row) {
                if ($row['RecordHeaderId'] == $recordHeaderId) {
                    $downtimeId = $row['DowntimeId'] ?? null;
                    $actionTakenId = $row['ActionTakenId'] ?? null;

                    $downtimeCode = $downtimeId && isset($downtimeMap[$downtimeId])
                        ? $downtimeMap[$downtimeId]['DowntimeCode']
                        : null;

                    $actionTakenDesc = $actionTakenId && isset($actionTakenMap[$actionTakenId])
                        ? $actionTakenMap[$actionTakenId]['ActionTakenName']
                        : 'No Description';

                    if ($downtimeCode) {
                        $badges[] = [
                            'DowntimeCode' => $downtimeCode,
                            'ActionDescription' => $actionTakenDesc
                        ];
                    }
                }
            }

            echo json_encode(['success' => true, 'badges' => $badges]);
            break;
        
        case 'renderDowntimeBadges':
    $recordHeaderId = $data['recordHeaderId'] ?? null;
    if (!$recordHeaderId) {
        echo json_encode(['success' => false, 'message' => 'Missing recordHeaderId']);
        exit;
    }

    ob_start();
    $allDetails = $controller->getAllDowntimeDetails();
    $downtimeMap = $controller->getDowntimeList();
    $actionTakenMap = $controller->getActionTakenList();
    $details = $allDetails[$recordHeaderId] ?? [];

    if (!empty($details)) {
        foreach ($details as $detail) {
            $downtimeId = $detail['DowntimeId'] ?? null;
            $actionTakenId = $detail['ActionTakenId'] ?? null;

            $downtimeCode = $downtimeId && isset($downtimeMap[$downtimeId])
                ? $downtimeMap[$downtimeId]['DowntimeCode']
                : null;

            $actionTakenTitle = $actionTakenId && isset($actionTakenMap[$actionTakenId])
                ? $actionTakenMap[$actionTakenId]['ActionTakenName']
                : 'No Description';

            if (!empty($downtimeCode)) {
                echo '<small class="badge bg-danger text-white me-1 mb-1" title="' . htmlspecialchars($actionTakenTitle) . '">'
                    . htmlspecialchars($downtimeCode) .
                    '</small>';
            }
        }
    } else {
        echo '<small class="badge bg-secondary text-white me-1 mb-1">No Downtime</small>';
    }

    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    break;



        case 'deleteOperator':
            $success = $controller->deleteOperator($data['recordHeaderId'], $data['operatorCode']);
            echo json_encode(['success' => $success]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown request type']);
            break;
    }
    exit;
}
