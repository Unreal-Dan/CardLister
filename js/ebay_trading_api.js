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

    const data = await response.json();
    if (data.error) {
      console.error("eBay Proxy Error:", data.error);
      return { ack: "Error", items: [] };
    }
    return data; // e.g. { ack: "Success", items: [ { itemId, title }, ... ] }
  } catch (err) {
    console.error("Failed to fetch eBay listings:", err);
    return { ack: "Error", items: [] };
  }
}

