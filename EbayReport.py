import requests
import xmltodict
import json
from colorama import Fore, Style, init

# Initialize Colorama
init(autoreset=True)

# eBay API Credentials
EBAY_AUTH_TOKEN = "YOUR_EBAY_AUTH_TOKEN"  # Replace with your eBay API token
EBAY_API_ENDPOINT = "https://api.ebay.com/ws/api.dll"

# eBay API Headers
HEADERS = {
    "X-EBAY-API-SITEID": "0",
    "X-EBAY-API-COMPATIBILITY-LEVEL": "967",
    "X-EBAY-API-CALL-NAME": "GetMyeBaySelling",
    "X-EBAY-API-IAF-TOKEN": EBAY_AUTH_TOKEN,
    "Content-Type": "text/xml"
}

# XML Request to Fetch Active Listings
XML_BODY = f"""<?xml version="1.0" encoding="utf-8"?>
<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>{EBAY_AUTH_TOKEN}</eBayAuthToken>
    </RequesterCredentials>
    <ActiveList>
        <Include>true</Include>
        <Pagination>
            <EntriesPerPage>50</EntriesPerPage>
            <PageNumber>1</PageNumber>
        </Pagination>
    </ActiveList>
</GetMyeBaySellingRequest>"""

def get_ebay_listings():
    """Fetch all active listings from eBay and format them for the pricing script."""
    print(Fore.CYAN + "\n🔄 Fetching eBay listings...")

    try:
        response = requests.post(EBAY_API_ENDPOINT, headers=HEADERS, data=XML_BODY)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(Fore.RED + f"❌ API request failed: {e}")
        return {}

    print(Fore.YELLOW + "🔍 Raw eBay API Response:")
    print(response.text)  # Print full response for debugging

    data = xmltodict.parse(response.text)

    # Extract active listings
    items = data.get("GetMyeBaySellingResponse", {}).get("ActiveList", {}).get("ItemArray", {}).get("Item", [])

    if isinstance(items, dict):  
        items = [items]  # Normalize to list if only one item is returned

    ebay_listings = {}
    for item in items:
        title = item["Title"]
        price = float(item["SellingStatus"]["CurrentPrice"]["#text"])
        ebay_listings[title] = price

    return ebay_listings

def save_listings_to_json(ebay_listings):
    """Save the formatted listings to a JSON file."""
    with open("ebay_listings.json", "w") as f:
        json.dump(ebay_listings, f, indent=4)

    print(Fore.GREEN + f"\n✅ eBay listings saved to {Fore.YELLOW}ebay_listings.json")

def main():
    """Main function to fetch, format, and save eBay listings."""
    listings = get_ebay_listings()
    
    if not listings:
        print(Fore.RED + "❌ No active listings found or API failed.")
        return
    
    print(Fore.YELLOW + "\n📄 Retrieved Listings:")
    for title, price in listings.items():
        print(Fore.CYAN + f"• {Fore.WHITE}{title}: {Fore.YELLOW}${price:.2f}")

    save_listings_to_json(listings)

if __name__ == "__main__":
    main()

