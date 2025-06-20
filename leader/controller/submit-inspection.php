<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/dbop.php';

try {
    $db = new DbOp(1);
    
    $recordId = $_POST['record_id'] ?? null;
    if (empty($recordId)) {
        throw new Exception("Record ID is required");
    }

    // Process checkpoints
    $checkpoints = $db->execute(
        "SELECT CheckpointId FROM GenDorCheckpointVisual WHERE IsActive = 1"
    );
    
    foreach ($checkpoints as $cp) {
        $cpId = $cp['CheckpointId'];
        $values = [
            'hatsumono' => $_POST["checkpoint_{$cpId}_hatsumono"] ?? null,
            'nakamono' => $_POST["checkpoint_{$cpId}_nakamono"] ?? null,
            'owarimono' => $_POST["checkpoint_{$cpId}_owarimono"] ?? null
        ];
        
        // Build SQL dynamically
        $columns = [];
        $params = [];
        
        foreach ($values as $type => $value) {
            if ($value !== null) {
                $columns[] = $type;
                $params[] = $value;
            }
        }
        
        if (!empty($columns)) {
            // Try update first
            $setClause = implode(' = ?, ', $columns) . ' = ?';
            $sql = "UPDATE AtoDorCheckpointVisual 
                    SET $setClause
                    WHERE RecordId = ? AND CheckpointId = ?";
            
            $params[] = $recordId;
            $params[] = $cpId;
            
            $result = $db->execute($sql, $params);
            
            // If no rows updated, insert new
            if ($result === 0) {
                $columns[] = 'RecordId';
                $columns[] = 'CheckpointId';
                $params[] = $recordId;
                $params[] = $cpId;
                
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $sql = "INSERT INTO AtoDorCheckpointVisual 
                        (" . implode(', ', $columns) . ")
                        VALUES ($placeholders)";
                
                $db->execute($sql, $params);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Inspection data saved successfully',
        'record_id' => $recordId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}