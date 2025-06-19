<?php
    
    class Method {
        
        private $db;

        public function __construct($dbConnection) {
            $this->db = new DbOp($dbConnection); //use the DbOp class
        }

        //get active hostnames
        public function getActiveHostnames(){
            $sql = "SELECT Hostname, IsActive FROM GenHostname WHERE IsActive = 1 ORDER BY Hostname";

            return $this->db->execute($sql);
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