<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/dbop.php';

$db = new dbOp(1);

// --- Validate Hostname ID
$hostname_id = isset($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : 0;
if ($hostname_id <= 0) {
    die("Invalid HostnameId.");
}

// --- Fetch latest RecordId for this Hostname
$sql = "SELECT TOP 1 RecordId, DorTypeId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
$result = $db->execute($sql, [$hostname_id]);

if (empty($result)) {
    die("No record found for this hostname.");
}

$recordId = $result[0]['RecordId'];

// --- Insert or Update AtoDimensionCheck rows
for ($i = 1; $i <= 20; $i++) {
    $dimCheckId = $_POST['dim_check_id'][$i] ?? null;

    $h1 = $_POST['hatsumono_value_1'][$i] ?? null;
    $h2 = $_POST['hatsumono_value_2'][$i] ?? null;
    $h3 = $_POST['hatsumono_value_3'][$i] ?? null;

    $n1 = $_POST['nakamono_value_1'][$i] ?? null;
    $n2 = $_POST['nakamono_value_2'][$i] ?? null;
    $n3 = $_POST['nakamono_value_3'][$i] ?? null;

    $o1 = $_POST['owarimono_value_1'][$i] ?? null;
    $o2 = $_POST['owarimono_value_2'][$i] ?? null;
    $o3 = $_POST['owarimono_value_3'][$i] ?? null;

    $values = [$h1, $h2, $h3, $n1, $n2, $n3, $o1, $o2, $o3];
    $hasAnyValue = array_filter($values, fn($v) => $v !== null && $v !== '');

    if (empty($hasAnyValue)) {
        continue;
    }

    // Normalize empty strings to nulls
    $values = array_map(fn($v) => $v === '' ? null : $v, $values);

    try {
        if (!empty($dimCheckId)) {
            // Update
            $sql = "
                UPDATE AtoDimensionCheck SET
                    Hatsumono1 = ?, Hatsumono2 = ?, Hatsumono3 = ?,
                    Nakamono1 = ?, Nakamono2 = ?, Nakamono3 = ?,
                    Owarimono1 = ?, Owarimono2 = ?, Owarimono3 = ?
                WHERE DimCheckId = ?
            ";
            $db->execute($sql, [...$values, $dimCheckId]);
        } else {
            // Insert
            $sql = "
                INSERT INTO AtoDimensionCheck (
                    RecordId, Hatsumono1, Hatsumono2, Hatsumono3,
                    Nakamono1, Nakamono2, Nakamono3,
                    Owarimono1, Owarimono2, Owarimono3
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $db->execute($sql, [$recordId, ...$values]);
        }
    } catch (Exception $e) {
        die("DB Error (AtoDimensionCheck): " . $e->getMessage());
    }
}

// --- Prepare Judge and CheckBy fields for AtoDor
$fields = [];
$params = [];

foreach (['hatsumono', 'nakamono', 'owarimono'] as $section) {
    for ($j = 1; $j <= 3; $j++) {
        $judgeKey = "judge_{$section}_{$j}";
        $checkByKey = "checkby_{$section}{$j}";

        $judge = array_key_exists($judgeKey, $_POST) ? $_POST[$judgeKey] : null;
        $checkBy = array_key_exists($checkByKey, $_POST) ? $_POST[$checkByKey] : ($_SESSION['production_code'] ?? null);

        error_log("JUDGE {$judgeKey}: " . var_export($judge, true));

        $fields[] = ucfirst($section) . $j . "Judge = ?";
        $params[] = ($judge === '') ? null : $judge;

        $fields[] = ucfirst($section) . $j . "CheckBy = ?";
        $params[] = ($checkBy === '') ? null : $checkBy;
    }
}

// Metadata fields
if (!empty($_SESSION['production_code'])) {
    $fields[] = "ModifiedBy = ?";
    $params[] = $_SESSION['production_code'];
    $fields[] = "NotedBy = ?";
    $params[] = $_SESSION['production_code'];
}
$fields[] = "ModifiedDate = GETDATE()"; // No param needed

// --- Update or Insert into AtoDor
try {
    if (!empty($fields)) {
        $exists = $db->execute("SELECT RecordId FROM AtoDor WHERE RecordId = ?", [$recordId]);

        if ($exists) {
            // Update
            $setClause = implode(", ", $fields);
            $sql = "UPDATE AtoDor SET $setClause WHERE RecordId = ?";
            $db->execute($sql, array_merge($params, [$recordId]));
        } else {
            // Insert
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
} catch (Exception $e) {
    die("DB Error (AtoDor): " . $e->getMessage());
}

echo "Form saved successfully.";
