document.addEventListener('DOMContentLoaded', function () {
    const tabPanes = document.querySelectorAll('#dorTabContent > .tab-pane');
    const form = document.querySelector('form');
    const btnNext = document.getElementById('btnNext');
    const btnBack = document.getElementById('btnBack');
    const tabContent = document.getElementById('dorTabContent');

    if (!tabPanes.length || !form || !tabContent) {
        console.error('Required elements not found');
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const hostnameId = urlParams.get('hostname_id');
    const recordId = form.querySelector('[name="record_id"]')?.value;
    let currentTabIndex = parseInt(urlParams.get('tab')) || 0;
    currentTabIndex = Math.max(0, Math.min(currentTabIndex, tabPanes.length - 1));

    let isSaving = false;
    let isTab0Saved = false;

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

    function showTab(index) {
        index = parseInt(index);
        if (isNaN(index) || index < 0 || index >= tabPanes.length) {
            console.error('Invalid tab index:', index);
            return;
        }

        tabPanes.forEach((pane, i) => {
            pane.classList.toggle('show', i === index);
            pane.classList.toggle('active', i === index);
        });

        const newUrl = new URL(window.location);
        newUrl.searchParams.set('tab', index);
        window.history.replaceState({}, '', newUrl);

        if (btnNext) {
            btnNext.textContent = index === tabPanes.length - 1 ? 'Submit' : 'Next';
            btnNext.disabled = false;
        }
        if (btnBack) {
            btnBack.disabled = index === 0;
        }
    }

    function makeTab0ReadOnly() {
        const tab0 = document.getElementById('tab-0');
        if (!tab0) return;

        // Convert each radio group to badge display
        const radioGroups = {};

        tab0.querySelectorAll('input[type="radio"][name^="leader["]').forEach(radio => {
            const name = radio.name;
            if (!radioGroups[name]) {
                radioGroups[name] = [];
            }
            radioGroups[name].push(radio);
        });

        for (const [groupName, radios] of Object.entries(radioGroups)) {
            const container = radios[0].closest('.d-flex');
            if (!container) continue;

            const checkedRadio = radios.find(r => r.checked);
            if (!checkedRadio) continue;

            const value = checkedRadio.value;
            const badgeClass = value === 'OK' ? 'text-success fw-bold' :
                               value === 'NG' ? 'text-danger fw-bold' :
                               'text-secondary fw-bold';

            container.innerHTML = `
                <div class="text-center py-1">
                    <span class="${badgeClass}">${value}</span>
                </div>
            `;
        }

        // Disable all form elements in tab-0
        tab0.querySelectorAll('input, select, textarea, button').forEach(el => {
            el.disabled = true;
        });

        tab0.classList.add('read-only');
        isTab0Saved = true;
    }

    async function saveTab0Data() {
        if (!hostnameId || !recordId) {
            showToast('System error: Missing required parameters', 'danger');
            return false;
        }

        isSaving = true;
        if (btnNext) {
            btnNext.disabled = true;
            btnNext.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        }

        try {
            const leaderResponses = {};
            document.querySelectorAll('#tab-0 input[name^="leader["]:checked').forEach(input => {
                const match = input.name.match(/\[(\d+)]/);
                if (match && match[1]) {
                    leaderResponses[match[1]] = input.value;
                }
            });

            const formData = new FormData();
            formData.append('ajax_save', '1');
            formData.append('hostname_id', hostnameId);
            formData.append('record_id', recordId);
            formData.append('leader', JSON.stringify(leaderResponses));

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid server response format');
            }

            if (!result.success) {
                throw new Error(result.message || 'Save failed');
            }

            makeTab0ReadOnly();
            showToast('Leader responses saved successfully!');
            return true;
        } catch (error) {
            console.error('Save error:', error);
            showToast(`Error saving data: ${error.message}`, 'danger');
            return false;
        } finally {
            if (btnNext) {
                btnNext.disabled = false;
                btnNext.textContent = currentTabIndex === tabPanes.length - 1 ? 'Submit' : 'Next';
            }
            isSaving = false;
        }
    }

    btnNext?.addEventListener('click', async function (e) {
        e.preventDefault();
        if (isSaving) return;

        if (currentTabIndex === 0 && !isTab0Saved) {
            const success = await saveTab0Data();
            if (success) {
                currentTabIndex++;
                showTab(currentTabIndex);
            }
            return;
        }

        // Intermediate tabs (1 or 2): just go forward
        if (currentTabIndex < tabPanes.length - 1) {
            currentTabIndex++;
            showTab(currentTabIndex);
            return;
        }

        // Final submit (tab 3)
        if (currentTabIndex === tabPanes.length - 1) {
            try {
                btnNext.disabled = true;
                btnNext.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

                const formData = new FormData(form);
                formData.append('btnSubmit', '1');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (response.redirected) {
                    window.location.href = 'dor-leader-dashboard.php';
                    return;
                }

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response format');
                }

                if (!result.success) {
                    throw new Error(result.message || 'Submission failed');
                }

                showToast('Form submitted successfully!');
                setTimeout(() => {
                    window.location.href = 'dor-leader-dashboard.php';
                }, 1500);
            } catch (error) {
                console.error('Submit error:', error);
                showToast(`Error: ${error.message}`, 'danger');
                btnNext.disabled = false;
                btnNext.textContent = 'Submit';
            }
        }
    });

    btnBack?.addEventListener('click', function (e) {
        e.preventDefault();
        if (currentTabIndex > 0) {
            currentTabIndex--;
            showTab(currentTabIndex);
        }
    });

    showTab(currentTabIndex);
    tabContent.style.display = 'block';

    if (document.getElementById('tab-0')?.classList.contains('read-only')) {
        isTab0Saved = true;
        makeTab0ReadOnly();
    }
});
