// tcg_api.js

/**
 * Fetches the best-matching card's price and image from the TCG API.
 * @param {string} cardName - The name of the Pokémon TCG card.
 * @returns {Promise<{ price: number|null, image: string|null }>}
 */
export async function fetchTCGPrice(cardName) {
  if (!cardName) return { price: null, image: null };

  try {
    const response = await fetch(`/php/tcg_proxy.php?action=fetchPrice&cardName=${encodeURIComponent(cardName)}`, {
      method: "GET"
    });

    if (!response.ok) throw new Error(`HTTP Error ${response.status}`);

    const data = await response.json();

    return {
      price: data.price ?? null,
      image: data.image ?? null
    };
  } catch (error) {
    console.error(`❌ API request error for ${cardName}:`, error);
    return { price: null, image: null };
  }
}

/**
 * Searches for Pokémon TCG cards by name and returns possible matches.
 * @param {string} query - The partial or full name of the card to search for.
 * @returns {Promise<Array>}
 */
export async function searchTCGCards(query) {
  if (!query) return [];

  try {
    console.log("Searching: [" + encodeURIComponent(query) + "]");
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

/**
 * Finds the best-matching card by searching for related Pokémon TCG cards
 * and selecting the most relevant result.
 * @param {string} cardName - The full listing title (e.g., "Pokémon TCG Jolteon Jungle 4/64 Holo Unlimited Holo Rare")
 * @returns {Promise<{ name: string, price: number|null, image: string|null }>}
 */
export async function getBestMatchingCard(cardName) {
  if (!cardName) return { name: "", price: null, image: null };

  const searchResults = await searchTCGCards(cardName);

  if (!searchResults.length) {
    return { name: "", price: null, image: null };
  }

  // Sort and find the best match
  let bestMatch = null;
  const lowerCaseCardName = cardName.toLowerCase();

  for (const card of searchResults) {
    const lowerCaseResultName = card.name.toLowerCase();

    // Exact match first
    if (lowerCaseResultName === lowerCaseCardName) {
      bestMatch = card;
      break;
    }

    // Partial match fallback
    if (lowerCaseResultName.includes(lowerCaseCardName)) {
      bestMatch = card;
    }
  }

  if (!bestMatch) {
    bestMatch = searchResults[0]; // Fallback to first result
  }

  // Fetch price and image for best match
  const bestPriceResult = await fetchTCGPrice(bestMatch.name);

  return {
    name: bestMatch.name,
    price: bestPriceResult.price,
    image: bestPriceResult.image
  };
}

