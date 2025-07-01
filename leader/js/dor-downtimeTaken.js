
document.querySelectorAll('.operator-modal').forEach(modal => {
                const recordHeaderId = modal.dataset.recordId;
                const hiddenInput = document.getElementById(`operatorsHidden${recordHeaderId}`);
                const visibleInput = document.getElementById(`operators${recordHeaderId}`);
                const badgeList = document.getElementById(`operatorList${recordHeaderId}`);
                const modalBody = modal.querySelector('.current-operators');

                const updateDisplay = (codes) => {
                    badgeList.innerHTML = codes.map(code => {
                        const name = window.operatorMap[code] || 'Unknown';
                        return `<small class="badge bg-light text-dark border me-1 mb-1" title="${name}">${code}</small>`;
                    }).join('');
                };

                const syncCode = (code, action = 'add') => {
                    let codes = hiddenInput.value.split(',').map(c => c.trim()).filter(Boolean);
                    const codeSet = new Set(codes);

                    if (action === 'add') codeSet.add(code);
                    else if (action === 'remove') codeSet.delete(code);

                    const updated = [...codeSet];
                    hiddenInput.value = visibleInput.value = updated.join(',');
                    updateDisplay(updated);

                    if (action === 'add' && !modalBody.querySelector(`[data-code="${code}"]`)) {
                        const card = document.createElement('div');
                        card.className = 'card border-primary operator-card';
                        card.dataset.code = code;
                        card.innerHTML = `
                            <div class="card-body text-center p-2">
                                <h6 class="card-title mb-1">${window.operatorMap[code] || 'No operator'}</h6>
                                <small class="text-muted">${code}</small>
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-remove-operator">
                                    <i class="bi bi-x-circle"></i> Remove
                                </button>
                            </div>`;
                        modalBody.appendChild(card);
                    } else if (action === 'remove') {
                        modalBody.querySelector(`[data-code="${code}"]`)?.remove();
                    }
                };

                modal.addEventListener('click', e => {
                    const btn = e.target.closest('.btn-remove-operator');
                    if (btn) syncCode(btn.closest('.operator-card').dataset.code, 'remove');
                });

                modal.querySelector('.operator-search')?.addEventListener('input', function() {
                    const val = this.value.toLowerCase().trim();
                    const resultsBox = modal.querySelector('.search-results');
                    resultsBox.innerHTML = '';

                    if (!val) return;

                    let count = 0;
                    for (const [code, name] of Object.entries(window.operatorMap)) {
                        if (code.toLowerCase().includes(val) || name.toLowerCase().includes(val)) {
                            if (++count > 10) break;

                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-outline-primary btn-sm me-2 mb-2';
                            btn.textContent = `${name} (${code})`;
                            btn.addEventListener('click', () => {
                                syncCode(code, 'add');
                                this.value = '';
                                resultsBox.innerHTML = '';
                            });

                            resultsBox.appendChild(btn);
                        }
                    }

                    if (count === 0) {
                        resultsBox.innerHTML = '<small class="text-muted">No matches found.</small>';
                    }
                });
            });
            document.querySelectorAll('.btn-save-operators').forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.operator-modal');
                    const recordHeaderId = modal.dataset.recordId;
                    const operatorCards = modal.querySelectorAll('.operator-card');
                    const selectedCodes = [...new Set(Array.from(operatorCards).map(c => c.dataset.code))];

                    if (!selectedCodes.length) {
                        alert('No operator codes selected.');
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = 'Saving...';

                    fetch('../controller/dor-dor.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            type: 'saveOperators',
                            recordHeaderId,
                            employeeCodes: selectedCodes
                        })
                    }).then(res => res.json())
                    .then(json => {
                        btn.disabled = false;
                        btn.textContent = 'Save Changes';

                        if (json.success) {
                            alert('Operators saved.');

                             const bootstrapModal = bootstrap.Modal.getInstance(modal);
                                    bootstrapModal?.hide();
                        } else {
                            alert(json.message || 'Save failed.');
                        }
                    }).catch(err => {
                        console.error(err);
                        btn.disabled = false;
                        btn.textContent = 'Save Changes';
                        alert('Server error while saving.');
                    });
                });
            });

            // Downtime Modal
            document.querySelectorAll('.downtime-trigger').forEach(button => {
                button.addEventListener('click', function() {
                    const recordId = this.dataset.recordId;
                    const modalId = `downtimeModal${recordId}`;
                    const modal = document.getElementById(modalId);

                    if (!modal) {
                        console.error("Modal not found for RecordHeaderId:", recordId);
                        return;
                    }

                    const downtimeSelect = modal.querySelector('.downtime-select');
                    const actionSelect = modal.querySelector('.action-select');
                    const picInput = modal.querySelector('.pic-input');

                    // Attach handler for save button
                    const saveButton = modal.querySelector('.btn-save-downtime');
                    if (saveButton) {
                        saveButton.onclick = function() {
                            const downtimeId = downtimeSelect.value;
                            const actionTakenId = actionSelect.value;
                            const selectedPic = picInput?.value?.trim();

                            if (!recordId || !downtimeId || !actionTakenId || !selectedPic) {
                                alert("Please fill out all fields, including PIC.");
                                return;
                            }

                            fetch("../controller/dor-dor.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                },
                                body: JSON.stringify({
                                    type: "saveActionDowntime",
                                    recordHeaderId,
                                    downtimeId,
                                    actionTakenId,
                                    pic: selectedPic
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert("Downtime saved successfully!");

                                    const container = document.getElementById(`downtimeInfo${recordId}`);
                                    if (container) {
                                        const downtimeCode = window.downtimeMap?.[downtimeId]?.DowntimeCode || '???';
                                        const actionDesc = window.actionTakenMap?.[actionTakenId]?.ActionTakenName || 'Action info missing';

                                        const newBadge = document.createElement('small');
                                        newBadge.className = 'badge bg-danger text-white me-1 mb-1';
                                        newBadge.title = actionDesc;
                                        newBadge.textContent = downtimeCode;

                                        container.appendChild(newBadge);
                                    }

                                    const bootstrapModal = bootstrap.Modal.getInstance(modal);
                                    bootstrapModal?.hide();
                                } else {
                                    alert("Failed to save downtime: " + (data.message || "Unknown error."));
                                }
                            })
                            .catch(err => {
                                console.error("Fetch error:", err);
                                alert("An error occurred while saving downtime.");
                            });
                        };
                    }
                });
            });