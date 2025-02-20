<?php
// ebay_oauth_failure.php

$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'Unknown error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>eBay Authorization Failed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            text-align: center;
            padding: 50px;
        }
        .container {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: inline-block;
        }
        h1 { color: #D70022; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Authorization Rejected</h1>
        <p>Your eBay account connection was not successful.</p>
        <p>Error: <?php echo $error; ?></p>
        <p><a href="index.php">Return to CardLister</a></p>
    </div>
</body>
</html>

