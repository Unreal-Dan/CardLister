// tcg_api.js

/**
 * Fetches a Pokémon TCG card by its unique card number (e.g., "095/203").
 * @param {string} cardNumber - The set/card number identifier.
 * @returns {Promise<{ name: string, price: number|null, image: string|null }>}
 */
export async function fetchTCGByCardNumber(cardNumber) {
  if (!cardNumber) return { name: "", price: null, image: "", url: "#" };

  try {
    console.log(`Looking up card: ${cardNumber}`);
    const response = await fetch(`/php/tcg_proxy.php?action=fetchCardByNumber&cardNumber=${encodeURIComponent(cardNumber)}`, {
      method: "GET"
    });

    if (!response.ok) throw new Error(`HTTP Error ${response.status}`);

    const data = await response.json();
    if (data.error) throw new Error(data.error);

    // Extract additional details
    const prices = data.tcgplayer?.prices ?? {};
    const marketPrice = prices.holofoil?.market ?? prices.reverseHolofoil?.market ?? prices.normal?.market ?? null;
    const imageUrl = data.images?.large ?? data.images?.small ?? null;
    const tcgUrl = data.tcgplayer?.url ?? "#";

    return {
      name: data.name,
      price: marketPrice,
      image: imageUrl,
      url: tcgUrl,
      setName: data.set?.name || "Unknown",
      rarity: data.rarity || "N/A"
    };
  } catch (error) {
    console.error(`❌ API request error for card number ${cardNumber}:`, error);
    return { name: "", price: null, image: "", url: "#" };
  }
}

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

  let bestMatch = searchResults[0]; // Default to first result

  // Extract key words from cardName
  const stopWords = ["pokemon", "tcg", "holo", "foil", "rare", "unlimited", "first", "edition", "shadowless", "promo"];
  const words = cardName.toLowerCase().split(/[\s\-\(\)\/]+/).filter(word => !stopWords.includes(word));

  // Try to find a better match based on extracted keywords
  for (const card of searchResults) {
    for (const word of words) {
      if (card.name.toLowerCase().includes(word)) {
        bestMatch = card;
        break;
      }
    }
  }

  // Fetch price and image for best match
  const bestPriceResult = await fetchTCGPrice(bestMatch.name);

  return {
    name: bestMatch.name,
    price: bestPriceResult.price,
    image: bestPriceResult.image
  };
}
