<?php
require_once '../../config/dbop.php';
$db = new dbOp(1);

$recordId = $_POST['record_id'] ?? null;

if (!$recordId) {
    die("Missing RecordId.");
}

// --- Insert Dimension Values to AtoDimensionCheck (up to 20 rows)
for ($i = 1; $i <= 20; $i++) {
    $hasValue = false;

    $h1 = $_POST["dimension_hatsumono_{$i}_1"] ?? null;
    $h2 = $_POST["dimension_hatsumono_{$i}_2"] ?? null;
    $h3 = $_POST["dimension_hatsumono_{$i}_3"] ?? null;

    $n1 = $_POST["dimension_nakamono_{$i}_1"] ?? null;
    $n2 = $_POST["dimension_nakamono_{$i}_2"] ?? null;
    $n3 = $_POST["dimension_nakamono_{$i}_3"] ?? null;

    $o1 = $_POST["dimension_owarimono_{$i}_1"] ?? null;
    $o2 = $_POST["dimension_owarimono_{$i}_2"] ?? null;
    $o3 = $_POST["dimension_owarimono_{$i}_3"] ?? null;

    // Check if any field is filled
    if ($h1 || $h2 || $h3 || $n1 || $n2 || $n3 || $o1 || $o2 || $o3) {
        $sql = "
            INSERT INTO AtoDimensionCheck (
                RecordId, Hatsumono1, Hatsumono2, Hatsumono3,
                Nakamono1, Nakamono2, Nakamono3,
                Owarimono1, Owarimono2, Owarimono3
            ) VALUES (
                '$recordId',
                " . ($h1 === null ? "NULL" : "'$h1'") . ",
                " . ($h2 === null ? "NULL" : "'$h2'") . ",
                " . ($h3 === null ? "NULL" : "'$h3'") . ",
                " . ($n1 === null ? "NULL" : "'$n1'") . ",
                " . ($n2 === null ? "NULL" : "'$n2'") . ",
                " . ($n3 === null ? "NULL" : "'$n3'") . ",
                " . ($o1 === null ? "NULL" : "'$o1'") . ",
                " . ($o2 === null ? "NULL" : "'$o2'") . ",
                " . ($o3 === null ? "NULL" : "'$o3'") . "
            )
        ";
        $db->execute($sql);
    }
}

// --- Prepare AtoDor update data (judge & checkedBy)
$fields = [];

foreach (['hatsumono', 'nakamono', 'owarimono'] as $section) {
    for ($i = 1; $i <= 3; $i++) {
        $judge = $_POST["dimension_judge_{$section}_{$i}"] ?? null;
        if ($judge !== null) {
            $fields[] = ucfirst($section) . $i . "Judge = '$judge'";
        }

        $checkedBy = $_POST["dimension_checked_by_{$section}"] ?? null;
        if ($checkedBy !== null) {
            $fields[] = ucfirst($section) . $i . "CheckBy = '$checkedBy'";
        }
    }
}

// --- Insert or update AtoDor
if (!empty($fields)) {
    // Check if record exists
    $exists = $db->execute("SELECT RecordId FROM AtoDor WHERE RecordId = '$recordId'");
    
    if (count($exists) > 0) {
        $updateFields = implode(", ", $fields);
        $db->execute("UPDATE AtoDor SET $updateFields WHERE RecordId = '$recordId'");
    } else {
        $columns = ['RecordId'];
        $values = ["'$recordId'"];
        
        foreach ($fields as $field) {
            [$col, $val] = explode(" = ", $field);
            $columns[] = $col;
            $values[] = $val;
        }

        $colStr = implode(",", $columns);
        $valStr = implode(",", $values);
        $db->execute("INSERT INTO AtoDor ($colStr) VALUES ($valStr)");
    }
}

echo "Partial form saved.";
?>
