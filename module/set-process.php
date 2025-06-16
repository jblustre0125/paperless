<?php
session_start();
if (isset($_POST['processNumber'])) {
    $_SESSION['activeProcess'] = intval($_POST['processNumber']);
}
