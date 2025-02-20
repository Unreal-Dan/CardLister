<?php
// ebay_oauth_callback.php

// Enable error reporting during development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Your eBay app credentials—ideally loaded from a secure place (env variables, config file)
$appID  = getenv("EBAY_APP_ID");
$certID = getenv("EBAY_CERT_ID");
$redirectUri  = 'https://cardlister.lol/ebay_oauth_callback.php';

// Check if eBay returned an error
if (isset($_GET['error'])) {
    // Redirect to your auth rejected URL, passing the error details if desired
    $error = urlencode($_GET['error']);
    header("Location: https://cardlister.lol/auth_rejected.php?error=$error");
    exit;
}

// Check for the authorization code
if (!isset($_GET['code'])) {
    // No code provided; consider it a failure
    header("Location: https://cardlister.lol/auth_rejected.php?error=no_code");
    exit;
}

$authCode = $_GET['code'];

// Prepare the data to exchange the authorization code for an access token
$data = http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $authCode,
    'redirect_uri'  => $redirectUri,
]);

// Initialize cURL to call eBay’s token endpoint
$ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    // Include client credentials in Basic Auth header
    'Authorization: Basic ' . base64_encode($appID . ':' . $certID),
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    // If there's a cURL error, redirect to auth rejected page
    $curlError = urlencode(curl_error($ch));
    curl_close($ch);
    header("Location: https://cardlister.lol/auth_rejected.php?error=curl_error:$curlError");
    exit;
}
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($statusCode !== 200) {
    // Token request failed—redirect to auth rejected
    header("Location: https://cardlister.lol/auth_rejected.php?error=token_http_$statusCode");
    exit;
}

// Decode the token response
$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    header("Location: https://cardlister.lol/auth_rejected.php?error=no_token");
    exit;
}

// At this point, the authorization is successful.
// You can store the token data (access_token, refresh_token, etc.) in the session or your database.
$_SESSION['ebay_token'] = $tokenData;

// Redirect to your auth accepted page
header("Location: https://cardlister.lol/auth_accepted.php");
exit;

