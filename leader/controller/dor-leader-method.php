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

    public function getAllTabletWithStatus($currentTabletId = null)
    {
        try {
            $sql = "SELECT
                    h.HostnameId,
                    h.Hostname,
                    h.ProcessId,
                    h.TeamId,
                    h.IpAddress,
                    h.IsLoggedIn,
                    h.IsLeader,
                    h.IsActive,
                    l.LineId,
                    l.LineNumber,
                    l.DorTypeId,
                    l.LineStatusId,
                    l.IsLoggedIn as LineIsLoggedIn,
                    l.IsActive as LineIsActive,
                    a.RecordId,
                    CASE
                        WHEN h.IsLoggedIn = 1 AND h.IsActive = 1 AND l.IsLoggedIn = 1 AND l.IsActive = 1
                        THEN 'online'
                        ELSE 'offline'
                    END as Status
                FROM GenHostname h
                LEFT JOIN GenLine l ON h.HostnameId = l.HostnameId
                LEFT JOIN AtoDor a ON h.HostnameId = a.HostnameId
                    AND a.DorDate = CAST(GETDATE() AS DATE)
                WHERE h.IsActive = 1
                AND ISNULL(h.IsLeader, 0) = 0"; // Only non-leader tablets

            $params = [];

            // Exclude current tablet if specified
            if ($currentTabletId) {
                $sql .= " AND h.HostnameId != ?";
                $params[] = $currentTabletId;
            }

            // Order by status (online first) then by hostname
            $sql .= " ORDER BY
                    CASE WHEN h.IsLoggedIn = 1 AND h.IsActive = 1 AND l.IsLoggedIn = 1 AND l.IsActive = 1
                         THEN 0 ELSE 1 END,
                    h.Hostname ASC";

            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching tablets with status: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updated method to get all tablets with line status from database
     */
    public function getAllTabletWithLineStatus($currentTabletId = null)
    {
        try {
            $sql = "SELECT
                    h.HostnameId,
                    h.Hostname,
                    h.ProcessId,
                    h.TeamId,
                    h.IpAddress,
                    h.IsLoggedIn,
                    h.IsLeader,
                    h.IsActive,
                    l.LineId,
                    l.LineNumber,
                    l.DorTypeId,
                    l.LineStatusId,
                    ls.LineStatusName,
                    l.IsLoggedIn as LineIsLoggedIn,
                    l.IsActive as LineIsActive,
                    a.RecordId
                FROM GenHostname h
                LEFT JOIN GenLine l ON h.HostnameId = l.HostnameId
                LEFT JOIN GenLineStatus ls ON l.LineStatusId = ls.LineStatusId
                LEFT JOIN AtoDor a ON h.HostnameId = a.HostnameId
                    AND a.DorDate = CAST(GETDATE() AS DATE)
                WHERE h.IsActive = 1
                AND ISNULL(h.IsLeader, 0) = 0"; // Only non-leader tablets

            $params = [];

            // Exclude current tablet if specified
            if ($currentTabletId) {
                $sql .= " AND h.HostnameId != ?";
                $params[] = $currentTabletId;
            }

            // Order by line status (Normal Operation first) then by hostname
            $sql .= " ORDER BY
                    CASE WHEN l.LineStatusId = 1 THEN 0 ELSE 1 END,
                    h.Hostname ASC";

            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error fetching tablets with line status: " . $e->getMessage());
            return [];
        }
    }

    public function searchTablets($searchTerm, $currentTabletId = null)
    {
        try {
            $searchTerm = '%' . $searchTerm . '%';

            $sql = "SELECT
                    h.HostnameId,
                    h.Hostname,
                    h.ProcessId,
                    h.TeamId,
                    h.IpAddress,
                    h.IsLoggedIn,
                    h.IsLeader,
                    h.IsActive,
                    l.LineId,
                    l.LineNumber,
                    l.DorTypeId,
                    l.LineStatusId,
                    l.IsLoggedIn as LineIsLoggedIn,
                    l.IsActive as LineIsActive,
                    a.RecordId,
                    CASE
                        WHEN h.IsLoggedIn = 1 AND h.IsActive = 1 AND l.IsLoggedIn = 1 AND l.IsActive = 1
                        THEN 'online'
                        ELSE 'offline'
                    END as Status
                FROM GenHostname h
                LEFT JOIN GenLine l ON h.HostnameId = l.HostnameId
                LEFT JOIN AtoDor a ON h.HostnameId = a.HostnameId
                    AND a.DorDate = CAST(GETDATE() AS DATE)
                WHERE h.IsActive = 1
                AND ISNULL(h.IsLeader, 0) = 0
                AND (h.Hostname LIKE ? OR l.LineNumber LIKE ?)";

            $params = [$searchTerm, $searchTerm];

            if ($currentTabletId) {
                $sql .= " AND h.HostnameId != ?";
                $params[] = $currentTabletId;
            }

            $sql .= " ORDER BY
                    CASE WHEN h.IsLoggedIn = 1 AND h.IsActive = 1 AND l.IsLoggedIn = 1 AND l.IsActive = 1
                         THEN 0 ELSE 1 END,
                    h.Hostname ASC";

            return $this->db->execute($sql, $params);
        } catch (Exception $e) {
            error_log("Error searching tablets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update the line status for a specific line
     */
    public function updateLineStatus($lineId, $lineStatusId)
    {
        try {
            $sql = "UPDATE GenLine SET LineStatusId = ? WHERE LineId = ?";
            $this->db->execute($sql, [$lineStatusId, $lineId]);
            return true;
        } catch (Exception $e) {
            error_log("Error updating line status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update line status by hostname ID
     */
    public function updateLineStatusByHostname($hostnameId, $lineStatusId)
    {
        try {
            $sql = "UPDATE GenLine SET LineStatusId = ? WHERE HostnameId = ?";
            $this->db->execute($sql, [$lineStatusId, $hostnameId]);
            return true;
        } catch (Exception $e) {
            error_log("Error updating line status by hostname: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all available line statuses from GenLineStatus table
     */
    public function getLineStatuses()
    {
        try {
            $sql = "SELECT LineStatusId, LineStatusName FROM GenLineStatus ORDER BY LineStatusId";
            return $this->db->execute($sql);
        } catch (Exception $e) {
            error_log("Error fetching line statuses: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current line status for a specific line
     */
    public function getLineStatus($lineId)
    {
        try {
            $sql = "SELECT
                        l.LineId,
                        l.LineNumber,
                        l.LineStatusId,
                        ls.LineStatusName
                    FROM GenLine l
                    INNER JOIN GenLineStatus ls ON l.LineStatusId = ls.LineStatusId
                    WHERE l.LineId = ?";
            $result = $this->db->execute($sql, [$lineId]);
            return !empty($result) ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching line status: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get line information by hostname ID
     */
    public function getLineByHostname($hostnameId)
    {
        try {
            $sql = "SELECT
                        l.LineId,
                        l.LineNumber,
                        l.HostnameId,
                        l.DorTypeId,
                        l.LineStatusId,
                        ls.LineStatusName,
                        l.IsLoggedIn,
                        l.IsActive,
                        h.Hostname
                    FROM GenLine l
                    INNER JOIN GenLineStatus ls ON l.LineStatusId = ls.LineStatusId
                    INNER JOIN GenHostname h ON l.HostnameId = h.HostnameId
                    WHERE l.HostnameId = ?";
            $result = $this->db->execute($sql, [$hostnameId]);
            return !empty($result) ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching line by hostname: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Batch update line status for multiple lines
     */
    public function batchUpdateLineStatus($updates)
    {
        try {
            $sql = "UPDATE GenLine SET LineStatusId = ? WHERE LineId = ?";

            foreach ($updates as $update) {
                $this->db->execute($sql, [$update['lineStatusId'], $update['lineId']]);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error batch updating line status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log line status change for audit trail
     */
    public function logLineStatusChange($lineId, $oldStatusId, $newStatusId, $userId, $reason = '')
    {
        try {
            // You might want to create a LineStatusLog table for this
            $sql = "INSERT INTO LineStatusLog (LineId, OldStatusId, NewStatusId, ChangedBy, ChangeDate, Reason)
                    VALUES (?, ?, ?, ?, GETDATE(), ?)";
            $this->db->execute($sql, [$lineId, $oldStatusId, $newStatusId, $userId, $reason]);
            return true;
        } catch (Exception $e) {
            error_log("Error logging line status change: " . $e->getMessage());
            return false;
        }
    }
}
