<?php
ini_set('session.gc_maxlifetime', 86400);

$deviceName = gethostname();

if (str_starts_with($deviceName, 'TAB-')) {
    // Production tablet
    header("Location: module/dor-home.php");
} elseif (str_starts_with($deviceName, 'NBCP-LT-144')) {
    // Developer laptop
    header("Location: module/adm-mode.php");
} else {
    // Manager, IT, QC, Supervisor (PC/laptop)
    header("Location: module/dor-dashboard.php");
}
