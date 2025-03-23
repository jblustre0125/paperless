<?php

require_once 'dbop.php'; // Ensure DbOp is included

/**
 * Format and display errors for SQL Server and general exceptions.
 * @param mixed $error Exception or SQL error array
 */
function formatErrors($error)
{
    echo "<div style='padding: 10px; background-color: #f44336; color: white;'>";

    if ($error instanceof Exception) {
        echo "<strong>Exception:</strong> " . $error->getMessage() . "<br/>";
        echo "<strong>Line:</strong> " . $error->getLine() . "<br/>";
        echo "<strong>File:</strong> " . $error->getFile() . "<br/>";
    } elseif (is_array($error) && is_iterable($error)) {
        echo "<strong>SQL Errors:</strong><br/>";
        foreach ($error as $err) {
            echo "Code: " . $err['code'] . "<br/>";
            echo "Message: " . $err['message'] . "<br/>";
        }
    } else {
        echo "<strong>Error:</strong> " . $error . "<br/>";
    }

    echo "</div>";
}

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
 * Display an alert message in JavaScript.
 * @param string $message The message to display
 */
function alertMsg($message)
{
    echo "<script>alert('$message');</script>";
}

/**
 * Debugging helper - prints formatted variable dump.
 * @param mixed $mixed The variable to dump
 */
function var_dump_pre($mixed = null)
{
    echo '<pre>';
    var_dump($mixed);
    echo '</pre>';
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
    formatErrors($exception);
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
    $selQry = "SELECT COUNT(LineId) AS Count FROM dbo.AtoLine WHERE IsLoggedIn = 0 AND LineNumber = ?";
    $res = $db1->execute($selQry, [$lineNumber], 1);

    return !empty($res) && $res[0]['Count'] > 0;
}

function isValidModel($modelName)
{
    $db1 = new DbOp(1);
    $selQry = "SELECT COUNT(MODEL_ID) AS Count FROM dbo.GenModel WHERE ISACTIVE = 1 AND ITEM_ID = ?";
    $res = $db1->execute($selQry, [$modelName], 1);

    return !empty($res) && $res[0]['Count'] > 0;
}

function isExistDor($date, $shiftId, $lineId, $modelId, $dortypeId)
{
    $db1 = new DbOp(1);
    $selSp = "EXEC CntAtoDOR @CreatedDate=?, @ShiftId=?, @LineId=?, @ModelId=?, @DorTypeId=?";
    $res = $db1->execute($selSp, [$date, $shiftId, $lineId, $modelId, $dortypeId], 1);

    return !empty($res) && $res[0]['Count'] > 0;
}

function getAutocompleteName($query, $departmentId)
{
    $db1 = new DbOp(1);
    $sql = "SELECT employeeid, employeename FROM employee WHERE employeename LIKE ? AND departmentid = ? AND isactive = ?";
    $res =  $db1->execute($sql, ["%" . $query . "%", $departmentId, 1], 1);

    return !empty($res) && $res[0]['Count'] > 0;
}

// function to get image URL based on model number
function getImageUrl($dorTypeId, $modelNumber)
{
    $dorTypeName = "";

    switch ($dorTypeId) {
        case 1:
            $dorTypeName = "pre-assy";
            break;
        case 2:
            $dorTypeName = "clamp-assy";
            break;
        case 3:
            $dorTypeName = "taping";
            break;
        default:
            $dorTypeName = "pre-assy";
    }

    $imagePath = "../img/drawings/" . $dorTypeName . "/"; // directory where images are stored
    $imageExtension = ".png"; // assuming images are in jpg format

    if (file_exists($imagePath . $modelNumber . $imageExtension)) {
        return $imagePath . $modelNumber . $imageExtension;
    } else {
        return $imagePath . "default.jpg"; // fallback image if model-specific image is not found
    }
}
