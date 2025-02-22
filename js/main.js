// Import the fetchTCGPrice function from tcg_api.js
import { fetchTCGByCardNumber, searchTCGCards, fetchTCGPrice, getBestMatchingCard } from "./tcg_api.js";
import { getMyEbaySelling, createEbayListing } from "./ebay_trading_api.js";

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
const cardGraderInput = document.getElementById("cardGrader");
const cardGradeInput = document.getElementById("cardGrade");

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
confirmListingBtn.addEventListener("click", async () => {
  if (!selectedCard) return alert("Select a card first.");

  const newListing = {
    title: selectedCard.name + " (" + selectedCard.set.name + ")",
    startPrice: parseFloat(document.getElementById("listingPrice").value) || 1.00,
    conditionID: document.getElementById("cardCondition").value,
    quantity: 1,
    image: selectedCard.images?.large || null,
    description: `A beautiful ${document.getElementById("cardCondition").value} condition Pokémon card.`,
    grader: cardGraderInput.value,
    grade: cardGradeInput.value,
  };

  console.log("New Listing:", newListing);

  createListingView.classList.add("hidden");
  createListingView.style.display = "none"; // Ensure it is hidden
  listingsView.classList.remove("hidden");
  listingsView.style.display = "block"; // Ensure it becomes visible again


  const result = await createEbayListing(newListing);
  if (result.ack === "Success") {
    alert("Listing successfully created on eBay!");
  }
});

// Example usage to fetch and parse your listings:
async function handleFetchEbayListings() {
  try {
    console.log("Fetching eBay Listings...");

    const parsedData = await getMyEbaySelling();

    console.log("API Response:", parsedData);
    console.log("Parsed eBay Items:", parsedData.items);

    if (!parsedData.items || parsedData.items.length === 0) {
      console.warn("No listings found.");
      return;
    }

    // Update `listings`
    listings = parsedData.items.map(item => ({
      name: item.title,
      ebayPrice: parseFloat(item.price) || 0,
      currency: item.currency || "USD",
      image: item.image || "placeholder.jpg",
      url: item.url || "#"
    }));

    console.log("Updated Listings Array:", listings);

    // Call renderListings AFTER updating listings
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
  console.log("Rendering Listings... Current listings:", listings);

  listingList.innerHTML = ""; // Clear old content

  if (!listings || listings.length === 0) {
    listingList.innerHTML = "<p>No listings found.</p>";
    console.warn("No listings available to render.");
    return;
  }

  for (let item of listings) {
    console.log("Rendering Item:", item);

    // Extract card number from title
    const cardNumberMatch = item.name.match(/\d{1,3}\/\d{1,3}/);
    const cardNumber = cardNumberMatch ? cardNumberMatch[0] : null;

    let tcgCard = { name: "N/A", price: 0, image: "", url: "#" };
    if (cardNumber) {
      tcgCard = await fetchTCGByCardNumber(cardNumber);
    }

    const selectedCurrency = currencySelect.value;
    const tcgPrice = await convertCurrency(tcgCard.price || 1.0, "USD", selectedCurrency);

    const priceDiff = ((item.ebayPrice - tcgPrice) / tcgPrice) * 100;
    const priceDiffClass = priceDiff > 0 ? "positive" : "negative";

    const li = document.createElement("li");
    li.classList.add("card-item");
    li.innerHTML = `
        <a href="${item.url}" target="_blank">
            <img src="${item.image}" alt="${item.name}">
        </a>
        <div class="card-info">
            <strong>${item.name}</strong><br>
            <span style="color: gray;">eBay Listing</span>: ${item.currency} $${item.ebayPrice.toFixed(2)}<br>
            <span style="color: gray;">TCG Card:</span> <a href="${tcgCard.url}" target="_blank">${tcgCard.name}</a><br>
            <span style="color: gray;">Set:</span> ${tcgCard.setName || "Unknown"}<br>
            <span style="color: gray;">Rarity:</span> ${tcgCard.rarity || "N/A"}<br>
            <span style="color: gray;">TCG Price:</span> ${selectedCurrency} $${tcgPrice.toFixed(2)}<br>
            <span class="price-diff ${priceDiffClass}">${priceDiff.toFixed(2)}%</span>
        </div>
        <input type="number" value="${item.ebayPrice}" class="update-price">
        <button class="update-button">✔</button>
    `;

    listingList.appendChild(li);
  }

  console.log("Listings successfully rendered.");
}

// Event Listeners
ebayButton.addEventListener("click", handleFetchEbayListings);
currencySelect.addEventListener("change", renderListings);

