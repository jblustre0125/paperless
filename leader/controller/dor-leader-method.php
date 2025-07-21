<?php

class Method
{

    private $db;

    public function __construct($dbConnection)
    {
        $this->db = new DbOp($dbConnection); //use the DbOp class
    }

    //get active hostnames
    public function getActiveHostnames()
    {
        $db = new DbOp(1);
        $sql = "SELECT
                        h.HostnameId,
                        h.Hostname,
                        h.IsActive,
                        d.RecordId
                    FROM GenHostname h
                    LEFT JOIN AtoDor d ON
                        d.HostnameId = h.HostnameId AND
                        d.DorDate = CAST(GETDATE() AS DATE)
                    WHERE h.IsActive = 1
                    ORDER BY h.Hostname";
        return $db->execute($sql);
    }


    public function getOnlineTablets($excludeHostnameId = null)
    {
        $query = "
        SELECT
            h.HostnameId,
            h.Hostname,
            h.IsActive,
            h.IsLoggedin,
            a.RecordId
        FROM GenHostname h
        LEFT JOIN AtoDor a
            ON h.HostnameId = a.HostnameId
            AND a.DorDate = CAST(GETDATE() AS DATE)
        WHERE h.IsLoggedin = 1
          AND ISNULL(h.IsLeader, 0) = 0
    ";

        $params = [];

        if ($excludeHostnameId) {
            $query .= " AND h.HostnameId != ?";
            $params[] = $excludeHostnameId;
        }

        return $this->db->execute($query, $params);
    }


    public function updateTabletStatus($hostnameId, $status)
    {
        $sql = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
        $this->db->execute($sql, [$hostnameId, $status]);
        return true;
    }

    public function getTabletStatus()
    {
        $query = "SELECT
                        HostnameId as RecordId,
                        Hostname,
                        IsActive,
                        IsLoggedin
                    FROM GenHostname
                    ORDER BY IsLogin DESC, Hostname ASC";
        return $this->db->execute($query);
    }

    public function getCurrentTablet($hostnameId)
    {
        $hostnames = $this->getActiveHostnames();
        foreach ($hostnames as $hostname) {
            if ($hostname['HostnameId'] == $hostnameId) {
                return $hostname;
            }
        }
        return null;
    }
}
