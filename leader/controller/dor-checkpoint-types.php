<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/dbop.php';

try {
    $db = new DbOp(1);
    
    $sql = "SELECT CheckpointTypeId, CheckpointTypeName, CheckpointControl 
            FROM GenCheckpointType";
    
    $types = $db->execute($sql);
    
    if (empty($types)) {
        throw new Exception("No checkpoint types found");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $types
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}