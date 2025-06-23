function confirmLogout(e) {
  e.preventDefault();
  if (confirm("Are you sure you want to exit the application?")) {
    const logoutLink = e.target.closest("a");
    const originalContent = logoutLink.innerHTML;
    logoutLink.innerHTML =
      '<span class="spinner-border spinner-border-sm"></span> Logging out...';
    logoutLink.classList.add("disabled");

    // Create a form to submit via POST
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "../controller/logout.php";
    document.body.appendChild(form);
    form.submit();
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("hostnameModal");
  let types = []; // Store types globally

  modal.addEventListener("show.bs.modal", async function (event) {
    const trigger = event.relatedTarget;
    if (!trigger) return;

    const hostname = trigger.getAttribute("data-hostname");
    let recordId = trigger.getAttribute("data-record-id");
    const modalTitle = modal.querySelector(".modal-title");

    // Handle new inspections
    if (recordId === "new") {
      try {
        const response = await fetch("../controller/dor-record.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ hostname }),
        });

        const result = await response.json();

        if (!result.success) {
          throw new Error(result.message);
        }

        recordId = result.record_id;
        trigger.setAttribute("data-record-id", recordId);
      } catch (error) {
        console.error("Error creating record:", error);
        alert("Failed to start new inspection: " + error.message);
        return;
      }
    }

    document.getElementById("globalRecordId").value = recordId;
    modalTitle.textContent = `Visual Inspection - ${hostname}`;

    loadInspectionData(recordId);
  });

  async function loadInspectionData(recordId) {
    try {
      // Show loading state
      document.getElementById("tabContent").innerHTML =
        '<div class="text-center py-4">Loading inspection data...</div>';

      const urls = [
        `../controller/dor-visual-checkpoints.php?record_id=${recordId}`,
        "../controller/dor-checkpoint-types.php",
      ];

      const responses = await Promise.all(
        urls.map((url) =>
          fetch(url).then((res) => {
            if (!res.ok) throw new Error(`Failed to load ${url}`);
            return res.json();
          })
        )
      );

      const [checkpoints, typesResponse] = responses;

      if (!checkpoints?.success || !typesResponse?.success) {
        throw new Error("Invalid data received from server");
      }

      // Store types globally for use in render function
      types = typesResponse.data;

      renderInspectionTabs(checkpoints.data, types, recordId);
    } catch (error) {
      console.error("Error loading inspection data:", error);
      document.getElementById(
        "tabContent"
      ).innerHTML = `<div class="alert alert-danger">
                                Error loading inspection data: ${error.message}
                            </div>`;
    }
  }

  function renderInspectionTabs(checkpoints, types, recordId) {
    const tabList = document.getElementById("modalTab");
    const tabContent = document.getElementById("tabContent");

    // Clear existing content
    tabList.innerHTML = "";
    tabContent.innerHTML = "";

    // Define the DOR types we need tabs for
    const dorTypes = [
      { id: "hatsumono", name: "Hatsumono" },
      { id: "nakamono", name: "Nakamono" },
      { id: "owarimono", name: "Owarimono" },
      { id: "dimension", name: "Dimension Check" }
    ];

    // Create tabs for each DOR type
    dorTypes.forEach((dorType, index) => {
      const tabId = `tab-${dorType.id}`;

      // Create tab button
      const tabBtn = document.createElement("li");
      tabBtn.className = "nav-item";
      tabBtn.role = "presentation";
      tabBtn.innerHTML = `
        <button class="nav-link ${index === 0 ? "active" : ""}" 
                id="${tabId}-tab"
                data-bs-toggle="tab"
                data-bs-target="#${tabId}"
                type="button"
                role="tab"
                aria-controls="${tabId}"
                aria-selected="${index === 0 ? "true" : "false"}">
            ${dorType.name}
        </button>`;
      tabList.appendChild(tabBtn);

      // Create tab content
      const tabPane = document.createElement("div");
      tabPane.className = `tab-pane fade ${index === 0 ? "show active" : ""}`;
      tabPane.id = tabId;
      tabPane.role = "tabpanel";
      tabPane.setAttribute("aria-labelledby", `${tabId}-tab`);

    // Create form for this tab
    const form = document.createElement("form");
      form.className = "dor-form";
      form.dataset.dorType = dorType.id;

    if (dorType.id === 'dimension') {
    // Dimension Check Tab
    const container = document.createElement("div");
    container.className = "table-responsive";
    
    const table = document.createElement("table");
    table.className = "table table-bordered align-middle text-center";
    
    // Create table header
    const thead = document.createElement("thead");
    thead.className = "table-light";
    thead.innerHTML = `
        <tr>
            <th rowspan="2">No.</th>
            <th colspan="3">Hatsumono</th>
            <th colspan="3">Nakamono</th>
            <th colspan="3">Owarimono</th>
        </tr>
        <tr>
            <th>1</th><th>2</th><th>3</th>
            <th>1</th><th>2</th><th>3</th>
            <th>1</th><th>2</th><th>3</th>
        </tr>
    `;
    
    // Create table body with 20 rows
    const tbody = document.createElement("tbody");
    for (let i = 1; i <= 20; i++) {
        const row = document.createElement("tr");
        let cells = `<td>${i}</td>`;
        
        // Add input cells for each section (Hatsumono, Nakamono, Owarimono)
        for (let section of ["hatsumono", "nakamono", "owarimono"]) {
            for (let j = 1; j <= 3; j++) {
                cells += `
                    <td>
                        <input type="number" 
                               class="form-control form-control-sm text-center" 
                               name="dimension_${section}_${i}_${j}"
                               step="0.01">
                    </td>
                `;
            }
        }
        
        row.innerHTML = cells;
        tbody.appendChild(row);
    }
    
    // Add Judge section with proper headers
    const judgeSection = document.createElement("tbody");
    judgeSection.innerHTML = `
        <tr>
            <td rowspan="10" class="fw-bold text-center">JUDGE</td>
        </tr>
        <tr>
            <td colspan="3" class="fw-bold">Hatsumono</td>
            <td colspan="3" class="fw-bold">Nakamono</td>
            <td colspan="3" class="fw-bold">Owarimono</td>
        </tr>
          <tr>
            <td>1</td><td>2</td><td>3</td>
            <td>1</td><td>2</td><td>3</td>
            <td>1</td><td>2</td><td>3</td>
        </tr>
        <tr>
            ${createJudgeCells('hatsumono', '1')}
            ${createJudgeCells('nakamono', '1')}
            ${createJudgeCells('owarimono', '1')}
        </tr>
        <tr>
            ${createJudgeCells('hatsumono', '2')}
            ${createJudgeCells('nakamono', '2')}
            ${createJudgeCells('owarimono', '2')}
        </tr>
        <tr>
            ${createJudgeCells('hatsumono', '3')}
            ${createJudgeCells('nakamono', '3')}
            ${createJudgeCells('owarimono', '3')}
        </tr>
    `;
    
    
    // Add Note section
    const noteSection = document.createElement("tbody");
    noteSection.innerHTML = `
        <tr>
            <td colspan="10" class="text-start small text-muted">
                NOTE: If hatsumono/owarimono #1 is NG, conduct 1 more sample and fill-in #2, if still NG, STOP, CALL, WAIT.
            </td>
        </tr>
    `;
    
    // Helper function to create judge cells
    function createJudgeCells(section, num) {
        return `
            <td>
                <div class="d-flex flex-column gap-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" 
                               name="dimension_judge_${section}_${num}" 
                               id="dimension_judge_${section}_${num}_ok" 
                               value="OK" required>
                        <label class="form-check-label" for="dimension_judge_${section}_${num}_ok">OK</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" 
                               name="dimension_judge_${section}_${num}" 
                               id="dimension_judge_${section}_${num}_na" 
                               value="NA">
                        <label class="form-check-label" for="dimension_judge_${section}_${num}_na">NA</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" 
                               name="dimension_judge_${section}_${num}" 
                               id="dimension_judge_${section}_${num}_ng" 
                               value="NG">
                        <label class="form-check-label" for="dimension_judge_${section}_${num}_ng">NG</label>
                    </div>
                </div>
            </td>
        `.repeat(3); // Repeat for each of the 3 columns
    }
    
    // Append all sections to the table
    table.appendChild(thead);
    table.appendChild(tbody);
    table.appendChild(judgeSection);
    table.appendChild(noteSection);
    container.appendChild(table);
    form.appendChild(container);
} else {
        // Standard inspection tabs (Hatsumono, Nakamono, Owarimono)
        const table = document.createElement("table");
        table.className = "table table-bordered";
        table.innerHTML = `
          <thead class="text-center">
            <tr>
              <th>Visual Checkpoint</th>
              <th colspan="2">Criteria</th>
              <th>${dorType.name}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td rowspan="3">1.Taping Condition</td>
              <td>Correct shifting & winding/NO peel off, flip out, loose tape</td>
              <td>Wrong shifting & winding/peel off/flip out/loose tape</td>
              <td rowspan="2" class="text-center align-middle"></td>
            </tr>
            <tr></tr>
            <tr>
              <td colspan="2" class="text-center">"Put <strong>WF</strong> WITH FOLDING and <strong>WOF</strong> WITHOUT FOLDING"</td>
              <td></td>
            </tr>
            <tr>
              <td>2.Connector lock condition</td>
              <td>Fully locked</td>
              <td>Halflocked/Unlock</td>
              <td class="text-center align-middle"></td>
            </tr>
          </tbody>
        `;

        const tbody = table.querySelector("tbody");

        // Adding radio buttons for Taping Condition
        const tapingInputCell = tbody.querySelector("tr:nth-child(1) td:last-child");
        tapingInputCell.innerHTML = `
          <div class="d-flex inline-flex gap-2 px-5">
            <div class="form-check">
              <input class="form-check-input" type="radio"
                name="taping_condition_${dorType.id}"
                id="taping_ok_${dorType.id}"
                value="OK" required>
              <label class="form-check-label" for="taping_ok_${dorType.id}">OK</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio"
                name="taping_condition_${dorType.id}"
                id="taping_ng_${dorType.id}"
                value="NG" required>
              <label class="form-check-label" for="taping_ng_${dorType.id}">NG</label>
            </div>
          </div>
        `;

        // Folding Type
        const foldingInputCell = tbody.querySelector("tr:nth-child(3) td:last-child");
        foldingInputCell.innerHTML = `
          <div class="d-flex inline-flex gap-2 px-5">
            <div class="form-check">
              <input class="form-check-input" type="radio"
                name="folding_type_${dorType.id}"
                id="with_folding_${dorType.id}"
                value="WF" required>
              <label class="form-check-label" for="with_folding_${dorType.id}">WF</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio"
                name="folding_type_${dorType.id}"
                id="without_folding_${dorType.id}"
                value="WOF" required>
              <label class="form-check-label" for="without_folding_${dorType.id}">WOF</label>
            </div>
          </div>
        `;

        // Connector Lock Condition
        const connectorInputCell = tbody.querySelector("tr:nth-child(4) td:last-child");
        connectorInputCell.innerHTML = `
          <div class="d-flex inline-flex gap-2 px-5">
            <div class="form-check">
              <input class="form-check-input" type="radio"
                name="connector_condition_${dorType.id}"
                id="connector_ok_${dorType.id}"
                value="OK" required>
              <label class="form-check-label" for="connector_ok_${dorType.id}">OK</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio"
                name="connector_condition_${dorType.id}"
                id="connector_ng_${dorType.id}"
                value="NG" required>
              <label class="form-check-label" for="connector_ng_${dorType.id}">NG</label>
            </div>
          </div>
        `;

        form.appendChild(table);
      }

      // Add submit button for all tabs
      const submitDiv = document.createElement("div");
      submitDiv.className = "text-end mt-3";
      submitDiv.innerHTML = `
        <input type="hidden" name="record_id" value="${recordId}">
        <input type="hidden" name="dor_type" value="${dorType.id}">
        <button type="submit" class="btn btn-success">
          Submit ${dorType.name}
        </button>
      `;
      form.appendChild(submitDiv);

      // Add form submission handler
      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML =
          '<span class="spinner-border spinner-border-sm"></span> Processing...';

        try {
          const formData = new FormData(this);

          // Validate all required fields
          let isValid = true;
          
          if (dorType.id === 'dimension') {
            // Validate dimension check form
            if (!this.querySelector('input[name="dimension_judge"]:checked')) {
              isValid = false;
              this.querySelector('input[name="dimension_judge"]').closest('td').classList.add('border-danger');
            }
          } else {
            // Validate standard inspection form
            const requiredRadios = [
              `taping_condition_${dorType.id}`,
              `folding_type_${dorType.id}`,
              `connector_condition_${dorType.id}`
            ];

            requiredRadios.forEach((name) => {
              if (!this.querySelector(`input[name="${name}"]:checked`)) {
                const input = this.querySelector(`input[name="${name}"]`);
                input.closest("td")?.classList.add("border-danger");
                isValid = false;
              }
            });
          }

          if (!isValid) {
            throw new Error(
              `Please complete all required fields in ${dorType.name} tab`
            );
          }

          const endpoint = dorType.id === 'dimension' 
            ? "../controller/submit-dimension.php"
            : "../controller/submit-inspection.php";

          const response = await fetch(endpoint, {
            method: "POST",
            body: formData,
          });

          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          const result = await response.json();

          if (!result.success) {
            throw new Error(result.message);
          }

          alert(`${dorType.name} submitted successfully!`);
        } catch (error) {
          console.error("Submission error:", error);
          alert(`Error: ${error.message}`);
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = `Submit ${dorType.name}`;
        }
      });

      tabPane.appendChild(form);
      tabContent.appendChild(tabPane);
    });

    // Initialize Bootstrap tabs
    const tabTriggers = [].slice.call(
      tabList.querySelectorAll('button[data-bs-toggle="tab"]')
    );
    tabTriggers.forEach((triggerEl) => {
      triggerEl.addEventListener("click", (event) => {
        event.preventDefault();
        const tab = new bootstrap.Tab(triggerEl);
        tab.show();
      });
    });
  }

  function renderCheckpointInput(checkpoint, type, dorType) {
    if (!type || !type.CheckpointControl) {
      console.warn("Missing input type for checkpoint:", checkpoint);
      return "N/A";
    }

    const inputName = `checkpoint_${checkpoint.CheckpointId}_${dorType}`;
    const currentValue = checkpoint[dorType] || "";

    switch (type.CheckpointControl.toLowerCase()) {
      case "radio":
        if (!type.CheckpointTypeName) {
          console.warn("Missing options for radio input:", checkpoint);
          return "N/A";
        }

        const options = type.CheckpointTypeName.split("_");
        return options
          .map(
            (option) => `
              <div class="form-check form-check-inline">
                <input type="radio" 
                  name="${inputName}" 
                  id="${inputName}_${option}" 
                  value="${option}" 
                  class="form-check-input" 
                  required
                  ${option === currentValue ? "checked" : ""}>
                <label class="form-check-label" for="${inputName}_${option}">
                  ${option}
                </label>
              </div>
            `
          )
          .join("");

      case "text":
        return `<input type="text" 
                name="${inputName}" 
                class="form-control form-control-sm" 
                value="${currentValue}"
                required>`;

      default:
        console.warn("Unknown input type:", type.CheckpointControl);
        return `<input type="text" 
                name="${inputName}" 
                class="form-control form-control-sm"
                value="${currentValue}">`;
    }
  }

  // Helper function to create judge rows for each measurement column
function createJudgeRow(columnNum) {
    const row = document.createElement("tr");
    row.innerHTML = `
        <td class="fw-bold text-start">Judge ${columnNum}</td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_hatsumono_${columnNum}" 
                           id="dimension_judge_hatsumono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_hatsumono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_nakamono_${columnNum}" 
                           id="dimension_judge_nakamono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_nakamono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_ok" 
                           value="OK" required>
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_ok">OK</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_na" 
                           value="NA">
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_na">NA</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="dimension_judge_owarimono_${columnNum}" 
                           id="dimension_judge_owarimono_${columnNum}_ng" 
                           value="NG">
                    <label class="form-check-label" for="dimension_judge_owarimono_${columnNum}_ng">NG</label>
                </div>
            </div>
        </td>
    `;
    return row;
}
}); 