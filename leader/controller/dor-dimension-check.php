<?php
require_once '../../config/dbop.php';
session_start();

$db = new DbOp(1);
$employeeCode = $_SESSION['employee_code'] ?? 'unknown';
$hostnameId = gethostname();

// ✅ Get latest RecordId from AtoDor
$recordRow = $db->execute("SELECT TOP 1 RecordId FROM AtoDor ORDER BY CreatedDate DESC");
if (empty($recordRow)) {
    echo json_encode(['success' => false, 'message' => 'No RecordId found in AtoDor']);
    exit;
}
$recordId = $recordRow[0]['RecordId'];

$sections = ['Hatsumono', 'Nakamono', 'Owarimono'];
$updateFields = [];

// ✅ Always set all judge fields, even if null
foreach ($sections as $section) {
  for ($i = 1; $i <= 3; $i++) {
    $postKey = "dimension_judge_" . strtolower($section) . "_$i";
    $value = $_POST[$postKey] ?? null;

    $judgeCol = "{$section}{$i}Judge";
    $checkByCol = "{$section}{$i}CheckBy";

    if ($value !== null && $value !== '') {
      $safeVal = str_replace("'", "''", $value); // escape quotes
      $updateFields[] = "$judgeCol = '$safeVal'";
      $updateFields[] = "$checkByCol = '$employeeCode'";
    } else {
      $updateFields[] = "$judgeCol = NULL";
      $updateFields[] = "$checkByCol = NULL";
    }
  }
}

// ✅ Mandatory audit fields
$updateFields[] = "NotedBy = '$employeeCode'";
$updateFields[] = "HostnameId = '$hostnameId'";
$updateFields[] = "ModifiedBy = '$employeeCode'";
$updateFields[] = "ModifiedDate = GETDATE()";

// ✅ Build and log SQL
$updateSql = "
  UPDATE AtoDor SET
    " . implode(",\n    ", $updateFields) . "
  WHERE RecordId = '$recordId'
";
error_log("UPDATE AtoDor SQL: $updateSql");

$db->execute($updateSql);

// ✅ Clear old rows from AtoDimensionCheck
$db->execute("DELETE FROM AtoDimensionCheck WHERE RecordId = '$recordId'");

// ✅ Insert up to 20 dimension rows
error_log("=== Starting dimension insert for RecordId: $recordId ===");

for ($i = 1; $i <= 20; $i++) {
  $rowValues = [];
  $hasAnyValue = false;

  foreach ($sections as $section) {
    for ($j = 1; $j <= 3; $j++) {
      $postKey = "dimension_" . strtolower($section) . "_{$i}_{$j}";
      $val = $_POST[$postKey] ?? '';
      if ($val !== '') $hasAnyValue = true;
      $rowValues[] = ($val !== '') ? "'" . str_replace("'", "''", $val) . "'" : "NULL";
    }
  }

  if ($hasAnyValue) {
    $insertSql = "
      INSERT INTO AtoDimensionCheck (
        RecordId,
        Hatsumono1, Hatsumono2, Hatsumono3,
        Nakamono1, Nakamono2, Nakamono3,
        Owarimono1, Owarimono2, Owarimono3
      ) VALUES (
        '$recordId',
        " . implode(", ", $rowValues) . "
      )
    ";
    error_log("Inserted row $i: $insertSql");
    $db->execute($insertSql);
  } else {
    error_log("Skipped row $i (no dimension values)");
  }
}

error_log("=== Finished dimension insert for RecordId: $recordId ===");

echo json_encode(['success' => true]);
?>
