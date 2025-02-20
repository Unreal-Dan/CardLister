<?php
// php/tcg_proxy.php

header('Content-Type: application/json; charset=utf-8');

$apiKey = getenv('TCG_API_KEY'); // Read the key from your environment
$apiUrl = "https://api.pokemontcg.io/v2/cards";

$action   = isset($_GET['action'])   ? $_GET['action']   : '';
$cardName = isset($_GET['cardName']) ? trim($_GET['cardName']) : '';
$query    = isset($_GET['query'])    ? trim($_GET['query']) : '';

if (!$apiKey) {
  echo json_encode(["error" => "Missing TCG_API_KEY in environment."]);
  exit;
}

if ($action === 'fetchPrice') {
  if (!$cardName) {
    echo json_encode(["price" => null, "image" => null]);
    exit;
  }
  
  $params = [
    'q' => 'name:"' . $cardName . '"',
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
  
  // Improved best match logic
  $bestMatch = findBestMatch($cardName, $cards);
  
  if (!$bestMatch) {
    echo json_encode(["price" => null, "image" => null]);
    exit;
  }

  // Extract prices and image
  $prices = $bestMatch['tcgplayer']['prices'] ?? [];
  $marketPrice = null;

  if (!empty($prices)) {
    if (isset($prices['holofoil']['market'])) {
      $marketPrice = $prices['holofoil']['market'];
    } elseif (isset($prices['reverseHolofoil']['market'])) {
      $marketPrice = $prices['reverseHolofoil']['market'];
    } elseif (isset($prices['normal']['market'])) {
      $marketPrice = $prices['normal']['market'];
    }
  }

  $imageUrl = $bestMatch['images']['large'] ?? ($bestMatch['images']['small'] ?? null);

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

  $params = [
    'q' => 'name:' . $query . '*',
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
 * Make a GET request to the TCG API using cURL.
 *
 * @param string $url
 * @param string $apiKey
 * @return array|null
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
  $decoded = json_decode($response, true);
  return $decoded ?: null;
}

/**
 * Find the best matching card based on the provided name.
 * - Prioritizes exact matches first
 * - Then looks for substring matches
 * - If no match, returns the first result
 *
 * @param string $searchTerm
 * @param array $cards
 * @return array|null
 */
function findBestMatch($searchTerm, $cards) {
  $searchTerm = strtolower($searchTerm);
  $bestMatch = null;

  foreach ($cards as $card) {
    $cardName = strtolower($card['name']);
    if ($cardName === $searchTerm) {
      return $card; // Exact match
    }
    if (strpos($cardName, $searchTerm) !== false) {
      $bestMatch = $card; // Partial match (fallback)
    }
  }
  
  return $bestMatch ?? ($cards[0] ?? null);
}

