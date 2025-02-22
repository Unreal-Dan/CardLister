// tcg_api.js

/**
 * Fetches a Pokémon TCG card by its unique card number (e.g., "095/203").
 * @param {string} cardNumber - The set/card number identifier.
 * @returns {Promise<{ name: string, price: number|null, image: string|null }>}
 */
export async function fetchTCGByCardNumber(cardNumber, setId) {
  try {
    if (!cardNumber || !setId) {
      console.warn(`⚠️ Missing card number or set ID for: ${ebayTitle}`);
      return { name: "", price: null, image: "", url: "#" };
    }

    // Log the final API request URL
    const apiUrl = `/php/tcg_proxy.php?action=fetchCardByNumber&setId=${encodeURIComponent(setId)}&cardNumber=${encodeURIComponent(cardNumber)}`;
    console.log(`🌐 Fetching from API: ${apiUrl}`);

    // Corrected API call
    const cardResponse = await fetch(apiUrl);
    if (!cardResponse.ok) throw new Error(`HTTP Error ${cardResponse.status}`);

    const cardData = await cardResponse.json();
    if (!cardData || cardData.error) throw new Error(`Card not found: ${JSON.stringify(cardData)}`);

    // Extract price & image
    const prices = cardData.tcgplayer?.prices ?? {};
    return {
      name: cardData.name,
      price: prices.holofoil?.market ?? prices.reverseHolofoil?.market ?? prices.normal?.market ?? null,
      image: cardData.images?.large ?? cardData.images?.small ?? null,
      url: cardData.tcgplayer?.url ?? "#",
      setName: cardData.set?.name || "Unknown",
      rarity: cardData.rarity || "N/A"
    };
  } catch (error) {
    console.error(`❌ API request error for eBay title "${ebayTitle}":`, error);
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

/**
 * Extracts the card number and set name from an eBay listing title.
 * @param {string} title - The eBay listing title.
 * @returns {{ cardNumber: string, setId: string, setName: string }} - Extracted card data.
 */
export async function extractCardInfoFromEbayTitle(title) {
  if (!title) return { cardNumber: "", setId: "", setName: "" };

  console.log(`🔍 Parsing eBay Title: ${title}`);

  // Extract set name from title
  const SET_NAME_TO_ID = {
    "Base Set": "base1",
    "Jungle": "base2",
    "Fossil": "base3",
    "Team Rocket": "base4",
    "Neo Genesis": "neo1",
    "Neo Discovery": "neo2",
    "Neo Revelation": "neo3",
    "Neo Destiny": "neo4",
    "Expedition": "ecard1",
    "Aquapolis": "ecard2",
    "Skyridge": "ecard3",
    "EX Ruby & Sapphire": "ex1",
    "EX Sandstorm": "ex2",
    "EX Dragon": "ex3",
    "Diamond & Pearl": "dp1",
    "Platinum": "pl1",
    "HeartGold & SoulSilver": "hgss1",
    "Black & White": "bw1",
    "XY": "xy1",
    "Sun & Moon": "sm1",
    "Sword & Shield": "swsh1",
    "Scarlet & Violet": "sv1"
  };



  let setId = "";
  let cardNumber = "";

  if (title.includes("-")) {
    // If the title is already in "setId-cardNumber" format, extract directly
    const parts = title.split("-");
    if (parts.length === 2) {
      setId = parts[0];       // base2
      cardNumber = parts[1];   // 4
    }
  } else {
    // Extract card number from eBay title (e.g., "Jungle 4/64")
    const numberMatch = title.match(/\b\d{1,3}\/\d{1,3}\b/);
    if (numberMatch) {
      cardNumber = numberMatch[0].split("/")[0];
    }
    for (const [name, id] of Object.entries(SET_NAME_TO_ID)) {
      if (title.toLowerCase().includes(name.toLowerCase())) {
        setId = id;
        break;
      }
    }
  }

  console.log(`✅ Parsed from eBay Title -> Card Number: ${cardNumber}, Set ID: ${setId}`);

  return { cardNumber, setId, setName: setId ? SET_NAME_TO_ID[setId] || "Unknown" : "" };
}

