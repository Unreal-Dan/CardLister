<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Load credentials from environment
$devID     = getenv("EBAY_DEV_ID");
$appID     = getenv("EBAY_APP_ID");
$certID    = getenv("EBAY_CERT_ID");
$userToken = isset($_SESSION['ebay_token']['access_token']) ? $_SESSION['ebay_token']['access_token'] : null;

// If the token is stored as an array, convert it to a string.
if (is_array($userToken)) {
    $userToken = implode(',', $userToken);
}

if (!$userToken) {
    echo json_encode(["error" => "Missing user token, try connecting ebay account"]);
    exit;
}

if (!$devID || !$appID || !$certID) {
    echo json_encode(["error" => "Missing one or more eBay credentials in environment"]);
    exit;
}

// Parse "action" to decide which call to make
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === "getMyEbaySelling") {
    getMyEbaySelling($devID, $appID, $certID, $userToken);
} else {
    echo json_encode(["error" => "Unknown or missing action"]);
    exit;
}

/**
 * Calls GetSellerList via cURL and returns JSON with ack, errors & items
 */
function getMyEbaySelling($devID, $appID, $certID, $userToken) {
    $endpoint = "https://api.ebay.com/ws/api.dll";

    $xmlBody = '<?xml version="1.0" encoding="utf-8"?>
<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>' . $userToken . '</eBayAuthToken>
  </RequesterCredentials>
  <ActiveList>
    <Sort>TimeLeft</Sort>
    <Pagination>
      <EntriesPerPage>10</EntriesPerPage>
      <PageNumber>1</PageNumber>
    </Pagination>
  </ActiveList>
</GetMyeBaySellingRequest>';

    // Ensure credentials are strings (convert arrays if needed)
    $devID  = is_array($devID)  ? implode(',', $devID)  : $devID;
    $appID  = is_array($appID)  ? implode(',', $appID)  : $appID;
    $certID = is_array($certID) ? implode(',', $certID) : $certID;

    $headers = [
        "X-EBAY-API-SITEID: 0",
        "X-EBAY-API-COMPATIBILITY-LEVEL: 967",
        "X-EBAY-API-CALL-NAME: GetMyeBaySelling",
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

    header("Content-Type: application/xml");
    echo $rawResponse;
    exit;
}

/**
 * Parse the eBay Trading API XML response and extract:
 *  {
 *    ack: "Success" or "Failure" or ...
 *    errors: [ { code: "...", message: "..." }, ... ],
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
        return ["ack" => "XML Parse Error", "errors" => [], "items" => []];
    }

    $namespaces = $xml->getNamespaces(true);
    $ns = isset($namespaces['']) ? $namespaces[''] : '';
    $xml->registerXPathNamespace('ns', $ns);

    // Extract error details from <Errors> nodes
    $errorsNodes = $xml->xpath("//ns:Errors");
    $errorMessages = [];
    if ($errorsNodes) {
        foreach ($errorsNodes as $errorNode) {
            $codeNodes = $errorNode->xpath("./ns:ErrorCode");
            $msgNodes = $errorNode->xpath("./ns:LongMessage");
            $code = ($codeNodes && isset($codeNodes[0])) ? (string)$codeNodes[0] : "";
            $msg = ($msgNodes && isset($msgNodes[0])) ? (string)$msgNodes[0] : "";
            $errorMessages[] = ["code" => $code, "message" => $msg];
        }
    }

    $ackNodes = $xml->xpath("//ns:Ack");
    $ack = ($ackNodes && isset($ackNodes[0])) ? (string)$ackNodes[0] : "Unknown";

    $itemNodes = $xml->xpath("//ns:Item") ?: [];
    $items = [];
    foreach ($itemNodes as $item) {
        $titleNode = $item->xpath("./ns:Title");
        $itemIdNode = $item->xpath("./ns:ItemID");
        $title = ($titleNode && isset($titleNode[0])) ? (string)$titleNode[0] : "";
        $itemId = ($itemIdNode && isset($itemIdNode[0])) ? (string)$itemIdNode[0] : "";
        $items[] = ["itemId" => $itemId, "title" => $title];
    }

    return [
        "response" => $xml,
        "ack" => $ack,
        "errors" => $errorMessages,
        "items" => $items
    ];
}

