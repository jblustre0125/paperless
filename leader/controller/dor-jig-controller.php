<?php
require_once '../../config/dbop.php';

class DorJigController
{
  private $db;
  private $db3;


  public function __construct()
  {
    $this->db = new DbOp(1);
    $this->db3 = new DbOp(3);
  }

  public function getJigByNameById($jigId)
  {
    try {
      if (empty($jigId)) {
        return null;
      }

      $sql = "SELECT JigName FROM MntJig WHERE JigId=? AND IsActive=1";
      $result = $this->db3->execute($sql, [$jigId]);

      return !empty($result) ? $result[0]['JigName'] : null;
    } catch (Exception $e) {
      error_log("Error fetching jig name: " . $e->getMessage());
      return null;
    }
  }

  public function getDorJigInfo($recordId)
  {
    try {
      $sql = "SELECT DorTypeId, JigId FROM AtoDor WHERE RecordId=?";
      $result = $this->db->execute($sql, [$recordId]);

      if (empty($result)) {
        return null;
      }

      $dorInfo = $result[0];
      $jigName = null;

      if ($dorInfo['DorTypeId'] == 2 && !empty($dorInfo['JigId'])) {
        $jigName = $this->getJigByNameById($dorInfo['JigId']);
      }

      return [
        'DorTypeId' => $dorInfo['DorTypeId'],
        'JigId' => $dorInfo['JigId'],
        'JigName' => $jigName
      ];
    } catch (Exception $e) {
      error_log("Error fetching DOR Jig info: " . $e->getMessage());
      return null;
    }
  }
}
