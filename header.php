<?php
session_status() === PHP_SESSION_ACTIVE ?: session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- display blank favicon - -->
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=">
</head>

<body>
    <?php

    // check if the user is logged in, otherwise redirect to login page
    if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
        header("Location: index.php");  // redirect to the login page
        exit;
    }

    // $_SESSION['operatorId'] 
    // $_SESSION['employeeCode']
    // $_SESSION['employeeName'] = $row['EmployeeName'];
    // $_SESSION['productionCode'];
    // $_SESSION['isAbnormality'] = $row['IsAbnormality'];
    // $_SESSION['isLeader'] = $row['IsLeader'];
    // $_SESSION['isSrLeader'] = $row['IsSrLeader'];
    // $_SESSION['isSupervisor'] = $row['IsSupervisor'];
    // $_SESSION['isManager'] = $row['IsManager'];
    // $_SESSION['isLoggedIn'] = $row['IsLoggedIn'];
    // $_SESSION['isActive'] = $row['IsActive'];

    ?>
</body>

</html>