<?php
require_once '../../config/dbop.php';

class DorReportController
{
    private $db;

    public function __construct()
    {
        $this->db = new DbOp(1); // Connect to Paperless
    }

    public function getAllDorReports()
    {
        $results = [];

        $results['AtoDor'] = $this->db->execute(
            "SELECT * FROM AtoDor"
        );

        $results['AtoDorCheckpointDefinition'] = $this->db->execute(
            "SELECT * FROM AtoDorCheckpointDefinition"
        );

        $results['AtoDorCheckpointRefresh'] = $this->db->execute(
            "SELECT * FROM AtoDorCheckpointRefresh"
        );

        $results['AtoDorCheckpointVisual'] = $this->db->execute(
            "SELECT * FROM AtoDorCheckpointVisual"
        );

        $results['AtoDorHeader'] = $this->db->execute(
            "SELECT * FROM AtoDorHeader"
        );

        $results['AtoDorDetail'] = $this->db->execute(
            "SELECT * FROM AtoDorDetail"
        );

        return $results;
    }

    public function getAsJson()
    {
        $report = $this->getAllDorReports();

        header('Content-Type: application/json');
        echo json_encode($report);
    }
}
