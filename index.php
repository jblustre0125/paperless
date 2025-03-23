<?php require_once 'admin/authenticate.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/custom.css" />
</head>

<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header text-center">
                <h2>Sub-Assy DOR System</h2>
            </div>
            <div class="card-body">
                <form id="myForm" action="" method="POST">
                    <div class="mb-4">
                        <label for="productionCode" class="form-label">Production Code</label>
                        <input type="text" class="form-control form-control-lg" id="productionCode" name="txtProductionCode" required value="SA001">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" name="btnlogin">Login</button>
                    </div>
                </form>

                <?php if ($errorPrompt) : ?>
                    <div class="alert alert-danger mt-3" role="alert">
                        <?php echo $errorPrompt; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
require_once 'config/footer.php';
?>