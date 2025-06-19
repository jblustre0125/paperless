<?php

require_once '../../config/dbop.php';

$method = new Method(1);
$recordId = $_POST['record_id'] ?? null;


if(!$recordId){
    die("Missing record ID.");
}

$checkpointIds = [
    1 => "Taping Condition",
    2 => "Folding",
    3 => "Connector Lock Condition"
];

foreach ($checkpointIds as $checkpointId => $checkpointName) {
    $hatsumono = $_POST["Hatsumono{$checkpointId}"] ?? "NA";
    $nakamono = $_POST["Nakamono{$checkpointId}"] ?? "NA";
    $owarinomo = $_POST["Owarinomo{$checkpointId}"] ?? "NA";

        // Insert the visual checkpoint result
        $method->insertVisualCheckpoint($recordId, $checkpointId, $hatsumono, $nakamono, $owarinomo);
    }
echo "Data submitted successfully for visual checkpoints.";
?>