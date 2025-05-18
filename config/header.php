<?php
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

    // Pages that don't require login
    $openPages = ['adm-mode.php', 'adm-dashboard.php', 'dor-login.php'];

    $currentFile = basename($_SERVER['PHP_SELF']);
    if (!in_array($currentFile, $openPages)) {
        // check if the user is logged in, otherwise redirect to login page
        if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
            header("Location: ../index.php");
            exit;
        }
    }

    ?>
</body>

</html>