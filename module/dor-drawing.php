<?php
// get_drawing.php
require_once "../config/dbop.php"; // Ensure you include your DB class if needed.

$dorTypeId = $_GET['dorTypeId'] ?? null;

if (!$dorTypeId) {
    echo json_encode(["error" => "Invalid request."]);
    exit;
}

// Mapping DOR Type IDs to folder names
$dorFolders = [
    1 => "pre-assy",
    2 => "clamp-assy",
    3 => "taping"
];

// Check if the selected DOR type exists in the mapping
if (!isset($dorFolders[$dorTypeId])) {
    echo json_encode(["error" => "Invalid DOR type."]);
    exit;
}

$folder = "../img/drawings/" . $dorFolders[$dorTypeId];
$drawingFile = glob("$folder/*.png"); // Get the first PNG file in the folder

if (!empty($drawingFile)) {
    echo json_encode(["drawing" => $drawingFile[0]]);
} else {
    echo json_encode(["error" => "Drawing is not available for this model."]);
}
