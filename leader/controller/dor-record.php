<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/dbop.php';

try {
    $db = new DbOp(1);
    
    $data = json_decode(file_get_contents('php://input'), true);
    $hostname = $data['hostname'] ?? '';
    
    if (empty($hostname)) {
        throw new Exception("Hostname is required");
    }
    
    // Get HostnameId
    $host = $db->execute(
        "SELECT HostnameId FROM GenHostname WHERE Hostname = ?", 
        [$hostname]
    );
    
    if (empty($host)) {
        throw new Exception("Hostname not found");
    }
    
    $hostnameId = $host[0]['HostnameId'];
    
    // Create new record
    $sql = "INSERT INTO AtoDor (
                HostnameId,
                DorDate,
                CreatedBy,
                CreatedDate,
                Incharge
            ) VALUES (?, GETDATE(), ?, GETDATE(), ?)";
    
    $userId = 1; // Replace with actual user ID from session
    $incharge = 'Leader'; // Or get from session
    
    $db->execute($sql, [$hostnameId, $userId, $incharge]);
    
    $recordId = $db->execute("SELECT SCOPE_IDENTITY() AS id")[0]['id'];
    
    echo json_encode([
        'success' => true,
        'record_id' => $recordId,
        'message' => 'New inspection record created'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}