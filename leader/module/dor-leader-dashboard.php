<?php
require_once __DIR__ . "/../../config/header.php";
require_once __DIR__ . "/../../config/dbop.php";
require_once "method.php";
ob_start();

$title = "Leader Dashboard";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$title ?></title>
    <!-- <link rel="stylesheet" href="../../css/bootstrap.min.css" /> -->
     <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="../../css/index.css" />

    <link href="../css/leader-dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row row-cols-4 row-cols-md-8 row-cols-xl-8 g-3">
            <?php if(!empty($hostnames)): ?>
                <?php foreach($hostnames as $row): ?>
                    <?php $borderClass = ($row['IsActive']) ? 'bg-success' : 'bg-secondary'?>
                <div class="col">
                    <div class="card text-center shadow-sm bg <?= $borderClass?> ">
                        <div class="card-body py-4 cursor-pointer" 
                        data-bs-toggle="modal"
                        data-bs-target="#hostnameModal"
                        data-hostname="<?= htmlspecialchars($row['Hostname'])?>"
                        data-status="<?= $row['IsActive']? 'Active' : 'Inactive'?>">
                            <h6 class="card-title mb-0 text-white">
                                <?= htmlspecialchars($row['Hostname'])?>
                            </h6>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- modal that handles the visual inspection checklist -->
        <div class="modal fade" id="hostnameModal" tabindex="-1" aria-labelledby="hostnameModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 70%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="hostnameModalLabel"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0" id="hostnameModalBody">
                        <!-- Tabs -->
                         <ul class="nav nav-tab mb-0" id="modalTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link  custom-button text-dark" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab">
                                    Hatsumono
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link  custom-button text-dark" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab">
                                    Nakamono
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link  custom-button text-dark" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button" role="tab">
                                    Owarinomo
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link  custom-button text-dark" id="tab4-tab" data-bs-toggle="tab" data-bs-target="#tab4" type="button" role="tab">
                                    Dimension Check
                                </button>
                            </li>
                         </ul>

                         <!-- Tab content -->
                          <div class="tab-content p-3">
                            <div class="tab-pane fade show active" id="tab1" role="tabpanel">
                                <div class="container">
                                    <span>Incharge: Leader</span>
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">Visual Checkpoint</th>
                                                <th colspan="3" class="text-center">Criteria</th>
                                                <th class="text-center">Hatsumono</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td rowspan="2" class="text-center">1. Taping Condition</td>
                                                <td colspan="2">
                                                    Correct shifting & winding/ NO peel off, flip out, loose tape
                                                </td>
                                                <td>Wrong shifting & winding/ peel off/flip out/loose tape</td>
                                                <td rowspan="1" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" rowspan="1">*PUT <strong>WF </strong> if WITH FOLDING and <strong>WOF </strong> if WITHOUT FOLDING</td>
                                                <td rowspan="1" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center">2. Connector Lock Condition</td>
                                                <td colspan="2">Fully locked</td>
                                                <td>Halflocked/Unlock</td>
                                                <td rowspan="2" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tab2" role="tabpanel">
                                <span>Incharge: Leader</span>
                                <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">Visual Checkpoint</th>
                                                <th colspan="3" class="text-center">Criteria</th>
                                                <th class="text-center">Nakamono</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td rowspan="2" class="text-center">1. Taping Condition</td>
                                                <td colspan="2">
                                                    Correct shifting & winding/ NO peel off, flip out, loose tape
                                                </td>
                                                <td>Wrong shifting & winding/ peel off/flip out/loose tape</td>
                                                <td rowspan="1" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_nakamono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_nakamono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_nakamono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" rowspan="1">*PUT <strong>WF </strong> if WITH FOLDING and <strong>WOF </strong> if WITHOUT FOLDING</td>
                                                <td rowspan="1" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center">2. Connector Lock Condition</td>
                                                <td colspan="2">Fully locked</td>
                                                <td>Halflocked/Unlock</td>
                                                <td rowspan="2" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                            </div>
                            <div class="tab-pane fade" id="tab3" role="tabpanel">
                                <span>Incharge: Leader</span>
                                <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">Visual Checkpoint</th>
                                                <th colspan="3" class="text-center">Criteria</th>
                                                <th class="text-center">Owarinomo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td rowspan="2" class="text-center">1. Taping Condition</td>
                                                <td colspan="2">
                                                    Correct shifting & winding/ NO peel off, flip out, loose tape
                                                </td>
                                                <td>Wrong shifting & winding/ peel off/flip out/loose tape</td>
                                                <td rowspan="1" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_nakamono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_nakamono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_nakamono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3">*PUT <strong>WF </strong> if WITH FOLDING and <strong>WOF </strong> if WITHOUT FOLDING</td>
                                                <td rowspan="1" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center">2. Connector Lock Condition</td>
                                                <td colspan="2">Fully locked</td>
                                                <td>Halflocked/Unlock</td>
                                                <td rowspan="2" class="text-center align-middle">
                                                    <div class="d-flex justify-content-center">
                                                        <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_good" value="OK">
                                                        <label for="taping_good" class="form-check-label">Good</label>
                                                    </div>

                                                    <div class="form-check me-3">
                                                        <input type="radio" name="taping_hatsumono" id="taping_na" value="NA">
                                                        <label for="taping_na" class="form-check-label">N/A</label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input type="radio" name="taping_hatsumono" id="taping_ng" value="NG">
                                                        <label for="taping_ng" class="form-check-label">NG</label>
                                                    </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                            </div>
                            <div class="tab-pane fade" id="tab4" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th rowspan="2">No.</th>
                                                <th colspan="3">Hatsumono</th>
                                                <th colspan="3">Nakamono</th>
                                                <th colspan="3">Owarinomo</th>
                                            </tr>
                                            <tr>
                                                <th>1</th><th>2</th><th>3</th>
                                                <th>1</th><th>2</th><th>3</th>
                                                <th>1</th><th>2</th><th>3</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($i = 1; $i <= 20; $i++): ?>
                                            <tr>
                                                <td><?php echo $i; ?></td>
                                                <?php for ($j = 1; $j <= 9; $j++): ?>
                                                <td>
                                                    <input type="number" name="dim_<?php echo $i . '_' . $j; ?>" class="form-control form-control-sm">
                                                </td>
                                                <?php endfor; ?>
                                            </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                          </div>
                    </div>
                    <div class="modal-footer">
                         <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const modal = document.getElementById('hostnameModal');

            modal.addEventListener('show.bs.modal', function (event){
                const trigger = event.relatedTarget;
                if (!trigger) return;
                const hostname = trigger.getAttribute('data-hostname') || 'N/A';    
                const status = trigger.getAttribute('data-status') || 'Unknown';

                const modalTitle = modal.querySelector('.modal-title');
                // const modalBody = modal.querySelector('#hostnameModalBody');

                modalTitle.textContent = `Details for  ${hostname}`;
                // modalBody.innerHTML = `
                // <p><strong>Hostname: </strong> ${hostname}</p>
                // <p><strong>Status: </strong> ${status}</p>`;
            });
        });
    </script>  
</body>
</html>