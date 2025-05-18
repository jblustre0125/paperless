<?php
require_once "../config/dbop.php";
require_once "../config/header.php";

$db1 = new DbOp(1);

$clientIp = $_SERVER['REMOTE_ADDR'];
$errorPrompt = '';

// Developer override
if ($isProdMode === 1) {
	$deviceName = 'NBCP-TAB-001';
} else {
	$query = "SELECT TOP 1 HostnameId FROM GenHostname WHERE IpAddress = ? AND IsActive = 1";
	$result = $db1->execute($query, [$clientIp], 1);

	if (!empty($result)) {
		$deviceName = $result[0]['Hostname'];
	} else {
		$deviceName = 'NBCP-TAB-001';
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	try {
		$employeeCode = testInput($_POST["txtProductionCode"]); // Employee code

		$isActive = 1;
		$isLoggedIn = 1;

		$spRd1 = "EXEC RdGenEmployeeAll @EmployeeCode=?, @IsActive=?";
		$res1 = $db1->execute($spRd1, [$employeeCode, $isActive], 1);

		$spRd2 = "EXEC RdGenHostname @Hostname=?, @IsActive=?";
		$res2 = $db1->execute($spRd2, [$deviceName, $isActive], 1);

		// Validate employee
		if (!empty($res1)) {
			// Check if registered but already logged in
			$spChkEmp = "EXEC RdGenEmployeeAll @EmployeeCode=?, @IsLoggedIn=?, @IsActive=?";
			$chkEmp = $db1->execute($spChkEmp, [$employeeCode, $isLoggedIn, $isActive], 1);
			if (!empty($chkEmp)) {
				$errorPrompt = "Employee already logged in.";
			}
		} else {
			$errorPrompt .= ($errorPrompt ? "<br/>" : "") . "Employee is not registered or inactive.";
		}

		// Validate tablet
		if (!empty($res2)) {
			$spChkDev = "EXEC RdGenHostname @Hostname=?, @IsLoggedIn=?, @IsActive=?";
			$chkDev = $db1->execute($spChkDev, [$deviceName, $isLoggedIn, $isActive], 1);
			if (!empty($chkDev)) {
				$errorPrompt .= ($errorPrompt ? "<br/>" : "") . "Tablet already logged in.";
			}
		} else {
			$errorPrompt .= ($errorPrompt ? "<br/>" : "") . "Tablet is not registered or inactive.";
		}

		// If both valid
		if (empty($chkEmp) && empty($chkDev)) {
			foreach ($res1 as $row1) {
				$_SESSION['loggedIn'] = true;
				$_SESSION['processId'] = $row1['ProcessId'];
				$_SESSION['employeeCode'] = $row1['EmployeeCode'];
				$_SESSION['employeeName'] = $row1['EmployeeName'];
				$_SESSION['productionCode'] = $row1['ProductionCode'];
				$_SESSION['positionId'] = $row1['PositionId'];
				$_SESSION['isLoggedIn'] = $row1['IsLoggedIn'];
				$_SESSION['isActive'] = $row1['IsActive'];

				$positionId = (int)$row1['PositionId'];
				$departmentId = (int)$row1['DepartmentId'];

				$_SESSION['isManager'] = in_array($positionId, [2, 13, 15, 21, 43]) ? 1 : 0;

				if ($departmentId === 4) { //Production Department
					$_SESSION['isSupervisor'] = in_array($positionId, [19, 48]) ? 1 : 0;
					$_SESSION['isAssistantSupervisor'] = $positionId === 4 ? 1 : 0;
					$_SESSION['isLineLeader'] = in_array($positionId, [7, 51]) ? 1 : 0;
					$_SESSION['isSeniorLineLeader'] = $positionId === 17 ? 1 : 0;
				} else {
					$_SESSION['isSupervisor'] = 0;
					$_SESSION['isAssistantSupervisor'] = 0;
					$_SESSION['isLineLeader'] = 0;
					$_SESSION['isSeniorLineLeader'] = 0;
				}

				$updQry1 = "EXEC UpdGenEmployeeAll @EmployeeCode=?, @IsLoggedIn=?";
				$db1->execute($updQry1, [$row1['EmployeeCode'], 1], 1);
			}

			foreach ($res2 as $row2) {
				$_SESSION['hostnameId'] = $row2["HostnameId"];
				$_SESSION['hostname'] = $row2["Hostname"];
				$_SESSION['processId'] = $row2['ProcessId'];
				$_SESSION['ipAddress'] = $row2["IpAddress"];

				$updQry2 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
				$db1->execute($updQry2, [$row2["HostnameId"], 1], 1);
			}

			header('Location: ../module/dor-home.php');
			exit();
		}
	} catch (Exception $e) {
		globalExceptionHandler($e);
	}
}
