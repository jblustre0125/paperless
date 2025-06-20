<?php
require_once __DIR__ . "/../../config/header.php";
require_once __DIR__ . "/../../config/dbop.php";
require_once "../controller/method.php";

ob_start();

$title = "Leader Dashboard";
$method = new Method(1);

// Get current user's tablet ID from session
    $currentTabletId = $_SESSION['hostnameId'] ?? null;

    $hostnames = $method->getOnlineTablets($currentTabletId);


// Handle logout request
if (isset($_GET['logOut'])) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear all session data
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Display logout confirmation
    displayLogoutPage();
    exit;
}

function displayLogoutPage() {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Closing Application</title>
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background-color: #f8f9fa;
                font-family: Arial, sans-serif;
            }
            .message {
                text-align: center;
                padding: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>Application Closed</h2>
            <p>Please close this window manually.</p>
        </div>
        <script>
            // Try to close the window
            if (window.opener) {
                window.opener.focus();
                window.close();
            } else {
                // For modern browsers
                window.location.href = "about:blank";
                setTimeout(function() {
                    window.close();
                }, 100);
                
                // Fallback message
                setTimeout(function() {
                    document.querySelector(".message p").textContent = "Please close this window manually.";
                }, 500);
            }
        </script>
    </body>
    </html>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$title ?></title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link href="../css/leader-dashboard.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
    <div class="container-lg">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active fs-5" href="dor-home.php">DOR System</a>
                </li>
            </ul>

            <!-- Device Name Display -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <?php
                    // Get current tablet info
                    $currentTablet = isset($_SESSION['hostnameId']) ? $method->getCurrentTablet($_SESSION['hostnameId']) : null;
                    $tabletName = $currentTablet ? htmlspecialchars($currentTablet['Hostname']) : 'Tablet Name';
                    $isActive = $currentTablet ? $currentTablet['IsActive'] : false;
                    $statusClass = $isActive ? 'text-success' : 'text-warning';
                    ?>
                    <a class="nav-link dropdown-toggle <?= $statusClass ?> fw-bold" href="#" id="deviceDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-tablet"></i> <?= $tabletName ?>
                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> ms-2">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item text-danger fw-bold" href="#" onclick="confirmLogout(event)">
                            <i class="bi bi-box-arrow-right"></i> Exit Application
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <div class="container mt-5">
       <!-- Current User Tablet (Displayed separately) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-tablet-fill fs-1 me-3"></i>
                            <div>
                                <h5 class="card-title mb-1">Your Tablet</h5>
                                <p class="card-text mb-0">
                                    <?php if(isset($_SESSION['is_leader']) || isset($_SESSION['is_sr_leader'])): ?>
                                        <span class="badge bg-light text-dark me-2">
                                            <?= $_SESSION['is_sr_leader'] ? 'SR Leader' : 'Leader' ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-success">
                                        <?= isset($currentTablet['Hostname']) ? htmlspecialchars($currentTablet['Hostname']) : 'Unknown Tablet' ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <!-- Other Online Tablets -->
            <h4 class="mb-3">Other Online Tablets</h4>
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
                <?php if(!empty($hostnames)): ?>
                    <?php foreach($hostnames as $row): ?>
                        <div class="col">
                            <div class="card text-center shadow-sm border-success">
                                <div class="card-body py-3 cursor-pointer"
                                     data-bs-toggle="modal" 
                                     data-bs-target="#hostnameModal"
                                     data-hostname="<?= htmlspecialchars($row['Hostname']) ?>"
                                     data-record-id="<?= $row['RecordId'] ?? 'new' ?>">
                                    <i class="bi bi-tablet text-success fs-3 mb-2"></i>
                                    <h6 class="card-title mb-1"><?= htmlspecialchars($row['Hostname']) ?></h6>
                                    <span class="badge bg-secondary">
                                        <?= $row['IsActive'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i> No other tablets are currently online
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- modal that handles the visual inspection checklist -->
        <div class="modal fade" id="hostnameModal" tabindex="-1" aria-labelledby="hostnameModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 80%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="hostnameModalLabel"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" id="hostnameModalBody">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-0" id="modalTab" role="tablist"></ul>

                         <!-- Tab content -->
                        <div class="tab-content p-3" id="tabContent"></div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" id="globalRecordId" name="record_id" value="1">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function confirmLogout(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to exit the application?')) {
                // Add a loading indicator
                const logoutLink = e.target.closest('a');
                logoutLink.innerHTML = '<i class="bi bi-box-arrow-right"></i> Exiting...';
                
                // Disable the link to prevent multiple clicks
                logoutLink.classList.add('disabled');
                
                // Perform the logout
                window.location.href = '?logOut=true';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('hostnameModal');
            let types = []; // Store types globally

            modal.addEventListener('show.bs.modal', async function(event) {
                const trigger = event.relatedTarget;
                if (!trigger) return;

                const hostname = trigger.getAttribute('data-hostname');
                let recordId = trigger.getAttribute('data-record-id');
                const modalTitle = modal.querySelector('.modal-title');
                
                // Handle new inspections
                if (recordId === 'new') {
                    try {
                        const response = await fetch('../controller/dor-record.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ hostname })
                        });
                        
                        const result = await response.json();
                        
                        if (!result.success) {
                            throw new Error(result.message);
                        }
                        
                        recordId = result.record_id;
                        trigger.setAttribute('data-record-id', recordId);
                    } catch (error) {
                        console.error('Error creating record:', error);
                        alert('Failed to start new inspection: ' + error.message);
                        return;
                    }
                }

                document.getElementById('globalRecordId').value = recordId;
                modalTitle.textContent = `Visual Inspection - ${hostname}`;
                
                loadInspectionData(recordId);
            });

            async function loadInspectionData(recordId) {
                try {
                    // Show loading state
                    document.getElementById('tabContent').innerHTML = 
                        '<div class="text-center py-4">Loading inspection data...</div>';

                    const urls = [
                        `../controller/dor-visual-checkpoints.php?record_id=${recordId}`,
                        '../controller/dor-checkpoint-types.php'
                    ];

                    const responses = await Promise.all(urls.map(url => 
                        fetch(url).then(res => {
                            if (!res.ok) throw new Error(`Failed to load ${url}`);
                            return res.json();
                        })
                    )
                    );

                    const [checkpoints, typesResponse] = responses;
                    
                    if (!checkpoints?.success || !typesResponse?.success) {
                        throw new Error('Invalid data received from server');
                    }

                    // Store types globally for use in render function
                    types = typesResponse.data;
                    
                    renderInspectionTabs(checkpoints.data, types, recordId);
                } catch (error) {
                    console.error('Error loading inspection data:', error);
                    document.getElementById('tabContent').innerHTML = 
                        `<div class="alert alert-danger">
                            Error loading inspection data: ${error.message}
                        </div>`;
                }
            }

            function renderInspectionTabs(checkpoints, types, recordId) {
            const tabList = document.getElementById('modalTab');
            const tabContent = document.getElementById('tabContent');

            // Clear existing content
            tabList.innerHTML = '';
            tabContent.innerHTML = '';

            // Define the DOR types we need tabs for
            const dorTypes = [
                {id: 'hatsumono', name: 'Hatsumono'},
                {id: 'nakamono', name: 'Nakamono'},
                {id: 'owarimono', name: 'Owarimono'}
            ];
            
            // Create tabs for each DOR type
            dorTypes.forEach((dorType, index) => {
                const tabId = `tab-${dorType.id}`;

                // Create tab button
                const tabBtn = document.createElement('li');
                tabBtn.className = 'nav-item';
                tabBtn.role = 'presentation';
                tabBtn.innerHTML = `
                    <button class="nav-link ${index === 0 ? 'active' : ''}" 
                            id="${tabId}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#${tabId}"
                            type="button"
                            role="tab"
                            aria-controls="${tabId}"
                            aria-selected="${index === 0 ? 'true' : 'false'}">
                        ${dorType.name}
                    </button>`;
                tabList.appendChild(tabBtn);

                // Create tab content
                const tabPane = document.createElement('div');
                tabPane.className = `tab-pane fade ${index === 0 ? 'show active' : ''}`;
                tabPane.id = tabId;
                tabPane.role = 'tabpanel';
                tabPane.setAttribute('aria-labelledby', `${tabId}-tab`);

                // Create form for this tab
                const form = document.createElement('form');
                form.className = 'dor-form';
                form.dataset.dorType = dorType.id;

                // Table with 3 columns
                const table = document.createElement('table');
                table.className = 'table table-bordered';
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
                            <td rowspan="2" class="text-center align-middle">
                            </td>
                        </tr>
                        <tr>

                        </tr>
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

                const tbody = table.querySelector('tbody');
                
                // Adding radio buttons for Taping Condition
                const tapingInputCell = tbody.querySelector('tr:nth-child(1) td:last-child');
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
                const foldingInputCell = tbody.querySelector('tr:nth-child(3) td:last-child');
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
                        </div>
                    `;
                // Add radio buttons for Connector Lock Condition
                const connectorInputCell = tbody.querySelector('tr:nth-child(4) td:last-child');
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

                // Add submit button for this tab
                const submitDiv = document.createElement('div');
                submitDiv.className = 'text-end mt-3';
                submitDiv.innerHTML = `
                    <input type="hidden" name="record_id" value="${recordId}">
                    <input type="hidden" name="dor_type" value="${dorType.id}">
                    <button type="submit" class="btn btn-success">
                        Submit ${dorType.name}
                    </button>
                `;

                form.appendChild(table);
                form.appendChild(submitDiv);
                tabPane.appendChild(form);
                tabContent.appendChild(tabPane);

                // Add submit handler for this form
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

                    try {
                        const formData = new FormData(this);
                        
                        // Validate all required fields
                        const requiredRadios = [
                            `taping_condition_${dorType.id}`,
                            `folding_type_${dorType.id}`,
                            `connector_condition_${dorType.id}`
                        ];
                        
                        let isValid = true;
                        
                        requiredRadios.forEach(name => {
                            if (!this.querySelector(`input[name="${name}"]:checked`)) {
                                const input = this.querySelector(`input[name="${name}"]`);
                                input.closest('td')?.classList.add('border-danger');
                                isValid = false;
                            }
                        });

                        if (!isValid) {
                            throw new Error(`Please complete all required fields in ${dorType.name} tab`);
                        }

                        const response = await fetch('../controller/submit-inspection.php', {
                            method: 'POST',
                            body: formData
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
                        console.error('Submission error:', error);
                        alert(`Error: ${error.message}`);
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.textContent = `Submit ${dorType.name}`;
                    }
                });
            });

            // Initialize Bootstrap tabs
            const tabTriggers = [].slice.call(tabList.querySelectorAll('button[data-bs-toggle="tab"]'));
            tabTriggers.forEach(triggerEl => {
                triggerEl.addEventListener('click', event => {
                    event.preventDefault();
                    const tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                });
            });
        }

            function renderCheckpointInput(checkpoint, type, dorType) {
                if (!type || !type.CheckpointControl) {
                    console.warn('Missing input type for checkpoint:', checkpoint);
                    return 'N/A';
                }

                const inputName = `checkpoint_${checkpoint.CheckpointId}_${dorType}`;
                const currentValue = checkpoint[dorType] || '';

                switch(type.CheckpointControl.toLowerCase()) {
                    case 'radio':
                        if (!type.CheckpointTypeName) {
                            console.warn('Missing options for radio input:', checkpoint);
                            return 'N/A';
                        }
                        
                        const options = type.CheckpointTypeName.split('_');
                        return options.map(option => `
                            <div class="form-check form-check-inline">
                                <input type="radio" 
                                       name="${inputName}" 
                                       id="${inputName}_${option}" 
                                       value="${option}" 
                                       class="form-check-input" 
                                       required
                                       ${option === currentValue ? 'checked' : ''}>
                                <label class="form-check-label" for="${inputName}_${option}">
                                    ${option}
                                </label>
                            </div>
                        `).join('');
                        
                    case 'text':
                        return `<input type="text" 
                                      name="${inputName}" 
                                      class="form-control form-control-sm" 
                                      value="${currentValue}"
                                      required>`;
                        
                    default:
                        console.warn('Unknown input type:', type.CheckpointControl);
                        return `<input type="text" 
                                      name="${inputName}" 
                                      class="form-control form-control-sm"
                                      value="${currentValue}">`;
                }
            }
        });
    </script> 
</body>
</html>