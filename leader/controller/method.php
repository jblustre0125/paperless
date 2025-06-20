<?php
    
    class Method {
        
        private $db;

        public function __construct($dbConnection) {
            $this->db = new DbOp($dbConnection); //use the DbOp class
        }

        //get active hostnames
        public function getActiveHostnames() {
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

        // In your Method class
        public function getOnlineTablets($excludeHostnameId = null) {
            $query = "SELECT 
                        HostnameId as RecordId, 
                        Hostname, 
                        IsActive,
                        IsLoggedin
                    FROM GenHostname 
                    WHERE IsLoggedin = 1";
            
            if ($excludeHostnameId) {
                $query .= " AND HostnameId != ?";
                return $this->db->execute($query, [$excludeHostnameId]);
            }
            
            return $this->db->execute($query);
        }
        public function getTabletStatus() {
            $query = "SELECT 
                        HostnameId as RecordId, 
                        Hostname, 
                        IsActive,
                        IsLoggedin
                    FROM GenHostname 
                    ORDER BY IsLogin DESC, Hostname ASC";
            return $this->db->execute($query);
        }

        public function getAllHostnames() {
            $query = "SELECT HostnameId as RecordId, Hostname, IsActive 
                    FROM GenHostname 
                    ORDER BY IsActive DESC, Hostname ASC";
            return $this->db->execute($query);
        }
        
        public function getCurrentTablet($hostnameId) {
            $hostnames = $this->getActiveHostnames();
            foreach ($hostnames as $hostname) {
                if ($hostname['HostnameId'] == $hostnameId) {
                    return $hostname;
                }
            }
            return null;
        }

        public function updateTabletStatus($hostnameId, $status) {
            $sql = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
            $this->db->execute($sql, [$hostnameId, $status]);
            return true;
        }
        //get visual checkpoints by DorTypeId
        public function getVisualCheckpoint($dorTypeId){
            $sql = "SELECT * FROM GenDorCheckpointVisual WHERE DorTypeId = ? AND IsActive = 1 ORDER BY SequenceId ";
            return $this->db->execute($sql, [$dorTypeId]);
        }

        //Insert a visual checkpoints result
        public function insertVisualCheckpoint($recordId, $checkpointId, $hatsumono, $nakamono, $owarinomo){
            $sql = "INSERT INTO AtoDorCheckpointVisual (RecordId, CheckpointId, Hatsumono, Nakamono, Owarinomo) VALUES
            (?,?,?,?,?)";
            return $this->db->execute($sql, [$recordId, $checkpointId, $hatsumono, $nakamono, $owarinomo]);
        }

        //Insert dimension check result
        public function insertDimensionCheck($recordId, $values){
            $sql = "INSERT INTO AtoDimensionCheck(RecordId, Hatsumono1, Hatsumono2, Hatsumono3,
            Nakamono1, Nakamono2, Nakamono3,
            Owarinomo1, Owarinomo2, Owarinomo3) VALUES (?,?,?,?,?,?,?,?,?,?)";

            $params = [
                $recordId,
                $values ['Hatsumono1'], $values['Hatsumono2'], $values['Hatsumono3'],
                $values ['Nakamono1'], $values['Nakamono2'], $values['Nakamono3'],
                $values ['Owarinomo1'], $values['Owarinomo2'], $values['Owarinomo3']
            ];
            return $this->db->execute($sql, $params);
        }


    }

    
?>