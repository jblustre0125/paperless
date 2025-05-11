<?php
ini_set('session.gc_maxlifetime', 86400);

require_once "../config/dbop.php";
require_once "../config/header.php";

$errorPrompt = '';
$db1 = new DbOp(1);
$deviceName = gethostname();

// Developer override
if ($deviceName === 'NBCP-LT-144') {
	$deviceName = 'TAB-ATO1';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	try {
		$productionCode = testInput($_POST["txtProductionCode"]);

		$isActive = 1;
		$isLoggedIn = 0;

		$spRd1 = "EXEC RdGenOperator @ProductionCode=?, @IsActive=?, @IsLoggedIn=?";
		$res1 = $db1->execute($spRd1, [$productionCode, $isActive, $isLoggedIn], 1);

		$spRd2 = "EXEC RdAtoLine @LineNumber=?, @IsLoggedIn=?";
		$res2 = $db1->execute($spRd2, [$deviceName, $isLoggedIn], 1);

		// Validate employee
		if (empty($res1)) {
			// Check if registered but already logged in
			$spChkEmp = "EXEC RdGenOperator @ProductionCode=?, @IsActive=?, @IsLoggedIn=NULL";
			$chkEmp = $db1->execute($spChkEmp, [$productionCode, $isActive], 1);
			if (!empty($chkEmp)) {
				$errorPrompt = "Employee already logged in.";
			} else {
				$errorPrompt = "Employee not registered.";
			}
		}

		// Validate device
		if (empty($res2)) {
			$spChkDev = "EXEC RdAtoLine @LineNumber=?, @IsLoggedIn=NULL";
			$chkDev = $db1->execute($spChkDev, [$deviceName], 1);
			if (!empty($chkDev)) {
				$errorPrompt .= ($errorPrompt ? " " : "") . "Device already logged in.";
			} else {
				$errorPrompt .= ($errorPrompt ? " " : "") . "Device not registered.";
			}
		}

		// If both valid
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
				$_SESSION['deviceName'] = $deviceName;
				$_SESSION['lineId'] = $row2["LineId"];
				$_SESSION['processId'] = $row2['ProcessId'];

				$updQry2 = "EXEC UpdAtoLine @LineNumber=?, @IsLoggedIn=?";
				$db1->execute($updQry2, [$deviceName, 1], 1);
			}

			header('Location: ../module/dor-home.php');
			exit();
		}
	} catch (Exception $e) {
		globalExceptionHandler($e);
	}
}
