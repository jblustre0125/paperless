<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
require_once '../controller/dor-report.php';

use Dompdf\Dompdf;

// Fetch data
$controller = new DorReportController();
$data = $controller->getAllDorReports();

// Start output buffering
ob_start();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #000;
        }

        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 12px;
            margin-top: 25px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #333;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>

<h1>All DOR Reports</h1>

<?php foreach ($data as $section => $rows): ?>
    <h2><?= htmlspecialchars($section) ?></h2>

    <?php if (empty($rows)): ?>
        <p>No data available.</p>
    <?php else: ?>
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
                                <?= htmlspecialchars(
                                    $val instanceof DateTime ? $val->format('Y-m-d H:i:s') : (string)$val
                                ) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endforeach; ?>

</body>
</html>

<?php
$html = ob_get_clean();

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape'); // Wider layout for many columns
$dompdf->render();

// Stream PDF to browser with download prompt
$dompdf->stream("DOR_All_Records.pdf", ["Attachment" => true]);
