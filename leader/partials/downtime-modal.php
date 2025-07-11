<?php if (isset($recordHeaderId)): ?>
    <div class="modal-header bg-danger text-white">
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
                            <input type="text" class="form-control text-center time-start" placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" id="timeStart<?= $recordHeaderId ?>" />
                        </td>
                        <td>
                            <input type="text" class="form-control text-center time-end" placeholder="HH:mm" maxlength="5" pattern="[0-9]{2}:[0-9]{2}" id="timeEnd<?= $recordHeaderId ?>" />
                        </td>
                        <td>
                            <span id="duration<?= $recordHeaderId ?>" class="badge bg-secondary">00:00</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mb-3">
            <label for="downtimeSelect<?= $recordHeaderId ?>" class="form-label">Downtime Reason</label>
            <select id="downtimeSelect<?= $recordHeaderId ?>" class="form-select">
                <option value="">-- Select Downtime --</option>
                <?php foreach ($downtimeOptions as $d): ?>
                    <option value="<?= $d['DowntimeId'] ?>">
                        <?= htmlspecialchars($d['DowntimeCode']) ?> - <?= htmlspecialchars($d['DowntimeName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="badge bg-info mt-1" id="downtimeBadge<?= $recordHeaderId ?>"></span>
        </div>

        <div class="mb-3">
            <label for="actionTakenSelect<?= $recordHeaderId ?>" class="form-label">Action Taken</label>
            <select id="actionTakenSelect<?= $recordHeaderId ?>" class="form-select">
                <option value="">-- Select Action Taken --</option>
                <?php foreach ($actionTakenOptions as $a): ?>
                    <option value="<?= $a['ActionTakenId'] ?>">
                        <?= htmlspecialchars($a['ActionTakenCode']) ?> - <?= htmlspecialchars($a['ActionTakenName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="remarksSelect<?= $recordHeaderId ?>" class="form-label">Remarks</label>
            <select id="remarksSelect<?= $recordHeaderId ?>" class="form-select">
                <option value="">-- Select Remarks --</option>
                <?php foreach ($remarksOptions as $r): ?>
                    <option value="<?= $r['RemarksId'] ?>">
                        <?= htmlspecialchars($r['RemarksCode']) ?> - <?= htmlspecialchars($r['RemarksName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="picInput<?= $recordHeaderId ?>" class="form-label">PIC (Person In Charge)</label>
            <input type="text" id="picInput<?= $recordHeaderId ?>" class="form-control" placeholder="Enter PIC name">
        </div>
    </div>

    <div class="modal-footer">
        <button type="button"
                class="btn btn-danger btn-save-downtime"
                data-record-id="<?= $recordHeaderId ?>">
            Save Downtime
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
<?php endif; ?>
