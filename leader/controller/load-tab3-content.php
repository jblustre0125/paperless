<?php
require_once '../../config/dbop.php';
require_once 'dor-downtime.php';

$controller = new DorDowntime();
$atoDorDetails = $controller->AtoDor();

$recordId = isset($_GET['record_id']) && is_numeric($_GET['record_id']) ? (int)$_GET['record_id'] : null;
$hostname_id = isset($_GET['hostname_id']) && is_numeric($_GET['hostname_id']) ? (int)$_GET['hostname_id'] : 0;

if ($hostname_id <= 0) {
  echo "Invalid HostnameId.";
  exit;
}

$db = new DbOp(1);
$hostnameResult = $db->execute("SELECT Hostname FROM GenHostname WHERE HostnameId = ?", [$hostname_id]);
$hostname = $hostnameResult[0]['Hostname'] ?? 'Unknown Hostname';

$downtimeList = $controller->getDowntimeList();
$downtimeMap = [];
$downtimeCodeMap = [];

foreach ($downtimeList as $d) {
  $downtimeMap[$d['DowntimeId']] = $d['DowntimeName'];
  $downtimeCodeMap[$d['DowntimeId']] = $d['DowntimeCode'];
}

$headers = [];
$filteredDetails = [];

if ($recordId !== null) {
  $headers = $db->execute("SELECT * FROM AtoDorHeader WHERE RecordId = ?", [$recordId]);
  if (!empty($headers)) {
    $recordHeaderIds = array_column($headers, 'RecordHeaderId');
    foreach ($atoDorDetails as $detail) {
      $rhid = (int)$detail['RecordHeaderId'];
      if (in_array($rhid, $recordHeaderIds)) {
        $filteredDetails[$rhid][] = $detail;
      }
    }
  }
}
?>

<!-- Modal Header -->
<div class="modal-header bg-secondary text-white">
  <h5 class="modal-title">Downtime Details – <?= htmlspecialchars($hostname) ?></h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<!-- Modal Body -->
<div class="modal-body">
  <table class="table table-bordered table-sm align-middle text-center">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th style="width: 15%;">Box No.</th>
        <th style="width: 15%;">Start Time</th>
        <th style="width: 15%;">End Time</th>
        <th style="width: 10%;">Duration</th>
        <th>Downtime</th>
        <th>*</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($headers)): ?>
        <?php foreach ($headers as $i => $header): ?>
          <?php
          $rhid = $header['RecordHeaderId'];
          $boxNo = $header['BoxNumber'] ?? '';

          try {
            $start = $header['TimeStart'] instanceof DateTime
              ? $header['TimeStart']->format('H:i')
              : (new DateTime($header['TimeStart']))->format('H:i');
          } catch (Exception $e) {
            $start = '';
          }

          try {
            $end = (!empty($header['TimeEnd']))
              ? ($header['TimeEnd'] instanceof DateTime
                ? $header['TimeEnd']->format('H:i')
                : (new DateTime($header['TimeEnd']))->format('H:i'))
              : '';
          } catch (Exception $e) {
            $end = '';
          }

          $duration = $header['Duration'] ?? '';
          $relatedDetails = $filteredDetails[$rhid] ?? [];

          $badges = [];
          foreach ($relatedDetails as $detail) {
            if (!empty($detail['DowntimeId'])) {
              foreach (explode(',', $detail['DowntimeId']) as $id) {
                $id = trim($id);
                if ($id !== '') {
                  $badge = $downtimeCodeMap[$id] ?? $id;
                  $badges[] = htmlspecialchars($badge);
                }
              }
            }
          }

          $modalId = "viewDowntimeModal_" . $rhid;
          $modalLabelId = "downtimeModalLabel_" . $rhid;
          $modalContentId = "downtimeModalContent_" . $rhid;
          ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><input type="text" class="form-control text-center bg-light" value="<?= htmlspecialchars($boxNo) ?>"
                disabled></td>
            <td><input type="text" class="form-control text-center bg-light" value="<?= $start ?>" disabled></td>
            <td><input type="text" class="form-control text-center bg-light" value="<?= $end ?>" disabled></td>
            <td><?= htmlspecialchars($duration) ?></td>
            <td>
              <div class="d-flex flex-column align-items-center gap-2 py-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#downtimeModal"
                  onclick="loadDowntimeContent(<?= $rhid ?>, <?= $i ?>)">
                  View Downtime
                </button>
                <div class="d-flex flex-wrap justify-content-center gap-1">
                  <?php foreach ($badges as $badge): ?>
                    <span class="badge bg-secondary"><?= $badge ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-danger" title="Remove">&times;</button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center text-muted">No Downtime Details Found</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script src="../../js/bootstrap.bundle.min.js"></script>
