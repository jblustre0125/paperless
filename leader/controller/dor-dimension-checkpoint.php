<?php

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
        $judge = $_POST["judge_{$section}_{$j}"] ?? null;

        if ($judge !== null && $judge !== '') {
            $fields[] = ucfirst($section) . $j . "Judge = ?";
            $params[] = $judge;

            $checkBy = $_POST["checkby_{$section}{$j}"] ?? ($_SESSION['employee_code'] ?? null);
            if (!empty($checkBy)) {
                $fields[] = ucfirst($section) . $j . "CheckBy = ?";
                $params[] = $checkBy;
            }
        }
    }
}

// Metadata fields
if (!empty($_SESSION['employee_code'])) {
    $fields[] = "ModifiedBy = ?";
    $fields[] = "NotedBy = ?";
    $params[] = $_SESSION['employee_code'];
    $params[] = $_SESSION['employee_code'];
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
            $db->execute($sql, [...$params, $recordId]);
        } else {
            // Insert
            $columns = ['RecordId'];
            $placeholders = ['?'];
            $values = [$recordId];

            foreach ($fields as $index => $field) {
                if (strpos($field, ' = ') !== false) {
                    [$col, $val] = explode(' = ', $field);
                    if (trim($val) !== 'GETDATE()') {
                        $columns[] = trim($col);
                        $placeholders[] = '?';
                        $values[] = $params[$index];
                    }
                }
            }

            $columns[] = "ModifiedDate";
            $placeholders[] = "GETDATE()";

            $sql = "INSERT INTO AtoDor (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $db->execute($sql, $values);
        }
    }
} catch (Exception $e) {
    die("DB Error (AtoDor): " . $e->getMessage());
}

echo "Form saved successfully.";
