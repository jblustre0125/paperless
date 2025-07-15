<?php
if (!isset($recordHeaderId)) {
  echo "<div class='text-danger p-3'>Error: recordHeaderId not set in downtime-modal.php</div>";
  return;
}
?>
<div class="modal-header bg-secondary text-white">
  <h5 class="modal-title" id="downtimeModalLabel<?= $recordHeaderId ?>">
    Manage Downtime for Row #<?= htmlspecialchars($i + 1) ?>
  </h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <div class="mb-3">
    <table class="table table-bordered text-center align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 20%;">Time Start</th>
          <th style="width: 20%;">Time End</th>
          <th style="width: 20%;">Duration</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <input type="text" class="form-control text-center time-start" placeholder="HH:mm" maxlength="5"
              pattern="[0-9]{2}:[0-9]{2}" id="timeStart<?= $recordHeaderId ?>" />
          </td>
          <td>
            <input type="text" class="form-control text-center time-end" placeholder="HH:mm" maxlength="5"
              pattern="[0-9]{2}:[0-9]{2}" id="timeEnd<?= $recordHeaderId ?>" />
          </td>
          <td>
            <span id="duration<?= $recordHeaderId ?>" class="badge bg-secondary">00:00</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="mb-3">
    <label class="form-label">Downtime</label>
    <div class="searchable-dropdown">
      <input type="text" class="form-control" onkeyup="filterDropdown(this)" placeholder="Search Downtime...">
      <ul class="dropdown-list list-group position-absolute w-100"
        style="max-height: 150px; overflow-y: auto; z-index: 1000; display: none;">
        <?php foreach ($downtimeOptions as $d): ?>
          <li class="list-group-item list-group-item-action" onclick="selectOption(this)"
            data-id="<?= $d['DowntimeId'] ?>">
            <?= htmlspecialchars($d['DowntimeCode']) ?> -
            <?= htmlspecialchars($d['DowntimeName']) ?>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item list-group-item-action text-primary fw-bold" onclick="selectOption(this)"
          data-id="custom">Others</li>
      </ul>
      <input type="hidden" id="downtimeSelect<?= $recordHeaderId ?>" />
      <input type="text" id="downtimeInput<?= $recordHeaderId ?>" class="form-control mt-2 d-none"
        placeholder="Enter custom downtime">
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">Action Taken</label>
    <div class="searchable-dropdown">
      <input type="text" class="form-control" onkeyup="filterDropdown(this)" placeholder="Search Action Taken...">
      <ul class="dropdown-list list-group position-absolute w-100"
        style="max-height: 150px; overflow-y: auto; z-index: 1000; display: none;">
        <?php foreach ($actionTakenOptions as $a): ?>
          <li class="list-group-item list-group-item-action" onclick="selectOption(this)"
            data-id="<?= $a['ActionTakenId'] ?>">
            <?= htmlspecialchars($a['ActionTakenCode']) ?> -
            <?= htmlspecialchars($a['ActionTakenName']) ?>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item list-group-item-action text-primary fw-bold" onclick="selectOption(this)"
          data-id="custom">Others</li>
      </ul>
      <input type="hidden" id="actionTakenSelect<?= $recordHeaderId ?>" />
      <input type="text" id="actionTakenInput<?= $recordHeaderId ?>" class="form-control mt-2 d-none"
        placeholder="Enter custom action taken">
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">Remarks</label>
    <div class="searchable-dropdown">
      <input type="text" class="form-control" onkeyup="filterDropdown(this)" placeholder="Search Remarks...">
      <ul class="dropdown-list list-group position-absolute w-100"
        style="max-height: 150px; overflow-y: auto; z-index: 1000; display: none;">
        <?php foreach ($remarksOptions as $r): ?>
          <li class="list-group-item list-group-item-action" onclick="selectOption(this)"
            data-id="<?= $r['RemarksId'] ?>">
            <?= htmlspecialchars($r['RemarksCode']) ?> -
            <?= htmlspecialchars($r['RemarksName']) ?>
          </li>
        <?php endforeach; ?>
        <li class="list-group-item list-group-item-action text-primary fw-bold" onclick="selectOption(this)"
          data-id="custom">Others</li>
      </ul>
      <input type="hidden" id="remarksSelect<?= $recordHeaderId ?>" />
      <input type="text" id="remarksInput<?= $recordHeaderId ?>" class="form-control mt-2 d-none"
        placeholder="Enter custom remarks">
    </div>
  </div>

  <div class="mb-3">
    <label for="picInput<?= $recordHeaderId ?>" class="form-label">PIC (Person In Charge)</label>
    <input type="text" id="picInput<?= $recordHeaderId ?>" class="form-control" placeholder="Enter PIC name">
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-success btn-save-downtime" data-record-id="<?= $recordHeaderId ?>">
    Save Downtime
  </button>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
