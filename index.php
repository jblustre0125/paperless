<?php
require_once 'authenticate.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- display blank favicon - -->
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=">
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="text-center">SAATO Paperless DOR</h4>
                    </div>
                    <div class="card-body">
                        <form id="myForm" action="" method="POST">
                            <div class="mb-3">
                                <label for="productionCode" class="form-label form-lable-lg">Production Code</label>
                                <input type="text" class="form-control form-control-lg" id="productionCode" name="txtProductionCode" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary form-control-lg" name="btnlogin">Login</button>
                            </div>
                            <?php if ($loginError) : ?>
                                <div class="alert alert-danger mt-3" role="alert">
                                    <?php echo $loginError; ?>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');

            form.addEventListener('myForm', function(e) {
                e.preventDefault(); // stop form from submitting normally

                // use fetch API to submit the form data
                fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form)
                    })
                    .then(response => response.text())
                    .then(html => alert(html)) // display response
                    .catch(error => console.error('Error:', error));
            });
        });
    </script>
</body>

</html>

<?php
require_once 'config/footer.php';
?>