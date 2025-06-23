<?php

set_time_limit(0);

class DbOp
{
    private $conn;

    public function __construct($dbConnection)
    {
        // Database connection details
        $serverName = gethostname() . "\SQLEXPRESS";
        $username = "sa";
        $password = "Nbc12#";
        $db1 = "Paperless";
        $db2 = "Brightkeeper";
        $db3 = "MachineMonitoring";

        // Selecting database
        switch ($dbConnection) {
            case 1:
                $database = $db1;
                break;
            case 2:
                $database = $db2;
                break;
            case 3:
                $database = $db3;
                break;
            default:
                $database = $db1;
                break;
        }

        // Connection options
        $connectionOptions = [
            "Database" => $database,
            "UID" => $username,
            "PWD" => $password,
            "CharacterSet" => "UTF-8"
        ];

        // Create connection
        $this->conn = sqlsrv_connect($serverName, $connectionOptions);

        // Check connection
        if (!$this->conn) {
            die(print_r(sqlsrv_errors(), true));
        }
    }

    public function execute($query, $params = [], $com = 0)
    {
        $stmt = sqlsrv_query($this->conn, $query, $params);
        if ($stmt === false) {
            return false;
        }

        // Determine query type or stored procedure type
        if ($com === 1) {
            $comType = strtoupper(substr(trim($query), 0, 6));
        } else if ($com === 2) {
            $comType = strtoupper(substr(trim($query), 0, 7));
        } else {
            $comType = strtoupper(substr(trim($query), 0, 6));
        }

        // Handle SELECT queries and READ stored procedures
        if ($comType === 'SELECT' || $com === 1 || strpos($comType, 'EXEC Rd') === 0) {
            $results = [];
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $results[] = $row;
            }
            sqlsrv_free_stmt($stmt);
            return $results;
        }

        // Handle update, insert, and delete stored procedures
        if (strpos($comType, 'EXEC Upd') === 0 || strpos($comType, 'EXEC Ins') === 0 || strpos($comType, 'EXEC Del') === 0) {
            $rowsAffected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            return $rowsAffected;
        }

        sqlsrv_free_stmt($stmt);
        return true;
    }

    public function __destruct()
    {
        sqlsrv_close($this->conn);
    }
}
