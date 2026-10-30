# Brief: fix order.hakkarestaurant.com (online ordering site)

**For:** a new Claude chat that works only on the ordering site.
**Owner:** Kiel (approves anything risky).
**Date:** 30 Oct 2026

---

## 1. Scope: stay inside the ordering site

- **Work only on `https://order.hakkarestaurant.com`.** It is a WordPress + WooCommerce site.
- **Do not change `hakkarestaurant.com` (the main site).** Another chat runs it, including:
  - the new booking plugin, **HAKKA Reservations**;
  - the `/reservations/` and `/staff-bookings/` pages;
  - its Rank Math SEO.
- **Links into the main site are fine.**
  - The booking page stays at `https://hakkarestaurant.com/reservations/`. Use that URL for every "Make A Reservation" / "Restaurant Booking" button.
  - A link can pre-pick a location: `https://hakkarestaurant.com/reservations/?loc=port-of-spain` or `?loc=brentwood`.
- **Staff logins:** booking staff use separate accounts on the main site. If ordering staff accounts are needed, they are created here on order., separately.

## 2. How to work

1. **Confirm which site you're on.** Find the WordPress connector for order.hakkarestaurant.com, then call its site-info tool and check the URL before changing anything. The connector named "HAKKA_Restaurant" is the **main** site, so don't use it.
2. **Check backups first.** Ask Kiel to confirm a full backup exists (SiteGround → Backups) before the first change.
3. **Audit before you fix.** List what you find (plugins, theme, menus, categories, payment gateways), then fix the small safe things in §4.
4. **Ask first before:**
   - deleting users, products or plugins;
   - changing payment, checkout or shipping settings;
   - anything that could stop orders coming in.
5. **After changes,** clear the cache (SiteGround Speed Optimizer, or the connector's clear-cache tool) and re-check the page.
6. **Keep a checklist.** Report back in plain language: what was fixed, what Kiel has to do by hand, and what's still open.

**Lessons from the main-site work:**
- **Kiel installs plugins by hand.** The connector may not upload or install plugins. If one is needed, hand Kiel a zip or the plugin name; he uploads it.
- **Some Elementor content can't be edited through the connector.** List items inside widgets (sliders, icon lists, repeaters) are an example. Give Kiel exact click-by-click steps instead.
- **The cloud sandbox may not be able to load the live site in a browser.** Use the connector's REST and read tools to inspect pages.
- **Never print secrets** (API keys, access tokens, passwords) in chat.

## 3. Business facts (use these exactly)

| | **HAKKA Port of Spain** | **HAKKA Brentwood** |
|---|---|---|
| Address | 101 Queen Street, Port of Spain | 200 South Brentwood Avenue, Chaguanas |
| Phone | +1 868-254-7426 | +1 868-334-2552 |
| Map | https://maps.app.goo.gl/hPLKozLHLcosXHbn9 | (on the current site) |
| Hours | Mon–Fri 10am–9pm · Sat 11am–9pm · Sun closed | Mon–Thu 11am–11pm · Fri–Sat 11am–12am (midnight) · Sun 11am–11pm |
| Email | queenstreet@hakkarestaurant.com | booking@hakkarestaurant.com |

- **Brand:** HAKKA Restaurant. The cuisine is Tangra-style Hakka Chinese, Indian and Thai.
- **Colours:** red `#dd3333`, near-black `#111111`, dark backgrounds.
- **Hosting:** SiteGround (SG Optimizer / SG Security), behind Cloudflare.

## 4. Fixes from the audit (do these first)

| # | Problem | Fix |
|---|---|---|
| O-01 | Customers can see an **"Uncategorized"** product category | Move those products into real categories. Hide empty categories from the shop and category bar. |
| O-02 | **The mobile menu shows two link lists.** In the working one, "Make A Reservation" goes to the homepage. Every link in the second list is `href="#"`. | Delete the dead list. Point "Make A Reservation" to `https://hakkarestaurant.com/reservations/`. |
| O-03 | **Dead buttons on `/find-us/`**: "Restaurant Booking" and "Contact us" have an empty `href` | Booking → the reservations URL. Contact → `tel:` links (or a contact page). |
| O-04 | **The admin username `adminhakkar` is public** on `/privacy-policy/`, linked to its author archive | Hide the author on pages and block `/author/` archives and `?author=` lookups. Hide the public `/wp-json/wp/v2/users` list from logged-out visitors. Change that user's display name. **Recommend** (Kiel decides): a new admin with a different username, remove the old one, and turn on 2FA. |
| O-05 | The privacy policy has **12 table-of-contents links to `hakkaexpress.com`** (another site) | Rewrite them as same-page `#section` links. |
| O-06 | The footer copyright sits in an `<h1>` and says **2025** | Change it to a `<p>` with the current year (dynamic). |
| M-09/M-10 | `http://` links and images (mixed content) | Sweep content, menus and widgets for `http://order.hakkarestaurant.com` and `http://hakkarestaurant.com`, and change them to `https://`. Confirm HTTPS is forced. |
| — | Google shows text like *"Calculating fare… Unable to calculate delivery fare"* in search results | Give the homepage and key pages proper SEO titles and meta descriptions (§5), so Google stops picking up that text. |

## 5. SEO and tracking

- **SEO plugin:** check what's installed on order. The main site uses **Rank Math (free)**, so prefer the same if nothing good is already active.
  - Home title: **Order Online for Delivery or Pickup | HAKKA Restaurant**
  - Home description: **Order your favourite HAKKA dishes for pickup or delivery from Port of Spain or Brentwood.**
  - Give product and category pages sensible templates. Keep junk out of the sitemap (cart, checkout, my-account, template and builder post types).
- **Search Console:** get a sitemap ready and tell Kiel what to submit.
- **Tracking (plan it, then ask Kiel for the account IDs):**
  - Google Tag Manager with GA4 and the WooCommerce e-commerce events (`view_item`, `add_to_cart`, `begin_checkout`, `purchase`).
  - Meta Pixel.
  - Cross-domain linking with hakkarestaurant.com.
- **Schema:** each location gets its own Restaurant schema on the main site. On order., only add Organization/WebSite schema if the SEO plugin does it cleanly.

## 6. Hardening and speed

- Update WordPress, the theme and plugins, **one at a time, after a backup**. Afterwards, place a test order with each payment gateway (Fygaro and any others).
- Remove plugins and themes that are inactive or unused. **List them for Kiel first.**
- Check Cloudflare and SiteGround Security are on: login limits, 2FA for admins.
- Speed:
  - images to WebP, lazy loading, caching on;
  - review any preloader GIF;
  - target mobile Lighthouse ≥ 85.
- Check the mobile layout of: home, a category, a product, the cart, checkout and `/find-us/`.

## 7. Phase 2: ordering by location (plan only; build after Kiel gets the answers in §8)

- **Choose a location first.** Ask "Ordering from: Port of Spain / Brentwood" before anything goes in the cart. Pre-pick it from a link (`?loc=port-of-spain` / `?loc=brentwood`) and remember it in a cookie.
- **Separate settings per location:** pickup/delivery, delivery zones and fees, hours and lead times.
- **Menu per location:** hide items a location doesn't sell, with per-location prices if they differ.
- **The right details everywhere:** order emails, receipts and kitchen tickets show that location's address and phone.
- **Each kitchen gets only its own orders.** Check the printer and kitchen-display routing.
- **Switching location with a full cart** warns before removing items that location doesn't sell.
- **`/find-us/`** shows both locations, each with map, hours, phone, and booking and order buttons.

Write up the options before building: plugin vs. custom code, cost, and the risk to the live checkout.

## 8. Questions for Kiel (collect the answers early)

1. Are the menu and prices the same at both locations? If not, what's different?
2. Delivery zones, fees and minimum orders for each location. Pickup lead times.
3. Which payment gateways are live, and can we place real test orders?
4. Which kitchen printer or kitchen-display system does each location use?
5. Is Brentwood ordering live today, and is Port of Spain ordering live yet?
6. Account IDs for GA4, Meta Pixel and Search Console, if they already exist.
7. Can a new admin user replace `adminhakkar`?

## 9. Finished means

- [ ] Every item in §4 is fixed and checked on the live site.
- [ ] No `http://` content remains, and no admin username is visible anywhere.
- [ ] Every reservation link goes to `https://hakkarestaurant.com/reservations/`.
- [ ] The home page and key pages have proper titles and descriptions, and a sitemap is ready.
- [ ] Plugins are up to date, and a test order works with each payment gateway.
- [ ] Mobile pages look right, and Lighthouse mobile performance is ≥ 85.
- [ ] Kiel has written answers to §8 and a short plan for Phase 2.
