<?php
require_once '../controller/dor-report.php';

$controller = new DorReportController();
$data = $controller->getAllDorReports();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All DOR Records</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
     <link href="../css/leader-dashboard.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9f9f9;
            color: #333;
            padding: 30px;
        }

        h1 {
            text-align: center;
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 40px;
        }

        .section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .section-header {
            background-color: #2980b9;
            color: #fff;
            padding: 15px 20px;
            cursor: pointer;
            user-select: none;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header:hover {
            background-color: #2471a3;
        }

        .section-content {
            display: none;
            padding: 0 20px 20px;
        }

        .table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 15px;
    border-radius: 8px;
    min-width: 100%;
}


        th, td {
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background-color: #3498db;
            color: white;
            font-weight: normal;
            font-size: 13px;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #eaf1f8;
        }

        button {
            display: block;
            margin: 40px auto;
            padding: 12px 25px;
            background-color: #27ae60;
            color: white;
            font-size: 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #219150;
        }

        .arrow {
            transition: transform 0.3s ease;
        }

        .arrow.rotate {
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }

            .section-header {
                font-size: 14px;
                padding: 12px;
            }
        }

        .pagination-nav {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 15px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination-nav button {
            padding: 6px 12px;
            background-color: #2980b9;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background-color 0.2s ease;
        }

        .pagination-nav button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .pagination-nav button:hover:not(:disabled) {
            background-color: #1c6ea4;
        }

        .page-info {
            font-size: 14px;
            color: #2c3e50;
        }
    </style>
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
                            <a class="nav-link active fs-5" href="../../leader/module/dor-leader-dashboard.php">DOR System</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fs-5" href="#" id="masterDataDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                Master Data
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../../leader/module/dor-model.php">
                                        <i class="bi bi-diagram-3"></i> Model
                                    </a></li>
                                <li><a class="dropdown-item" href="../../leader/module/dor-user.php">
                                        <i class="bi bi-person"></i> User
                                    </a></li>
                                <li><a class="dropdown-item" href="../../leader/module/dor-line.php">
                                        <i class="bi bi-tablet"></i> Line
                                    </a></li>
                                <li><a class="dropdown-item" href="../../leader/module/dor-tablet-management.php">
                                        <i class="bi bi-tablet"></i> Tablet
                                    </a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a href="#" id="reportDropdown" class="nav-link dropdown-toggle fs-5" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Reports
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="reportDropdown">
                                <li><a href="dor-report.php" class="dropdown-item">DOR</a></li>
                            </ul>
                        </li>

                    </ul>

                    <!-- Device Name Display -->
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <?php
                            // Get current tablet info
                            $currentTablet = isset($_SESSION['hostnameId']) ? $method->getCurrentTablet($_SESSION['hostnameId']) : null;
                            $tabletName = $currentTablet ? htmlspecialchars($currentTablet['Hostname']) : 'Tablet Name';
                            ?>
                            <a class="nav-link dropdown-toggle fw-bold" href="#" id="deviceDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-tablet"></i> <?= $tabletName ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php" onclick="exitApplication(event)">
                                        <i class="bi bi-box-arrow-right"></i> Exit Application
                                    </a>
                                </li>
                                <li><a class="dropdown-item text-danger fw-bold" href="../controller/dor-leader-logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Log Out
                                    </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
<h1>All DOR Records</h1>

<?php foreach ($data as $section => $rows): ?>
    <div class="section">
        <div class="section-header" onclick="toggleSection(this)">
            <span><?= htmlspecialchars($section) ?></span>
            <span class="arrow">&#9654;</span>
        </div>
        <div class="section-content">
            <?php if (empty($rows)): ?>
                <p>No data available.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                    <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td>
                                    <?php
                                    if ($val instanceof DateTime) {
                                        echo htmlspecialchars($val->format('Y-m-d H:i:s'));
                                    } else {
                                        echo htmlspecialchars((string)$val);
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<form method="get" action="<?= dirname($_SERVER['PHP_SELF']) ?>/../controller/dor_report_pdf.php">

    <button type="submit">Download All as PDF</button>
</form>

<script>
    function toggleSection(header) {
        const content = header.nextElementSibling;
        const arrow = header.querySelector('.arrow');
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
        arrow.classList.toggle('rotate');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const pageSize = 10;

        document.querySelectorAll('.section-content table').forEach(function (table) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const totalPages = Math.ceil(rows.length / pageSize);

            if (rows.length <= pageSize) return;

            let currentPage = 1;

            const nav = document.createElement('div');
            nav.className = 'pagination-nav';

            const prevBtn = document.createElement('button');
            prevBtn.textContent = '◀ Prev';

            const nextBtn = document.createElement('button');
            nextBtn.textContent = 'Next ▶';

            const pageInfo = document.createElement('span');
            pageInfo.className = 'page-info';

            nav.appendChild(prevBtn);
            nav.appendChild(pageInfo);
            nav.appendChild(nextBtn);
            table.parentElement.appendChild(nav);

            function showPage(page) {
                const start = (page - 1) * pageSize;
                const end = start + pageSize;

                rows.forEach((row, i) => {
                    row.style.display = (i >= start && i < end) ? '' : 'none';
                });

                currentPage = page;
                pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
                prevBtn.disabled = currentPage === 1;
                nextBtn.disabled = currentPage === totalPages;
            }

            prevBtn.addEventListener('click', () => {
                if (currentPage > 1) showPage(currentPage - 1);
            });

            nextBtn.addEventListener('click', () => {
                if (currentPage < totalPages) showPage(currentPage + 1);
            });

            showPage(1);
        });
    });
</script>

</body>
</html>
