<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="icon" type="image/png" href="../img/nbc.jpg">
</head>

<?php

session_status() === PHP_SESSION_ACTIVE ?: session_start();

require_once "config/dbop.php";
require_once "config/method.php";

$errorPrompt = '';

$db1 = new DbOp(1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	try {
		$productionCode = testInput($_POST["txtProductionCode"]);
		$isActive = 1;
		$isLoggedIn = 0;

		$spRd1 = "EXEC RdGenOperator @ProductionCode=?, @IsActive=?, @IsLoggedIn=?";
		$res1 = $db1->execute($spRd1, [$productionCode, $isActive, $isLoggedIn], 1);

		$spRd2 = "EXEC RdAtoLine @LineNumber=?, @IsLoggedIn=?";
		$res2 = $db1->execute($spRd2, ['SAATO 144', 0], 1);

		if (empty($res1)) {
			$errorPrompt = "Employee not found or already logged in.";
		}

		if (empty($res2)) {
			$errorPrompt = "Device not found or already logged in.";
		}

		if (!empty($res1) && !empty($res2)) {
			foreach ($res1 as $row1) {
				$_SESSION['loggedIn'] = true;
				$_SESSION['operatorId'] = $row1['OperatorId'];
				$_SESSION['processId'] = $row1['ProcessId'];
				$_SESSION['employeeCode'] = $row1['EmployeeCode'];
				$_SESSION['employeeName'] = $row1['EmployeeName'];
				$_SESSION['firstName'] = $row1['FirstName'];
				$_SESSION['lastName'] = $row1['LastName'];
				$_SESSION['productionCode'] = $row1['ProductionCode'];
				$_SESSION['positionId'] = $row1['PositionId'];
				$_SESSION['companyId'] = $row1['CompanyId'];
				$_SESSION['isAbnormality'] = $row1['IsAbnormality'];
				$_SESSION['isLeader'] = $row1['IsLeader'];
				$_SESSION['isSrLeader'] = $row1['IsSrLeader'];
				$_SESSION['isSupervisor'] = $row1['IsSupervisor'];
				$_SESSION['isManager'] = $row1['IsManager'];
				$_SESSION['isLoggedIn'] = $row1['IsLoggedIn'];
				$_SESSION['isActive'] = $row1['IsActive'];

				$updQry1 = "EXEC UpdGenOperator @OperatorId=?, @IsLoggedIn=?";
				$db1->execute($updQry1, [$row1['OperatorId'], 1], 1);
			}

			foreach ($res2 as $row2) {
				$_SESSION['deviceName'] = $row2['LineNumber'];
				$_SESSION['lineId'] = $row2["LineId"];
				$_SESSION['processId'] = $row2['ProcessId'];

				$updQry2 = "EXEC UpdAtoLine @LineNumber=?, @IsLoggedIn=?";
				$db1->execute($updQry2, ['SAATO 144', 1], 1);
			}

			header('Location: module/dor.php');
			exit();
		} else {
			$errorPrompt = "Employee or device not found.";
		}
	} catch (Exception $e) {
		globalExceptionHandler($e);
	}
}
