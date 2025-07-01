
 document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs
            const tabPanes = document.querySelectorAll('#dorTabContent > .tab-pane');
            const tabInput = document.getElementById("currentTabInput");

            // Get initial tab index from URL or default to 0
            const urlParams = new URLSearchParams(window.location.search);
            let currentTabIndex = parseInt(urlParams.get('tab')) || 0;
            currentTabIndex = Math.max(0, Math.min(currentTabIndex, tabPanes.length - 1));

            function showTab(index) {
                // Safety check
                if (!tabPanes || !tabPanes.length) {
                    console.error("No tab panes found");
                    return;
                }

                // Validate index
                if (index < 0 || index >= tabPanes.length) {
                    console.error("Invalid tab index:", index);
                    return;
                }

                // Hide all tabs
                tabPanes.forEach(tab => {
                    if (tab && tab.classList) { // Double-check element exists
                        tab.classList.remove('show', 'active');
                    }
                });

                // Show selected tab
                if (tabPanes[index] && tabPanes[index].classList) {
                    tabPanes[index].classList.add('show', 'active');
                }

                // Update URL
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('tab', index);
                window.history.replaceState({}, '', newUrl);

                // Update next button text
                const btnNext = document.getElementById('btnNext');
                if (btnNext) {
                    btnNext.textContent = (index === tabPanes.length - 1) ? 'Submit' : 'Next';
                }
            }

            // Initialize with first tab
            showTab(currentTabIndex);

            // Make sure tab content is visible
            const tabContent = document.getElementById('dorTabContent');
            if (tabContent) {
                tabContent.style.display = 'block';
            }

            // Tab navigation handlers
            document.getElementById('btnNext')?.addEventListener('click', function(e) {
                e.preventDefault();
                if (currentTabIndex < tabPanes.length - 1) {
                    currentTabIndex++;
                    showTab(currentTabIndex);
                } else {
                    // Submit form or redirect
                    window.location.href = 'dor-leader-dashboard.php';
                }
            });

            document.getElementById('btnBack')?.addEventListener('click', function(e) {
                e.preventDefault();
                if (currentTabIndex > 0) {
                    currentTabIndex--;
                    showTab(currentTabIndex);
                }
            });


            showTab(currentTabIndex);
            document.getElementById('dorTabContent').style.display = 'block';
        });