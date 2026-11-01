# Integrating the redesign into hakkarestaurant.com

Design source: canvas https://claude.ai/artifact/9zHW8Q9G6DD3C3MkbqzW5j (v26).
Target: the live WordPress site (Grand Restaurant theme + Elementor 4.3.5).
Rule: **nothing changes on the live site until you approve it.** Every page is built as a draft, previewed, then swapped in.

## 1. Strategy: keep WordPress, re-skin it

We keep the current theme, Elementor and existing page URLs, so SEO and links survive. The redesign is applied in three layers:

| Layer | What | How |
|---|---|---|
| **Global look** | Fonts (Cormorant Garamond, Poppins, Noto Serif SC), colours (#050505 / #dd3333), pill buttons, dark pattern background, section spacing | One custom CSS file (`hakka-site.css`) with reusable classes (`.hk-pattern`, `.hk-eyebrow`, `.hk-btn`, `.hk-card`…). Elementor sections then just add a class. |
| **Static sections** | Heroes, intro text, location cards, dish cards, private dining, CTAs | Built with normal Elementor widgets (heading, text, image, button, container), so you can still edit text and photos yourself in Elementor. |
| **Interactive parts** | Daily deals tabs, menu tabs/filters/"show all"/upsell, scroll gallery, gallery lightbox, location-aware header & order buttons | A small custom plugin, **HAKKA Site** (in this repo, next to `hakka-reservations/`), that provides shortcodes. You drop a shortcode widget into Elementor where the section goes. |

Why a plugin for the interactive parts: Elementor alone can't do the scroll-driven gallery, day-of-week deals or the menu filter cleanly, and putting the code in a plugin means it survives theme updates and is version-controlled here.

### Shortcodes in the HAKKA Site plugin

| Shortcode | Used on | Content source (editable in WP admin) |
|---|---|---|
| `[hakka_deals loc="all\|pos\|bw"]` | Home, both location pages | New "Daily Deals" settings screen: one row per weekday per location (title, text, price, photo). Auto-selects today (Trinidad time). |
| `[hakka_menu]` | Menu | Existing **menus** post type/categories the current menu page already uses, so no re-typing of 152 dishes. Adds tabs, heat filter, "show all N", and the "Make it a meal" upsell. |
| `[hakka_scroll_gallery loc="pos\|bw"]` | Location pages | A WP media gallery per location (pick photos + captions in admin). |
| `[hakka_gallery]` | /gallery/ | Same two galleries, masonry + lightbox, `#pos` / `#bw` tabs. |
| `[hakka_order_button loc=""]` | Header, CTAs | Builds `https://order.hakkarestaurant.com/?loc=…`. |
| `[hakka_location_info loc=""]` | Location pages, footer, contact | One settings screen for address, phones, hours, email, map link per location; it also feeds the Rank Math schema, so hours are typed once. |

The booking form stays as `[hakka_booking]` from HAKKA Reservations; that plugin gets a CSS update (v1.0.11) so the form matches the new look.

## 2. Page-by-page mapping

| Canvas board | WordPress page | Action |
|---|---|---|
| Header | Elementor header template **#122** | Duplicate → rebuild as two-tier header (utility bar + main bar, white logo, nav, Reserve + Order Now, mobile drawer) → swap the theme setting when approved. |
| Footer | Elementor footer template **#1289** | Same: duplicate, rebuild, swap. |
| Main nav | Menu **#3** (currently only "Menu" and "Find Us" → `#`) | New menu: Menu · Locations (Port of Spain, Brentwood) · Daily Deals · HAKKA Social · Private Dining · Celebrations · Contact. |
| Home | **Home #74** | Draft copy → rebuild: hero, quick strip, deals, choose-your-HAKKA, signature dishes, celebrate (toast photo), private dining/catering, HAKKA delivered. |
| Port of Spain | **new** `/port-of-spain/` | Create as draft. |
| Brentwood | **new** `/brentwood/` | Create as draft. |
| Menu | **Menu #101** | Draft copy → replace the theme food-menu widgets with `[hakka_menu]` + hero + offers. |
| Drinks | **Drinks #1895** | Draft copy → restyle (waiting on cocktail list). |
| Reservations | **Reservations #379** | Keep `[hakka_booking]`; add the how-it-works steps and the hours for both locations around it. |
| Contact | **new** `/contact/` | Create (location info + map + Contact Form 7 form). |
| Celebrations | **Celebrations #234** | Draft copy → restyle; keep existing Brentwood gallery photos. |
| Catering / Private Dining | **Catering #276** | Draft copy → restyle; move the enquiry form into the page (`#enquire`). |
| HAKKA Social | **HAKKA Social #338** | Draft copy → rebuild as Brentwood-only page (waiting on details). |
| Our Story | **Story #1533** | Draft copy → add the history text around the existing video. |
| Gallery | **new** `/gallery/` | Create with `[hakka_gallery]`. |
| Old pages | Catering Form #465, reservations-test #3838, coming-soon #1384 | 301 redirect (`/catering-form/` → `/catering/#enquire`), then trash the test pages. |

**How a draft is swapped in:** I duplicate the live page, build on the copy, and send you a preview link. When you approve, the copy's layout is copied onto the original page ID, so the URL, SEO settings and inbound links stay the same. The old version stays in revisions for a one-click rollback.

## 3. Media

- Upload only the photos actually used (POS webp set, minus Buddha/statue images, plus the live Brentwood photos already in the library) with proper file names and alt text (e.g. `hakka-port-of-spain-private-room.webp`).
- Videos: placeholders (poster images) until you send the final videos. Upload them as MP4 (H.264, ~1080p, under 8 MB for heroes, muted, no audio track). Large files can go on the CDN or YouTube/Vimeo if the hosting struggles.
- New food photos: drop-in replacements in the dish cards and the menu once you send them.

## 4. SEO and technical clean-up (same rollout)

- Site URL to `https://`, set the timezone to `America/Port_of_Spain` (needed for "today's deal").
- Rank Math: one Restaurant schema per location (address, phone, correct hours, menu URL, reservation URL), titles/descriptions for every page, sitemap includes the new pages.
- Redirects for retired URLs; check for 404s after launch.
- Switch off the LoftLoader preloader (it slows first paint). After a week on HAKKA Reservations with no issues, deactivate MotoPress.
- Clear the SiteGround cache after each swap.

## 5. Rollout order

1. **Foundation** (invisible to visitors): HAKKA Site plugin installed, global CSS, fonts, location info and deals settings filled in, media uploaded.
2. **Header + footer** drafts → approve → swap (the whole site instantly feels new).
3. **Home + Port of Spain + Brentwood**: the CEO's priority (both stores clear).
4. **Menu + Reservations** (the money pages).
5. **Contact, Gallery, Celebrations, Catering, HAKKA Social, Story, Drinks.**
6. **Redirects, SEO, schema, plugin clean-up**, then a final link and mobile check of every page.

Each step is reviewed on a preview link before going live. Steps 1 to 3 can start now with placeholders. Videos and new food photos slot in later without rebuilding anything.

## 6. Recommended: staging first

SiteGround includes one-click **staging** (Site Tools → WordPress → Staging). Building on a staging copy and pushing it live in one go is safer than drafts for the header/footer swap, because those affect every page at once. If staging isn't an option, the draft-and-swap method above still works.

## 7. Still needed from you

- Videos (POS story, food reels, toast animation)
- New food photos
- Daily deals for each day (and whether they differ by location)
- HAKKA Social details: capacity, equipment, karaoke/live-music nights, how to book
- Cocktail list; price/VAT note; Brentwood opening year; Port of Spain menu PDF (if different)
- Confirm the private room / bar / dining room photos for Port of Spain
- Should "Pork" (on the order site) appear on the main menu?
- Go-ahead on staging vs drafts
