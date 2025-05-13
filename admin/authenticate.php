<?php
ini_set('session.gc_maxlifetime', 86400);

require_once "../config/dbop.php";
require_once "../config/header.php";

$errorPrompt = '';
$db1 = new DbOp(1);
$deviceName = gethostname();

// Developer override
if ($deviceName === 'NBCP-LT-144') {
	$deviceName = 'TAB-ATO-001';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	try {
		$employeeCode = testInput($_POST["txtProductionCode"]); // Employee code

		$isActive = 1;
		$isLoggedIn = 0;

		$spRd1 = "EXEC RdGenEmployeeAll @EmployeeCode=?, @IsActive=?";
		$res1 = $db1->execute($spRd1, [$employeeCode, $isActive], 1);

		$spRd2 = "EXEC RdAtoLine @LineNumber=?, @IsActive=?";
		$res2 = $db1->execute($spRd2, [$deviceName, $isActive], 1);

		// Validate employee
		if (empty($res1)) {
			// Check if registered but already logged in
			$spChkEmp = "EXEC RdGenEmployeeAll @ProductionCode=?, @IsLoggedIn=?";
			$chkEmp = $db1->execute($spChkEmp, [$employeeCode, $isLoggedIn], 1);
			if (!empty($chkEmp)) {
				$errorPrompt = "Employee already logged in.";
			} else {
				$errorPrompt = "Employee not registered or inactive.";
			}
		}

		// Validate device
		if (empty($res2)) {
			$spChkDev = "EXEC RdAtoLine @LineNumber=?, @IsLoggedIn=?";
			$chkDev = $db1->execute($spChkDev, [$deviceName, $isLoggedIn], 1);
			if (!empty($chkDev)) {
				$errorPrompt .= ($errorPrompt ? " " : "") . "Device already logged in.";
			} else {
				$errorPrompt .= ($errorPrompt ? " " : "") . "Device not registered or inactive.";
			}
		}

		// If both valid
		if (!empty($res1) && !empty($res2)) {
			foreach ($res1 as $row1) {
				$_SESSION['operatorId'] = $row1['OperatorId'];
				$_SESSION['processId'] = $row1['ProcessId'];
				$_SESSION['employeeCode'] = $row1['EmployeeCode'];
				$_SESSION['employeeName'] = $row1['EmployeeName'];
				$_SESSION['firstName'] = $row1['FirstName'];
				$_SESSION['lastName'] = $row1['LastName'];
				$_SESSION['productionCode'] = $row1['ProductionCode'];
				$_SESSION['positionId'] = $row1['PositionId'];

				$_SESSION['isLeader'] = $row1['IsLeader'];
				$_SESSION['isSrLeader'] = $row1['IsSrLeader'];

				$positionId = (int)$row1['PositionId'];
				$_SESSION['isManager'] = in_array($positionId, [2, 13, 15, 21, 43]) ? 1 : 0;
				$_SESSION['isSupervisor'] = in_array($positionId, [19, 48]) ? 1 : 0;
				$_SESSION['isAssistantSupervisor'] = $positionId === 4 ? 1 : 0;
				$_SESSION['isLineLeader'] = in_array($positionId, [7, 51]) ? 1 : 0;
				$_SESSION['isSeniorLineLeader'] = $positionId === 17 ? 1 : 0;

				$_SESSION['isLoggedIn'] = $row1['IsLoggedIn'];
				$_SESSION['isActive'] = $row1['IsActive'];

				$updQry1 = "EXEC UpdGenEmployeeAll @EmployeeCode=?, @IsLoggedIn=?";
				$db1->execute($updQry1, [$row1['EmployeeCode'], 1], 1);
			}

			foreach ($res2 as $row2) {
				$_SESSION['deviceName'] = $deviceName;
				$_SESSION['lineId'] = $row2["LineId"];
				$_SESSION['processId'] = $row2['ProcessId'];

				echo $row2["LineId"];

				$updQry2 = "EXEC UpdAtoLine @LineId=?, @IsLoggedIn=?";
				$db1->execute($updQry2, [$row2["LineId"], 1], 1);
			}

			header('Location: ../module/dor-home.php');
			exit();
		}
	} catch (Exception $e) {
		globalExceptionHandler($e);
	}
}
