<?php
ini_set('session.gc_maxlifetime', 86400);
session_status() === PHP_SESSION_ACTIVE ?: session_start();
require_once "../config/method.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
    <?php

    $deviceName = gethostname();
    $isTablet = str_starts_with($deviceName, 'TAB-');

    if ($isTablet) {
        // Require login for tablet users
        if (!isset($_SESSION['isLoggedIn']) || $_SESSION['isLoggedIn'] !== true) {
            header("Location: ../index.php");
            exit;
        }
    }

    ?>
</body>

</html>