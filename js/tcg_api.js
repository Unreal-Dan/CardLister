// tcg_api.js

// All requests are now proxied through a PHP script that holds the API key in an environment variable.
export async function fetchTCGPrice(cardName) {
  try {
    const response = await fetch(`/php/tcg_proxy.php?action=fetchPrice&cardName=${encodeURIComponent(cardName)}`, {
      method: "GET"
    });
    if (!response.ok) throw new Error(`HTTP Error ${response.status}`);
    const data = await response.json();
    return {
      price: data.price,
      image: data.image
    };
  } catch (error) {
    console.error(`❌ API request error for ${cardName}:`, error);
    return { price: null, image: null };
  }
}

export async function searchTCGCards(query) {
  if (!query) return [];
  try {
    const response = await fetch(`/php/tcg_proxy.php?action=searchCards&query=${encodeURIComponent(query)}`, {
      method: "GET"
    });
    if (!response.ok) throw new Error(`HTTP Error ${response.status}`);
    return await response.json();
  } catch (error) {
    console.error(`❌ API request error for card search:`, error);
    return [];
  }
}

