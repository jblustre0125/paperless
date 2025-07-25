<?php
require_once __DIR__ . "/header.php";

$db1 = new DbOp(1);

if (isset($_GET['logOut'])) {
    // Update GenHostname and GenLine logged in status to 0
    $updQry2 = "EXEC UpdGenHostname @HostnameId=?, @IsLoggedIn=?";
    $db1->execute($updQry2, [$_SESSION['hostnameId'], 0], 1);
    if (isset($_SESSION['lineId'])) {
        $updQryLine = "UPDATE GenLine SET IsLoggedIn = 0 WHERE LineId = ?";
        $db1->execute($updQryLine, [$_SESSION['lineId']], 1);
    }

    // Clear all session data
    session_destroy();

    // Send HTML response to close the window
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
            try {
                if (window.AndroidApp && AndroidApp.exitApp) {
                    AndroidApp.exitApp(); // This will call the Android code
                } else {
                    alert("Please close this window manually.");
                }
            } catch (e) {
                alert("Please close this window manually.");
            }
        </script>
    </body>
    </html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $title = $title ?? 'DOR System'; ?></title>
    <link rel="icon" type="image/png" href="../img/dor-1024.png">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/master.css" rel="stylesheet">
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

                <!-- Device Name Styled & Aligned -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-info fw-bold" href="#" id="deviceDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <?php
                            // Only use session hostname, never fallback to server/computer hostname
                            if (isset($_SESSION['hostname']) && !empty($_SESSION['hostname'])) {
                                echo testInput($_SESSION['hostname']);
                            } else {
                                echo 'Unregistered Device';
                            }
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item text-danger fw-bold text-center" href="#"
                                    onclick="exitApplication(event)">Exit Application</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content will be added here -->
    <div class="container-lg mt-4">
        <?php echo $content ?? ''; ?>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        function exitApplication(event) {
            event.preventDefault();

            // Show confirmation dialog
            if (confirm('Are you sure you want to exit the application?')) {
                // Update database logout status
                fetch('?logOut=1')
                    .then(response => {
                        // Try to exit the Android app
                        try {
                            if (window.AndroidApp && typeof window.AndroidApp.exitApp === 'function') {
                                window.AndroidApp.exitApp();
                            } else if (window.Android && typeof window.Android.exitApp === 'function') {
                                window.Android.exitApp();
                            } else {
                                // Fallback: close window or show manual close message
                                window.close();
                                if (!window.closed) {
                                    alert('Please close this application manually.');
                                }
                            }
                        } catch (e) {
                            console.error('Error exiting app:', e);
                            alert('Please close this application manually.');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating logout status:', error);
                        // Still try to exit the app even if database update fails
                        try {
                            if (window.AndroidApp && typeof window.AndroidApp.exitApp === 'function') {
                                window.AndroidApp.exitApp();
                            } else if (window.Android && typeof window.Android.exitApp === 'function') {
                                window.Android.exitApp();
                            } else {
                                window.close();
                                if (!window.closed) {
                                    alert('Please close this application manually.');
                                }
                            }
                        } catch (e) {
                            alert('Please close this application manually.');
                        }
                    });
            }
        }
    </script>
</body>

</html>

<?php require_once __DIR__ . '/footer.php'; ?>
