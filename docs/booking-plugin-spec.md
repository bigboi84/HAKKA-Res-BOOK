# HAKKA Reservations Plugin: Build Spec

**For:** the build session (hakkarestaurant.com only) · **Written:** 30 Oct 2026
**Replaces:** MotoPress Appointment Booking on `/reservations/`
**Plan reference:** `README.md` §4 in this folder

---

## 0. What exists today (reviewed on the live site)

| Item | Current state |
|---|---|
| Stack | WordPress 7.1.4, Grand Restaurant theme, Elementor 4.3.5, Rank Math, SiteGround caching, Post SMTP, WPConsent |
| Booking | **MotoPress Appointment Booking** (Lite 2.4.8 + Pro 2.1.0, with Twilio SMS, Square, Google Analytics and Checkout Fields add-ons). The booking form is the Elementor `appointment-form` widget on `/reservations/` (page ID 379). |
| MotoPress setup | 1 location ("HAKKA Restaurant"), 1 employee ("HAKKA Staff"), 3 services (Dining, Event, Corporate Event). Time step 30 min. Default status *pending*, confirmation mode *manual*, payments off, timezone UTC-4, terms page 1327. |
| Opening hours shown on site | Mon–Thu 11am–11pm · Fri–Sat 11am–12am (midnight, fixed this session) · Sun 11am–11pm. One theme setting still says "10:00am - 11:00PM". |
| Current slot length | Per the owner: **1 hour** |
| Other forms | Contact Form 7 and JetFormBuilder (catering form) |
| Email | Post SMTP is installed. The site admin email is `kieljoseph@hotmail.com`. |
| WooCommerce | **Not installed** on this site. It lives on order.hakkarestaurant.com, which is out of scope for this build. |

---

## 1. Requirements (from the owner)

1. A plugin with a **backend** where the admin can **select a location and link it to an email**, so each location's bookings go to its own email.
2. Two locations: **HAKKA Port of Spain** and **HAKKA Brentwood**.
3. **Backend options for everything else**, including time slots. Default slot = **1 hour**.
4. **Different opening and closing hours for different days.** Weekends and Friday open at different times than weekdays.
5. **No deposits.**
6. **WhatsApp: DEFERRED (phase 2).** Leave it out of the first build, but keep the plugin ready for it (see §3.4). The owner will ask the CEO about the WhatsApp setup, so no decision yet. The Business account already exists.
6b. **Every booking must be confirmed by staff.** There is no auto-confirm. Emails tell the guest: *"We will call you to confirm your booking."*
7. A **password-protected front-end screen** where staff see bookings and **confirm** them.
8. **Email by location (decided 30 Oct 2026):** the guest email is sent **from `booking@hakkarestaurant.com` for both locations**. The **staff alert goes to a different address for each location**: Port of Spain → `queenstreet@hakkarestaurant.com`, Brentwood → `booking@hakkarestaurant.com`. The staff alert address is set per location in the backend.
9. **Booking types (seating areas) with their own capacity.** A booking is for a type, e.g. *Private room*, *Bar*, *Dining area*. Each type has a number for how many people it holds per time slot. See §3.1 "Booking types" and the Port of Spain example in §8.
10. **Beautiful, clear booking emails.** When someone books, the **guest** gets an email and the **location's email** gets one. Both lay out all the details clearly (party size, date, time, booking type, notes). See §6.

---

## 2. Plugin basics

- **Name:** HAKKA Reservations · **Folder/slug:** `hakka-reservations` · **Text domain:** `hakka-reservations`
- Prefix everything `hkr_` (functions, options, tables, hooks, CSS classes)
- Requires PHP 7.4+ and WordPress 6.0+ (the site runs 7.1.4 and PHP 8.3)
- Plain WordPress code, no build tooling required. Admin screens use the WordPress Settings API and standard admin UI.
- All strings translatable. All output escaped. All input sanitized. Nonces on every form and AJAX/REST write. Capability checks on every admin action.
- Timezone: always use `America/Port_of_Spain` (UTC-4, no daylight saving)

---

## 3. Admin backend (wp-admin → "Reservations")

Menu: **Reservations** with sub-pages: *Bookings · Locations · Settings · Messages & Email · Reports* (WhatsApp is added in phase 2).

### 3.1 Locations (the core screen)

A list of locations, with **Add / Edit / Deactivate**. Each location has these fields, grouped into tabs:

**General**
| Field | Type | Notes |
|---|---|---|
| Name | text | e.g. "HAKKA Port of Spain" |
| Slug | text | `port-of-spain`, `brentwood`. Used in `?loc=` links and shortcodes. |
| Active | toggle | Inactive locations are hidden from the form |
| Accepts online bookings | toggle | |
| Address | textarea | |
| Phone | text | |
| Google Maps link | url | |
| Display order | number | |
| **Booking form header image** | image upload (media library) | The big header image at the top of the booking form. It changes to this image when the guest selects this location. Recommended size 1600 x 600 px, JPG/WebP, under 300 KB. The owner will upload these in the backend after the build. |
| Image alt text | text | For accessibility |
| Email header image (optional) | image upload | Used at the top of this location's emails. Falls back to the form header image, then to the HAKKA logo. |

**Email routing (the point of this plugin)**
| Field | Type | Notes |
|---|---|---|
| **Staff booking alert email(s)** | text, comma-separated | New-booking alerts for *this* location go here. Port of Spain: `queenstreet@hakkarestaurant.com`. Brentwood: `booking@hakkarestaurant.com`. |
| **Guest email From name** | text | Defaults to the global setting (e.g. "HAKKA Restaurant"). Can be overridden per location (e.g. "HAKKA Port of Spain"). |
| **Guest email From address** | email | **Defaults to the global setting `booking@hakkarestaurant.com`, which both locations use.** Can be overridden per location if that ever changes. |
| **Reply-to address** | email | Defaults to the From address |
| BCC (optional) | email | |
| Test button | button | "Send test guest email" and "Send test staff alert" for this location |

The global default sender (`booking@hakkarestaurant.com`) lives in *Settings → Email*. A location only fills in its own From fields if it should differ from the default. The staff alert address is always set per location.

**Opening hours (different for each day)**
- A row for each day, Monday to Sunday, with:
  - **Open / Closed** toggle
  - **One or more time ranges** (Open time → Close time), with "Add another range" for split service such as lunch and dinner
  - Close time can be **12:00am / midnight** (treated as the end of that day)
- Pre-loaded defaults (editable): Mon–Thu 11:00am–11:00pm · Fri–Sat 11:00am–12:00am · Sun 11:00am–11:00pm
- A "Copy these hours to…" helper (copy Monday to all weekdays, copy one day to another)
- **Special dates** table: a date (or date range) with an override, either *Closed* (holiday) or *Custom hours* (e.g. Christmas Eve), plus an optional note

**Slots and capacity**
| Field | Default | Notes |
|---|---|---|
| Slot length (minutes) | **60** | The booking duration and the step between start times. Options: 30 / 45 / 60 / 90 / 120, or custom. |
| Start-time step (minutes) | 60 | How often a slot starts (e.g. every 30 min). Default equals the slot length. |
| **Last booking before close (minutes)** | **60 (decided for both locations)** | No booking can start in the final hour before closing. The latest start time = closing time minus 60 minutes. **A setting in the backend, so it can be changed at any time** (per location, and optionally per day) without touching code. |
| **Max covers (guests) per slot, whole location** | 40 (Brentwood: **30**) | Total guests booked at the same start time across all booking types |
| Max bookings per slot | blank = unlimited | |
| Min party size | 1 | |
| Max party size (online) | 10 | Larger parties see "Please call us" or are sent as a request |
| Large-party threshold | blank | Parties at or above this always need manual confirmation |
| Minimum notice (hours) | 2 | |
| Book up to (days ahead) | 60 | |
| Change/cancel cutoff (hours before) | 2 | |
| **Per-day overrides** | | Each day can optionally override slot length, covers per slot and max party size |

**Booking types and capacity**

Each location has its own list of booking types. The admin can add, rename, reorder, activate and deactivate them. A booking type can be a *purpose* (Brentwood: **Dining, Event, Corporate Event**, the options on the current form) or a *seating area* (Port of Spain: **Private Room, Bar, Dining area**). The plugin treats both the same way.

| Field | Type | Notes |
|---|---|---|
| Name | text | e.g. "Private Room", "Bar", "Dining area" |
| Description | text | Shown to the guest on the form |
| **Capacity per time slot** | number, optional | How many people this type holds in one slot. Leave blank if the type has no limit of its own and only the location-wide limit applies (as at Brentwood). |
| **Ask about a celebration** | toggle | When on, the form shows the celebration question (see §5.1) once the guest picks this type. On by default for Dining, Event and Corporate Event. |
| Hard maximum | number, optional | The most it can take if staff squeeze guests in (staff can book up to this, online bookings stop at the capacity) |
| Min party size | number | e.g. a private room can require a minimum |
| Max party size (online) | number | |
| Available days / hours | optional | Restrict a type to certain days or times, otherwise it follows the location's hours |
| Requires manual confirmation | toggle | e.g. always for the private room |
| Active | toggle | |

- The guest picks the booking type on the form. A type that is full for that slot is shown as unavailable.
- A type with its own capacity is counted **per type, per slot**: a full Bar doesn't block the Dining area.
- The **location-wide "Max covers per slot"** is a separate ceiling across all types. Brentwood uses this: **30 people per slot in total**, shared between Dining, Event and Corporate Event.
- A booking must fit both its own type's capacity (if set) and the location-wide limit.
- If a location only has one area, the booking type step is hidden.

**Confirmation**
| Field | Default |
|---|---|
| Confirmation | **Always manual.** Every booking is saved as *Pending* until staff confirm it. There is no auto-confirm option in the first build. |
| Confirmation note shown to the guest | "We will call you to confirm your booking." Editable per location. |

**Staff access**
| Field | Notes |
|---|---|
| Staff users | Which staff user accounts can see this location's bookings (see §3.1b). Assigned on the user's profile or here. |
| Reminder timing | Hours before the booking (default 24) |

### 3.1b Who can change settings
- Administrators can change everything.
- The plugin adds a **Reservations Manager** role (and a `hkr_manage` capability). A person with it can edit locations, hours, the last-booking cutoff, slot capacity, booking types and special dates, and see bookings, without being a full WordPress administrator. Use it for managers such as Marcus.
- **Booking staff accounts (decided 30 Oct 2026):** each staff member gets their **own user account** with a custom role, **HAKKA Booking Staff**. The role can only see and manage bookings. It cannot touch pages, plugins, users, settings or anything else, and its members are sent to the staff booking screen after login (not the WordPress dashboard).
  - Each staff user is assigned **one or more locations** (a field on their profile). They only see bookings for those locations.
  - Why individual accounts: the booking log shows who confirmed or changed each booking, a person's access can be switched off without changing a shared password, and there's no shared password to leak.
  - Login uses the normal WordPress login with protections: no username shown anywhere on the public site (author archives off), limit login attempts, optional two-step login, and an idle session timeout.
  - The plugin has an **Add booking staff** screen: name, email, locations, and it sends them a set-your-password email.
  - The food-ordering site (order.hakkarestaurant.com) is a separate WordPress site, so its staff accounts are separate. If one login for both is wanted, that's a separate piece of work (see §10).
- Every settings change is recorded (who, what, when) in the booking log.

### 3.2 Settings (global)
- Business name · default country code (+1 868) · date/time format
- **Terms and cancellation text**, plus a page link (reuse page 1327)
- Booking form fields: toggle on/off and required/optional for *Occasion*, *Seating preference*, *High chair*, *Dietary / allergy notes*, *Special requests*; editable list of occasion and seating options
- Spam protection: honeypot (always on), rate limit (bookings per IP per hour), optional Cloudflare Turnstile keys
- Staff screen: page URL (default `/staff/bookings/`), session length, failed-login lockout
- Data retention: delete or anonymise guest data after N months
- Uninstall: keep or delete data

### 3.3 Messages & Email
- Templates for: *request received (guest) · confirmed · declined / new time suggested · reminder · cancelled · staff new-booking alert · staff change/cancel alert*
- Each has a subject and an HTML body with placeholders: `{guest_name} {party_size} {date} {time} {end_time} {location_name} {location_address} {location_phone} {location_map_link} {booking_ref} {manage_link} {notes} {occasion}`
- A **preview** and **send test** button for each
- Calendar file (`.ics`) attached to the confirmation email
- All sending goes through `wp_mail()` (Post SMTP delivers it), **setting From/Reply-To/BCC from the booking's location**. Never send one location's booking from or to another location's address.

### 3.4 WhatsApp — DEFERRED (not in the first build)

**Decision (30 Oct 2026):** leave WhatsApp out for now. The owner is meeting the CEO and will ask about it. Don't build the screens below yet, but **design for it**: keep the message log table's `channel` column (`email` now, `whatsapp` later), send every message through one internal "send message" function that can gain a second channel, and store the guest's phone number in international format.

Planned for phase 2 (kept here as the note):
- Settings: *Enable WhatsApp*, access token, phone number ID and business account ID (stored encrypted, never echoed back), API version
- **Per-location sender**: which WhatsApp number/phone number ID is used for each location (or one shared number)
- Template mapping: each message type → the approved Meta template name and language, with the order of placeholders
- A test message button. Failures are logged and the email still goes out.
- A guest opt-in checkbox on the form. No opt-in = no WhatsApp.
- Also adds: the *Message us on WhatsApp* button in guest emails, and a WhatsApp link next to the guest's phone on the staff screen

**Questions to ask the CEO:** one WhatsApp number for both locations, or one each? Which number(s) are on the Business account, and is it connected to Meta's Cloud API (not only the phone app)? Who will own the Meta Business account? WhatsApp templates need Meta's approval, so allow time.

---

### 3.5 Bookings (admin list)
- WP-style table: reference, date/time, location, guest, party size, status, source, created
- Filters: location, status, date range. Search by name, phone, email, reference.
- Bulk actions: confirm, cancel, mark no-show, export CSV
- Edit screen: all fields, status, internal notes, history log (who changed what, when)
- "Add booking" for phone/walk-in
- Admins see all locations

### 3.6 Reports
- Covers and bookings per day/week per location, no-show and cancellation rates, busiest slots. CSV export.

---

## 4. Data model

Custom tables (created on activation, versioned for upgrades). Do not use post meta for bookings.

- `{prefix}hkr_locations`: id, name, slug, active, accepts_bookings, address, phone, map_url, sort_order, `settings` (JSON for slot/capacity/confirmation options), `hours` (JSON, per weekday), `special_dates` (JSON), notification_emails, from_name, from_email, reply_to, bcc, whatsapp_phone_id, staff_password_hash, timestamps
- `{prefix}hkr_areas` (booking types): id, location_id, name, description, capacity, hard_max, min_party, max_party, available_days/hours (JSON, optional), requires_manual_confirm, active, sort_order
- `{prefix}hkr_bookings`: id, ref (unique, e.g. `HK-1042`), location_id, **area_id**, start_datetime, end_datetime, party_size, status (`pending|confirmed|seated|completed|no_show|cancelled|declined`), source (`web|phone|walkin|staff`), guest_name, guest_phone, guest_email, celebration_type, celebration_text, seating_pref, high_chair, guest_notes, internal_notes, whatsapp_optin, manage_token_hash, reminder_sent_at, created_at, updated_at, created_by
- `{prefix}hkr_booking_log`: id, booking_id, action, old_value, new_value, actor, created_at
- `{prefix}hkr_message_log`: id, booking_id, channel (`email|whatsapp`), template, to, status, error, created_at

Index `location_id, area_id, start_datetime` and `status`.

**Availability rule:** a slot is offered for a booking type if the location is open, the date isn't blocked, the slot is within the notice/advance limits, and `SUM(party_size)` of non-cancelled bookings **of that type** overlapping the slot, plus the new party, is within that type's capacity. The location-wide "max covers per slot" (§3.1) is an optional overall ceiling on top of this. Check again in a transaction at booking time to avoid double-booking.

**Last-booking rule (decided 30 Oct 2026):** at both locations, bookings stop **1 hour before closing**. The last bookable start time is the day's closing time minus 60 minutes, and it applies to each time range in a day. Examples with the 1-hour slots:

| Location / day | Closes | Last bookable start |
|---|---|---|
| Port of Spain, Mon-Fri | 9:00pm | 8:00pm |
| Port of Spain, Sat | 9:00pm | 8:00pm |
| Port of Spain, Sun | Closed | none |
| Brentwood, Mon-Thu | 11:00pm | 10:00pm |
| Brentwood, Fri-Sat | 12:00am | 11:00pm |
| Brentwood, Sun | 11:00pm | 10:00pm |

(Brentwood's hours are still to be confirmed, see §8.) The cutoff is a backend setting, so a manager can change it at any time. A special date with custom hours uses the same rule on its own closing time. This applies to online bookings and to bookings staff add.

**Hours rule:** a day's close time of midnight means 24:00 of that same day. Friday 11:00–24:00 is valid and the last slot is computed from it.

---

## 5. Front end

### 5.1 Booking form
- **Location header image (decided 30 Oct 2026):** the top of the form shows the selected location's header image. When the guest switches location, the image **cross-fades** to that location's image, and the location name, address and phone under it update too. Images are uploaded per location in the backend (§3.1). If a location has no image, show a default HAKKA image. The image carries alt text, is lazy-loaded and sized for mobile.
- Shortcode `[hakka_booking location="auto"]` plus an Elementor-friendly wrapper (a plain shortcode widget is enough)
- Steps: **Location → Booking type → Party size → Date → Time slot → Details → Confirm**
- Location is read from `?loc=` or the visitor's saved choice (cookie `hkr_loc`), and can be changed
- Date picker greys out closed days and blocked dates. Time slots are the buttons for the chosen location, booking type and date, showing only slots with room (remaining space can optionally be shown, e.g. "6 spots left").
- Fields: full name, phone, email, celebration (see below), seating preference, high chair, dietary/allergy notes, special requests, terms checkbox
- **Celebration question (decided 30 Oct 2026):** after the guest picks a booking type that has *Ask about a celebration* on (Dining, Event, Corporate Event), show a box:
  - **"Are you celebrating something?"** with options **None · Birthday · Anniversary · Other**
  - If **Other** is chosen, show a text box: *"Tell us what you're celebrating"* (free text, up to about 100 characters)
  - Optionally a note box for details (e.g. the name of the birthday person)
  - The answer is saved on the booking and shown on the staff screen and in both emails (see §6)
  - The options list is editable in *Settings* (admin can add more, such as Graduation or Retirement)
- Over the max party size: show "Please call {phone}" or send a request for approval
- Accessible, mobile-first, styled to match the site (black/red theme, Poppins), CSS variables for colours
- After submit: success message with the reference and the line "We will call you to confirm your booking", plus the emails as above

### 5.2 Manage link (guest)
- Signed token link from the email lets the guest view, cancel, or request a change, up to the cutoff

### 5.3 Staff screen
- Page created on activation: `/staff/bookings/` (shortcode `[hakka_staff_bookings]`), `noindex`, excluded from caching and sitemaps
- **Login:** staff log in with their own WordPress user account (role *HAKKA Booking Staff*, §3.1b) and land on this screen. They see only the locations assigned to them. Managers and administrators see all locations. Login attempts are limited and idle sessions expire.
- **Today view** (default) with date navigation and a live-refresh (polling) list. Pending count badge.
- Each booking shows time, name, party size, phone (tap to call), occasion, seating, allergy notes, special requests, status
- Actions: **Confirm · Decline / suggest another time (with message) · Seated · Completed · No-show · Cancel · Edit · Add internal note**
- **Add booking** (walk-in / phone)
- Sound and pop-up alert when a new booking arrives (while the page is open)
- Search and filter by status and date

---

## 6. Booking emails (guest and location)

Two emails go out for every new booking: one to the guest and one to the location's staff. Both are built to be **beautiful and easy to scan**: branded, mobile-friendly, with the key facts in a clear summary block at the top.

### 6.1 To the guest: "We've received your booking" / "Your booking is confirmed"
- **From:** HAKKA Restaurant <booking@hakkarestaurant.com> (the same for both locations). **Reply-to:** `booking@hakkarestaurant.com`. The email body names the location, address and phone number the guest booked.
- **Design:** HAKKA logo and black/red brand header, a clear status banner (*Request received* or *Confirmed*). While the booking is pending, the banner reads **"We will call you to confirm your booking"**, and the status shows *Pending confirmation*, then a summary card, then location details, then buttons.
- **Summary card (everything laid out clearly):**
  - Booking reference
  - **Location** name and full address, with a *Get directions* button
  - **Date** (e.g. Friday, 6 November 2026) and **time** (start to end)
  - **Party size** (e.g. 4 guests)
  - **Booking type** (e.g. Dining area, Bar, Private Room)
  - Guest name, phone, email
  - **Celebration** (e.g. Birthday, Anniversary, or the text the guest typed), seating preference, high chair
  - Dietary / allergy notes and special requests
  - Status (*Pending confirmation* or *Confirmed*)
- **Buttons:** *Add to calendar* (.ics attached too), *Manage / cancel booking*, *Call the restaurant*
- **Footer:** location address, phone, opening hours, the cancellation policy and a short note on what happens next ("Our team will call you on {guest_phone} to confirm your booking. Your table isn't guaranteed until we do.")

### 6.2 To the location: "New booking"
- **To:** the location's staff alert email: `queenstreet@hakkarestaurant.com` for Port of Spain, `booking@hakkarestaurant.com` for Brentwood. **From:** `booking@hakkarestaurant.com`. **BCC:** optional.
- **Subject** shows the key facts at a glance, e.g. `New booking: Sat 7 Nov, 6:00pm · 4 guests · Dining area · Jane Smith (HK-1042)`
- Same clean summary card as the guest's, plus:
  - Guest phone as a tap-to-call link
  - Internal flags: *large party*, *allergy note*, *special occasion*
  - A **Confirm booking** button (opens the staff screen) and a link to the staff screen
  - How full that booking type is for that slot (e.g. `Dining area: 12 of 44 booked`)

### 6.3 Other emails
Confirmed · Declined / new time suggested · Reminder · Cancelled · Changed. All share the same layout, and the staff versions go to the location email.

### 6.4 Build notes
- Table-based HTML with inline CSS (works in Gmail, Outlook and Apple Mail), 600px wide, readable on a phone, with a plain-text version as the fallback
- Colours and logo come from plugin settings
- Editable in *Messages & Email*, with a live preview and a "send test" button
- Sent with `wp_mail()` through Post SMTP, with the From address set from the global default (overridable per location) and the staff alert sent to the booking location's alert address

---

## 6b. Notifications flow

| Event | Guest | Staff (location email) |
|---|---|---|
| Booking submitted (status *Pending*) | Email: "Request received. We will call you to confirm your booking." | Email alert (+ sound on the staff screen) |
| Staff call the guest and press **Confirm** | Email: "Confirmed" + `.ics` | |
| Staff decline / suggest a new time | Email | |
| Guest cancels or changes | Email confirmation | Email alert |
| Reminder (default 24h before, confirmed bookings only) | Email | |

No booking is ever confirmed automatically. WhatsApp messages are added in phase 2 (§3.4).

---

## 7. Migration from MotoPress (build beside it, then switch)
1. **Build and test on a new, hidden page** (e.g. `/reservations-test/`, `noindex`, not in the menu or sitemap). The live `/reservations/` page keeps MotoPress running during this time. Customers see no change.
2. Test every flow on the test page: bookings at both locations, the celebration question, capacity limits, last-booking cutoff, guest and staff emails, staff screen and logins.
3. When it works, export MotoPress's future bookings (REST route `/motopress/appointment/v1/bookings/reservations` or the `mpa_*` data) and import them as Brentwood bookings, **confirming with the owner** which are real.
4. **Go live:** replace the `appointment-form` widget on `/reservations/` (page 379) with `[hakka_booking]`.
5. Keep MotoPress deactivated, not deleted, for a few weeks. Then remove it and its Twilio, Square, Google Analytics and Checkout Fields add-ons (owner has approved removal once the new plugin works).
6. The "Make Reservation" buttons across the site already point to `/reservations/`, so nothing else changes.

---

## 8. Locations to create on first run

### HAKKA Port of Spain (details supplied by the owner)
| Field | Value |
|---|---|
| Slug | `port-of-spain` |
| Staff booking alert email | `queenstreet@hakkarestaurant.com` |
| Guest email sent from | `booking@hakkarestaurant.com` (shared with Brentwood) |
| Phone | 254-7426 (+1 868 254-7426) |
| Google Maps | https://maps.app.goo.gl/hPLKozLHLcosXHbn9 |
| **Hours** | **Monday to Friday: 10:00am - 9:00pm · Saturday: 11:00am - 9:00pm · Sunday: Closed** |
| Slot length | 1 hour (default) |
| Street address | **101 Queen Street, Port of Spain** |

**Booking types for Port of Spain** (owner confirmed: use the higher number as the capacity)
| Booking type | Capacity per slot | Notes |
|---|---|---|
| Private Room | **30** | |
| Bar | **12** | |
| Dining area | **48** | |

The capacity is the number that online bookings stop at. The separate "hard maximum" field stays available in the admin, but it is left blank for Port of Spain.

---

### HAKKA Brentwood (found on the current website and in MotoPress, 30 Oct 2026)
| Field | Value | Source |
|---|---|---|
| Slug | `brentwood` | |
| Address | 200 South Brentwood Avenue, Chaguanas | Footer, reservations page |
| Phone | +1-868-334-2552 | Owner, footer, homepage |
| Google Maps | https://www.google.com/maps/place/Hakka/@10.5046519,-61.4080961,17z/data=!3m1!4b1!4m6!3m5!1s0x8c35f94d25015e3d:0xab1ec19b15869914!8m2!3d10.5046519!4d-61.4055212!16s%2Fg%2F11rz09v3lr (coordinates 10.5046519, -61.4055212). The short link https://maps.app.goo.gl/bJTW3FgporBSnryh9 is also in use on the site. | Owner, 30 Oct 2026 |
| Public email (footer) | `info@hakkarestaurant.com` | Footer |
| **Staff booking alert email** | **`booking@hakkarestaurant.com`** | Owner, 30 Oct 2026 |
| Guest email sent from | `booking@hakkarestaurant.com` | Owner, 30 Oct 2026 |
| **Hours shown to customers** | Mon-Thu 11am-11pm · Fri-Sat 11am-12am · Sun 11am-11pm | Reservations page (Fri-Sat fixed from "12pm" this session) |
| Hours in the theme settings | "10:00am - 11:00PM" | Theme setting (not used on the page, but inconsistent) |
| **Hours in the booking system (MotoPress)** | **10:00am-9:00pm, every day, 7 days** | MotoPress schedule |
| Time-slot step in MotoPress | 30 minutes | MotoPress settings |
| Capacity in MotoPress | 1 booking per slot (there is no guest or seat count) | MotoPress calendar |
| Special dates in MotoPress | Closed 16-17 Feb 2026 · 11 May 2025 (Mother's Day) custom hours 7:30pm-10:00pm | MotoPress schedule |
| Booking types in MotoPress | Dining (categories: Birthday, Reservation) · Event · Corporate Event | MotoPress |
| **Booking types** | **Dining, Event, Corporate Event** (the options on the current form) | Owner, 30 Oct 2026 |
| **Capacity** | **30 people per time slot**, for the whole location (shared across the three types) | Owner, 30 Oct 2026 |
| Separate seating areas | None given. Add later in the backend if needed. | |

**Decision (30 Oct 2026): use the website hours (11am to 11pm; Fri-Sat to midnight as shown on the site), not the 10am-9pm MotoPress schedule.** Bookings still stop 1 hour before closing. *(Previously noted discrepancy:)* the website says Brentwood is open 11am-11pm (to midnight on Fri-Sat), but the booking system only accepts bookings from 10am to 9pm every day. The new plugin needs one set of hours per day. Is the booking window meant to be shorter than opening hours (e.g. last bookings at 9pm), or are the booking hours out of date?

Note: the Port of Spain hours supplied by the owner (Mon-Fri 10am-9pm) look similar to the MotoPress schedule. Worth checking that the MotoPress schedule wasn't set up with those hours in mind.

---

## 9. Testing before launch
- Unit-level checks for: hours parsing (including midnight close, closed days and special dates), slot generation (60-minute and 30-minute), capacity maths, double-booking prevention
- Email routing: a booking at each location produces a guest email from that location's From address and a staff alert only to that location's email. No cross-sending.
- Staff screen: wrong password, lockout, one location can't see another's bookings
- Mobile and desktop form pass, keyboard use, screen-reader labels
- Caching: the form, availability and staff screen must never be served from the SiteGround cache (exclude their URLs/endpoints)

---

## 10. Open items for the owner
1. ~~Email addresses~~ Decided: guest emails come from `booking@hakkarestaurant.com` for both locations. Staff alerts go to `queenstreet@hakkarestaurant.com` (Port of Spain) and `booking@hakkarestaurant.com` (Brentwood). Both mailboxes must exist and be set up in Post SMTP (SPF/DKIM) so email delivers.
2. ~~Port of Spain address~~ 101 Queen Street, Port of Spain.
2a. ~~Brentwood booking types and capacity~~ Decided: Dining, Event, Corporate Event, 30 people per slot (whole location).
2d. ~~Brentwood hours~~ Decided: website hours (11am-11pm), bookings stop 1 hour before closing.
2b. ~~Capacity numbers~~ Decided: Port of Spain uses the higher numbers (Private Room 30, Bar 12, Dining area 48).
2c. ~~Private room rules~~ Decided: use the setup Deanna sent (Private Room capacity 30). No other rules given, so no minimum party size and normal confirmation (every booking is confirmed by staff).
3. ~~WhatsApp~~ Deferred. The owner will ask the CEO (see §3.4 for the questions).
4. Confirm the **default slot length** at each location (1 hour assumed), and the covers limit per slot
5. ~~Confirmation~~ Decided: every booking is confirmed by staff, and the emails say we will call to confirm.
6. ~~Staff login~~ Decided: individual user accounts with a custom booking-staff role (§3.1b). Open: does the owner want one login to also work on order.hakkarestaurant.com? (Separate site, so it would need extra work.)
7. Large parties: from what size, and a request-only flow?
8. ~~MotoPress~~ Decided: build first on a test page, then remove MotoPress once the new plugin works (§7). Still to confirm: which existing MotoPress bookings should be imported.
