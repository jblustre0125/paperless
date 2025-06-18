<!-- Login methods to follow -->

<!-- Method in fetching Tablet Hostname1 -->

<?php
    require_once "../../config/dbop.php";

    $db = new DbOp(1);

    $query = "SELECT Hostname, IsActive FROM GenHostname WHERE IsActive = 1 ORDER BY Hostname";
    $hostnames = $db->execute($query);
?>