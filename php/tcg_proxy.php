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
  // Find the best match
  $bestMatch = null;
  foreach ($cards as $card) {
    if (stripos($card['name'], $cardName) !== false) {
      $bestMatch = $card;
      break;
    }
  }
  if (!$bestMatch) {
    $bestMatch = $cards[0];
  }

  // Extract prices and image
  $prices = isset($bestMatch['tcgplayer']['prices']) ? $bestMatch['tcgplayer']['prices'] : [];
  $marketPrice = null;
  if (isset($prices['holofoil']['market'])) {
    $marketPrice = $prices['holofoil']['market'];
  } elseif (isset($prices['reverseHolofoil']['market'])) {
    $marketPrice = $prices['reverseHolofoil']['market'];
  } elseif (isset($prices['normal']['market'])) {
    $marketPrice = $prices['normal']['market'];
  }
  $imageUrl = null;
  if (isset($bestMatch['images']['large'])) {
    $imageUrl = $bestMatch['images']['large'];
  } elseif (isset($bestMatch['images']['small'])) {
    $imageUrl = $bestMatch['images']['small'];
  }

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
  return $decoded ? $decoded : null;
}

