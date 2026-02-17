# RE Property Lookup — WordPress Plugin

A password-protected property data lookup tool for commercial real estate insurance brokerage teams.

---

## What It Does

Team members navigate to any page where the shortcode is embedded, enter the team password (set by the WordPress admin), and look up any commercial property address. The tool returns:

| Data Point | Source | Notes |
|---|---|---|
| **Zillow link** | Generated from address | Always available |
| **Redfin link** | Generated from address | Always available |
| **LoopNet link** | Generated from address | Always available — best for commercial |
| **Year built** | OpenStreetMap / Overpass API | Available for most urban properties |
| **Building type** | OpenStreetMap / Overpass API | e.g., Office, Retail, Commercial |
| **Building levels** | OpenStreetMap / Overpass API | Above-ground floors |
| **City / State / ZIP** | Nominatim geocoder | Normalised from input address |
| **Permit portal links** | Curated city/county open data | Links to official public permit searches |
| **Square footage** | _(future API)_ | Add ATTOM / CoStar API key to expand |
| **Available tenants** | _(future API)_ | Add LoopNet / CoStar API key to expand |

No third-party API keys are required for the initial feature set. All geocoding uses OpenStreetMap services which are free and require no registration.

---

## Installation

1. **Upload the plugin folder** `re-property-lookup/` to your WordPress installation at:
   ```
   /wp-content/plugins/re-property-lookup/
   ```

2. **Activate** the plugin in **WP Admin → Plugins**.

3. **Set a password** in **WP Admin → Settings → Property Lookup**.

4. **Embed the tool** on any page or post using the shortcode:
   ```
   [property_lookup]
   ```

The page will show a password gate to visitors. Once the correct password is entered, the full lookup tool becomes available for that browser session.

---

## WordPress Admin Settings

Navigate to **Settings → Property Lookup** to configure:

| Setting | Description |
|---|---|
| **Tool Access Password** | Shared password for all team members. Stored as a bcrypt hash — never plain text. |
| **Tool Title** | Heading shown at the top of the embedded tool. |
| **Instructions Text** | Short description shown above the address input. |

Passwords are hashed with `password_hash( ..., PASSWORD_BCRYPT )` before storage. Leaving the password field blank on subsequent saves preserves the existing password.

---

## Shortcode

```
[property_lookup]
```

Place this on any WordPress page. The tool is fully self-contained and works inside the page editor, Elementor, or any other page builder that renders shortcodes.

---

## Data Sources

- **[Nominatim / OpenStreetMap](https://nominatim.org/)** — address geocoding. Free, no API key required. Max ~1 request/second; appropriate for team-sized usage.
- **[Overpass API / OpenStreetMap](https://overpass-api.de/)** — building attributes (year built, type, levels). Free, no API key required.
- **City/County Open Data Portals** — curated links to official permit search tools for 40+ major US cities.
- **[PermitData.com](https://www.permitdata.com/)** — national permit aggregator (manual search, no API required).

---

## Adding a Data API (Future)

The data fetching is isolated in `includes/class-re-data-fetcher.php`. To add a paid data source:

1. Add an API key field to the admin settings page in `includes/class-re-admin.php`.
2. Retrieve the stored key with `get_option('re_plu_your_api_key')`.
3. Add a new private method to `RE_PLU_Data_Fetcher` that calls the provider's endpoint.
4. Call that method inside `fetch_all_data()` and populate `$result['square_footage']` and `$result['tenants']`.

Suggested providers:
- **ATTOM Data** — property characteristics, square footage, ownership
- **CoStar / LoopNet API** — tenant data, lease rates, availability
- **CoreLogic** — comprehensive property data

---

## File Structure

```
re-property-lookup/
├── re-property-lookup.php          # Plugin entry point, hooks, AJAX handlers
├── includes/
│   ├── class-re-admin.php          # WP Admin settings page
│   ├── class-re-url-generator.php  # Generates Zillow / Redfin / LoopNet URLs
│   └── class-re-data-fetcher.php   # Geocoding + public data retrieval
├── assets/
│   ├── css/re-property-lookup.css  # Frontend styles
│   └── js/re-property-lookup.js    # AJAX + results rendering
└── templates/
    ├── password-form.php           # Password gate UI
    └── lookup-tool.php             # Main tool UI (shown after auth)
```

---

## Security Notes

- Passwords are stored as bcrypt hashes (`PASSWORD_BCRYPT`) — never in plain text.
- All AJAX endpoints verify a WordPress nonce (`wp_create_nonce` / `check_ajax_referer`).
- All user input is sanitised with `sanitize_text_field()` before use.
- Authentication state is stored server-side in a PHP session — not in a client-side cookie or localStorage.
- The tool session is cleared on sign-out via an authenticated AJAX call.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- An internet connection on the server (for Nominatim / Overpass API calls)
