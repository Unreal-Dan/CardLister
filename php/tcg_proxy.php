<?php
// php/tcg_proxy.php

header('Content-Type: application/json; charset=utf-8');

$apiKey = getenv('TCG_API_KEY'); // Read the key from your environment
$apiUrl = "https://api.pokemontcg.io/v2/cards";

$action      = isset($_GET['action'])   ? $_GET['action']   : '';
$cardName    = isset($_GET['cardName']) ? $_GET['cardName'] : '';
$query       = isset($_GET['query'])    ? $_GET['query']    : '';
$cardNumber  = isset($_GET['cardNumber']) ? $_GET['cardNumber'] : '';

if (!$apiKey) {
    echo json_encode(["error" => "Missing TCG_API_KEY in environment."]);
    exit;
}

if ($action === 'fetchPrice') {
    if (!$cardName) {
        echo json_encode(["price" => null, "image" => null]);
        exit;
    }

    $searchQuery = formatSearchQuery($cardName);

    $params = [
        'q' => $searchQuery,
        'pageSize' => 10,
        'orderBy' => '-set.releaseDate'
    ];

    $url = $apiUrl . '?' . http_build_query($params);
    $responseData = doApiRequest($url, $apiKey);

    if (!$responseData || empty($responseData['data'])) {
        echo json_encode(["price" => null, "image" => null]);
        exit;
    }

    $bestMatch = $responseData['data'][0];

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
elseif ($action === 'fetchCardById') {
    $cardId = isset($_GET['cardId']) ? $_GET['cardId'] : '';

    if (!$cardId) {
        echo json_encode(["error" => "Missing card ID"]);
        exit;
    }

    $url = $apiUrl . '/' . urlencode($cardId);

    $responseData = doApiRequest($url, $apiKey);

    if (!$responseData || empty($responseData['data'])) {
        echo json_encode(["error" => "Card not found", "cardId" => $cardId]);
        exit;
    }

    $cardData = $responseData['data'];

    echo json_encode([
        "id" => $cardData['id'],
        "name" => $cardData['name'],
        "set" => $cardData['set']['name'] ?? "Unknown",
        "rarity" => $cardData['rarity'] ?? "N/A",
        "tcgplayer" => [
            "url" => $cardData['tcgplayer']['url'] ?? "#",
            "prices" => $cardData['tcgplayer']['prices'] ?? []
        ],
        "images" => $cardData['images'] ?? []
    ]);
    exit;
}
elseif ($action === 'fetchCardByNumber') {
    if (!$cardNumber || !$setId) {
        echo json_encode(["error" => "Missing card number or set ID"]);
        exit;
    }

    $formattedQuery = 'set.id:"' . addslashes($setId) . '" AND number:"' . addslashes($cardNumber) . '"';

    $params = [
        'q' => $formattedQuery,
        'pageSize' => 1
    ];

    $url = $apiUrl . '?' . http_build_query($params);
    $responseData = doApiRequest($url, $apiKey);

    if (!$responseData || empty($responseData['data'])) {
        echo json_encode(["error" => "Card not found"]);
        exit;
    }

    $bestMatch = $responseData['data'][0];

    echo json_encode([
        "name" => $bestMatch['name'],
        "set" => $bestMatch['set']['name'] ?? "Unknown",
        "rarity" => $bestMatch['rarity'] ?? "N/A",
        "tcgplayer" => [
            "url" => $bestMatch['tcgplayer']['url'] ?? "#",
            "prices" => $bestMatch['tcgplayer']['prices'] ?? []
        ],
        "images" => $bestMatch['images'] ?? []
    ]);
    exit;
}
elseif ($action === 'searchCards') {
    if (!$query) {
        echo json_encode([]);
        exit;
    }

    $searchQuery = formatSearchQuery($query);

    $params = [
        'q' => $searchQuery,
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
 * Formats a search query for TCG API by filtering out stop words.
 */
function formatSearchQuery($fullTitle) {
    $stopWords = ['pokemon', 'tcg', 'holo', 'foil', 'rare', 'unlimited', 'first', 'edition', 'shadowless', 'promo', 'japanese', 'card', 'set', 'number'];
    $words = preg_split('/[\s\-\(\)\/]+/', strtolower($fullTitle));

    $filteredWords = array_diff($words, $stopWords);

    if (empty($filteredWords)) {
        return 'name:"' . addslashes($fullTitle) . '"';
    }

    $searchQuery = implode(' OR ', array_map(fn($word) => 'name:"' . addslashes($word) . '"', $filteredWords));

    return $searchQuery;
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

