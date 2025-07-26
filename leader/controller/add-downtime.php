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

$db = new DbOp(1);

// Step 1: Get RecordId from AtoDorHeader
$headerIdResult = $db->execute("SELECT RecordId FROM AtoDorHeader WHERE RecordHeaderId = ?", [$recordHeaderId]);
$recordId = !empty($headerIdResult) ? $headerIdResult[0]['RecordId'] : null;

// Step 2: Get HostnameId from AtoDor using RecordId
$headerResult = $db->execute("SELECT HostnameId FROM AtoDor WHERE RecordId = ?", [$recordId]);
$header = !empty($headerResult) ? $headerResult[0] : [];

// Now $header['HostnameId'] should be set
$header['RecordId'] = $recordId;

include '../partials/downtime-modal.php';
