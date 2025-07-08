<?php
ob_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../config/dbop.php";

// Check authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['production_code'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$itemId = trim($_POST['item_id'] ?? '');
$modelId = isset($_POST['model_id']) ? (int)$_POST['model_id'] : null;

if (empty($itemId)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $db = new DbOp(1);

    if ($modelId) {
        // Check for update (exclude current model)
        $sql = "SELECT COUNT(*) as count FROM GenModel WHERE ITEM_ID = ? AND MODEL_ID != ?";
        $params = [$itemId, $modelId];
    } else {
        // Check for add
        $sql = "SELECT COUNT(*) as count FROM GenModel WHERE ITEM_ID = ?";
        $params = [$itemId];
    }

    $result = $db->execute($sql, $params, 1);

    if ($result && $result[0]['count'] > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
