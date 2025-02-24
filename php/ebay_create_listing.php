<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Retrieve eBay authentication token
$userToken = $_SESSION['ebay_token']['access_token'] ?? null;
if (!$userToken) {
    echo json_encode(["error" => "Missing eBay auth token"]);
    exit;
}

// Parse input JSON
$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['title'], $data['startPrice'], $data['categoryID'], $data['conditionID'])) {
    echo json_encode(["error" => "Invalid input parameters"]);
    exit;
}

// Default values for missing fields
$data['grader'] = $data['grader'] ?? "Ungraded";  // Default to 'Ungraded'
$data['grade'] = $data['grade'] ?? "Ungraded";    // Default to 'Ungraded'
$data['game'] = $data['game'] ?? "Pokémon TCG";   // Default to Pokémon TCG
$data['listingDuration'] = $data['listingDuration'] ?? "GTC"; // Fix listing duration
$data['image'] = $data['image'] ?? "https://i.ebayimg.com/images/g/default.jpg"; // Default placeholder image
$data['description'] = $data['description'] ?? "No description provided.";
$isGraded = !empty($data['grader']) && $data['grader'] !== "Ungraded";
$data['conditionID'] = getEbayConditionID($data['conditionID'], $isGraded);

// eBay API Endpoint
$endpoint = "https://api.ebay.com/ws/api.dll";

// Construct XML payload
$xmlBody = '<?xml version="1.0" encoding="utf-8"?>
<AddItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
  <RequesterCredentials>
    <eBayAuthToken>' . $userToken . '</eBayAuthToken>
  </RequesterCredentials>
  <Item>
    <Title>' . htmlspecialchars($data['title']) . '</Title>
    <PrimaryCategory>
      <CategoryID>183454</CategoryID>
    </PrimaryCategory>
    <StartPrice currencyID="USD">' . $data['startPrice'] . '</StartPrice>
    <ConditionID>' . $data['conditionID'] . '</ConditionID>
    <ListingDuration><![CDATA[GTC]]></ListingDuration>
    <ListingType>FixedPriceItem</ListingType>
    <Quantity>1</Quantity>
    <PaymentMethods>CreditCard</PaymentMethods>
    <DispatchTimeMax>2</DispatchTimeMax>
    <ShippingDetails>
      <ShippingType>Flat</ShippingType>
      <ShippingServiceOptions>
        <ShippingService>USPSFirstClass</ShippingService>
        <ShippingServiceCost currencyID="USD">4.99</ShippingServiceCost>
      </ShippingServiceOptions>
    </ShippingDetails>
    <ReturnPolicy>
      <ReturnsAcceptedOption>ReturnsAccepted</ReturnsAcceptedOption>
      <RefundOption>MoneyBack</RefundOption>
      <ReturnsWithinOption>Days_30</ReturnsWithinOption>
      <ShippingCostPaidByOption>Buyer</ShippingCostPaidByOption>
    </ReturnPolicy>
    <Country>US</Country>
    <Currency>USD</Currency>
    <PostalCode>10001</PostalCode>
    <Location>New York, NY</Location>
    <Site>US</Site>
    <PictureDetails>
      <PictureURL>' . $data['image'] . '</PictureURL>
    </PictureDetails>
    <Description><![CDATA[' . $data['description'] . ']]></Description>

    <!-- Item Specifics -->
    <ItemSpecifics>
      <NameValueList>
        <Name>Game</Name>
        <Value>' . htmlspecialchars($data['game']) . '</Value>
      </NameValueList>
    </ItemSpecifics>

    <!-- Condition Descriptors for Trading Cards -->
    <ConditionDescriptors>
      <ConditionDescriptor>
        <Name>27501</Name> <!-- Professional Grader -->
        <Value>' . htmlspecialchars($data['grader']) . '</Value>
      </ConditionDescriptor>
      <ConditionDescriptor>
        <Name>27502</Name> <!-- Grade -->
        <Value>' . htmlspecialchars($data['grade']) . '</Value>
      </ConditionDescriptor>
    </ConditionDescriptors>
  </Item>
</AddItemRequest>';

// eBay API Headers
$headers = [
    "X-EBAY-API-SITEID: 0",
    "X-EBAY-API-COMPATIBILITY-LEVEL: 967",
    "X-EBAY-API-CALL-NAME: AddItem",
    "Content-Type: text/xml"
];

// Send Request to eBay
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlBody);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$rawResponse = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

// Handle errors
if ($err) {
    echo json_encode(["error" => "cURL Error: $err"]);
    exit;
}

// Parse eBay Response
echo json_encode(parseEbayXmlResponse($rawResponse));

/**
 * Parses eBay's XML response into JSON.
 */
function parseEbayXmlResponse($xmlString) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    if (!$xml) {
        return ["ack" => "XML Parse Error", "errors" => [], "itemId" => ""];
    }

    $ack = (string)$xml->Ack;
    $itemId = isset($xml->ItemID) ? (string)$xml->ItemID : "";
    $errors = [];

    foreach ($xml->Errors as $error) {
        $errors[] = ["code" => (string)$error->ErrorCode, "message" => (string)$error->LongMessage];
    }

    return [
        "ack" => $ack,
        "errors" => $errors,
        "itemId" => $itemId
    ];
}

/**
 * Maps the condition to an eBay Condition ID.
 */
function getEbayConditionID($condition, $isGraded = false) {
    if ($isGraded) return 2750; // Graded Condition ID
    return 4000; // Default to "Ungraded"
}
?>

