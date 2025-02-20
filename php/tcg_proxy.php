<?php
// php/tcg_proxy.php

header('Content-Type: application/json; charset=utf-8');

$apiKey = getenv('TCG_API_KEY'); // Read the key from your environment
$apiUrl = "https://api.pokemontcg.io/v2/cards";

$action   = isset($_GET['action'])   ? $_GET['action']   : '';
$cardName = isset($_GET['cardName']) ? $_GET['cardName'] : '';
$query    = isset($_GET['query'])    ? $_GET['query']    : '';

if (!$apiKey) {
    echo json_encode(["error" => "Missing TCG_API_KEY in environment."]);
    exit;
}

if ($action === 'fetchPrice') {
    if (!$cardName) {
        echo json_encode(["price" => null, "image" => null]);
        exit;
    }
    
    // Ensure the query searches for full names in a flexible way
    $formattedQuery = 'name:"' . addslashes($cardName) . '"';
    
    $params = [
        'q' => $formattedQuery,
        'pageSize' => 10,
        'orderBy' => '-set.releaseDate'
    ];
    
    $url = $apiUrl . '?' . http_build_query($params);

    $responseData = doApiRequest($url, $apiKey);
    if (!$responseData || empty($responseData['data'])) {
        echo json_encode(["price" => null, "image" => null]);
        exit;
    }

    $cards = $responseData['data'];
    $bestMatch = $cards[0]; // Default to first match

    // Extract price & image
    $prices = $bestMatch['tcgplayer']['prices'] ?? [];
    $marketPrice = $prices['holofoil']['market'] ?? $prices['reverseHolofoil']['market'] ?? $prices['normal']['market'] ?? null;
    $imageUrl = $bestMatch['images']['large'] ?? $bestMatch['images']['small'] ?? null;

    echo json_encode([
        "price" => $marketPrice,
        "image" => $imageUrl
    ]);
    exit;
}
elseif ($action === 'searchCards') {
    if (!$query) {
        echo json_encode([]);
        exit;
    }
    
    // Properly format the query using wildcard for better matching
    $formattedQuery = 'name:"' . addslashes($query) . '" OR name:' . addslashes($query) . '*';
    
    $params = [
        'q' => $formattedQuery,
        'pageSize' => 50,
        'orderBy' => '-set.releaseDate'
    ];
    
    $url = $apiUrl . '?' . http_build_query($params);

    $responseData = doApiRequest($url, $apiKey);
    if (!$responseData || empty($responseData['data'])) {
        echo json_encode([]);
        exit;
    }

    echo json_encode($responseData['data']);
    exit;
}
else {
    echo json_encode(["error" => "Invalid or missing action."]);
    exit;
}

/**
 * Makes a GET request to the TCG API using cURL.
 */
function doApiRequest($url, $apiKey) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode !== 200) {
        return null;
    }

    return json_decode($response, true);
}

