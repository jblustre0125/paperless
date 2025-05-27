<?php
session_status() === PHP_SESSION_ACTIVE ?: session_start();

session_unset();
session_destroy();

header('Location: index.php');
exit;
