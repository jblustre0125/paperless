<?php
session_start();
require_once "../config/method.php";

header('Content-Type: application/json');

$response = ['success' => false, 'workInstructionPath' => ''];

if (isset($_POST['processNumber']) && isset($_SESSION['dorTypeId']) && isset($_SESSION['dorModelId'])) {
    $processNumber = intval($_POST['processNumber']);
    if ($processNumber >= 1 && $processNumber <= 4) {
        $_SESSION['activeProcess'] = $processNumber;
        $workInstructionPath = getWorkInstruction($_SESSION['dorTypeId'], $_SESSION['dorModelId'], $processNumber);
        $response['success'] = true;
        $response['workInstructionPath'] = $workInstructionPath;
    }
}

echo json_encode($response);
