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
            } else {
                error_log("Missing RecordHeaderId in AtoDorDetail row: " . print_r($row, true));
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
    //     public function syncOperatorsFromCheckpointDefinition()
    // {
    //     $logFile = __DIR__ . '/sync-debug.txt';
    //     file_put_contents($logFile, "Starting sync at " . date('Y-m-d H:i:s') . "\n");

    //     $records = $this->db->execute("SELECT RecordHeaderId, RecordId FROM AtoDorHeader");

    //     $updated = 0;

    //     foreach ($records as $record) {
    //         $recordHeaderId = $record['RecordHeaderId'];
    //         $recordId = $record['RecordId'];

    //         // Step 1: Fetch non-leader employee codes from CheckpointDefinition
    //         $employees = $this->db->execute(
    //             "SELECT DISTINCT EmployeeCode FROM AtoDorCheckpointDefinition WHERE RecordId = ? AND IsLeader = 0",
    //             [$recordId]
    //         );

    //         if (!$employees || count($employees) === 0) {
    //             file_put_contents($logFile, "No non-leader employees for RecordId $recordId\n", FILE_APPEND);
    //             continue;
    //         }

    //         $employeeCodes = array_unique(array_column($employees, 'EmployeeCode'));

    //         // Step 2: Get ModelId from AtoDor
    //         $atoDor = $this->db->execute("SELECT ModelId FROM AtoDor WHERE RecordId = ?", [$recordId]);
    //         if (!$atoDor || !isset($atoDor[0]['ModelId'])) {
    //             file_put_contents($logFile, "No ModelId for RecordId $recordId\n", FILE_APPEND);
    //             continue;
    //         }

    //         $modelId = $atoDor[0]['ModelId'];

    //         // Step 3: Get MP from GenModel
    //         $genModel = $this->db->execute("SELECT MP FROM GenModel WHERE MODEL_ID = ?", [$modelId]);
    //         if (!$genModel || !isset($genModel[0]['MP'])) {
    //             file_put_contents($logFile, "No MP defined for ModelId $modelId\n", FILE_APPEND);
    //             continue;
    //         }

    //         $mp = (int)$genModel[0]['MP'];
    //         if ($mp <= 0) {
    //             file_put_contents($logFile, "MP is zero or invalid for ModelId $modelId\n", FILE_APPEND);
    //             continue;
    //         }

    //         // Step 4: Match MP count with available employee codes
    //         $operatorCodes = array_slice($employeeCodes, 0, $mp);

    //         // Step 5: Pad up to 4 values
    //         while (count($operatorCodes) < 4) {
    //             $operatorCodes[] = null;
    //         }

    //         list($op1, $op2, $op3, $op4) = $operatorCodes;

    //         // Step 6: Ensure corresponding AtoDorDetail exists
    //         $detail = $this->db->execute("SELECT RecordDetailId FROM AtoDorDetail WHERE RecordHeaderId = ?", [$recordHeaderId]);
    //         if (!$detail || count($detail) === 0) {
    //             file_put_contents($logFile, "No AtoDorDetail found for RecordHeaderId $recordHeaderId\n", FILE_APPEND);
    //             continue;
    //         }

    //         // Step 7: Update the operator codes
    //         $result = $this->db->execute(
    //             "UPDATE AtoDorDetail SET OperatorCode1 = ?, OperatorCode2 = ?, OperatorCode3 = ?, OperatorCode4 = ? WHERE RecordHeaderId = ?",
    //             [$op1, $op2, $op3, $op4, $recordHeaderId]
    //         );

    //         if ($result) {
    //             file_put_contents($logFile, "Updated RecordHeaderId $recordHeaderId with: [$op1, $op2, $op3, $op4] (MP=$mp)\n", FILE_APPEND);
    //             $updated++;
    //         } else {
    //             file_put_contents($logFile, "Failed to update RecordHeaderId $recordHeaderId\n", FILE_APPEND);
    //         }
    //     }

    //     file_put_contents($logFile, "Sync completed. $updated rows updated.\n", FILE_APPEND);
    //     return $updated;
    // }





    public function insertOrUpdateDetail($data)
    {
        $recordHeaderId = $data['RecordHeaderId'] ?? null;
        if (!$recordHeaderId) return false;

        $existing = $this->db->execute("SELECT * FROM AtoDorDetail WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? [];

        $fields = [
            'OperatorCode1' => $data['OperatorCode1'] ?? $existing['OperatorCode1'] ?? null,
            'OperatorCode2' => $data['OperatorCode2'] ?? $existing['OperatorCode2'] ?? null,
            'OperatorCode3' => $data['OperatorCode3'] ?? $existing['OperatorCode3'] ?? null,
            'OperatorCode4' => $data['OperatorCode4'] ?? $existing['OperatorCode4'] ?? null,
            'DowntimeId'    => $data['DowntimeId'] ?? $existing['DowntimeId'] ?? null,
            'ActionTakenId' => $data['ActionTakenId'] ?? $existing['ActionTakenId'] ?? null,
            'TimeStart'     => $data['TimeStart'] ?? $existing['TimeStart'] ?? null,
            'TimeEnd'       => $data['TimeEnd'] ?? $existing['TimeEnd'] ?? null,
            'Duration'      => $data['Duration'] ?? $existing['Duration'] ?? null,
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

        $header = $this->db->execute("SELECT RecordId FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId])[0] ?? null;

        if ($header) {
            $recordId = $header['RecordId'];
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
        if (empty($recordHeaderId) || !is_array($employeeCodes)) {
            file_put_contents(__DIR__ . '/log.txt', "Invalid input: Missing RecordHeaderId or employeeCodes is not array\n", FILE_APPEND);
            return false;
        }

        // Clean input: remove duplicates and empty values
        $employeeCodes = array_values(array_unique(array_filter($employeeCodes)));

        // Check if enough operators (if MP exists)
        $mpRequired = $this->getMPByRecordHeaderId($recordHeaderId);
        if ($mpRequired !== null && count($employeeCodes) < (int)$mpRequired) {
            file_put_contents(__DIR__ . '/log.txt', "Not enough operators: MP required = $mpRequired, given = " . count($employeeCodes) . "\n", FILE_APPEND);
            return false;
        }

        // Prepare up to 4 operator codes
        $fields = [
            'OperatorCode1' => $employeeCodes[0] ?? null,
            'OperatorCode2' => $employeeCodes[1] ?? null,
            'OperatorCode3' => $employeeCodes[2] ?? null,
            'OperatorCode4' => $employeeCodes[3] ?? null,
        ];

        // Build SQL
        $setClause = implode(", ", array_map(fn($k) => "$k = ?", array_keys($fields)));
        $values = array_values($fields);
        $values[] = $recordHeaderId;

        $sql = "UPDATE AtoDorDetail SET $setClause WHERE RecordHeaderId = ?";

        // Log for debugging
        file_put_contents(__DIR__ . '/log.txt', print_r([
            'query' => $sql,
            'values' => $values
        ], true), FILE_APPEND);

        // Run update
        try {
            $result = $this->db->execute($sql, $values);
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/log.txt', "PDO Error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }

        if (!$result) {
            file_put_contents(__DIR__ . '/log.txt', "Update failed (possibly no matching RecordHeaderId)\n", FILE_APPEND);
            return false;
        }

        // Update CreatedBy in AtoDor (based on RecordId from header)
        $record = $this->db->execute("SELECT RecordId FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId]);

        if ($record && isset($record['RecordId'])) {
            foreach (array_reverse($employeeCodes) as $code) {
                if (!empty($code)) {
                    $this->db->execute("UPDATE AtoDor SET CreatedBy = ? WHERE RecordId = ?", [$code, $record['RecordId']]);
                    break;
                }
            }
        } else {
            file_put_contents(__DIR__ . '/log.txt', "No matching RecordId found for RecordHeaderId: $recordHeaderId\n", FILE_APPEND);
        }

        return true;
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
    file_put_contents(__DIR__ . '/log.txt', print_r($data, true));
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

        // case 'syncOperators':
        //     $success = $controller->syncOperatorsFromCheckpointDefinition();
        //     echo json_encode(['success' => true, 'updated' => $success]);
        //     break;

        case 'getOperatorMap':
            $operatorMap = $controller->getOperatorMap();
            echo json_encode([
                'success' => true,
                'operatorMap' => $operatorMap
            ]);
            break;


        case 'deleteOperator':
            $success = $controller->deleteOperator($data['recordHeaderId'], $data['operatorCode']);
            echo json_encode(['success' => $success]);
            break;

        default:
            $html = ob_get_clean();
            echo json_encode([
                'success' => true,
                'html' => $html
            ]);
            exit;
    }
    exit;
}
