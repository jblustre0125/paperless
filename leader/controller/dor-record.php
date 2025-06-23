<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/dbop.php';

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $db = new DbOp(1);
    $data = json_decode(file_get_contents('php://input'), true);

    $hostname = trim($data['hostname'] ?? '');
    $dorType = strtolower($data['dor_type'] ?? '');

    $userId = $_SESSION['user_id'] ?? 1;
    $incharge = $_SESSION['employee_name'] ?? 'Leader';

    if (!$hostname || !in_array($dorType, ['hatsumono', 'nakamono', 'owarimono'])) {
        throw new Exception("Invalid hostname or DOR type.");
    }

    // Get HostnameId
    $host = $db->execute("SELECT HostnameId FROM GenHostname WHERE Hostname = ?", [$hostname]);
    if (empty($host)) {
        throw new Exception("Hostname not found.");
    }
    $hostnameId = $host[0]['HostnameId'];

    // Fetch existing RecordId for this hostname & today's date
    $recordRow = $db->execute("
        SELECT TOP 1 RecordId 
        FROM AtoDor 
        WHERE HostnameId = ? 
        AND CAST(DorDate AS DATE) = CAST(GETDATE() AS DATE)
        ORDER BY CreatedDate DESC
    ", [$hostnameId]);

    $recordId = $recordRow[0]['RecordId'] ?? null;

    if (!$recordId) {
        throw new Exception("No existing DOR found for this hostname today.");
    }

    // Define checkpoints to process
    $checkpoints = [
        'Taping Condition'    => $data['taping_condition'] ?? null,
        'Folding Type'        => $data['folding_type'] ?? null,
        'Connector Condition' => $data['connector_condition'] ?? null
    ];

    $dorTypeCol = ucfirst($dorType); // e.g., "Hatsumono"

    foreach ($checkpoints as $checkpointName => $value) {
        if (is_null($value)) {
            throw new Exception("Missing value for '$checkpointName'");
        }

        //  Get CheckpointId from GenDorCheckpointVisual
        $checkpoint = $db->execute("
            SELECT TOP 1 CheckpointId 
            FROM GenDorCheckpointVisual 
            WHERE CheckpointName = ? AND DorTypeId = (
                SELECT DorTypeId FROM GenDorType WHERE DorTypeName = ?
            )
            ORDER BY SequenceId
        ", [$checkpointName, $dorType]);

        if (empty($checkpoint)) {
            throw new Exception("Checkpoint not found: $checkpointName");
        }

        $checkpointId = $checkpoint[0]['CheckpointId'];

        // UPSERT into AtoDorCheckpointVisual
        $existing = $db->execute("
            SELECT RecordDetailId 
            FROM AtoDorCheckpointVisual
            WHERE RecordId = ? AND CheckpointId = ?
        ", [$recordId, $checkpointId]);

        if (!empty($existing)) {
            // Update only the current DOR type column
            $sql = "
                UPDATE AtoDorCheckpointVisual 
                SET {$dorTypeCol} = ? 
                WHERE RecordId = ? AND CheckpointId = ?
            ";
            $params = [$value, $recordId, $checkpointId];
        } else {
            // Insert with only current DOR type filled
            $sql = "
                INSERT INTO AtoDorCheckpointVisual 
                (RecordId, CheckpointId, Hatsumono, Nakamono, Owarimono)
                VALUES (?, ?, ?, ?, ?)
            ";
            $params = [
                $recordId,
                $checkpointId,
                $dorTypeCol === 'Hatsumono' ? $value : '',
                $dorTypeCol === 'Nakamono' ? $value : '',
                $dorTypeCol === 'Owarimono' ? $value : ''
            ];
        }

        $db->execute($sql, $params);
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'DOR and visual checkpoints saved successfully',
        'record_id'  => $recordId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
