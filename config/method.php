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

// function to get image URL based on model number
function getDrawing($modelName)
{
    $imageDirectory = "/dor-system/drawing/"; // URL relative to the web root
    $imagePath = $_SERVER['DOCUMENT_ROOT'] . $imageDirectory;
    $imageExtension = ".png";

    $fullPath = $imagePath . $modelName . $imageExtension;
    if (file_exists($fullPath)) {
        return $imageDirectory . $modelName . $imageExtension; // Return the web-accessible URL
    }

    return ""; // Optional: return empty string or default image if not found
}

function initQRScanner($pageType)
{
?>
    <script>
        let stream;
        let scanInterval;

        function openQRScanner(title, callback) {
            const qrModal = new bootstrap.Modal(document.getElementById('qrScannerModal'));
            document.querySelector('#qrScannerModal .modal-title').innerText = title;
            const video = document.getElementById('qrVideo');
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment'
                    }
                })
                .then(function(s) {
                    stream = s;
                    video.srcObject = s;
                    video.play();
                    qrModal.show();

                    scanInterval = setInterval(() => {
                        if (video.readyState === video.HAVE_ENOUGH_DATA) {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);
                            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                            const code = jsQR(imageData.data, canvas.width, canvas.height);

                            if (code) {
                                stopQRScan();
                                handleQRResult(code.data.trim(), callback);
                            }
                        }
                    }, 300);
                })
                .catch(err => {
                    alert('Camera error: ' + err.message);
                });

            document.getElementById('qrScannerModal').addEventListener('hidden.bs.modal', stopQRScan);
        }

        function stopQRScan() {
            if (scanInterval) clearInterval(scanInterval);
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        }

        function handleQRResult(raw, callback) {
            let result = {};
            let valid = false;
            let err = '';

            switch ('<?= $pageType ?>') {
                case 'dor-login':
                case 'dor-form':
                    if (/^(FMB-\d{4}|\d{4}-\d{3})$/.test(raw)) {
                        result.employeeCode = raw;
                        valid = true;
                    } else {
                        err = 'Invalid Employee Code format';
                    }
                    break;
                case 'dor-home':
                    const parts = raw.split(' ');
                    if (parts.length === 1 || parts.length === 3) {
                        result.model = parts[0];
                        result.quantity = parts[1] ?? '';
                        result.lotNumber = parts[2] ?? '';
                        valid = true;
                    } else {
                        err = 'Expected: MODEL or MODEL QTY LOT';
                    }
                    break;
            }

            if (valid) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
                modal.hide();
                callback(result);
            } else {
                alert(err);
            }
        }
    </script>
<?php
}
