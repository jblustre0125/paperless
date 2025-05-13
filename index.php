<?php
// Developer override by query param (safe only in localhost/dev)
if (php_uname('n') === 'NBCP-LT-144' && isset($_GET['dev'])) {
    header('Location: module/adm-mode.php');
    exit;
}

// Tablet detection (via REMOTE_HOST unreliable, so use User-Agent or enforce naming scheme)
$hostname = gethostname();
if (strpos($hostname, 'TAB-') === 0) {
    header('Location: module/dor-login.php');
    exit;
}

// Default fallback
header('Location: module/adm-dashboard.php');
exit;
