document.addEventListener("DOMContentLoaded", function () {
  const tabPanes = document.querySelectorAll("#dorTabContent > .tab-pane");
  const form = document.querySelector("form");
  const btnNext = document.getElementById("btnNext");
  const btnBack = document.getElementById("btnBack");
  const tabContent = document.getElementById("dorTabContent");

  if (!tabPanes.length || !form || !tabContent) {
    console.error("Required elements not found");
    return;
  }

  const urlParams = new URLSearchParams(window.location.search);
  const hostnameId = urlParams.get("hostname_id");
  const recordId = form.querySelector('[name="record_id"]')?.value;
  let currentTabIndex = parseInt(urlParams.get("tab")) || 0;
  currentTabIndex = Math.max(0, Math.min(currentTabIndex, tabPanes.length - 1));

  let isSaving = false;
  let isTab0Saved = false;

  function showToast(message, type = "success", options = {}) {
    const {
      confirm = false,
      onConfirm = null,
      onCancel = null,
      duration = 5000,
    } = options;

    const toastContainer = document.getElementById("toast-container");
    const toastId = "toast-" + Date.now();

    const toast = document.createElement("div");
    toast.id = toastId;
    toast.className = `toast show align-items-center ${
      type === "custom" ? "bg-white text-dark border" : `text-white bg-${type}`
    }`;
    toast.style.width = "320px";
    toast.style.marginBottom = "10px";
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");
    toast.setAttribute("aria-atomic", "true");

    // Toast content with or without confirmation buttons
    toast.innerHTML = confirm
      ? `
      <div class="d-flex flex-column p-2">
        <div class="toast-body">
          ${message}
        </div>
        <div class="d-flex justify-content-end gap-2 mt-2 px-2">
          <button type="button" class="btn btn-sm btn-danger" id="${toastId}-cancel">Cancel</button>
          <button type="button" class="btn btn-sm btn-success" id="${toastId}-confirm">Confirm</button>
        </div>
      </div>
    `
      : `
      <div class="d-flex">
        <div class="toast-body">
          ${message}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;

    toastContainer.appendChild(toast);

    // Attach handlers for confirm/cancel buttons if needed
    if (confirm) {
      document
        .getElementById(`${toastId}-confirm`)
        .addEventListener("click", () => {
          toast.remove();
          onConfirm?.();
        });

      document
        .getElementById(`${toastId}-cancel`)
        .addEventListener("click", () => {
          toast.remove();
          onCancel?.();
        });

      // Optional: extend timeout for confirmation dialogs
      setTimeout(() => {
        const el = document.getElementById(toastId);
        if (el) el.remove();
      }, Math.max(duration, 30000)); // 30s for confirm toast
    } else {
      // Auto-remove normal toast after duration
      setTimeout(() => {
        const el = document.getElementById(toastId);
        if (el) {
          el.classList.remove("show");
          setTimeout(() => el.remove(), 500);
        }
      }, duration);

      // Close button click
      toast.querySelector(".btn-close")?.addEventListener("click", () => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 500);
      });
    }
  }

  function showTab(index) {
    index = parseInt(index);
    if (isNaN(index) || index < 0 || index >= tabPanes.length) {
      console.error("Invalid tab index:", index);
      return;
    }

    tabPanes.forEach((pane, i) => {
      pane.classList.toggle("show", i === index);
      pane.classList.toggle("active", i === index);
    });

    const newUrl = new URL(window.location);
    newUrl.searchParams.set("tab", index);
    window.history.replaceState({}, "", newUrl);

    if (btnNext) {
      btnNext.textContent = index === tabPanes.length - 1 ? "Submit" : "Next";
      btnNext.disabled = false;
    }
    if (btnBack) {
      btnBack.disabled = index === 0;
    }
  }

  function makeTab0ReadOnly() {
    const tab0 = document.getElementById("tab-0");
    if (!tab0) return;

    // Convert each radio group to badge display
    const radioGroups = {};

    tab0
      .querySelectorAll('input[type="radio"][name^="leader["]')
      .forEach((radio) => {
        const name = radio.name;
        if (!radioGroups[name]) {
          radioGroups[name] = [];
        }
        radioGroups[name].push(radio);
      });

    for (const [groupName, radios] of Object.entries(radioGroups)) {
      const container = radios[0].closest(".d-flex");
      if (!container) continue;

      const checkedRadio = radios.find((r) => r.checked);
      if (!checkedRadio) continue;

      const value = checkedRadio.value;
      const badgeClass =
        value === "OK"
          ? "text-success fw-bold"
          : value === "NG"
          ? "text-danger fw-bold"
          : "text-secondary fw-bold";

      container.innerHTML = `
                <div class="text-center py-1">
                    <span class="${badgeClass}">${value}</span>
                </div>
            `;
    }

    // Disable all form elements in tab-0
    tab0.querySelectorAll("input, select, textarea, button").forEach((el) => {
      el.disabled = true;
    });

    tab0.classList.add("read-only");
    isTab0Saved = true;
  }

  async function saveTab0Data() {
    if (!hostnameId || !recordId) {
      showToast("System error: Missing required parameters", "danger");
      return false;
    }

    isSaving = true;
    if (btnNext) {
      btnNext.disabled = true;
      btnNext.innerHTML =
        '<span class="spinner-border spinner-border-sm"></span> Saving...';
    }

    try {
      const leaderResponses = {};
      document
        .querySelectorAll('#tab-0 input[name^="leader["]:checked')
        .forEach((input) => {
          const match = input.name.match(/\[(\d+)]/);
          if (match && match[1]) {
            leaderResponses[match[1]] = input.value;
          }
        });

      const formData = new FormData();
      formData.append("ajax_save", "1");
      formData.append("hostname_id", hostnameId);
      formData.append("record_id", recordId);
      formData.append("leader", JSON.stringify(leaderResponses));

      const response = await fetch(window.location.href, {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        credentials: "same-origin",
      });

      const text = await response.text();
      let result;
      try {
        result = JSON.parse(text);
      } catch (e) {
        throw new Error("Invalid server response format");
      }

      if (!result.success) {
        throw new Error(result.message || "Save failed");
      }

      makeTab0ReadOnly();
      showToast("Leader responses saved successfully!");
      return true;
    } catch (error) {
      console.error("Save error:", error);
      showToast(`Error saving data: ${error.message}`, "danger");
      return false;
    } finally {
      if (btnNext) {
        btnNext.disabled = false;
        btnNext.textContent =
          currentTabIndex === tabPanes.length - 1 ? "Submit" : "Next";
      }
      isSaving = false;
    }
  }

  function disableDimensionCheckInputs() {
    const tab2 = document.getElementById("tab-2");
    if (!tab2) return;
    tab2.querySelectorAll("input").forEach((input) => {
      input.disabled = true;
    });
  }

  function enableDiemsnionCheckInputs() {
    const tab2 = document.getElementById("tab-2");
    if (!tab2) return;
    tab2.querySelectorAll("input").forEach((input) => {
      input.disabled = false;
    });
  }

  btnNext?.addEventListener("click", async function (e) {
    e.preventDefault();
    if (isSaving) return;

    // TAB 0: Validation and Confirmation
    if (currentTabIndex === 0 && !isTab0Saved) {
      const radioGroups = {};
      document
        .querySelectorAll('#tab-0 input[type="radio"][name^="leader["]')
        .forEach((radio) => {
          const match = radio.name.match(/^leader\[(\d+)\]$/);
          if (match) {
            const id = match[1];
            if (!radioGroups[id]) radioGroups[id] = [];
            radioGroups[id].push(radio);
          }
        });

      for (const [id, group] of Object.entries(radioGroups)) {
        const isChecked = group.some((radio) => radio.checked);
        if (!isChecked) {
          showToast(
            `Checkpoint ${id}: Leader must answer all of the selections`,
            "danger"
          );
          return;
        }
      }

      // Show confirmation toast
      showToast("Are you sure you want to save your response?", "custom", {
        confirm: true,
        onConfirm: async () => {
          const success = await saveTab0Data();
          if (success) {
            currentTabIndex++;
            showTab(currentTabIndex);
          } else {
            showToast("Failed to save tab 0 data", "danger");
          }
        },
      });
      return;
    }

    // Intermediate tabs: Just go forward
    if (currentTabIndex < tabPanes.length - 1) {
      currentTabIndex++;
      showTab(currentTabIndex);
      return;
    }

    // Final Submit: Tab 3
    if (currentTabIndex === tabPanes.length - 1) {
      showToast("Are you sure you want to submit the form?", "custom", {
        confirm: true,
        onConfirm: async () => {
          try {
            btnNext.disabled = true;
            btnNext.innerHTML =
              '<span class="spinner-border spinner-border-sm"></span> Submitting...';

            const formData = new FormData(form);
            formData.append("btnSubmit", "1");

            const response = await fetch(window.location.href, {
              method: "POST",
              body: formData,
              headers: {
                "X-Requested-With": "XMLHttpRequest",
              },
            });

            // Show success toast
            showToast("Form submitted successfully!", "success");

            // Redirect after toast is visible
            setTimeout(() => {
              window.location.href = "dor-leader-dashboard.php";
            }, 1500);
          } catch (error) {
            console.error("Submit error:", error);
            showToast(`Error: ${error.message}`, "danger");

            // Still redirect even after error
            setTimeout(() => {
              window.location.href = "dor-leader-dashboard.php";
            }, 1500);
          }
        },
        onCancel: () => {
          console.log("Form submission canceled.");
        },
        duration: 10000,
      });
      return;
    }
  });

  btnBack?.addEventListener("click", function (e) {
    e.preventDefault();
    if (currentTabIndex > 0) {
      currentTabIndex--;
      showTab(currentTabIndex);
    }
  });

  showTab(currentTabIndex);
  tabContent.style.display = "block";

  const wiKey = `wiConfirmed_${recordId}`;

  if (currentTabIndex === 2 && !sessionStorage.getItem(wiKey)) {
    disableDimensionCheckInputs();

    showToast(
      "You must open the Work Instruction before entering data.",
      "custom",
      {
        confirm: true,
        onConfirm: () => {
          sessionStorage.setItem(wiKey, "1");
          enableDiemsnionCheckInputs();
          showToast(
            "Work Instruction confimed. You may now input data",
            "success"
          );
        },
        onCancel: () => {
          showToast(
            "Access to Dimension Check is restricted until Work Instruction is confirmed.",
            "danger"
          );
        },
      }
    );
  }

  if (document.getElementById("tab-0")?.classList.contains("read-only")) {
    isTab0Saved = true;
    makeTab0ReadOnly();
  }
});
