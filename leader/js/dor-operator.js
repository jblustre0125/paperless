document.addEventListener("DOMContentLoaded", function () {
  // === CONFIG ===
  const DEBUG = true;

  // === UTILITY FUNCTIONS ===
  function escapeHtml(unsafe) {
    return unsafe
      .toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function debugLog(...args) {
    if (DEBUG) console.log("[DOR DEBUG]", ...args);
  }

  // Create toast container if it doesn't exist
  if (!document.getElementById('toast-container')) {
    const toastContainer = document.createElement('div');
    toastContainer.id = 'toast-container';
    toastContainer.style.position = 'fixed';
    toastContainer.style.top = '20px';
    toastContainer.style.right = '20px';
    toastContainer.style.zIndex = '9999';
    document.body.appendChild(toastContainer);
  }

  function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    const toastId = 'toast-' + Date.now();
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast show align-items-center text-white bg-${type}`;
    toast.style.width = '300px';
    toast.style.marginBottom = '10px';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
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
        toastElement.classList.remove('show');
        setTimeout(() => toastElement.remove(), 500);
      }
    }, 5000);
    
    // Add click to dismiss
    toast.querySelector('.btn-close').addEventListener('click', function() {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 500);
    });
  }

  window.operatorMap = window.operatorMap || {};

  // === DATA LOADING ===
  function fetchOperatorAssignments() {
    return fetch("../controller/dor-dor.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ type: "syncOperators" }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          window.operatorMap = {
            ...window.operatorMap,
            ...(data.operatorMap || {}),
          };
          debugLog("Operator map loaded:", window.operatorMap);
          const ops = data.assignedOperators || {};
          loadSavedOperators(ops);
          return true;
        }
        throw new Error(data.message || "Failed to sync operators");
      })
      .catch((err) => {
        console.error("Sync error:", err);
        showToast("Error syncing operators: " + err.message, "danger");
        return false;
      });
  }

  function loadSavedOperators(serverOperators = {}) {
    const allRows = document.querySelectorAll("tr[data-row-id]");
    let saved =
      serverOperators || JSON.parse(localStorage.getItem("dorOperators")) || {};

    allRows.forEach((row) => {
      const recordId = row.dataset.rowId;
      if (!saved[recordId]) {
        const hidden = document.getElementById(`operatorsHidden${recordId}`);
        if (hidden?.value) saved[recordId] = hidden.value.split(",");
      }
    });

    allRows.forEach((row) => {
      const recordId = row.dataset.rowId;
      const codes = saved[recordId] || [];
      updateOperatorDisplay(recordId, codes);
      refreshModalContent(recordId, codes);
    });

    localStorage.setItem("dorOperators", JSON.stringify(saved));
  }

  // === OPERATOR DISPLAY ===
  function updateOperatorDisplay(recordId, codes) {
    const badgeList = document.getElementById(`operatorList${recordId}`);
    if (badgeList) {
      badgeList.innerHTML = "";
      codes.forEach((code) => {
        const badge = document.createElement("small");
        badge.className = "badge bg-light text-dark border me-1 mb-1";
        badge.title = window.operatorMap[code] || "Unknown";
        badge.textContent = code;
        badgeList.appendChild(badge);
      });
    }

    const hidden = document.getElementById(`operatorsHidden${recordId}`);
    const visible = document.getElementById(`operators${recordId}`);
    if (hidden && visible) hidden.value = visible.value = codes.join(",");

    const saved = JSON.parse(localStorage.getItem("dorOperators")) || {};
    saved[recordId] = codes;
    localStorage.setItem("dorOperators", JSON.stringify(saved));
  }

  function refreshModalContent(recordId, codes) {
    const modal = document.getElementById(`operatorModal${recordId}`);
    if (!modal) {
      debugLog(`Modal not found for recordId ${recordId}`);
      return;
    }

    const container = modal.querySelector(".current-operators");
    if (!container) {
      debugLog(`.current-operators not found in modal ${recordId}`);
      return;
    }

    container.innerHTML = "";
    codes.forEach((code) => container.appendChild(createOperatorCard(code)));
  }

  function createOperatorCard(code) {
    const div = document.createElement("div");
    div.className = "card border-primary operator-card";
    div.dataset.code = code;
    div.innerHTML = `
            <div class="card-body text-center p-2">
                <h6 class="card-title mb-1">${escapeHtml(
                  window.operatorMap[code] || "Unknown"
                )}</h6>
                <small class="text-muted">${escapeHtml(code)}</small>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-remove-operator">
                    <i class="bi bi-x-circle"></i> Remove
                </button>
            </div>`;
    return div;
  }

  // === EVENT HANDLERS ===
  function syncOperatorCode(recordId, code, action = "add") {
    const hidden = document.getElementById(`operatorsHidden${recordId}`);
    const modal = document.querySelector(
      `#operatorModal${recordId} .current-operators`
    );
    if (!hidden || !modal) return;

    let codes = hidden.value.split(",").filter(Boolean);
    const req = getModelRequirements(recordId);

    if (action === "add") {
      if (codes.length >= req.maxMP) {
        showToast(`Cannot add more than ${req.maxMP} operators`, "warning");
        return;
      }
      if (!codes.includes(code)) codes.push(code);
    } else {
      codes = codes.filter((c) => c !== code);
    }

    updateOperatorDisplay(recordId, codes);
    refreshModalContent(recordId, codes);
  }

  function saveOperators(modal) {
    const recordId = modal?.dataset.recordId;
    if (!recordId) {
      console.error("No recordId found on modal.");
      showToast("Missing record ID.", "danger");
      return;
    }

    const operatorCards = modal.querySelectorAll(".operator-card");
    const selectedCodes = Array.from(operatorCards)
      .map((card) => card.dataset.code)
      .filter(Boolean);

    const saveBtn = modal.querySelector(".btn-save-operators");
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

    debugLog("Saving operators for RecordHeaderId:", recordId, selectedCodes);

    fetch("../controller/dor-dor.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        type: "saveOperators",
        recordHeaderId: recordId,
        employeeCodes: selectedCodes,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          updateOperatorDisplay(recordId, selectedCodes);
          refreshModalContent(recordId, selectedCodes);

          const saved = JSON.parse(localStorage.getItem("dorOperators")) || {};
          saved[recordId] = selectedCodes;
          localStorage.setItem("dorOperators", JSON.stringify(saved));

          bootstrap.Modal.getInstance(modal)?.hide();
          showToast("Operators saved successfully!", "success");
        } else {
          console.error("Save failed:", data);
          throw new Error(data.message || "Save failed");
        }
      })
      .catch((err) => {
        console.error("Save error:", err);
        showToast(err.message || "Failed to save operators", "danger");
      })
      .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = "Save Changes";
      });
  }

  // === SEARCH ===
  function handleSearchInput(e) {
    const input = e.target;
    const val = input.value.toLowerCase();
    const modal = input.closest(".modal");
    const results = modal.querySelector(".search-results");
    const recordId = modal.dataset.recordId;

    if (!val || !results) return (results.innerHTML = "");

    if (!window.operatorMap || Object.keys(window.operatorMap).length === 0) {
      results.innerHTML = '<div class="text-danger p-2">Loading...</div>';
      fetchOperatorAssignments().then(() => handleSearchInput(e));
      return;
    }

    const matches = Object.entries(window.operatorMap)
      .filter(
        ([code, name]) =>
          code.toLowerCase().includes(val) || name.toLowerCase().includes(val)
      )
      .slice(0, 10);

    results.innerHTML = matches.length
      ? ""
      : '<div class="text-muted p-2">No matches found</div>';
    matches.forEach(([code, name]) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-light text-start w-100 mb-1";
      btn.dataset.code = code;
      btn.dataset.recordId = recordId;
      btn.innerHTML = `<strong>${escapeHtml(
        name
      )}</strong><small class="d-block text-muted">${escapeHtml(code)}</small>`;
      results.appendChild(btn);
    });
  }

  // === HELPERS ===
  function getModelRequirements(recordId) {
    const row = document.querySelector(`[data-row-id="${recordId}"]`);
    return {
      maxMP: parseInt(row?.dataset.mpRequirement || "2", 10),
      modelName: row?.dataset.modelName || "Unknown",
    };
  }

  // === EVENT SETUP ===
  function setup() {
    document.body.addEventListener("click", function (e) {
      if (e.target.closest(".btn-remove-operator")) {
        const card = e.target.closest(".operator-card");
        const recordId = card.closest(".modal").dataset.recordId;
        syncOperatorCode(recordId, card.dataset.code, "remove");
      }

      if (e.target.closest(".search-results button")) {
        const btn = e.target.closest("button");

        if (!btn || !btn.dataset.recordId || !btn.dataset.code) {
          console.warn("Search result button missing required data:", btn);
          return;
        }

        const modal = btn.closest(".modal");
        if (!modal) {
          console.warn("Modal not found for search result button:", btn);
          return;
        }

        const recordId = btn.dataset.recordId;
        const code = btn.dataset.code;

        syncOperatorCode(recordId, code, "add");

        const results = modal.querySelector(".search-results");
        const input = modal.querySelector(".operator-search");

        if (results) results.innerHTML = "";
        if (input) input.value = "";
      }

      if (e.target.closest(".btn-save-operators")) {
        saveOperators(e.target.closest(".modal"));
      }
    });

    document.querySelectorAll(".operator-search").forEach((input) => {
      input.addEventListener("input", handleSearchInput);
    });

    if (Object.keys(window.operatorMap).length === 0) {
      fetchOperatorAssignments().then(() => debugLog("Initialized"));
    } else {
      loadSavedOperators();
      debugLog("Initialized");
    }
  }

  setup();
});