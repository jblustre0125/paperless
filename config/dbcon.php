<?php

// sql server database connection
$serverName = gethostname() . "\SQLEXPRESS";
$connectionInfo = array("Database" => "Paperless", "Uid" => "sa", "PWD" => "Nbc12#");
$connectionInfoBk = array("Database" => "Brightkeeper", "Uid" => "sa", "PWD" => "Nbc12#");

// $conn = sqlsrv_connect($serverName, $connectionInfo);

// if ($conn) {
//     echo "Connection established to Paperless Database.<br />";
// } else {
//     echo "Connection could not be established to Paperless Database.<br />";
//     die(print_r(sqlsrv_errors(), true));
// }

/**
 * Execute a SQL query with parameters.
 *
 * @param int $dbConnection Use 1 for LeaveFiling and 2 for Jeonsoft.
 * @param int $commandType  Use 1 for statement and 2 for stored procedure.
 * @param string $query The SQL query.
 * @param array $params The parameters for the query.
 * @return array|bool The result set for SELECT queries, or true/false for other queries.
 */
function execQuery($dbCon, $com, $query, $params = [])
{
    global $serverName, $connectionInfo, $connectionInfoBk;

    // identify specified database then connect
    if ($dbCon === 1) {
        $conn = sqlsrv_connect($serverName, $connectionInfo);
    } else if ($dbCon === 2) {
        $conn = sqlsrv_connect($serverName, $connectionInfoBk);
    }

    if ($conn === false) {
        die(formatErrors(sqlsrv_errors()));
    }

    // prepare and execute the query
    $stmt = sqlsrv_query($conn, $query, $params);
    if ($stmt === false) {
        die(formatErrors(sqlsrv_errors()));
    }

    // determine the type of query or stored procedure
    if ($com === 1) {
        $comType = strtoupper(substr(trim($query), 0, 6));
    } else if ($com === 2) {
        $comType = strtoupper(substr(trim($query), 0, 7));
    } else {
        $comType = strtoupper(substr(trim($query), 0, 6));
    }

    //handle SELECT queries and READ stored procedures
    if ($comType === 'SELECT' || $com === 1) {
        $results = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        return $results;
    }

    if ($comType === 'EXEC Rd' || $com === 2) {
        $results = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        return $results;
    }

    // handle INSERT, UPDATE, DELETE queries
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    return true;
}

/**
 * Manage transactions.
 * 
 * @param int $dbConnection Use 1 for LeaveFiling and 2 for Jeonsoft.
 * @param callable $transactionCallback The callback function that contains the transactional operations.
 * @return bool True on success, false on failure.
 */
function execTransaction($dbCon, $transactionCallback)
{
    global $serverName, $connectionInfo, $connectionInfoJs;

    // identify specified database then connect
    if ($dbCon === 1) {
        $conn = sqlsrv_connect($serverName, $connectionInfo);
    } else if ($dbCon === 2) {
        $conn = sqlsrv_connect($serverName, $connectionInfoJs);
    }

    if ($conn === false) {
        die(formatErrors(sqlsrv_errors()));
    }

    // start transaction
    if (sqlsrv_begin_transaction($conn) === false) {
        die(formatErrors(sqlsrv_errors()));
    }

    try {
        // execute the transactional operations
        $transactionCallback($conn);

        // commit transaction
        if (sqlsrv_commit($conn) === false) {
            throw new Exception(print_r(sqlsrv_errors(), true));
        }
    } catch (Exception $e) {
        // rollback transaction
        sqlsrv_rollback($conn);
        sqlsrv_close($conn);
        return false;
    }

    sqlsrv_close($conn);
    return true;
}

function formatErrors($errors)
{
    if (is_iterable($errors)) {
        echo "Error information: <br/>";

        foreach ($errors as $error) {
            echo "SQLSTATE: " . $error['SQLSTATE'] . "<br/>";
            echo "Code: " . $error['code'] . "<br/>";
            echo "Message: " . $error['message'] . "<br/>";
        }
    }
}

function testInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function alert($message)
{
    echo "<script>alert('$message');</script>";
}

function var_dump_pre($mixed = null)
{
    echo '<pre>';
    var_dump($mixed);
    echo '</pre>';
    return null;
}

function var_dump_ret($mixed = null)
{
    ob_start();
    var_dump($mixed);
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}
