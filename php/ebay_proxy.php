<?php
// somephp/ebay_proxy.php
// Proxy script to call eBay Trading API from the server.
// Reads dev/app/cert IDs + user token from environment variables.
// Returns JSON { ack, items }.

header("Content-Type: application/json; charset=utf-8");

// Load credentials from environment
$devID     = getenv("EBAY_DEV_ID");
$appID     = getenv("EBAY_APP_ID");
$certID    = getenv("EBAY_CERT_ID");
$userToken = $SESSION['ebay_token'];

if (!$devID || !$appID || !$certID || !$userToken) {
    echo json_encode(["error" => "Missing one or more eBay credentials in environment"]);
    exit;
}

// Parse "action" to decide which call to make
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === "getSellerList") {
    getSellerList($devID, $appID, $certID, $userToken);
} else {
    echo json_encode(["error" => "Unknown or missing action"]);
    exit;
}

/**
 * Calls GetSellerList via cURL and returns JSON with ack & items
 */
function getSellerList($devID, $appID, $certID, $userToken) {
    $endpoint = "https://api.ebay.com/ws/api.dll";

    $xmlBody = '<?xml version="1.0" encoding="utf-8"?>
<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>' . $userToken . '</eBayAuthToken>
  </RequesterCredentials>
  <DetailLevel>ReturnAll</DetailLevel>
  <ErrorLanguage>en_US</ErrorLanguage>
  <WarningLevel>High</WarningLevel>
  <StartTimeFrom>2023-01-01T00:00:00.000Z</StartTimeFrom>
  <StartTimeTo>2025-12-31T23:59:59.000Z</StartTimeTo>
  <Pagination>
    <EntriesPerPage>10</EntriesPerPage>
    <PageNumber>1</PageNumber>
  </Pagination>
  <IncludeVariations>true</IncludeVariations>
</GetSellerListRequest>';

    // Set headers for Trading API
    $headers = [
        "X-EBAY-API-SITEID: 0",
        "X-EBAY-API-COMPATIBILITY-LEVEL: 967",
        "X-EBAY-API-CALL-NAME: GetSellerList",
        "X-EBAY-API-DEV-NAME: $devID",
        "X-EBAY-API-APP-NAME: $appID",
        "X-EBAY-API-CERT-NAME: $certID",
        "Content-Type: text/xml"
    ];

    // Make the request via cURL
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawResponse = curl_exec($ch);
    $err = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Handle errors
    if ($err) {
        echo json_encode(["error" => "cURL Error: $err"]);
        return;
    }
    if ($statusCode !== 200) {
        echo json_encode(["error" => "HTTP $statusCode from eBay"]);
        return;
    }

    // Parse the XML response in PHP, returning a simplified JSON structure
    $parsed = parseEbayXmlResponse($rawResponse);
    echo json_encode($parsed);
}

/**
 * Parse the eBay Trading API XML response and extract:
 *  {
 *    ack: "Success" or "Failure" or ...
 *    items: [
 *      { itemId: "...", title: "..." },
 *      ...
 *    ]
 *  }
 */
function parseEbayXmlResponse($xmlString) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) {
        return ["ack" => "XML Parse Error", "items" => []];
    }

    // Convert to JSON or find relevant fields
    $namespaces = $xml->getNamespaces(true);
    // Typically, "urn:ebay:apis:eBLBaseComponents"
    $ns = $namespaces[''] ?? '';

    $ack  = (string) $xml->xpath("//ns:Ack")[0];
    $itemNodes = $xml->xpath("//ns:Item") ?: [];

    $items = [];
    foreach ($itemNodes as $item) {
        $titleNode = $item->xpath("./ns:Title");
        $itemIdNode = $item->xpath("./ns:ItemID");
        $title = $titleNode ? (string)$titleNode[0] : "";
        $itemId = $itemIdNode ? (string)$itemIdNode[0] : "";
        $items[] = [
            "itemId" => $itemId,
            "title"  => $title
        ];
    }

    return [
        "ack" => $ack ?: "Unknown",
        "items" => $items
    ];
}

