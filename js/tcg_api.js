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

    // Step 1: Extract card number & set name
    const { cardNumber, setName } = extractCardInfoFromEbayTitle(ebayTitle);
    if (!cardNumber || !setName) throw new Error("Card number or set name missing");

    console.log(`📌 Extracted: Card Number=${cardNumber}, Set=${setName}`);

    // Step 2: Get Set ID
    const setResponse = await fetch(`/php/tcg_proxy.php?action=fetchSetByName&setName=${encodeURIComponent(setName)}`);
    if (!setResponse.ok) throw new Error(`HTTP Error ${setResponse.status}`);

    const setData = await setResponse.json();
    if (!setData || setData.error) throw new Error("Set ID not found");

    const setId = setData.id;
    console.log(`✅ Found Set ID: ${setId}`);

    // Step 3: Fetch Card by ID
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
 * @returns {{ cardNumber: string, setName: string }} - Extracted card data.
 */
function extractCardInfoFromEbayTitle(title) {
  if (!title) return { cardNumber: "", setName: "" };

  // Extract card number (XXX/YYY format)
  const numberMatch = title.match(/\d{1,3}\/\d{1,3}/);
  const cardNumber = numberMatch ? numberMatch[0].split("/")[0] : "";

  // Known Pokémon TCG set names
  const knownSets = [
    "Base Set", "Jungle", "Fossil", "Team Rocket", "Neo Genesis", "Neo Discovery", "Neo Revelation",
    "Neo Destiny", "Expedition", "Aquapolis", "Skyridge", "EX", "Diamond & Pearl", "Platinum",
    "HeartGold & SoulSilver", "Black & White", "XY", "Sun & Moon", "Sword & Shield", "Scarlet & Violet",
    "Evolutions", "Champion’s Path", "Hidden Fates", "Brilliant Stars", "Evolving Skies", "Obsidian Flames",
    "Lost Origin", "Silver Tempest", "Crown Zenith", "Paldea Evolved", "Paradox Rift", "Prize Pack Series"
  ];

  // Find set name in title
  let setName = "";
  for (const set of knownSets) {
    if (title.toLowerCase().includes(set.toLowerCase())) {
      setName = set;
      break;
    }
  }

  console.log(`📌 Extracted from title: Number=${cardNumber}, Set=${setName}`);
  return { cardNumber, setName };
}

