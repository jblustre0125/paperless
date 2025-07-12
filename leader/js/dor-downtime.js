document.addEventListener("DOMContentLoaded", function () {
  // Initialize all modals and their event listeners
  initializeDowntimeModals();

  // Save button handler
  document.body.addEventListener("click", handleSaveDowntime);

  // Create toast container if it doesn't exist
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
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

  toastContainer.appendChild(toast);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    const toastElement = document.getElementById(toastId);
    if (toastElement) {
      toastElement.classList.remove("show");
      setTimeout(() => toastElement.remove(), 500);
    }
  }, 5000);

  // Add click to dismiss
  toast.querySelector(".btn-close").addEventListener("click", function () {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 500);
  });
}

function initializeDowntimeModals() {
  // Set up event listeners for all downtime triggers
  document.querySelectorAll(".downtime-trigger").forEach((button) => {
    button.addEventListener("click", function () {
      const recordHeaderId = this.dataset.recordId;
      setupTimeInputListeners(recordHeaderId);
    });
  });
}

function setupTimeInputListeners(recordHeaderId) {
  const timeStart = document.getElementById(`timeStart${recordHeaderId}`);
  const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`);

  // Add input masking for time fields
  [timeStart, timeEnd].forEach((input) => {
    input.addEventListener("input", function (e) {
      // Enforce HH:MM format
      let value = this.value.replace(/\D/g, "");
      if (value.length > 2) {
        value = value.substring(0, 2) + ":" + value.substring(2, 4);
      }
      this.value = value;

      // Update duration in real-time
      updateDuration(recordHeaderId);
    });

    // Also update on blur in case user pastes content
    input.addEventListener("change", function () {
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

    // Visual feedback
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

  // Convert to minutes
  const startMinutes = startH * 60 + startM;
  const endMinutes = endH * 60 + endM;

  // Calculate difference
  let diff = endMinutes - startMinutes;

  // Handle overnight (if end time is next day)
  if (diff < 0) {
    diff += 24 * 60; // Add 24 hours
  }

  // Convert back to hours and minutes
  const hrs = Math.floor(diff / 60);
  const mins = diff % 60;

  // Return formatted duration or "Invalid" if still negative
  return diff >= 0
    ? `${hrs.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")}`
    : "Invalid";
}

async function handleSaveDowntime(event) {
  if (!event.target.classList.contains("btn-save-downtime")) return;

  try {
    const button = event.target;
    const modal = button.closest(".modal");
    const recordHeaderId = modal.dataset.recordId;

    // Validate inputs
    const timeStart = document.getElementById(
      `timeStart${recordHeaderId}`
    ).value;
    const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`).value;

    if (!timeStart || !timeEnd) {
      throw new Error("Both start and end times are required");
    }

    const duration = calculateDuration(timeStart, timeEnd);
    if (duration === "Invalid") {
      throw new Error("End time must be after start time");
    }

    // Collect all downtime data
    const downtimeData = {
      DowntimeId: document.getElementById(`downtimeSelect${recordHeaderId}`)
        .value,
      ActionTakenId: document.getElementById(
        `actionTakenSelect${recordHeaderId}`
      ).value,
      TimeStart: timeStart,
      TimeEnd: timeEnd,
      Duration: duration,
      Pic: document.getElementById(`picInput${recordHeaderId}`).value || null,
      RemarksId:
        document.getElementById(`remarksSelect${recordHeaderId}`).value || null,
    };

    // Send to server
    const response = await fetch("../controller/dor-downtime.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        type: "saveDowntime",
        recordHeaderId: recordHeaderId,
        downtimeData: downtimeData,
      }),
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Server error: ${errorText}`);
    }

    const result = await response.json();
    if (!result.success) {
      throw new Error(result.error || "Failed to save downtime");
    }

    // Update UI on success
    updateDowntimeBadge(recordHeaderId);
    bootstrap.Modal.getInstance(modal).hide();
    showToast("Downtime saved successfully!", "success");
  } catch (error) {
    console.error("Error saving downtime:", error);
    showToast(`Error: ${error.message}`, "danger");
  }
}

function updateDowntimeBadge(recordHeaderId) {
  const badgeContainer = document.getElementById(
    `downtimeInfo${recordHeaderId}`
  );
  if (badgeContainer) {
    const downtimeSelect = document.getElementById(
      `downtimeSelect${recordHeaderId}`
    );
    const selectedOption = downtimeSelect.options[downtimeSelect.selectedIndex];

    const badgeText = selectedOption
      ? selectedOption.text.split(" - ")[0]
      : "No Downtime";

    const newBadge = document.createElement("small");
    newBadge.className = "badge bg-danger text-white me-1 mb-1";
    newBadge.textContent = badgeText;

    badgeContainer.appendChild(newBadge);
  }
}
