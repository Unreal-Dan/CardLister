<?php
session_start();
if (!isset($_SESSION['ebay_token'])) {
    header("Location: ebay_oauth_failure.php?error=no_token_in_session");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Authorization Successful</title>
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
      h1 {
          color: #0078D7;
      }
  </style>
</head>
<body>
  <div class="container">
    <h1>Authorization Successful</h1>
    <p>Your eBay account is now connected.</p>
    <p><a href="index.php">Go to CardLister</a></p>
  </div>
</body>
</html>

