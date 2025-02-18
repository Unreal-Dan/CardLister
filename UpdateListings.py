import requests
import time
import json
from colorama import Fore, Style, init

# Initialize Colorama for Windows compatibility
init(autoreset=True)

# API Key
TCG_API_KEY = "0863e809-8d50-4006-a3fc-58d3ba2f9c2c"  # Replace with your API Key

# Configurable Settings
LOG_FILE = "tcg_price_report.txt"
TCG_API_URL = "https://api.pokemontcg.io/v2/cards"
ebay_listings = {"Lucario VSTAR": 15.50, "Snorlax V": 140.00}

with open("ebay_listings.json", "r") as f:
    ebay_listings = json.load(f)

def get_price_margin():
    """Ask the user for a price margin percentage, defaulting to 12%."""
    while True:
        user_input = input(Fore.MAGENTA +
                           f"🔢 Change price margin? ({Fore.WHITE}Default: {Fore.YELLOW}12%{Fore.MAGENTA}): " + Fore.WHITE).strip()

        if user_input == "":
            return 12  # Default to 12%

        try:
            price_margin = float(user_input)
            if 0 <= price_margin <= 100:
                return price_margin
            print(Fore.RED + "❌ Please enter a percentage between 0 and 100.")
        except ValueError:
            print(Fore.RED + "❌ Invalid input. Enter a number (e.g., 12 or 15.5).")


PRICE_MARGIN_PERCENT = get_price_margin()
print(Fore.GREEN + f"✅ Using price margin: {Fore.YELLOW}{PRICE_MARGIN_PERCENT}%")


def fetch_tcg_price(card_name):
    """Fetch the latest market price for a Pokémon card using proper API query syntax."""
    headers = {'X-Api-Key': TCG_API_KEY}
    params = {'q': f'name:"{card_name}"', 'pageSize': 10, 'orderBy': '-set.releaseDate'}

    try:
        response = requests.get(TCG_API_URL, headers=headers, params=params)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(Fore.RED + f"API request error for {card_name}: {e}")
        return None

    data = response.json().get("data", [])
    if not data:
        print(Fore.RED + f"No price data found for {card_name}.")
        return None

    best_match = next((card for card in data if card_name.lower() in card['name'].lower()), None)
    if not best_match:
        print(Fore.YELLOW + f"No exact match for {card_name}, but {len(data)} results were found.")
        return None

    prices = best_match.get('tcgplayer', {}).get('prices', {})
    market_price = next((prices[ptype].get("market") for ptype in ["holofoil", "reverseHolofoil", "normal"] if ptype in prices), None)

    if market_price is None:
        print(Fore.YELLOW + f"Matched {best_match['name']} but no market price available.")
        return None

    #print(Fore.GREEN + f"Matched {best_match['name']} -> Market Price: {Fore.YELLOW}${market_price:.2f}")
    #print(Fore.GREEN + f"Matched {best_match['name']}")
    return market_price


def process_listings():
    """Generate report first, then prompt for interactive or force mode."""
    log_entries = []

    print(Fore.CYAN + "\n📄 Generating Report...")
    for card_name, ebay_price in ebay_listings.items():
        tcg_price = fetch_tcg_price(card_name)
        if tcg_price is None:
            log_entries.append(f"{card_name} - No TCG price found")
            continue

        price_difference = ((ebay_price - tcg_price) / ebay_price) * 100
        suggested_price = round(tcg_price * (1 + PRICE_MARGIN_PERCENT / 100), 2)
        diff_color = (Fore.GREEN + "+") if price_difference > 0 else Fore.RED

        log_entry = (
            f"{Fore.CYAN}{card_name} {Fore.WHITE}| "
            f"{Fore.WHITE}eBay Price: {Fore.YELLOW}${ebay_price:.2f} {Fore.WHITE}| "
            f"{Fore.WHITE}TCG Price: {Fore.CYAN}${tcg_price:.2f} {Fore.WHITE}| "
            f"{Fore.WHITE}Diff: {diff_color}{price_difference:.2f}%"
        )

        log_entries.append(log_entry)
        print(log_entry)

    with open(LOG_FILE, "w") as f:
        f.write("\n".join(log_entries))

    print(Fore.MAGENTA + f"\n📄 Report saved to {Fore.YELLOW}{LOG_FILE}")

    # Prompt user to proceed with updates
    mode = input(Fore.CYAN + f"\n📢 Run in ({Fore.WHITE}I{Fore.CYAN})nteractive, ({Fore.WHITE}F{Fore.CYAN})orce, or ({Fore.WHITE}E{Fore.CYAN})xit: " + Fore.WHITE).strip().lower()
    if mode == "i":
        print(Fore.GREEN + "🔄 Switching to Interactive Mode...")
        process_listings_interactive()
    elif mode == "f":
        diff_color = (Fore.GREEN + "+") if PRICE_MARGIN_PERCENT > 0 else Fore.RED
        confirm = input(Fore.RED +
                        f"⚠️ Are you sure you want to force update {Fore.WHITE}ALL {Fore.RED}prices to {diff_color}{PRICE_MARGIN_PERCENT}%{Fore.RED}? ({Fore.YELLOW}yes/no{Fore.RED}): {Fore.WHITE}").strip().lower()
        if confirm == "yes":
            print(Fore.GREEN + "⚡ Running in Force Mode. All prices will be updated automatically.")
            process_listings_interactive(force_mode=True)
        else:
            print(Fore.YELLOW + "❌ Force mode cancelled.")
    else:
        print(Fore.YELLOW + "🚪 Exiting without updates.")
        exit()


def process_listings_interactive(force_mode=False):
    """Handle updates in Interactive or Force mode."""
    for card_name, ebay_price in ebay_listings.items():
        tcg_price = fetch_tcg_price(card_name)
        if tcg_price is None:
            continue

        price_difference = ((tcg_price - ebay_price) / ebay_price) * 100
        suggested_price = round(tcg_price * (1 + PRICE_MARGIN_PERCENT / 100), 2)

        print(Fore.CYAN + f"\n🔍 Checking {card_name} ({Fore.WHITE}Current Price: {Fore.YELLOW}${ebay_price:.2f}{Fore.CYAN})...")

        if force_mode:
            print(Fore.GREEN + f"⚡ {Fore.RED}FORCE UPDATING {Fore.CYAN}{card_name} {Fore.RED}to {Fore.YELLOW}${suggested_price:.2f}")
            continue

        while True:
            user_input = input(Fore.MAGENTA +
                f"Suggested price: {Fore.YELLOW}${suggested_price:.2f} \n"
                f"{Fore.WHITE}Enter new price:{Fore.WHITE} ").strip()

            new_price = suggested_price if user_input == "" else float(user_input) if user_input.replace(".", "").isdigit() else None
            if new_price is None:
                print(Fore.RED + "❌ Invalid input. Please enter a valid number.")
                continue

            new_price_difference = ((new_price - ebay_price) / ebay_price) * 100
            diff_color = (Fore.GREEN + "+") if price_difference > 0 else Fore.RED
            new_diff_color = (Fore.GREEN + "+") if new_price_difference > 0 else Fore.RED
            print(Fore.MAGENTA +
                  f"{Fore.YELLOW}New Price: {Fore.WHITE}${new_price} {new_diff_color}({new_price_difference:.2f}%) "
                  f"{Fore.YELLOW}Old Price: {Fore.CYAN}${ebay_price} {diff_color}({price_difference:.2f}%)")

            confirm = input(Fore.GREEN +
                            f"✅ Confirm update of [{Fore.CYAN}{card_name}{Fore.GREEN}] from {Fore.YELLOW}${ebay_price} {Fore.GREEN}to {Fore.WHITE}${new_price:.2f}{Fore.GREEN}? (y/n): {Fore.WHITE}").strip().lower()
            if confirm == "y":
                print(Fore.GREEN + f"✅ Updating {card_name} to {Fore.YELLOW}${new_price:.2f}")
                break
            elif confirm == "n":
                print(Fore.YELLOW + f"⏭️ Skipping {card_name}")
                break


if __name__ == "__main__":
    process_listings()

