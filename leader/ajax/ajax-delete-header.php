<?php
// leader/ajax/ajax-delete-header.php
require_once '../../config/dbop.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid request']);
  exit;
}

$recordHeaderId = $_POST['recordHeaderId'] ?? '';
if (!$recordHeaderId) {
  echo json_encode(['success' => false, 'message' => 'Missing recordHeaderId']);
  exit;
}

try {
  // Delete from AtoDorHeader
  $sql = "DELETE FROM AtoDorHeader WHERE RecordHeaderId = ?";
  $result = $db->execute($sql, [$recordHeaderId]);
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
