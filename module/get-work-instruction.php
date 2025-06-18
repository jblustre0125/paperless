<?php
session_start();
require_once "../config/dbop.php";
require_once "../config/method.php";

header('Content-Type: application/json');

$response = ['success' => false, 'file' => null, 'error' => null];

// Debug logging
error_log("get-work-instruction.php called");
error_log("Session data: " . print_r($_SESSION, true));
error_log("GET data: " . print_r($_GET, true));

if (!isset($_SESSION['dorTypeId']) || !isset($_SESSION['dorModelId'])) {
    $response['error'] = 'Session data missing';
    error_log("Error: Session data missing");
    echo json_encode($response);
    exit;
}

$process = isset($_GET['process']) ? intval($_GET['process']) : 1;
error_log("Process number: " . $process);

// Get the work instruction file for the specified process
$workInstructFile = getWorkInstruction($_SESSION["dorTypeId"], $_SESSION['dorModelId'], $process);
error_log("Work instruction file path: " . $workInstructFile);

if ($workInstructFile) {
    $response['success'] = true;
    $response['file'] = $workInstructFile;
    error_log("Success: Found work instruction file");
} else {
    $response['error'] = 'No work instruction found for process ' . $process;
    error_log("Error: No work instruction found");
}

echo json_encode($response);
