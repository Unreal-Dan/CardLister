// ebay_trading_api.js
// A simple function that fetches your listings from the PHP proxy.
// The PHP proxy returns JSON with { ack, items }, so we don't need
// to parse XML here.

export async function getMyEbaySelling() {
  try {
    const response = await fetch("/php/ebay_proxy.php?action=getMyEbaySelling", {
      method: "GET"
    });

    if (!response.ok) {
      throw new Error(`Network error: ${response.status} ${response.statusText}`);
    }

    const xmlText = await response.text();
    return parseEbayXml(xmlText);
  } catch (err) {
    console.error("Failed to fetch eBay listings:", err);
    return { ack: "Error", items: [] };
  }
}

/**
 * Parses eBay Trading API XML response into usable JSON.
 */
function parseEbayXml(xmlString) {
  const parser = new DOMParser();
  const xmlDoc = parser.parseFromString(xmlString, "application/xml");

  const ack = xmlDoc.querySelector("Ack")?.textContent || "Unknown";
  const items = [];

  xmlDoc.querySelectorAll("Item").forEach(item => {
    const title = item.querySelector("Title")?.textContent || "";
    const cardNumberMatch = title.match(/\d{1,3}\/\d{1,3}/); // Extract formats like 095/203

    items.push({
      itemId: item.querySelector("ItemID")?.textContent || "",
      title,
      cardNumber: cardNumberMatch ? cardNumberMatch[0] : null, // Extracted card number
      price: parseFloat(item.querySelector("ConvertedCurrentPrice")?.textContent || "0"),
      currency: item.querySelector("ConvertedCurrentPrice")?.getAttribute("currencyID") || "USD",
      image: item.querySelector("GalleryURL")?.textContent || "placeholder.jpg",
      url: item.querySelector("ViewItemURL")?.textContent || "#"
    });
  });

  return {
    ack,
    items,
    rawXml: xmlString
  };
}

/**
 * Creates a new eBay listing using the AddItem API.
 *
 * @param {Object} listingData - The listing details.
 * @param {string} listingData.title - The title of the listing.
 * @param {string} listingData.categoryID - The eBay category ID.
 * @param {number} listingData.startPrice - The starting price.
 * @param {string} listingData.conditionID - The condition of the item.
 * @param {string} listingData.listingDuration - The duration of the listing (e.g., "GTC").
 * @param {string} listingData.image - The URL of the product image.
 * @param {string} listingData.description - The product description.
 */
export async function createEbayListing(listingData) {
  try {
    console.log("🚀 Creating eBay Listing with Data:", JSON.stringify(listingData, null, 2));

    const response = await fetch("/php/ebay_create_listing.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(listingData),
    });

    if (!response.ok) {
      throw new Error(`Network error: ${response.status} ${response.statusText}`);
    }

    const result = await response.json();

    console.group("📦 eBay API Response:");
    console.log("✅ Full JSON Response:", JSON.stringify(result, null, 2));
    if (result.rawXml) {
      console.log("📜 Raw XML Response:\n", result.rawXml);
    }
    console.groupEnd();

    if (result.ack === "Success") {
      alert(`✅ Listing created successfully! eBay Item ID: ${result.itemId}`);
      return result;
    } else {
      console.error("❌ eBay API Error:", JSON.stringify(result.errors, null, 2));
      alert(`❌ Failed to create listing: ${JSON.stringify(result.errors, null, 2)}`);
      return result;
    }
  } catch (err) {
    console.error("❌ Fatal Error Creating eBay Listing:", err);
    return { ack: "Error", errors: [err.message] };
  }
}


