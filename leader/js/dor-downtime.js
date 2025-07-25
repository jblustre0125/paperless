document.addEventListener("DOMContentLoaded", function () {
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

  refreshDowntimeInfo();

  setInterval(refreshDowntimeInfo, 3000);
});

function refreshDowntimeInfo() {
  // First, check for new records that operators might have created
  fetch("../controller/dor-downtime.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      type: "getNewRecords",
      currentRecordIds: getCurrentRecordIds(),
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.newRecords && data.newRecords.length > 0) {
        // Reload the page to include new records
        setTimeout(() => {
          window.location.reload();
        }, 500);
        return; // Don't continue with regular refresh if we're reloading
      }
    })
    .catch((error) => {
      console.error("Error checking for new records:", error);
    });

  // Then refresh existing downtime info
  const downtimeInfoDivs = document.querySelectorAll('[id^="downtimeInfo"]');

  downtimeInfoDivs.forEach((div) => {
    const recordHeaderId = div.id.replace("downtimeInfo", "");

    fetch("../controller/dor-downtime.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        type: "getDowntimeInfo",
        recordHeaderId: recordHeaderId,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          div.innerHTML = data.html;
        }
      })
      .catch((error) => {
        console.error("Error refreshing downtime info:", error);
      });
  });
}

function getCurrentRecordIds() {
  const downtimeInfoDivs = document.querySelectorAll('[id^="downtimeInfo"]');
  return Array.from(downtimeInfoDivs).map((div) =>
    div.id.replace("downtimeInfo", "")
  );
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

  document.addEventListener("click", function (e) {
    document
      .querySelectorAll(".searchable-dropdown .dropdown-list")
      .forEach((list) => {
        if (!list.closest(".searchable-dropdown").contains(e.target)) {
          list.style.display = "none";
        }
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

function initializeDowntimeModals() {
  document.querySelectorAll(".downtime-trigger").forEach((button) => {
    button.addEventListener("click", function () {
      const recordHeaderId = this.dataset.recordId;
      setupTimeInputListeners(recordHeaderId);
      setupCustomDropdowns(recordHeaderId);
    });
  });
}

function setupTimeInputListeners(recordHeaderId) {
  const timeStart = document.getElementById(`timeStart${recordHeaderId}`);
  const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`);

  [timeStart, timeEnd].forEach((input) => {
    input.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value.length > 2) {
        value = value.substring(0, 2) + ":" + value.substring(2, 4);
      }
      this.value = value;
      updateDuration(recordHeaderId);
    });

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

  return diff >= 0
    ? `${hrs.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")}`
    : "Invalid";
}

// ... [ALL UNCHANGED CODE ABOVE] ...

async function handleSaveDowntime(event) {
  if (!event.target.classList.contains("btn-save-downtime")) return;

  try {
    const button = event.target;
    const modal = button.closest(".modal");
    const recordHeaderId = modal.dataset.recordId;

    const timeStart = document.getElementById(
      `timeStart${recordHeaderId}`
    ).value;
    const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`).value;

    if (!timeStart || !timeEnd) {
      throw new Error("Start and End times are required.");
    }

    const duration = calculateDuration(timeStart, timeEnd);
    if (duration === "Invalid") {
      throw new Error("End time must be after Start time.");
    }

    const downtimeId = document.getElementById(
      `downtimeSelect${recordHeaderId}`
    )?.value;
    const downtimeText = document.getElementById(
      `downtimeInput${recordHeaderId}`
    )?.value;
    const downtimeDisplay = document
      .querySelector(`#downtimeSelect${recordHeaderId}`)
      .closest(".searchable-dropdown")
      ?.querySelector('input[type="text"]')?.value;

    const actionTakenId = document.getElementById(
      `actionTakenSelect${recordHeaderId}`
    )?.value;
    const actionTakenText = document.getElementById(
      `actionTakenInput${recordHeaderId}`
    )?.value;

    const remarksId = document.getElementById(
      `remarksSelect${recordHeaderId}`
    )?.value;
    const remarksText = document.getElementById(
      `remarksInput${recordHeaderId}`
    )?.value;

    const downtimeData = {
      DowntimeId: downtimeId === "custom" ? "custom" : parseInt(downtimeId, 10),
      ActionTakenId:
        actionTakenId === "custom" ? "custom" : parseInt(actionTakenId, 10),
      RemarksId: remarksId === "custom" ? "custom" : parseInt(remarksId, 10),
      TimeStart: timeStart,
      TimeEnd: timeEnd,
      Duration: duration,
      Pic: document.getElementById(`picInput${recordHeaderId}`)?.value || null,
    };

    if (downtimeId === "custom" && downtimeText) {
      downtimeData.CustomDowntimeName = downtimeText;
    }

    if (actionTakenId === "custom" && actionTakenText) {
      downtimeData.CustomActionName = actionTakenText;
    }

    if (remarksId === "custom" && remarksText) {
      downtimeData.CustomRemarksName = remarksText;
    }

    console.log("Sending downtime data:", downtimeData); // Debug log

    const response = await fetch("../controller/dor-downtime.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        type: "saveDowntime",
        recordHeaderId,
        downtimeData,
      }),
    });

    console.log("Response status:", response.status); // Debug log
    const result = await response.json();
    console.log("Response result:", result); // Debug log

    if (!result.success) {
      throw new Error(result.message || "Failed to save downtime");
    }

    // SAFELY display the badge text
    const downtimeLabel =
      downtimeId === "custom" ? downtimeText : downtimeDisplay;
    updateDowntimeBadge(recordHeaderId, downtimeLabel);

    bootstrap.Modal.getInstance(modal).hide();
    showToast("Downtime saved successfully!", "success");
  } catch (error) {
    console.error("Error saving downtime:", error);
    showToast(`Error: ${error.message}`, "danger");
  }
}

function loadDowntimeContent(recordHeaderId, rowIndex) {
  // Support multiple modals: find the correct modal and content container for this recordHeaderId
  const modalId = `downtimeModal${recordHeaderId}`;
  const modalContentId = `downtimeModalContent${recordHeaderId}`;
  const modal = document.getElementById(modalId);
  const modalContent = document.getElementById(modalContentId);
  if (modalContent) {
    modalContent.innerHTML =
      '<div class="text-center py-5"><div class="spinner-border"></div></div>';
  }
  // Fetch downtime detail via AJAX (reload modal content dynamically)
  fetch(`../partials/downtime-modal.php?record_header_id=${recordHeaderId}`)
    .then((res) => {
      if (!res.ok) throw new Error("Failed to load downtime modal content");
      return res.text();
    })
    .then((html) => {
      if (modalContent) {
        modalContent.innerHTML = html;
        // After content is loaded, set up listeners and dropdowns
        setupTimeInputListeners(recordHeaderId);
        setupCustomDropdowns(recordHeaderId);
      }
    })
    .catch((err) => {
      if (modalContent) {
        modalContent.innerHTML = `<div class='text-danger text-center py-5'>${err.message}</div>`;
      }
    });
  // Show modal (Bootstrap 5)
  if (modal) {
    const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    bsModal.show();
    // Set modal context for save handler
    modal.dataset.recordId = recordHeaderId;
  }
}

function updateDowntimeBadge(recordHeaderId, badgeTextOverride = null) {
  const badgeContainer = document.getElementById(
    `downtimeInfo${recordHeaderId}`
  );
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
  newBadge.className = "badge bg-light text-dark border me-1 mb-1";
  newBadge.textContent = badgeText;
  badgeContainer.appendChild(newBadge);
}

function setupCustomDropdowns(recordHeaderId) {
  ["downtime", "actionTaken", "remarks"].forEach((type) => {
    const wrapper = document
      .getElementById(`${type}Select${recordHeaderId}`)
      ?.closest(".searchable-dropdown");
    if (!wrapper) return;

    const list = wrapper.querySelector(".dropdown-list");
    const items = list.querySelectorAll("li");
    const customInput = document.getElementById(
      `${type}Input${recordHeaderId}`
    );

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
