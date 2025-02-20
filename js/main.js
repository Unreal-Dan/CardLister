// Import the fetchTCGPrice function from tcg_api.js
import { searchTCGCards, fetchTCGPrice } from "./tcg_api.js";
import { getMyEbaySelling } from "./ebay_trading_api.js";

const ebayButton = document.getElementById("fetchEbay");
const listingList = document.getElementById("listingList");
const currencySelect = document.getElementById("currencySelect");
const massUpdateButton = document.getElementById("massUpdate");
const createListingBtn = document.getElementById("createListingBtn");
const fetchEbayBtn = document.getElementById("fetchEbay");
const createListingView = document.getElementById("createListingView");
const listingsView = document.getElementById("listingsView");
const cardSearchInput = document.getElementById("cardSearchInput");
const searchResults = document.getElementById("searchResults");
const selectedCardDetails = document.getElementById("selectedCardDetails");
const selectedCardImage = document.getElementById("selectedCardImage");
const selectedCardName = document.getElementById("selectedCardName");
const cardNumberInput = document.getElementById("cardNumber");
const cardConditionInput = document.getElementById("cardCondition");
const listingPriceInput = document.getElementById("listingPrice");
const confirmListingBtn = document.getElementById("confirmListing");

let listings = [];
let selectedCard = null;

cardSearchInput.addEventListener("keydown", (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (searchResults.children.length > 0) {
      searchResults.children[0].click();
    }
  }
});

// Switch to Create Listing View
createListingBtn.addEventListener("click", () => {
  listingsView.style.display = "none";
  // Remove any hidden class so that the view can properly display
  createListingView.classList.remove("hidden");
  createListingView.style.display = "block";
  selectedCardDetails.classList.add("hidden");
  searchResults.innerHTML = "";
  cardSearchInput.value = "";
});

// Real-time Search
cardSearchInput.addEventListener("input", async () => {
  selectedCardDetails.classList.add("hidden");
  const query = cardSearchInput.value.trim();
  if (query.length < 3) {
    searchResults.innerHTML = "";
    return;
  }

  const results = await searchTCGCards(query);
  searchResults.innerHTML = "";

  results.forEach(card => {
    const li = document.createElement("li");
    li.classList.add("search-item");
    li.innerHTML = `
            <img src="${card.images?.small}" alt="${card.name}">
            <span>${card.name} (${card.set.name})</span>
        `;
    li.addEventListener("click", () => selectCard(card));
    searchResults.appendChild(li);
  });
});

// Select a Card
function selectCard(card) {
  selectedCard = card;
  selectedCardDetails.classList.remove("hidden");
  selectedCardImage.src = card.images?.large || "";
  selectedCardName.textContent = `${card.name} (${card.set.name})`;
  listingPriceInput.value = card.tcgplayer?.prices?.holofoil?.market ||
    card.tcgplayer?.prices?.reverseHolofoil?.market ||
    card.tcgplayer?.prices?.normal?.market ||
    0;
}

// Confirm Listing
confirmListingBtn.addEventListener("click", () => {
  if (!selectedCard) return alert("Select a card first.");

  const newListing = {
    name: selectedCard.name,
    set: selectedCard.set.name,
    image: selectedCard.images?.large,
    number: cardNumberInput.value,
    condition: cardConditionInput.value,
    price: parseFloat(listingPriceInput.value),
  };

  console.log("New Listing:", newListing);

  createListingView.classList.add("hidden");
  createListingView.style.display = "none"; // Ensure it is hidden
  listingsView.classList.remove("hidden");
  listingsView.style.display = "block"; // Ensure it becomes visible again
});

// Example usage to fetch and parse your listings:
async function handleFetchEbayListings() {
  try {
    // Parse the XML response string into something usable
    const parsedData = await getMyEbaySelling();

    console.log("Ack:", parsedData.ack);
    console.log("Found Items:", parsedData.items);

    // Convert each returned item to your "listings" shape
    listings = parsedData.items.map(item => ({
      name: item.title,
      ebayPrice: 9.99, // placeholder, you’d parse from the XML if available
      image: null // likewise, if you have image data in the response
    }));

    // Then proceed to renderListings();
    renderListings();
  } catch (error) {
    console.error("Error fetching eBay listings:", error);
  }
}

// Convert currency (Mock API for now, replace with real exchange rate API)
async function convertCurrency(amount, from, to) {
  if (from === to) return amount;

  try {
    const response = await fetch(`https://api.exchangerate-api.com/v4/latest/${from}`);
    const rates = await response.json();
    return (amount * rates.rates[to]).toFixed(2);
  } catch (error) {
    console.error("❌ Failed to fetch currency conversion rates:", error);
    return amount; // Fallback to same price
  }
}

// Render listings in the UI
async function renderListings() {
  listingList.innerHTML = "";

  for (let item of listings) {
    const { price: tcgPriceUSD, image: tcgImage } = await fetchTCGPrice(item.name);
    if (!tcgPriceUSD) continue; // Skip if no TCG price found

    const selectedCurrency = currencySelect.value;
    const tcgPrice = await convertCurrency(tcgPriceUSD, "USD", selectedCurrency);

    const priceDiff = ((item.ebayPrice - tcgPrice) / item.ebayPrice) * 100;
    const priceDiffClass = priceDiff > 0 ? "positive" : "negative";

    const li = document.createElement("li");
    li.classList.add("card-item");
    li.innerHTML = `
            <img src="${tcgImage || 'placeholder.jpg'}" alt="${item.name}">
            <div class="card-info">
                <strong>${item.name}</strong><br>
                eBay: <span>${selectedCurrency} $${item.ebayPrice.toFixed(2)}</span><br>
                TCG: <span>${selectedCurrency} $${tcgPrice.toFixed(2)}</span><br>
                <span class="price-diff ${priceDiffClass}">${priceDiff.toFixed(2)}%</span>
            </div>
            <input type="number" value="${item.ebayPrice}" class="update-price">
            <button class="update-button">✔</button>
        `;

    listingList.appendChild(li);
  }
}

// Event Listeners
ebayButton.addEventListener("click", handleFetchEbayListings);
currencySelect.addEventListener("change", renderListings);

