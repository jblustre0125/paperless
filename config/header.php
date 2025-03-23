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

    // check if the user is logged in, otherwise redirect to login page
    if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
        header("Location: /index.php");  // redirect to the login page
        exit;
    }

    ?>
</body>

</html>