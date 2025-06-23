<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/dbop.php';


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    $db = new DbOp(1);

    $dorType = strtolower($_POST['dor_type'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['employee_name'] ?? 'System';

    if (!$dorType || !$userId) {
        throw new Exception('Missing DOR type or session user');
    }

    if (!in_array($dorType, ['hatsumono', 'nakamono', 'owarimono'])) {
        throw new Exception('Invalid DOR type specified');
    }

    $recordRow = $db->execute(
        "SELECT TOP 1 RecordId FROM AtoDor ORDER BY CreatedDate DESC"
    );

    if (empty($recordRow)) {
        throw new Exception('No record found in AtoDor');
    }

    $recordId = $recordRow[0]['RecordId'];

    // Collect inspection values
    $tapingCondition = $_POST["taping_condition_{$dorType}"] ?? null;
    $foldingType = $_POST["folding_type_{$dorType}"] ?? null;
    $connectorCondition = $_POST["connector_condition_{$dorType}"] ?? null;

    if (!$tapingCondition || !$foldingType || !$connectorCondition) {
        throw new Exception('All inspection fields are required');
    }

    $judgment = ($tapingCondition === 'OK' && $connectorCondition === 'OK') ? 'Approved' : 'Rejected';
    $dorTypeUC = ucfirst($dorType);

    // Update AtoDor table
    $updateSql = "
        UPDATE AtoDor SET 
            {$dorTypeUC}1Judge = ?, 
            {$dorTypeUC}1CheckBy = ?, 
            ModifiedBy = ?, 
            ModifiedDate = GETDATE()
        WHERE RecordId = ?
    ";
    $db->execute($updateSql, [$judgment, $username, $userId, $recordId]);

    // Checkpoints to insert/update
    $checkpoints = [
        1 => $tapingCondition,
        2 => $foldingType,
        5 => $connectorCondition
    ];

    foreach ($checkpoints as $checkpointId => $value) {
        $exists = $db->execute(
            "SELECT RecordDetailId FROM AtoDorCheckpointVisual WHERE RecordId = ? AND CheckpointId = ?",
            [$recordId, $checkpointId]
        );

        if (!empty($exists)) {
            // Update only the specific DOR column
            $sql = "
                UPDATE AtoDorCheckpointVisual
                SET {$dorTypeUC} = ?
                WHERE RecordId = ? AND CheckpointId = ?
            ";
            $params = [$value, $recordId, $checkpointId];
        } else {
            // Insert new row
            $sql = "
                INSERT INTO AtoDorCheckpointVisual (RecordId, CheckpointId, Hatsumono, Nakamono, Owarimono)
                VALUES (?, ?, ?, ?, ?)
            ";
            $params = [
                $recordId,
                $checkpointId,
                $dorTypeUC === 'Hatsumono' ? $value : '',
                $dorTypeUC === 'Nakamono' ? $value : '',
                $dorTypeUC === 'Owarimono' ? $value : ''
            ];
        }

        $db->execute($sql, $params);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Visual inspection saved for latest record',
        'data' => [
            'record_id' => $recordId,
            'judgment' => $judgment,
            'checked_by' => $username
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'record_id' => $recordId ?? null
    ]);
}
