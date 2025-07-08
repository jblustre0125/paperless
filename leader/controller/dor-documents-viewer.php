<?php
require_once '../../config/dbop.php';

$hostname_id = $_GET['hostname_id'] ?? null;
$drawing = $prepCard = $workInstruction = '';

if ($hostname_id) {
    $db = new DbOp(1);

    $sql = "SELECT TOP 1 RecordId, DorTypeId, ModelId FROM AtoDor WHERE HostnameId = ? ORDER BY RecordId DESC";
    $result = $db->execute($sql, [$hostname_id]);

    if ($result && count($result) > 0) {
        $recordId = $result[0]['RecordId'];
        $dorTypeId = $result[0]['DorTypeId'];
        $modelId = $result[0]['ModelId'];

        error_log("Fetched Model ID: $modelId | DorType ID: $dorTypeId");

        $drawing = getDrawing($dorTypeId, $modelId);
        $prepCard = getPreparationCard($modelId);
        $workInstruction = getWorkInstruction($dorTypeId, $modelId, 1);
    } else {
        error_log("No AtoDor record found for HostnameId: $hostname_id");
    }
}

// === Helper Functions ===

function getModelName($modelId) {
    $db = new DbOp(1);
    $res = $db->execute("EXEC RdGenModel @IsActive=?, @ModelId=?", [1, $modelId], 1);

    if ($res && is_array($res)) {
        foreach ($res as $row) {
            // Explicit match on MODEL_ID and valid ITEM_ID
            if (
                isset($row['MODEL_ID']) &&
                intval($row['MODEL_ID']) === intval($modelId) &&
                !empty($row['ITEM_ID'])
            ) {
                $modelName = strtoupper(trim($row['ITEM_ID']));
                error_log("✅ Found exact match for MODEL_ID $modelId: $modelName");
                return $modelName;
            }
        }

        // If nothing matched, log full result for debugging
        error_log("❌ No exact MODEL_ID match found for $modelId. Full result:");
        error_log(print_r($res, true));
    } else {
        error_log("❌ No results returned from RdGenModel for MODEL_ID: $modelId");
    }

    // Fallback
    $fallback = "MODEL_$modelId";
    error_log("⚠️ Using fallback model name: $fallback");
    return $fallback;
}



function getDorTypeName($dorTypeId) {
    $db = new DbOp(1);
    $res = $db->execute("EXEC RdAtoDorType @DorTypeId=?", [$dorTypeId], 1);

    if ($res && isset($res[0]['DorTypeName'])) {
        return strtoupper(trim($res[0]['DorTypeName']));
    }

    return '';
}

function getDrawing($dorTypeId, $modelId) {
    $dorType = getDorTypeName($dorTypeId);
    $modelName = getModelName($modelId);

    $imageExtension = ".PNG";
    $webPath = "/paperless-data/DRAWING/{$dorType}/{$modelName}{$imageExtension}";
    $localPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;

    if (file_exists($localPath)) {
        return $webPath;
    }

    error_log("Drawing not found at: $localPath");
    return "";
}

function getPreparationCard($modelId) {
    $modelName = getModelName($modelId);

    $fileExtension = ".pdf";
    $webPath = "/paperless-data/PREPARATION CARD/{$modelName}{$fileExtension}";
    $localPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;

    if (file_exists($localPath)) {
        return $webPath;
    }

    error_log("Preparation Card not found at: $localPath");
    return "";
}

function getWorkInstruction($dorTypeId, $modelId, $processNumber = 1) {
    $rawDorType = getDorTypeName($dorTypeId);
    $modelName = getModelName($modelId);

    // Normalize dorType
    if (strpos($rawDorType, "CLAMP") !== false) $dorType = "CLAMP";
    elseif (strpos($rawDorType, "TAPING") !== false) $dorType = "TAPING";
    elseif (strpos($rawDorType, "OFFLINE") !== false) $dorType = "OFFLINE";
    else $dorType = $rawDorType;

    $basePath = $_SERVER['DOCUMENT_ROOT'] . "/paperless-data/WORK INSTRUCTION";
    if (!is_dir($basePath)) {
        error_log("Work Instruction directory not found: $basePath");
        return "";
    }

    $latestFile = null;
    $latestMTime = 0;
    $processLetter = chr(64 + $processNumber); // A = 1, B = 2, etc.

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    error_log("Searching for Work Instruction for Model: $modelName, DorType: $dorType, ProcessLetter: $processLetter");

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
            $filename = strtoupper($file->getFilename());

            // Normalize for loose matching
            $normalizedFilename = preg_replace('/[^A-Z0-9]/', '', $filename);
            $normalizedModel = preg_replace('/[^A-Z0-9]/', '', $modelName);
            $normalizedDorType = preg_replace('/[^A-Z0-9]/', '', $dorType);

            if (
                strpos($normalizedFilename, $normalizedModel) !== false &&
                strpos($normalizedFilename, $normalizedDorType) !== false &&
                preg_match('/' . $processLetter . '\s*[-_]*\s*REV\.?/i', $filename)
            ) {
                $mtime = $file->getMTime();
                error_log("Matched file: $filename | Modified: $mtime");

                if ($mtime > $latestMTime) {
                    $latestMTime = $mtime;
                    $latestFile = $file->getPathname();
                }
            }
        }
    }

    if ($latestFile) {
        $webPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $latestFile);
        return str_replace('\\', '/', $webPath);
    }

    error_log("No matching Work Instruction found for Model: $modelName, DorType: $dorType, Process: $processLetter");
    return "";
}
?>
