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
    items.push({
      itemId: item.querySelector("ItemID")?.textContent || "",
      title: item.querySelector("Title")?.textContent || "",
      price: parseFloat(item.querySelector("ConvertedCurrentPrice")?.textContent || "0"),
      currency: item.querySelector("ConvertedCurrentPrice")?.getAttribute("currencyID") || "USD",
      image: item.querySelector("GalleryURL")?.textContent || "placeholder.jpg",
      url: item.querySelector("ViewItemURL")?.textContent || "#"
    });
  });

  return { ack, items };
}

