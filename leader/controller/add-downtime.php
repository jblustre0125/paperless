<?php
require_once '../../config/dbop.php';
require_once 'dor-downtime.php';

$recordHeaderId = $_GET['record_header_id'] ?? null;
if (!$recordHeaderId) {
    die('Invalid record header ID');
}

$i = isset($_GET['row']) ? (int) $_GET['row'] : 0;


$controller = new DorDowntime();
$downtimeOptions = $controller->getDowntimeList();
$actionTakenOptions = $controller->getActionList();
$remarksOptions = $controller->getRemarksList();

// Get existing downtime data if available
$existingData = $controller->AtoDor();

// Include the modal partial
include '../partials/downtime-modal.php';