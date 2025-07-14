<?php if (isset($recordHeaderId)): ?>
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
  <button type="button" class="btn btn-danger btn-save-downtime" data-record-id="<?= $recordHeaderId ?>">
    Save Downtime
  </button>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
  initializeDowntimeModals();
  document.body.addEventListener("click", handleSaveDowntime);

  if (!document.getElementById("toast-container")) {
    const toastContainer = document.createElement("div");
    toastContainer.id = "toast-container";
    toastContainer.style.position = "fixed";
    toastContainer.style.top = "20px";
    toastContainer.style.right = "20px";
    toastContainer.style.zIndex = "9999";
    document.body.appendChild(toastContainer);
  }
});

function initializeDowntimeModals() {
  document.querySelectorAll(".btn-save-downtime").forEach((button) => {
    const recordHeaderId = button.dataset.recordId;
    setupTimeInputListeners(recordHeaderId);
    setupCustomDropdowns(recordHeaderId);
  });
}

function setupTimeInputListeners(recordHeaderId) {
  const timeStart = document.getElementById(`timeStart${recordHeaderId}`);
  const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`);

  [timeStart, timeEnd].forEach((input) => {
    input.addEventListener("input", function() {
      let value = this.value.replace(/\D/g, "");
      if (value.length > 2) {
        value = value.substring(0, 2) + ":" + value.substring(2, 4);
      }
      this.value = value;
      updateDuration(recordHeaderId);
    });

    input.addEventListener("change", function() {
      updateDuration(recordHeaderId);
    });
  });
}

function updateDuration(recordHeaderId) {
  const timeStart = document.getElementById(`timeStart${recordHeaderId}`).value;
  const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`).value;
  const durationSpan = document.getElementById(`duration${recordHeaderId}`);

  if (isValidTime(timeStart) && isValidTime(timeEnd)) {
    const duration = calculateDuration(timeStart, timeEnd);
    durationSpan.textContent = duration;

    if (duration === "Invalid") {
      durationSpan.classList.remove("bg-success");
      durationSpan.classList.add("bg-danger");
    } else {
      durationSpan.classList.remove("bg-secondary", "bg-danger");
      durationSpan.classList.add("bg-success");
    }
  } else {
    durationSpan.textContent = "00:00";
    durationSpan.classList.remove("bg-success", "bg-danger");
    durationSpan.classList.add("bg-secondary");
  }
}

function isValidTime(time) {
  return /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(time);
}

function calculateDuration(start, end) {
  const [startH, startM] = start.split(":").map(Number);
  const [endH, endM] = end.split(":").map(Number);

  const startMinutes = startH * 60 + startM;
  const endMinutes = endH * 60 + endM;

  let diff = endMinutes - startMinutes;
  if (diff < 0) diff += 24 * 60;

  const hrs = Math.floor(diff / 60);
  const mins = diff % 60;

  return diff >= 0 ? `${hrs.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")}` : "Invalid";
}

function filterDropdown(input) {
  const wrapper = input.closest(".searchable-dropdown");
  const list = wrapper.querySelector(".dropdown-list");
  const items = list.querySelectorAll("li");
  const filter = input.value.toLowerCase();
  let hasMatch = false;
  list.style.display = "block";

  items.forEach((item) => {
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(filter) ? "" : "none";
    if (text.includes(filter)) hasMatch = true;
  });

  if (!hasMatch) list.style.display = "none";
}

function selectOption(li) {
  const wrapper = li.closest(".searchable-dropdown");
  const input = wrapper.querySelector('input[type="text"]');
  const hidden = wrapper.querySelector('input[type="hidden"]');

  input.value = li.textContent.trim();
  hidden.value = li.dataset.id;
  wrapper.querySelector(".dropdown-list").style.display = "none";
}

function setupCustomDropdowns(recordHeaderId) {
  ["downtime", "actionTaken", "remarks"].forEach((type) => {
    const wrapper = document.getElementById(`${type}Select${recordHeaderId}`)?.closest(".searchable-dropdown");
    if (!wrapper) return;

    const list = wrapper.querySelector(".dropdown-list");
    const items = list.querySelectorAll("li");
    const customInput = document.getElementById(`${type}Input${recordHeaderId}`);

    items.forEach((li) => {
      li.addEventListener("click", () => {
        const isCustom = li.dataset.id === "custom";
        if (customInput) {
          customInput.classList.toggle("d-none", !isCustom);
          if (!isCustom) customInput.value = "";
        }

        const input = wrapper.querySelector('input[type="text"]');
        const hidden = wrapper.querySelector('input[type="hidden"]');

        input.value = li.textContent.trim();
        hidden.value = li.dataset.id;
        list.style.display = "none";
      });
    });
  });
}

function showToast(message, type = "success") {
  const toastContainer = document.getElementById("toast-container");
  const toastId = "toast-" + Date.now();

  const toast = document.createElement("div");
  toast.id = toastId;
  toast.className = `toast show align-items-center text-white bg-${type}`;
  toast.style.width = "300px";
  toast.style.marginBottom = "10px";
  toast.setAttribute("role", "alert");
  toast.setAttribute("aria-live", "assertive");
  toast.setAttribute("aria-atomic", "true");

  toast.innerHTML = `
    <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  `;

  toastContainer.appendChild(toast);

  setTimeout(() => {
    const toastElement = document.getElementById(toastId);
    if (toastElement) {
      toastElement.classList.remove("show");
      setTimeout(() => toastElement.remove(), 500);
    }
  }, 5000);

  toast.querySelector(".btn-close").addEventListener("click", () => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 500);
  });
}

async function handleSaveDowntime(event) {
  if (!event.target.classList.contains("btn-save-downtime")) return;

  try {
    const button = event.target;
    const recordHeaderId = button.dataset.recordId;

    const timeStart = document.getElementById(`timeStart${recordHeaderId}`).value;
    const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`).value;

    if (!timeStart || !timeEnd) throw new Error("Start and End times are required.");

    const duration = calculateDuration(timeStart, timeEnd);
    if (duration === "Invalid") throw new Error("End time must be after Start time.");

    const downtimeId = document.getElementById(`downtimeSelect${recordHeaderId}`)?.value;
    const downtimeText = document.getElementById(`downtimeInput${recordHeaderId}`)?.value;
    const downtimeDisplay = document
      .querySelector(`#downtimeSelect${recordHeaderId}`)
      .closest(".searchable-dropdown")
      ?.querySelector('input[type="text"]')?.value;

    const actionTakenId = document.getElementById(`actionTakenSelect${recordHeaderId}`)?.value;
    const actionTakenText = document.getElementById(`actionTakenInput${recordHeaderId}`)?.value;

    const remarksId = document.getElementById(`remarksSelect${recordHeaderId}`)?.value;
    const remarksText = document.getElementById(`remarksInput${recordHeaderId}`)?.value;

    const downtimeData = {
      DowntimeId: downtimeId === "custom" ? "custom" : parseInt(downtimeId, 10),
      ActionTakenId: actionTakenId === "custom" ? "custom" : parseInt(actionTakenId, 10),
      RemarksId: remarksId === "custom" ? "custom" : parseInt(remarksId, 10),
      TimeStart: timeStart,
      TimeEnd: timeEnd,
      Duration: duration,
      Pic: document.getElementById(`picInput${recordHeaderId}`)?.value || null,
    };

    if (downtimeId === "custom" && downtimeText) downtimeData.CustomDowntimeName = downtimeText;
    if (actionTakenId === "custom" && actionTakenText) downtimeData.CustomActionName = actionTakenText;
    if (remarksId === "custom" && remarksText) downtimeData.CustomRemarksName = remarksText;

    const response = await fetch("../controller/dor-downtime.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        type: "saveDowntime",
        recordHeaderId,
        downtimeData,
      }),
    });

    const result = await response.json();

    if (!result.success) throw new Error(result.message || "Failed to save downtime");

    const downtimeLabel = downtimeId === "custom" ? downtimeText : downtimeDisplay;
    updateDowntimeBadge(recordHeaderId, downtimeLabel);

    bootstrap.Modal.getInstance(button.closest(".modal")).hide();
    showToast("Downtime saved successfully!", "success");
  } catch (error) {
    console.error("Error saving downtime:", error);
    showToast(`Error: ${error.message}`, "danger");
  }
}

function updateDowntimeBadge(recordHeaderId, badgeTextOverride = null) {
  const badgeContainer = document.getElementById(`downtimeInfo${recordHeaderId}`);
  if (!badgeContainer) return;

  const modal = document.getElementById(`downtimeModal${recordHeaderId}`);
  if (!modal) return;

  const wrapper = modal.querySelector(".searchable-dropdown");
  if (!wrapper) return;

  const textInput = wrapper.querySelector('input[type="text"]');
  const hiddenInput = wrapper.querySelector('input[type="hidden"]');
  if (!textInput || !hiddenInput || !hiddenInput.value.trim()) return;

  const badgeText = badgeTextOverride || textInput.value.split(" - ")[0].trim();

  const badgeExists = Array.from(badgeContainer.children).some(
    (badge) => badge.textContent.trim() === badgeText
  );
  if (badgeExists) return;

  const placeholder = badgeContainer.querySelector('[data-placeholder="true"]');
  if (placeholder) placeholder.remove();

  const newBadge = document.createElement("small");
  newBadge.className = "badge bg-secondary text-white me-1 mb-1";
  newBadge.textContent = badgeText;
  badgeContainer.appendChild(newBadge);
}
</script>