<?php

session_status() === PHP_SESSION_ACTIVE ?: session_start();

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	require "config/dbcon.php";

	$productionCode = testInput($_POST["txtProductionCode"]);
	$isActive = 1;
	$isLoggedIn = 0;

	try {
		$proc = "EXEC RdGenOperator @ProductionCode=?, @IsActive=?, @IsLoggedIn=?";
		$params = [$productionCode, $isActive, $isLoggedIn];
		$res = execQuery(1, 2, $proc, $params);

		if (count($res) == 1) {
			foreach ($res as $row) {
				$_SESSION['loggedIn'] = true;
				$_SESSION['operatorId'] = $row['OperatorId'];
				$_SESSION['employeeCode'] = $row['EmployeeCode'];
				$_SESSION['employeeName'] = $row['EmployeeName'];
				$_SESSION['productionCode'] = $row['ProductionCode'];
				$_SESSION['isAbnormality'] = $row['IsAbnormality'];
				$_SESSION['isLeader'] = $row['IsLeader'];
				$_SESSION['isSrLeader'] = $row['IsSrLeader'];
				$_SESSION['isSupervisor'] = $row['IsSupervisor'];
				$_SESSION['isManager'] = $row['IsManager'];
				$_SESSION['isLoggedIn'] = $row['IsLoggedIn'];
				$_SESSION['isActive'] = $row['IsActive'];

				$updQry = "EXEC UpdGenOperator @OperatorId=?, @IsLoggedIn=?";
				$prms = [$row['OperatorId'], 1];
				execQuery(1, 2, $updQry, $prms);
				header('Location: home.php');
			}
		} else {
			$loginError = "Invalid employee code.";
		}
	} catch (Exception $e) {
		die(FormatErrors(sqlsrv_errors()));
	}
}
