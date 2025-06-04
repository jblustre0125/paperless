<?php

require_once 'dbop.php'; // Ensure DbOp is included

/**
 * Sanitize user input to prevent XSS and SQL injection.
 * @param string $data The input data
 * @return string Sanitized data
 */
function testInput($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Debugging helper - returns formatted variable dump.
 * @param mixed $mixed The variable to dump
 * @return string Dump output
 */
function var_dump_ret($mixed = null)
{
    ob_start();
    var_dump($mixed);
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}

/**
 * Global exception handler.
 * Logs errors and optionally displays an error message.
 * @param Exception $exception The caught exception
 */
function globalExceptionHandler($exception)
{
    error_log('Uncaught Exception: ' . $exception->getMessage());
}

// Set the global exception handler
set_exception_handler('globalExceptionHandler');

function loadDorType()
{
    $db1 = new DbOp(1);
    $selQry = "SELECT DorTypeId, DorTypeName FROM dbo.AtoDorType";
    $res = $db1->execute($selQry, [], 1);

    if ($res === false) {
        return [];
    }

    $options = [];
    foreach ($res as $row) {
        $options[$row['DorTypeId']] = $row['DorTypeName'];
    }
    return $options;
}

function loadLeader($processId, $isActive)
{
    $db1 = new DbOp(1);
    $selQry = "EXEC RdGenOperatorLeader @ProcessId=?, @IsActive=?";
    $res = $db1->execute($selQry, [$processId, $isActive], 1);

    if ($res === false) {
        return [];
    }

    $options = [];
    foreach ($res as $row) {
        $options[$row['OperatorId']] = $row['EmployeeName'];
    }
    return $options;
}

function isValidLine($lineNumber)
{
    $db1 = new DbOp(1);
    $selQry = "SELECT COUNT(LineId) AS Count FROM dbo.GenLine WHERE IsLoggedIn = 0 AND LineNumber = ? AND IsActive = 1";
    $res = $db1->execute($selQry, [$lineNumber], 1);

    // Check if the result is empty or 'Count' is not set, return false
    if (empty($res) || !isset($res[0]['Count']) || $res[0]['Count'] == 0) {
        return false; // Model is not valid
    }

    return true;
}

function isValidModel($modelName)
{
    $db1 = new DbOp(1);
    $selQry = "SELECT COUNT(MODEL_ID) AS Count FROM dbo.GenModel WHERE ISACTIVE = 1 AND ITEM_ID = ?";
    $res = $db1->execute($selQry, [$modelName], 1);

    // Check if the result is empty or 'Count' is not set, return false
    if (empty($res) || !isset($res[0]['Count']) || $res[0]['Count'] == 0) {
        return false; // Model is not valid
    }

    return true;
}

function isExistDor($dorDate, $shiftId, $lineId, $modelId, $dortypeId)
{
    $db1 = new DbOp(1);
    $selSp = "EXEC CntAtoDOR @DorDate=?, @ShiftId=?, @LineId=?, @ModelId=?, @DorTypeId=?";
    $res = $db1->execute($selSp, [$dorDate, $shiftId, $lineId, $modelId, $dortypeId], 1);

    // Check if the result is empty or 'Count' is not set, return false
    if (empty($res) || !isset($res[0]['Count']) || $res[0]['Count'] == 0) {
        return false; // Model is not valid
    }

    return true;
}

function getAutocompleteName($query, $departmentId)
{
    $db1 = new DbOp(1);
    $sql = "SELECT employeeid, employeename FROM employee WHERE employeename LIKE ? AND departmentid = ? AND isactive = ?";
    $res =  $db1->execute($sql, ["%" . $query . "%", $departmentId, 1], 1);

    return !empty($res) && $res[0]['Count'] > 0;
}


function getDrawing($dorTypeId, $modelId)
{
    $db1 = new DbOp(1);
    $dorType = '';
    $modelName = '';

    $selQry1 = "EXEC RdAtoDorType @DorTypeId=?";
    $res1 = $db1->execute($selQry1, [$dorTypeId], 1);

    if ($res1 !== false && isset($res1[0]['DorTypeName'])) {
        $dorType = strtoupper(trim($res1[0]['DorTypeName']));
    }

    $selQry2 = "EXEC RdGenModel @IsActive=?, @ModelId=?";
    $res2 = $db1->execute($selQry2, [1, $modelId], 1);

    if ($res2 !== false && isset($res2[0]['ITEM_ID'])) {
        $modelName = strtoupper(trim($res2[0]['ITEM_ID']));
    }

    $imageExtension = ".PNG";
    $webPath = "/paperless-data/DRAWING/{$dorType}/{$modelName}{$imageExtension}";
    $localPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;

    if (file_exists($localPath)) {
        return $webPath;
    }

    return "";
}

function getPreparationCard($modelId)
{
    $db1 = new DbOp(1);
    $modelName = '';

    $selQry2 = "EXEC RdGenModel @IsActive=?, @ModelId=?";
    $res2 = $db1->execute($selQry2, [1, $modelId], 1);

    if ($res2 !== false && isset($res2[0]['ITEM_ID'])) {
        $modelName = strtoupper(trim($res2[0]['ITEM_ID']));
    }

    $imageExtension = ".pdf";
    $webPath = "/paperless-data/PREPARATION CARD/{$modelName}{$imageExtension}";
    $localPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;

    if (file_exists($localPath)) {
        return $webPath;
    }

    return "";
}

function getWorkInstruction($dorTypeId, $modelId)
{
    $db1 = new DbOp(1);
    $dorType = '';
    $modelName = '';

    // Get DorTypeName (e.g., Offline, Taping, Clamp)
    $selQry1 = "EXEC RdAtoDorType @DorTypeId=?";
    $res1 = $db1->execute($selQry1, [$dorTypeId], 1);
    if ($res1 && isset($res1[0]['DorTypeName'])) {
        $rawType = strtoupper(trim($res1[0]['DorTypeName']));
        if (strpos($rawType, "CLAMP") !== false) {
            $dorType = "CLAMP";
        } elseif (strpos($rawType, "TAPING") !== false) {
            $dorType = "TAPING";
        } elseif (strpos($rawType, "OFFLINE") !== false) {
            $dorType = "OFFLINE";
        } else {
            $dorType = $rawType;
        }
    }

    // Get model ITEM_ID
    $selQry2 = "EXEC RdGenModel @IsActive=?, @ModelId=?";
    $res2 = $db1->execute($selQry2, [1, $modelId], 1);
    if ($res2 && isset($res2[0]['ITEM_ID'])) {
        $modelName = strtoupper(trim($res2[0]['ITEM_ID']));
    } else {
        error_log("Model not found for ModelId: " . $modelId);
        return "";
    }

    // Start folder path
    $basePath = $_SERVER['DOCUMENT_ROOT'] . "/paperless-data/WORK INSTRUCTION";

    if (!is_dir($basePath)) {
        error_log("Work instruction directory not found: " . $basePath);
        return "";
    }

    $latestFile = null;
    $latestMTime = 0;

    error_log("Searching for Work Instruction - Model: " . $modelName . ", DorType: " . $dorType);

    // Search recursively
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
            $filename = strtoupper($file->getFilename());

            // Match: contains model + dorType
            if (strpos($filename, $modelName) !== false && strpos($filename, $dorType) !== false) {
                $mtime = $file->getMTime();
                if ($mtime > $latestMTime) {
                    $latestMTime = $mtime;
                    $latestFile = $file->getPathname();
                    error_log("Found matching file: " . $filename);
                }
            }
        }
    }

    if ($latestFile) {
        // Convert to web path
        $webPath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $latestFile);
        $webPath = str_replace('\\', '/', $webPath); // normalize for web use
        error_log("Returning web path: " . $webPath);
        return $webPath;
    }

    error_log("No matching work instruction found for Model: " . $modelName . ", DorType: " . $dorType);
    return "";
}
