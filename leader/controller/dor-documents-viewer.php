<?php
    require_once '../../config/method.php';

    header('Content-Type: application/json');

    $dorTypeId = $_GET['dorTypeId'] ?? null;
    $modelId = $_GET['modelid'] ?? null;
    $docType = $_GET['docType'] ?? null;
    $processNumber = $_GET['processNumber'] ?? 1;

    if(!$dorTypeId || !$modelId || !$docType){
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    switch (strtolower($docType)){
        case 'drawing':
            $path = getDrawing($dorTypeId, $modelId);
            break;

        case 'workingInstruction':
            $path = getWorkInstruction($dorTypeId, $modelId, $processNumber);
            break;
        
        case 'prepcard':
            $path = getPreparationCard($modelId);
            break;
        default:
            echo json_encode(['error' => 'Unknown document type']);
            exit;
    }

    echo json_encode(['path' => $path]);
?>  x