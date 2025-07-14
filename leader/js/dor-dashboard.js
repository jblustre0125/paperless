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

/**
 * DOM Ready Handler
 * Initializes all necessary functionality when the page loads
 */
document.addEventListener("DOMContentLoaded", function () {
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
  setInterval(checkTabletChanges, 5000);
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
 */
function loadDowntimeContent(recordHeaderId, rowIndex) {
  const loadingModalElement = document.getElementById("loadingModal");
  const loadingModal = bootstrap.Modal.getOrCreateInstance(loadingModalElement);

  const downtimeModalElement = document.getElementById("downtimeModal");
  const downtimeModal =
    bootstrap.Modal.getOrCreateInstance(downtimeModalElement);

  const wrapper = document.getElementById("downtimeModalContent");

  loadingModal.show();

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
      loadingModal.hide();
      downtimeModal.show();
    })
    .catch((err) => {
      // Show error state
      wrapper.innerHTML = `
                        <div class="modal-header">
                            <h5 class="modal-title text-danger">Error</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger">Failed to load downtime details. Please try again.</div>
                        </div>
                    `;
      loadingModal.hide();
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
                </div>`;

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
      document.getElementById("tablet-list").innerHTML = html;
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
