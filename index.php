<?php
session_start();

// Simple credentials (hard-coded example)
$USERNAME = getenv('CARDLISTER_USERNAME'); // Read the key from your environment
$PASSWORD = getenv('CARDLISTER_PASSWORD'); // Read the key from your environment

// If the user submitted the login form:
if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === $USERNAME && $_POST['password'] === $PASSWORD) {
        $_SESSION['logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}

// Check if user is already logged in
if (empty($_SESSION['logged_in'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CardLister Login</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      background: #f5f7fa;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      color: #333;
    }

    .login-container {
      max-width: 400px;
      margin: 100px auto;
      padding: 20px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    h1 {
      margin-bottom: 20px;
    }

    .error {
      color: red;
      margin-bottom: 10px;
    }

    label {
      display: block;
      text-align: left;
      margin: 10px 0 5px;
      font-weight: 600;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 10px;
      font-size: 14px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 5px;
      outline: none;
      transition: border-color 0.3s;
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      border-color: #0078D7;
    }

    button {
      display: inline-block;
      padding: 12px 20px;
      font-size: 16px;
      background: #0078D7;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
    }

    button:hover {
      background: #005cbf;
    }

    button:active {
      transform: scale(0.98);
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h1>CardLister Login</h1>
    <?php if (!empty($error)): ?>
      <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <label for="username">Username:</label>
      <input type="text" name="username" id="username" autocomplete="off">

      <label for="password">Password:</label>
      <input type="password" name="password" id="password">

      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
<?php
  exit;
endif;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Lister - Create Listings</title>
    <link rel="stylesheet" href="css/styles.css">
    <script type="module" src="js/main.js"></script>
</head>
<body>
    <header>
        <h1>Card Lister</h1>
        <button id="connect-ebay">Connect eBay Account</button>

        <script>
          document.getElementById('connect-ebay').addEventListener('click', function () {
            // Replace these with your actual credentials and scopes:
            const clientId = "DanielFr-TestStor-PRD-cdec421c8-8f28c701";
            const redirectUri = encodeURIComponent('https://cardlister.lol/ebay_oauth_success.php');
            const scopes = encodeURIComponent(
              'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.marketing https://api.ebay.com/oauth/api_scope/sell.inventory.readonly'
            );

            const authUrl = `https://auth.ebay.com/oauth2/authorize?client_id=${clientId}&response_type=code&redirect_uri=${redirectUri}&scope=${scopes}`;
            window.location.href = authUrl;
          });
        </script>

        <button id="fetchEbay">🔄 Load eBay Listings</button>
        <label for="currencySelect">Currency:</label>
        <select id="currencySelect">
            <option value="USD">USD</option>
            <option value="CAD">CAD</option>
            <option value="EUR">EUR</option>
        </select>
        <button id="massUpdate">⚡ Mass Update</button>
        <input type="number" id="massUpdatePercent" placeholder="± % of TCG Price">
        <button id="createListingBtn">➕ Create Listing</button>
    </header>

    <main>
        <div id="listingsView" class="view">
            <div class="list-container">
                <ul id="listingList"></ul>
            </div>
        </div>

        <div id="createListingView" class="view hidden">
            <h2>Create New Listing</h2>
            <input type="text" id="cardSearchInput" placeholder="Search for a card...">
            <ul id="searchResults"></ul>

            <div id="selectedCardDetails" class="hidden">
                <h3>Selected Card</h3>
                <img id="selectedCardImage" src="" alt="">
                <p id="selectedCardName"></p>
                <span>
                  <label for="cardNumber">Card Number:</label>
                  <input type="text" id="cardNumber">
                </span>
                <span>
                  <label for="cardGrader">Professional Grader:</label>
                  <select id="cardGrader">
                      <option value="PSA">PSA</option>
                      <option value="BGS">BGS</option>
                      <option value="CGC">CGC</option>
                      <option value="Ungraded">Ungraded</option>
                  </select>
                </span>
                <span>
                  <label for="cardGrade">Grade:</label>
                  <input type="number" id="cardGrade" min="1" max="10" step="0.5" placeholder="Enter a grade">
                </span>
                <span>
                  <label for="cardCondition">Condition:</label>
                  <select id="cardCondition">
                      <option value="Near Mint">Near Mint</option>
                      <option value="Mint">Mint</option>
                      <option value="Light Play">Light Play</option>
                      <option value="Moderate Play">Moderate Play</option>
                      <option value="Heavy Play">Heavy Play</option>
                      <option value="Damaged">Damaged</option>
                  </select>
                </span>
                <span>
                  <label for="cardType">Type:</label>
                  <select id="cardType">
                      <option value="Normal">Normal</option>
                      <option value="Holo">Holo</option>
                      <option value="Reverse">Reverse</option>
                      <option value="Reverse Holo">Reverse Holo</option>
                  </select>
                </span>
                <span>
                  <label for="listingPrice">Price (CAD):</label>
                  <input type="number" id="listingPrice" step="0.01">
                </span>
                <button id="confirmListing">Confirm Listing</button>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Card Lister</p>
    </footer>
</body>
</html>

