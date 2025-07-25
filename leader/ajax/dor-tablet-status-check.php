<?php
require_once __DIR__ . "/../../config/dbop.php";
require_once "../controller/dor-leader-method.php";
session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$method = new Method(1);
$currentTabletId = $_SESSION['hostnameId'] ?? null;
$hostnames = $method->getAllTabletWithStatus($currentTabletId);

// Only hash the fields that matter for status
$statusArray = [];
foreach ($hostnames as $row) {
    $statusArray[] = [
        'HostnameId' => $row['HostnameId'],
        'LineStatusId' => $row['LineStatusId'],
        'IsLoggedIn' => $row['IsLoggedIn'],
        'Status' => $row['Status'],
        // add more fields if needed
    ];
}

echo md5(json_encode($statusArray));
