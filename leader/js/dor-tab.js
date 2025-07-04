document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize elements
    const tabPanes = document.querySelectorAll('#dorTabContent > .tab-pane');
    const form = document.querySelector('form');
    const btnNext = document.getElementById('btnNext');
    const btnBack = document.getElementById('btnBack');
    const tabContent = document.getElementById('dorTabContent');
    
    // 2. Get required parameters
    const urlParams = new URLSearchParams(window.location.search);
    const hostnameId = urlParams.get('hostname_id');
    const recordId = form.querySelector('[name="record_id"]')?.value;
    let currentTabIndex = parseInt(urlParams.get('tab')) || 0;
    currentTabIndex = Math.max(0, Math.min(currentTabIndex, tabPanes.length - 1));
    
    let isSaving = false;
    let isTab0Saved = false;

    // 3. Alert notification function
    function showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => alertDiv.remove(), 150);
        }, 5000);
        
        new bootstrap.Alert(alertDiv);
    }

    // 4. Table synchronization (keep your existing implementation)
    function syncTableHeaderWidthsForTab(tabElement) {
       
    }

    // 5. Tab navigation function
    function showTab(index) {
        if (index < 0 || index >= tabPanes.length) return;

        tabPanes.forEach(tab => tab.classList.remove('show', 'active'));
        tabPanes[index].classList.add('show', 'active');

        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', index);
        window.history.replaceState({}, '', newUrl);

        if (btnNext) {
            btnNext.textContent = (index === tabPanes.length - 1) ? 'Submit' : 'Next';
            btnNext.disabled = false;
        }
        if (btnBack) {
            btnBack.disabled = (index === 0);
        }

        setTimeout(() => syncTableHeaderWidthsForTab(tabPanes[index]), 100);
    }

    // 6. Convert tab-0 to read-only
    function makeTab0ReadOnly() {
        const tab0 = document.getElementById('tab-0');
        if (!tab0) return;
        
        tab0.querySelectorAll('input[type="radio"]').forEach(radio => {
            if (radio.checked) {
                const container = radio.closest('.form-check');
                if (container) {
                    const value = radio.value;
                    const badgeClass = value === 'OK' ? 'text-success fw-bold' : 
                                      value === 'NG' ? 'text-danger fw-bold' : 
                                      'text-secondary fw-bold';
                    container.innerHTML = `
                        <div class="text-center">
                            <span class="${badgeClass}">${value}</span>
                        </div>
                    `;
                }
            }
        });
        
        tab0.querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = true;
        });
        
        isTab0Saved = true;
    }

    // 7. Save only tab-0 data with alert notifications
    async function saveTab0Data() {
        if (!hostnameId || !recordId) {
            showAlert('System error: Missing required parameters', 'danger');
            return false;
        }

        isSaving = true;
        btnNext.disabled = true;
        const originalText = btnNext.textContent;
        btnNext.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        try {
            const formData = new FormData(form);
            formData.append('ajax_save_tab0', '1');
            formData.append('hostname_id', hostnameId);
            formData.append('record_id', recordId);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) throw new Error('Network error');
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Save failed');
            }
            
            makeTab0ReadOnly();
            showAlert('Leader responses saved successfully!');
            return true;
        } catch (error) {
            console.error('Save error:', error);
            showAlert('Error saving leader responses: ' + error.message, 'danger');
            return false;
        } finally {
            btnNext.disabled = false;
            btnNext.textContent = originalText;
            isSaving = false;
        }
    }

    // 8. Navigation handlers with alerts
    btnNext?.addEventListener('click', async function(e) {
        e.preventDefault();
        if (isSaving) return;
        
        // Special handling for tab-0
        if (currentTabIndex === 0 && !isTab0Saved) {
            const success = await saveTab0Data();
            if (success) {
                currentTabIndex++;
                showTab(currentTabIndex);
            }
        } 
        // For other tabs, just navigate
        else if (currentTabIndex < tabPanes.length - 1) {
            currentTabIndex++;
            showTab(currentTabIndex);
        } 
        // Final submission
        else {
            try {
                btnNext.disabled = true;
                btnNext.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
                
                if (isTab0Saved) {
                    const formData = new FormData(form);
                    formData.delete('leader');
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) throw new Error('Submission failed');
                    
                    showAlert('Form submitted successfully!');
                    setTimeout(() => {
                        window.location.href = 'dor-leader-dashboard.php';
                    }, 1500);
                } else {
                    form.submit();
                }
            } catch (error) {
                showAlert('Submission error: ' + error.message, 'danger');
                btnNext.disabled = false;
                btnNext.textContent = 'Submit';
            }
        }
    });

    btnBack?.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentTabIndex > 0) {
            currentTabIndex--;
            showTab(currentTabIndex);
        }
    });

    // 9. Initialize
    showTab(currentTabIndex);
    if (tabContent) tabContent.style.display = 'block';

    // 10. Window resize handler
    window.addEventListener('resize', () => {
        const activeTab = document.querySelector('.tab-pane.show.active');
        syncTableHeaderWidthsForTab(activeTab);
    });

    // 11. Check if tab-0 is already saved on page load
    if (document.getElementById('tab-0').classList.contains('read-only')) {
        isTab0Saved = true;
    }
});