<?php
session_start();

// Define a simple, hardcoded username/password.
// If you'd rather keep them outside the doc root, you could.
$USERNAME = "cardadmin";
$PASSWORD = "SuperSecret123";

// If the user submitted the login form:
if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === $USERNAME && $_POST['password'] === $PASSWORD) {
        // Correct credentials
        $_SESSION['logged_in'] = true;
        header("Location: index.php"); // reload page
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}

// Check if user is already logged in
if (empty($_SESSION['logged_in'])) {
    // Show a basic login form and stop rendering the rest of the page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Login - CardLister</title>
    </head>
    <body>
    <h1>CardLister Login</h1>
    <?php if (!empty($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    <form method="post" action="">
        <label for="username">Username:</label><br>
        <input type="text" name="username" id="username"><br><br>

        <label for="password">Password:</label><br>
        <input type="password" name="password" id="password"><br><br>

        <button type="submit">Login</button>
    </form>
    </body>
    </html>
    <?php
    exit; // Stop processing further HTML/PHP below
}
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

