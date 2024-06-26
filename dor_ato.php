<?php

$title = "Creating DOR";
ob_start(); // start output buffering

require "config/dbcon.php";
require "header.php";
require "config/method.php";

$msgPrompt = '';

?>

<form id="myForm" class="p-1" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
    <div class="container-fluid">

    </div>
</form>


<h2> <?= testInput($_SESSION['employeeName']); ?></h2>


<?php



?>

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