<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/dbop.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = new dbOp(1);

    // --- Validate Hostname ID
    $hostname_id = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : 0;
    if ($hostname_id <= 0) {
        throw new Exception("Invalid HostnameId.");
    }

    // --- Fetch latest RecordId for this Hostname
    $sql = "SELECT TOP 1 RecordId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
    $result = $db->execute($sql, [$hostname_id]);

    if (empty($result)) {
        throw new Exception("No record found for this hostname.");
    }

    $recordId = $result[0]['RecordId'];

    $record = $db->execute("SELECT * FROM AtoDor WHERE RecordId = ?", [$recordId]);
    $record = $record[0] ?? [];

    // --- Track required judges per field
    $requiredJudges = [
        'hatsumono' => [false, false, false],
        'nakamono' => [false, false, false],
        'owarimono' => [false, false, false],
    ];

    // --- Insert or Update AtoDimensionCheck rows
    $dimCheckData = $_POST;

    for ($i = 1; $i <= 20; $i++) {
        $dimCheckId = $dimCheckData['dim_check_id'][$i] ?? null;

        $h1 = $dimCheckData['hatsumono_value_1'][$i] ?? null;
        $h2 = $dimCheckData['hatsumono_value_2'][$i] ?? null;
        $h3 = $dimCheckData['hatsumono_value_3'][$i] ?? null;

        $n1 = $dimCheckData['nakamono_value_1'][$i] ?? null;
        $n2 = $dimCheckData['nakamono_value_2'][$i] ?? null;
        $n3 = $dimCheckData['nakamono_value_3'][$i] ?? null;

        $o1 = $dimCheckData['owarimono_value_1'][$i] ?? null;
        $o2 = $dimCheckData['owarimono_value_2'][$i] ?? null;
        $o3 = $dimCheckData['owarimono_value_3'][$i] ?? null;

        if ($h1 !== null && $h1 !== '') $requiredJudges['hatsumono'][0] = true;
        if ($h2 !== null && $h2 !== '') $requiredJudges['hatsumono'][1] = true;
        if ($h3 !== null && $h3 !== '') $requiredJudges['hatsumono'][2] = true;

        if ($n1 !== null && $n1 !== '') $requiredJudges['nakamono'][0] = true;
        if ($n2 !== null && $n2 !== '') $requiredJudges['nakamono'][1] = true;
        if ($n3 !== null && $n3 !== '') $requiredJudges['nakamono'][2] = true;

        if ($o1 !== null && $o1 !== '') $requiredJudges['owarimono'][0] = true;
        if ($o2 !== null && $o2 !== '') $requiredJudges['owarimono'][1] = true;
        if ($o3 !== null && $o3 !== '') $requiredJudges['owarimono'][2] = true;

        $values = [$h1, $h2, $h3, $n1, $n2, $n3, $o1, $o2, $o3];
        $hasAnyValue = array_filter($values, fn($v) => $v !== null && $v !== '');

        if (empty($hasAnyValue)) {
            continue;
        }

        $values = array_map(fn($v) => $v === '' ? null : $v, $values);

        if (!empty($dimCheckId)) {
            $sql = "
                UPDATE AtoDimensionCheck SET
                    Hatsumono1 = ?, Hatsumono2 = ?, Hatsumono3 = ?,
                    Nakamono1 = ?, Nakamono2 = ?, Nakamono3 = ?,
                    Owarimono1 = ?, Owarimono2 = ?, Owarimono3 = ?
                WHERE DimCheckId = ?
            ";
            $db->execute($sql, [...$values, $dimCheckId]);
        } else {
            $sql = "
                INSERT INTO AtoDimensionCheck (
                    RecordId, Hatsumono1, Hatsumono2, Hatsumono3,
                    Nakamono1, Nakamono2, Nakamono3,
                    Owarimono1, Owarimono2, Owarimono3
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $db->execute($sql, [$recordId, ...$values]);
        }
    }

    // --- Prepare Judge and CheckBy fields for AtoDor
    $fields = [];
    $params = [];

    foreach (['hatsumono', 'nakamono', 'owarimono'] as $section) {
        for ($j = 1; $j <= 3; $j++) {
    $judge = $_POST["judge_{$section}_{$j}"] ?? null;
    $checkBy = $_POST["checkby_{$section}{$j}"] ?? ($_SESSION['production_code'] ?? null);

    // Accept and store even if no dimensional values
    if (isset($_POST["judge_{$section}_{$j}"])) {
        $fieldName = ucfirst($section) . $j . "Judge";
        $fields[] = "$fieldName = ?";
        $params[] = $judge;

        if (!empty($checkBy)) {
            $checkField = ucfirst($section) . $j . "CheckBy";
            $fields[] = "$checkField = ?";
            $params[] = $checkBy;
        }
    }
}

    }

    if (!empty($_SESSION['production_code'])) {
        $fields[] = "ModifiedBy = ?";
        $fields[] = "NotedBy = ?";
        $params[] = $_SESSION['production_code'];
        $params[] = $_SESSION['production_code'];
    }

    $fields[] = "ModifiedDate = GETDATE()"; // No param needed

    // --- Update or Insert into AtoDor
    if (!empty($fields)) {
        $exists = $db->execute("SELECT RecordId FROM AtoDor WHERE RecordId = ?", [$recordId]);

        if ($exists) {
            $setClause = implode(", ", $fields);
            $sql = "UPDATE AtoDor SET $setClause WHERE RecordId = ?";
            $db->execute($sql, array_merge($params, [$recordId]));
        } else {
            $columns = ['RecordId'];
            $placeholders = ['?'];
            $values = [$recordId];

            $paramIndex = 0;
            foreach ($fields as $field) {
                if (strpos($field, ' = ') !== false) {
                    [$col, $val] = explode(' = ', $field);
                    if (trim($val) === 'GETDATE()') {
                        $columns[] = trim($col);
                        $placeholders[] = 'GETDATE()';
                    } else {
                        $columns[] = trim($col);
                        $placeholders[] = '?';
                        $values[] = $params[$paramIndex++];
                    }
                }
            }

            $sql = "INSERT INTO AtoDor (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $db->execute($sql, $values);
        }
    }

    //echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
