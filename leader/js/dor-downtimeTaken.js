document.addEventListener('DOMContentLoaded', function() {
    // Initialize operator maps from PHP

    // UTILITY FUNCTIONS
    function escapeHtml(unsafe) {
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    function getModelRequirements(recordId) {
        const row = document.querySelector(`[data-row-id="${recordId}"]`);
        if (!row) return { maxMP: 2 }; // Default to 2MP if not found
        
        return {
            maxMP: parseInt(row.dataset.mpRequirement) || 2,
            modelName: row.dataset.modelName || 'Unknown'
        };
    }

    function showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }

    function updateOperatorDisplay(recordId, codes) {
        const requirements = getModelRequirements(recordId);
        
        const currentMP = codes.length;
        
        // Validate against model requirements
        if (currentMP > requirements.maxMP) {
            showAlert(`Model ${requirements.modelName} requires maximum ${requirements.maxMP} MP. Removing excess operators.`, 'warning');
            codes = codes.slice(0, requirements.maxMP);
        }

        // Save to localStorage
        const savedOperators = JSON.parse(localStorage.getItem('dorOperators')) || {};
        savedOperators[recordId] = codes;
        localStorage.setItem('dorOperators', JSON.stringify(savedOperators));

        // Update badge display with MP count
        const badgeList = document.getElementById(`operatorList${recordId}`);
        if (badgeList) {
            badgeList.innerHTML = codes.map(code => {
                const name = window.operatorMap?.[code] || 'Unknown';
                return `<small class="badge bg-light text-dark border me-1 mb-1" title="${escapeHtml(name)}">${escapeHtml(code)}</small>`;
            }).join('');
            
            // Add MP count badge
            const mpBadge = document.createElement('small');
            mpBadge.className = `badge ${currentMP === requirements.maxMP ? 'bg-success' : 'bg-warning text-dark'} ms-1`;
            mpBadge.textContent = `${currentMP}/${requirements.maxMP} MP`;
            badgeList.appendChild(mpBadge);
        }

        // Update hidden inputs
        const hiddenInput = document.getElementById(`operatorsHidden${recordId}`);
        const visibleInput = document.getElementById(`operators${recordId}`);
        if (hiddenInput && visibleInput) {
            hiddenInput.value = visibleInput.value = codes.join(',');
        }
    }

   function loadSavedOperators() {
    const savedOperators = JSON.parse(localStorage.getItem('dorOperators')) || {};
    const allRows = document.querySelectorAll('tr[data-row-id]');
    
    // Find first row with operators to use as template
    let firstRecordOperators = [];
    let foundFirstRow = false;

    // Check both localStorage and DOM for first row with operators
    allRows.forEach(row => {
        const recordId = row.dataset.rowId;
        if (!foundFirstRow && recordId) {
            // Check if we have saved operators for this row
            if (savedOperators[recordId] && savedOperators[recordId].length > 0) {
                firstRecordOperators = [...savedOperators[recordId]];
                foundFirstRow = true;
            }
            // Otherwise check the hidden input value
            else {
                const hiddenInput = document.getElementById(`operatorsHidden${recordId}`);
                if (hiddenInput && hiddenInput.value) {
                    const operators = hiddenInput.value.split(',').filter(Boolean);
                    if (operators.length > 0) {
                        firstRecordOperators = operators;
                        savedOperators[recordId] = operators; // Save to our storage
                        foundFirstRow = true;
                    }
                }
            }
        }
    });

    // Apply first record operators as placeholders for empty rows (up to 20 rows)
    allRows.forEach((row, index) => {
        const recordId = row.dataset.rowId;
        if (index < 20 && recordId) {
            // Initialize if no operators exist
            if (!savedOperators[recordId] || savedOperators[recordId].length === 0) {
                if (firstRecordOperators.length > 0) {
                    savedOperators[recordId] = [...firstRecordOperators];
                } else {
                    savedOperators[recordId] = []; // Initialize empty array
                }
            }
            
            // Update the display
            updateOperatorDisplay(recordId, savedOperators[recordId]);
            refreshModalContent(recordId, savedOperators[recordId]);
        }
    });

    // Save to localStorage
    localStorage.setItem('dorOperators', JSON.stringify(savedOperators));
}

    function refreshModalContent(recordId, codes) {
        const modal = document.getElementById(`operatorModal${recordId}`);
        if (!modal) return;
        
        const modalBody = modal.querySelector('.current-operators');
        if (!modalBody) return;
        
        // Clear existing cards
        modalBody.innerHTML = '';
        
        // Add current operator cards
        codes.forEach(code => {
            modalBody.appendChild(createOperatorCard(code));
        });
    }

    function createOperatorCard(code) {
        const card = document.createElement('div');
        card.className = 'card border-primary operator-card';
        card.dataset.code = code;
        card.innerHTML = `
            <div class="card-body text-center p-2">
                <h6 class="card-title mb-1">${escapeHtml(window.operatorMap?.[code] || 'No operator')}</h6>
                <small class="text-muted">${escapeHtml(code)}</small>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-remove-operator">
                    <i class="bi bi-x-circle"></i> Remove
                </button>
            </div>`;
        return card;
    }

   function syncOperatorCode(recordId, code, action = 'add') {
        const hiddenInput = document.getElementById(`operatorsHidden${recordId}`);
        const modalBody = document.querySelector(`#operatorModal${recordId} .current-operators`);
        if (!hiddenInput || !modalBody) return;

        let codes = hiddenInput.value.split(',').map(c => c.trim()).filter(Boolean);
        const requirements = getModelRequirements(recordId);
        
        // Check MP limit before adding
        if (action === 'add' && codes.length >= requirements.maxMP) {
            showAlert(`Cannot add more than ${requirements.maxMP} MP for this model`, 'warning');
            return;
        }

        const codeSet = new Set(codes);
        action === 'add' ? codeSet.add(code) : codeSet.delete(code);

        const updatedCodes = Array.from(codeSet);
        hiddenInput.value = updatedCodes.join(',');
        updateOperatorDisplay(recordId, updatedCodes);

        if (action === 'add' && !modalBody.querySelector(`[data-code="${code}"]`)) {
            modalBody.appendChild(createOperatorCard(code));
        } else if (action === 'remove') {
            modalBody.querySelector(`[data-code="${code}"]`)?.remove();
        }
    }
    function handleOperatorSearch(e) {
        const input = e.target;
        const val = input.value.trim();
        const modal = input.closest('.modal');
        const recordId = modal.dataset.recordId;
        const resultsBox = modal.querySelector('.search-results');

        resultsBox.innerHTML = '';
        if (!val || val.length < 1) return;

        const matches = Object.entries(window.operatorMap || {})
            .filter(([code, name]) => 
                code.toLowerCase().includes(val.toLowerCase()) || 
                String(name).toLowerCase().includes(val.toLowerCase())
            )
            .slice(0, 10);

        if (matches.length === 0) {
            resultsBox.innerHTML = '<div class="text-muted p-2">No matches found</div>';
            return;
        }

        matches.forEach(([code, name]) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-light text-start w-100 mb-1';
            btn.dataset.code = code;
            btn.innerHTML = `
                <strong>${escapeHtml(name)}</strong>
                <small class="text-muted d-block">${escapeHtml(code)}</small>
            `;
            resultsBox.appendChild(btn);
        });
    }

    function saveOperators(modal) {
        if (!modal) return;

        const recordId = modal.dataset.recordId;
        const operatorCards = modal.querySelectorAll('.operator-card');
        const selectedCodes = [...new Set(Array.from(operatorCards).map(c => c.dataset.code).filter(Boolean))];

        // Update UI immediately
        const btn = modal.querySelector('.btn-save-operators');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        // Save to localStorage first for instant feedback
        updateOperatorDisplay(recordId, selectedCodes);
        refreshModalContent(recordId, selectedCodes);

        // Then save to server
        fetch('../controller/dor-dor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'saveOperators',
                recordHeaderId: recordId,
                employeeCodes: selectedCodes
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reload data from server to ensure consistency
                loadSavedOperators();
                bootstrap.Modal.getInstance(modal)?.hide();
                showAlert('Operators saved successfully!', 'success');
            } else {
                throw new Error(data.message || 'Save failed');
            }
        })
        .catch(err => {
            console.error('Save error:', err);
            showAlert(err.message || 'Failed to save operators', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
        });
    }

    function saveDowntime(modal) {
        const recordId = modal.dataset.recordId;
        const downtimeId = modal.querySelector('.downtime-select')?.value;
        const actionTakenId = modal.querySelector('.action-select')?.value;
        const selectedPic = modal.querySelector('.pic-input')?.value.trim();

        if (!recordId || !downtimeId || !actionTakenId || !selectedPic) {
            alert("Please fill out all fields, including PIC.");
            return;
        }

        const downtimeKey = String(downtimeId);
        if (!window.downtimeMap || !window.downtimeMap[downtimeKey]) {
            alert("Invalid Downtime ID selected.");
            return;
        }

        fetch("../controller/dor-dor.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                type: "saveActionDowntime",
                recordHeaderId: recordId,
                downtimeId,
                actionTakenId,
                pic: selectedPic
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert("Downtime saved successfully!");
                const container = document.getElementById(`downtimeInfo${recordId}`);
                if (container) {
                    container.innerHTML = '';
                    const downtimeCode = window.downtimeMap?.[downtimeKey]?.DowntimeCode || '???';
                    const actionDesc = window.actionTakenMap?.[actionTakenId]?.ActionTakenName || 'No action info';

                    const badge = document.createElement('small');
                    badge.className = 'badge bg-danger text-white me-1 mb-1';
                    badge.title = actionDesc;
                    badge.textContent = downtimeCode;
                    container.appendChild(badge);
                }
                bootstrap.Modal.getInstance(modal)?.hide();
            } else {
                showAlert("Failed to save downtime: " + (data.message || "Unknown error."), 'danger');
            }
        })
        .catch(err => {
            console.error("Fetch error:", err);
            showAlert("An error occurred while saving downtime.", 'danger');
        });
    }

    // EVENT HANDLERS
    document.body.addEventListener('click', function(e) {
        // Handle operator removal
        const removeBtn = e.target.closest('.btn-remove-operator');
        if (removeBtn) {
            const card = removeBtn.closest('.operator-card');
            const recordId = card.closest('.modal').dataset.recordId;
            syncOperatorCode(recordId, card.dataset.code, 'remove');
            return;
        }

        // Handle operator selection from search results
        const operatorResult = e.target.closest('.search-results button');
        if (operatorResult) {
            const modal = operatorResult.closest('.modal');
            const recordId = modal.dataset.recordId;
            const code = operatorResult.dataset.code;
            if (recordId && code) {
                syncOperatorCode(recordId, code, 'add');
                modal.querySelector('.operator-search').value = '';
                modal.querySelector('.search-results').innerHTML = '';
            }
            return;
        }

        // Handle save operators button
        const saveOpBtn = e.target.closest('.btn-save-operators');
        if (saveOpBtn) {
            saveOperators(saveOpBtn.closest('.operator-modal'));
            return;
        }

        // Handle downtime trigger
        const downtimeTrigger = e.target.closest('.downtime-trigger');
        if (downtimeTrigger) {
            const recordId = downtimeTrigger.dataset.recordId;
            const modal = document.getElementById(`downtimeModal${recordId}`);
            const saveBtn = modal?.querySelector('.btn-save-downtime');
            if (modal && saveBtn && !saveBtn.dataset.bound) {
                saveBtn.dataset.bound = true;
                saveBtn.addEventListener('click', () => saveDowntime(modal));
            }
        }
    });

    document.body.addEventListener('input', function(e) {
        if (e.target.classList.contains('operator-search')) {
            handleOperatorSearch(e);
        }
    });

    // Initialize on page load
    loadSavedOperators();
});