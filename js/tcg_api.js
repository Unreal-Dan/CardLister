export const TCG_API_KEY = "0863e809-8d50-4006-a3fc-58d3ba2f9c2c"; // Replace with your API Key
export const TCG_API_URL = "https://api.pokemontcg.io/v2/cards";

/**
 * Fetches the latest market price for a Pokémon card using the TCG API.
 * @param {string} cardName - The name of the card to search for.
 * @returns {Promise<number|null>} - The market price or null if not found.
 */
export async function fetchTCGPrice(cardName) {
  const headers = { "X-Api-Key": TCG_API_KEY };
  const params = new URLSearchParams({
    q: `name:"${cardName}"`,  
    pageSize: 10,  
    orderBy: "-set.releaseDate"
  });

  try {
    const response = await fetch(`${TCG_API_URL}?${params}`, { headers });
    if (!response.ok) throw new Error(`HTTP Error ${response.status}`);

    const data = await response.json();
    const cards = data.data || [];

    if (cards.length === 0) {
      console.warn(`⚠️ No TCG price found for: ${cardName}`);
      return { price: null, image: null };
    }

    const bestMatch = cards.find(card => card.name.toLowerCase().includes(cardName.toLowerCase())) || cards[0];

    const prices = bestMatch.tcgplayer?.prices || {};
    const marketPrice = prices.holofoil?.market || prices.reverseHolofoil?.market || prices.normal?.market || null;
    const imageUrl = bestMatch.images?.large || bestMatch.images?.small || null;

    if (marketPrice === null) {
      console.warn(`⚠️ Matched ${bestMatch.name}, but no market price available.`);
      return { price: null, image: imageUrl };
    }

    console.log(`✅ Matched ${bestMatch.name} -> Market Price: $${marketPrice.toFixed(2)}`);
    return { price: marketPrice, image: imageUrl };
  } catch (error) {
    console.error(`❌ API request error for ${cardName}:`, error);
    return { price: null, image: null };
  }
}

export async function searchTCGCards(query) {
  if (!query) return [];

  const headers = { "X-Api-Key": TCG_API_KEY };
  const params = new URLSearchParams({
    q: `name:${query}*`,
    pageSize: 50,
    orderBy: "-set.releaseDate"
  });

  try {
    const response = await fetch(`${TCG_API_URL}?${params}`, { headers });
    if (!response.ok) throw new Error(`HTTP Error ${response.status}`);

    const data = await response.json();
    return data.data || [];
  } catch (error) {
    console.error(`❌ API request error for card search:`, error);
    return [];
  }
}

