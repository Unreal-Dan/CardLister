<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Load credentials from environment
$devID     = getenv("EBAY_DEV_ID");
$appID     = getenv("EBAY_APP_ID");
$certID    = getenv("EBAY_CERT_ID");
$userToken = isset($_SESSION['ebay_token']) ? $_SESSION['ebay_token'] : null;

// If the token is stored as an array, convert it to a string.
if (is_array($userToken)) {
    $userToken = implode(',', $userToken);
}

if (!$userToken) {
    echo json_encode(["error" => "Missing user token, try connecting ebay account"]);
    exit;
}

// (Optional) Debug: safely output the user token if needed
// echo json_encode(["debug" => "user token => " . (is_array($userToken) ? json_encode($userToken) : $userToken)]);

if (!$devID || !$appID || !$certID) {
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

    // Ensure credentials are strings (if they are arrays, convert them)
    $devID  = is_array($devID)  ? implode(',', $devID)  : $devID;
    $appID  = is_array($appID)  ? implode(',', $appID)  : $appID;
    $certID = is_array($certID) ? implode(',', $certID) : $certID;

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

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawResponse = curl_exec($ch);
    $err = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        echo json_encode(["error" => "cURL Error: $err"]);
        return;
    }
    if ($statusCode !== 200) {
        echo json_encode(["error" => "HTTP $statusCode from eBay"]);
        return;
    }

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

    $namespaces = $xml->getNamespaces(true);
    $ns = isset($namespaces['']) ? $namespaces[''] : '';

    // Register the default namespace as "ns" for XPath queries
    $xml->registerXPathNamespace('ns', $ns);

    // Safely fetch the Ack node
    $ackNodes = $xml->xpath("//ns:Ack");
    $ack = ($ackNodes && isset($ackNodes[0])) ? (string)$ackNodes[0] : "Unknown";

    $itemNodes = $xml->xpath("//ns:Item") ?: [];

    $items = [];
    foreach ($itemNodes as $item) {
        $titleNode = $item->xpath("./ns:Title");
        $itemIdNode = $item->xpath("./ns:ItemID");
        $title = ($titleNode && isset($titleNode[0])) ? (string)$titleNode[0] : "";
        $itemId = ($itemIdNode && isset($itemIdNode[0])) ? (string)$itemIdNode[0] : "";
        $items[] = [
            "itemId" => $itemId,
            "title"  => $title
        ];
    }

    return [
        "ack" => $ack,
        "items" => $items
    ];
}

