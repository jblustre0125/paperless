<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/dbop.php';

try {
    $db = new DbOp(1);
    
    // Test connection first
    $db->execute("SELECT 1");
    
    $sql = "SELECT CheckpointId, SequenceId, CheckpointName, 
                   CriteriaGood, CriteriaNotGood, CheckpointTypeId 
            FROM GenDorCheckpointVisual 
            WHERE IsActive = 1 
            ORDER BY SequenceId";
    
    $checkpoints = $db->execute($sql);
    
    if (empty($checkpoints)) {
        throw new Exception("No active checkpoints found");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $checkpoints
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTrace() // Remove in production
    ]);
}