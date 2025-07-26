/**
 * Initializes downtime history table and collapse icon for the modal
 * @param {string|number} recordHeaderId
 */
function initDowntimeHistory(recordHeaderId) {
  var collapseEl = document.getElementById(
    "downtimeHistoryCollapse" + recordHeaderId
  );
  var iconEl = document.getElementById("collapseIcon" + recordHeaderId);
  // Only icon toggles collapse
  if (iconEl && collapseEl) {
    iconEl.style.cursor = "pointer";
    iconEl.addEventListener("click", function (e) {
      e.stopPropagation();
      var bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
      bsCollapse.toggle();
    });
    collapseEl.addEventListener("show.bs.collapse", function () {
      iconEl.innerHTML = '<i class="bi bi-chevron-up"></i>';
    });
    collapseEl.addEventListener("hide.bs.collapse", function () {
      iconEl.innerHTML = '<i class="bi bi-chevron-down"></i>';
    });
    // Only fetch downtime records when card is expanded
    collapseEl.addEventListener("show.bs.collapse", function () {
      fetch("../controller/dor-downtime.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          type: "getDowntimeRecords",
          recordHeaderId: recordHeaderId,
        }),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          var tbody = document.getElementById(
            "downtimeHistoryBody" + recordHeaderId
          );
          if (!tbody) return;
          tbody.innerHTML = "";
          if (!data.success || !data.records || data.records.length === 0) {
            tbody.innerHTML =
              '<tr><td colspan="6" class="text-muted">No downtime recorded</td></tr>';
            return;
          }
          data.records.forEach(function (rec) {
            function formatTime(val) {
              if (!val || typeof val !== "string") return "";
              var d = new Date(val);
              if (!isNaN(d.getTime())) {
                var hours = d.getHours().toString().padStart(2, "0");
                var minutes = d.getMinutes().toString().padStart(2, "0");
                return hours + ":" + minutes;
              }
              var match = val.match(/(\d{2}):(\d{2})/);
              return match ? match[0] : "";
            }
            var dtStart = formatTime(rec.TimeStart);
            var dtEnd = formatTime(rec.TimeEnd);
            var duration = rec.Duration || "";
            var dtCode = rec.DowntimeCode || "";
            var actionTaken = rec.ActionTakenName || "";
            var pic = rec.Pic || "";
            var actionCell =
              '<span class="d-inline-block text-truncate" style="max-width:120px;white-space:normal;" title="' +
              actionTaken +
              '">' +
              actionTaken +
              "</span>";
            tbody.innerHTML +=
              "<tr>" +
              '<td style="width:60px;">' +
              dtStart +
              "</td>" +
              '<td style="width:60px;">' +
              dtEnd +
              "</td>" +
              '<td style="width:60px;">' +
              duration +
              "</td>" +
              '<td style="width:80px;">' +
              dtCode +
              "</td>" +
              "<td>" +
              actionCell +
              "</td>" +
              "<td>" +
              pic +
              "</td>" +
              "</tr>";
          });
        })
        .catch(function () {
          var tbody = document.getElementById(
            "downtimeHistoryBody" + recordHeaderId
          );
          if (tbody)
            tbody.innerHTML =
              '<tr><td colspan="6" class="text-danger">Error loading downtime history</td></tr>';
        });
    });
  }
}
// Modal management system
const modalManager = {
  modals: [],
  backdropCleanupTimer: null,

  init: function () {
    // Initialize all modals on the page
    document.querySelectorAll(".modal").forEach((modalElement) => {
      this.registerModal(modalElement);
    });

    // Set up mutation observer for dynamically added modals
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === 1 && node.classList.contains("modal")) {
            this.registerModal(node);
          }
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  },

  registerModal: function (modalElement) {
    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);

    // Only add if not already registered
    if (!this.modals.some((m) => m.element === modalElement)) {
      this.modals.push({
        instance: modalInstance,
        element: modalElement,
      });

      // Add hidden event listener
      modalElement.addEventListener("hidden.bs.modal", () => {
        this.cleanupBackdrops();
      });
    }
  },

  cleanupBackdrops: function () {
    // Clear any pending cleanup
    if (this.backdropCleanupTimer) {
      clearTimeout(this.backdropCleanupTimer);
    }

    // Schedule cleanup after animation completes
    this.backdropCleanupTimer = setTimeout(() => {
      const anyModalVisible = this.modals.some((m) =>
        m.element.classList.contains("show")
      );

      if (!anyModalVisible) {
        // Remove all backdrop elements
        document
          .querySelectorAll(".modal-backdrop")
          .forEach((el) => el.remove());

        // Reset body state
        document.body.classList.remove("modal-open");
        document.body.style.paddingRight = "";
        document.body.style.overflow = "";
      }

      this.backdropCleanupTimer = null;
    }, 300);
  },
};

// Global search functionality - moved outside DOMContentLoaded
function filterTablets() {
  const searchInput = document.getElementById("tabletSearch");
  const noResults = document.getElementById("noResults");

  if (!searchInput) return;

  const searchTerm = searchInput.value.toLowerCase().trim();
  // Query fresh tablet cards each time
  const tabletCards = document.querySelectorAll(".tablet-card");
  let visibleCount = 0;

  tabletCards.forEach((card) => {
    const hostname = card.dataset.hostname || "";
    const line = card.dataset.line || "";
    const shouldShow =
      hostname.includes(searchTerm) || line.includes(searchTerm);

    if (shouldShow) {
      card.style.display = "block";
      visibleCount++;
    } else {
      card.style.display = "none";
    }
  });

  // Show/hide no results message
  if (noResults) {
    if (visibleCount === 0 && searchTerm.length > 0) {
      noResults.style.display = "block";
    } else {
      noResults.style.display = "none";
    }
  }
}

function clearSearch() {
  const searchInput = document.getElementById("tabletSearch");
  const noResults = document.getElementById("noResults");
  const tabletCards = document.querySelectorAll(".tablet-card");

  if (searchInput) searchInput.value = "";
  tabletCards.forEach((card) => {
    card.style.display = "block";
  });
  if (noResults) noResults.style.display = "none";
  if (searchInput) searchInput.focus();
}

/**
 * DOM Ready Handler
 * Initializes all necessary functionality when the page loads
 */
document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("tabletSearch");
  const clearSearchBtn = document.getElementById("clearSearch");

  // Event listeners for search functionality
  if (searchInput) {
    searchInput.addEventListener("input", filterTablets);
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        filterTablets();
      }
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener("click", clearSearch);
  }

  // Initialize modal management system
  modalManager.init();

  // Initialize all tooltips
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Start auto-refresh of tablet list
  loadTabletList();
  setInterval(checkTabletChanges, 2000);
});

/**
 * Opens the Tab3 quick view modal for a specific tablet
 * @param {string} hostnameId - The ID of the tablet to view
 * @param {string} recordId - The record ID to load (if available)
 */
function openTab3Modal(hostnameId, recordId) {
  const modalElement = document.getElementById("tab3QuickModal");
  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const wrapper = document.getElementById("tab3ModalContent");

  // Show loading state
  wrapper.innerHTML = `
    <div class="modal-header">
      <h5 class="modal-title">Loading...</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center py-5">
      <div class="spinner-border text-success" role="status"></div>
      <p class="mt-3 text-muted">Loading DOR content...</p>
    </div>
  `;

  modal.show();

  // Prepare request parameters
  const params = new URLSearchParams({
    hostname_id: hostnameId,
  });
  if (recordId && recordId !== "null") params.append("record_id", recordId);

  // Fetch Tab3 content
  fetch(`../controller/load-tab3-content.php?${params.toString()}`)
    .then((res) => {
      if (!res.ok) throw new Error("Network response was not ok");
      return res.text();
    })
    .then((html) => {
      wrapper.innerHTML = html;

      // Initialize any modal triggers in the loaded content
      wrapper
        .querySelectorAll('[data-bs-toggle="modal"]')
        .forEach((trigger) => {
          trigger.addEventListener("click", (e) => {
            const targetModal = document.querySelector(
              trigger.getAttribute("data-bs-target")
            );
            if (targetModal) {
              bootstrap.Modal.getOrCreateInstance(targetModal).show();
            }
          });
        });
    })
    .catch((err) => {
      wrapper.innerHTML = `<div class="alert alert-danger">Failed to load content. Please try again.</div>`;
      console.error("Error loading Tab 3:", err);
    });
}

/**
 * Loads downtime details into the downtime modal
 * @param {string} recordHeaderId - The record header ID to load downtime for
 * @param {number} rowIndex - The row index
 */
function loadDowntimeContent(recordHeaderId, rowIndex) {
  const downtimeModalElement = document.getElementById("downtimeModal");
  const downtimeModal =
    bootstrap.Modal.getOrCreateInstance(downtimeModalElement);
  const wrapper = document.getElementById("downtimeModalContent");

  // Fetch downtime content
  fetch(
    `../controller/add-downtime.php?record_header_id=${recordHeaderId}&row=${rowIndex}`
  )
    .then((res) => {
      if (!res.ok) throw new Error("Network response was not ok");
      return res.text();
    })
    .then((html) => {
      wrapper.innerHTML = html;
      // Initialize downtime history after modal HTML is inserted
      if (typeof initDowntimeHistory === "function") {
        initDowntimeHistory(recordHeaderId);
      }
      downtimeModal.show();
      setupTimeInputListeners(recordHeaderId);
      setupCustomDropdowns(recordHeaderId);
    })
    .catch((err) => {
      wrapper.innerHTML = `
        <div class="modal-header">
          <h5 class="modal-title text-danger">Error</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger">Failed to load downtime details. Please try again.</div>
        </div>
      `;
      downtimeModal.show();
      console.error("Error loading downtime:", err);
    });
}
/**
 * Displays a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, warning, danger, etc.)
 */
function showToast(message, type = "success") {
  const toastContainer = document.getElementById("toast-container");
  if (!toastContainer) return;

  const toast = document.createElement("div");

  // Set toast styling based on type
  toast.className = `toast show align-items-center text-white bg-${type}`;
  toast.style.width = "300px";
  toast.style.marginBottom = "10px";
  toast.setAttribute("role", "alert");

  // Toast HTML structure
  toast.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  `;

  // Add to DOM
  toastContainer.appendChild(toast);

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 500);
  }, 5000);
}

/**
 * Loads the tablet list via AJAX
 */
function loadTabletList() {
  fetch("../ajax/dor-load-tablet.php")
    .then((response) => response.text())
    .then((html) => {
      const tabletListElement = document.getElementById("tablet-list");
      if (tabletListElement) {
        tabletListElement.innerHTML = html;

        // Re-apply current search filter after reload
        const searchInput = document.getElementById("tabletSearch");
        if (searchInput && searchInput.value.trim()) {
          filterTablets();
        }
      }
    })
    .catch((err) => console.error("Failed to load tablets:", err));
}

// Variable to track last known tablet state
let lastTabletHash = null;

/**
 * Checks for changes in tablet status and refreshes if needed
 */
function checkTabletChanges() {
  fetch("../ajax/dor-tablet-status-check.php")
    .then((response) => response.text())
    .then((currentHash) => {
      if (lastTabletHash === null || currentHash !== lastTabletHash) {
        lastTabletHash = currentHash;
        loadTabletList();
      }
    })
    .catch((err) => console.error("Status check failed:", err));
}

/**
 * Handles application exit with confirmation
 * @param {Event} event - The click event
 */
function exitApplication(event) {
  event.preventDefault();
  if (confirm("Are you sure you want to exit the application?")) {
    fetch("../controller/dor-leader-logout.php?exit=1").then(() => {
      try {
        // Try different methods to close the app based on platform
        if (window.AndroidApp?.exitApp) window.AndroidApp.exitApp();
        else if (window.Android?.exitApp) window.Android.exitApp();
        else window.close();
      } catch {
        alert("Please close this application manually.");
      }
    });
  }
}

// Event delegation for Tab3 modal buttons
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".open-tab3-modal");
  if (btn) {
    e.stopPropagation();
    openTab3Modal(btn.dataset.hostnameId, btn.dataset.recordId);
  }
});

// Downtime Modal Utility Functions
function setupTimeInputListeners(recordHeaderId) {
  const timeStart = document.getElementById(`timeStart${recordHeaderId}`);
  const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`);

  [timeStart, timeEnd].forEach((input) => {
    if (!input) return;

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
  const timeStart = document.getElementById(
    `timeStart${recordHeaderId}`
  )?.value;
  const timeEnd = document.getElementById(`timeEnd${recordHeaderId}`)?.value;
  const durationSpan = document.getElementById(`duration${recordHeaderId}`);

  if (!durationSpan) return;

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
  if (!start || !end) return "00:00";

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

// Save downtime event handler
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

    //fetch main box times from server
    const headerRes = await fetch("../controller/dor-downtime.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        type: "getHeaderTimes",
        recordHeaderId: recordHeaderId,
      }),
    });

    let headerData;
    try {
      headerData = await headerRes.json();
    } catch (jsonError) {
      const text = await headerRes.text();
      showToast(`Server error: ${text}`, "danger");
      return;
    }

    if (!headerData.success) {
      throw new Error("Could not fetch main box time range.");
    }

    const mainTimeStart = headerData.timeStart;
    const mainTimeEnd = headerData.timeEnd;

    //validate downtime timers are within main box times
    function toMinutes(t) {
      const [h, m] = t.split(":").map(Number);
      return h * 60 + m;
    }

    const tStart = toMinutes(timeStart);
    const tEnd = toMinutes(timeEnd);
    const mStart = toMinutes(mainTimeStart);
    const mEnd = toMinutes(mainTimeEnd);

    function isWithinRange(start, end, rangeStart, rangeEnd) {
      if (rangeEnd < rangeStart) rangeEnd += 24 * 60;
      if (end < start) end += 24 * 60;
      return start >= rangeStart && end <= rangeEnd;
    }

    if (!isWithinRange(tStart, tEnd, mStart, mEnd)) {
      throw new Error("Downtime must be within the box range.");
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

    let result;
    try {
      result = await response.json();
    } catch (jsonError) {
      const text = await response.text();
      showToast(`Server error: ${text}`, "danger");
      return;
    }

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

// Event delegation for downtime save button
document.addEventListener("DOMContentLoaded", function () {
  document.body.addEventListener("click", handleSaveDowntime);
});

// Global function to refresh tablets (can be called from outside)
window.refreshTabletList = function () {
  loadTabletList();
};

// Global function to show toast (can be called from outside)
window.showToast = showToast;

// Export functions for external use if needed
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    showToast,
    loadTabletList,
    openTab3Modal,
    loadDowntimeContent,
  };
}
