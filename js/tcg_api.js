// tcg_api.js

/**
 * Fetches a Pokémon TCG card by its unique card number (e.g., "095/203").
 * @param {string} cardNumber - The set/card number identifier.
 * @returns {Promise<{ name: string, price: number|null, image: string|null }>}
 */
export async function fetchTCGByCardNumber(ebayTitle) {
  if (!ebayTitle) return { name: "", price: null, image: "", url: "#" };

  try {
    console.log(`🔍 Extracting from eBay title: ${ebayTitle}`);

    // Extract card number & set ID
    const { cardNumber, setId, setName } = await extractCardInfoFromEbayTitle(ebayTitle);
    if (!cardNumber || !setId) {
      console.warn(`⚠️ Missing card number or set ID for: ${ebayTitle}`);
      return { name: "", price: null, image: "", url: "#" };
    }

    console.log(`📌 Extracted: Card Number=${cardNumber}, Set Name=${setName}, Set ID=${setId}`);

    // Step 2: Fetch Card by ID (setId-cardNumber)
    const cardId = `${setId}-${cardNumber}`;
    console.log(`🔗 Fetching card by ID: ${cardId}`);

    const cardResponse = await fetch(`/php/tcg_proxy.php?action=fetchCardById&cardId=${encodeURIComponent(cardId)}`);
    if (!cardResponse.ok) throw new Error(`HTTP Error ${cardResponse.status}`);

    const cardData = await cardResponse.json();
    if (!cardData || cardData.error) throw new Error("Card not found");

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
/**
 * Extracts the card number and set ID from an eBay listing title.
 * @param {string} title - The eBay listing title.
 * @returns {{ cardNumber: string, setId: string, setName: string }} - Extracted card data.
 */
export async function extractCardInfoFromEbayTitle(title) {
  if (!title) return { cardNumber: "", setId: "", setName: "" };

  // Extract card number (XXX/YYY format)
  const numberMatch = title.match(/\d{1,3}\/\d{1,3}/);
  const cardNumber = numberMatch ? numberMatch[0].split("/")[0] : "";

  // Hardcoded set name to ID mapping
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
    "Scarlet & Violet": "sv1",
    "Evolutions": "xy12",
    "Champion’s Path": "swsh35",
    "Hidden Fates": "sm115",
    "Brilliant Stars": "swsh9",
    "Evolving Skies": "swsh7",
    "Obsidian Flames": "sv3",
    "Lost Origin": "swsh11",
    "Silver Tempest": "swsh12",
    "Crown Zenith": "swsh12pt5",
    "Paldea Evolved": "sv2",
    "Paradox Rift": "sv4",
    "Prize Pack Series 1": "svp1",
    "Prize Pack Series 2": "svp2"
  };

  // Find set name in title
  let setName = "";
  let setId = "";
  for (const [name, id] of Object.entries(SET_NAME_TO_ID)) {
    if (title.toLowerCase().includes(name.toLowerCase())) {
      setName = name;
      setId = id;
      break;
    }
  }

  return { cardNumber, setId, setName };
}

