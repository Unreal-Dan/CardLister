<?php
// ebay_oauth_success.php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Replace these with your actual eBay app credentials, ideally loaded securely (e.g., from environment variables)
$appID  = getenv("EBAY_APP_ID");
$certID = getenv("EBAY_CERT_ID");
$redirectUri  = 'https://cardlister.lol/ebay_oauth_success.php';

// Check for the authorization code
if (!isset($_GET['code'])) {
    header("Location: ebay_oauth_failure.php?error=no_code");
    exit;
}
$authCode = $_GET['code'];

// Prepare the token exchange request data
$data = http_build_query([
    'grant_type'   => 'authorization_code',
    'code'         => $authCode,
    'redirect_uri' => $redirectUri,
]);

$ch = curl_init('https://api.ebay.com/identity/v1/oauth2/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Authorization: Basic ' . base64_encode($appID . ':' . $certID),
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// If the token request failed, redirect to the rejection page
if ($statusCode !== 200) {
    header("Location: ebay_oauth_failure.php?error=token_error_http_$statusCode");
    exit;
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    header("Location: ebay_oauth_failure.php?error=no_token");
    exit;
}

// Save the token information (you might store it in a database or session)
$_SESSION['ebay_token'] = $tokenData;

// Redirect to your post-login page (or display a success message)
header("Location: auth_accepted.php");
exit;
?>

