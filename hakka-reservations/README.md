# HAKKA Reservations

Table bookings for **HAKKA Port of Spain** and **HAKKA Brentwood** on hakkarestaurant.com.
Built to replace MotoPress Appointment Booking. Full spec: `../docs/booking-plugin-spec.md`.

## What it does

- **Booking form** `[hakka_booking]`:
  - The guest first picks a location, and that location's photo then appears. Every step has a Back button; Back on the first step returns to the choice of location.
  - Next they choose the booking type, number of guests, date, time and their details (including "Are you celebrating something?"). Only times with room are offered.
  - Required fields are marked with *. If anything is missing, the form lists exactly which fields to fix.
  - A pop-up shows the booking details and reference as soon as it is sent.
  - The form uses a dark style to match the site, and it protects itself against the theme's button and input styles.
- **Every booking is confirmed by staff.** The guest is told *"We will call you to confirm your booking."*
- **Emails routed by location** – the guest email is sent from `booking@hakkarestaurant.com`. The staff alert goes to that location's own address (Port of Spain → `queenstreet@`, Brentwood → `booking@`). Both emails show a clear summary card: date, time, party size, booking type, celebration, allergies.
- **Staff screen** at `/staff-bookings/`:
  - It is a full-screen page of its own, with no website header, footer or theme, just the logo and the bookings. Logged-out visitors see a username and password login card.
  - It opens on **Upcoming**, which shows every booking from today on, grouped by day. **Today**, ‹ › and the date picker show a single day.
  - Each staff member logs in with their own account and sees only their location(s). Admins and Reservations Managers see every location, with a location filter.
  - Locations are assigned under Users → Add New / Edit user → **Booking locations**. The Users list has a "Booking locations" column.
  - Staff can confirm, decline (with a message), seat, mark no-shows, edit, add notes, and add phone or walk-in bookings.
  - **Block times:** staff can close a whole day, or particular time slots, to online bookings (e.g. Mother's Day). It can apply to one booking type or all, for one day or a range of days, with a reason. Guests see blocked times as full. Staff can still add phone and walk-in bookings into them. A "Closed to online bookings" panel lists every block, with a button to reopen it.
  - A sound and pop-up alert shows when a new booking arrives.
- **Backend (wp-admin → Reservations)**:
  - **Locations**: header photo with drag-to-position previews for desktop and phone (plus zoom and an optional phone-only photo), a photo for each booking type (shown on its card and in the header when chosen), email routing, opening hours per day (split shifts, midnight close), holidays and special dates, slot length, the "last booking before closing" cutoff, guests per slot, booking types with their own capacity, and header images.
  - **Settings**: sender, logo, colours, form fields, celebration choices, spam protection, data retention.
  - **Messages & Email**: wording for every email, with preview and test send.
  - **Booking staff**: add or remove staff logins.
  - **Reports**: guests per location, no-shows, cancellations, busiest times, CSV export.
- **Guest manage link** `[hakka_manage_booking]` at `/manage-booking/` – the guest can cancel (up to the cutoff) or ask for a change.

## Set up on first activation

| | Port of Spain | Brentwood |
|---|---|---|
| Hours | Mon–Fri 10am–9pm · Sat 11am–9pm · Sun closed | Mon–Thu 11am–11pm · Fri–Sat 11am–midnight · Sun 11am–11pm |
| Last booking | 1 hour before closing | 1 hour before closing |
| Booking types | Private Room 30 · Bar 12 · Dining area 48 | Dining · Event · Corporate Event |
| Guests per slot (whole location) | – (each type has its own limit) | 30 |
| Staff alerts to | queenstreet@hakkarestaurant.com | booking@hakkarestaurant.com |

Other defaults (all editable):
- 1-hour slots
- 2 hours' minimum notice
- Bookings open 60 days ahead
- Largest online party 20 (the Private Room takes up to 30)
- Reminder 24 hours before

## Install

1. Plugins → Add New → **Upload Plugin** → choose `hakka-reservations.zip` → **Install** → **Activate**.
2. Activation creates three pages:
   - `/reservations-test/` – the new booking form, hidden from Google, for testing.
   - `/staff-bookings/` – the staff screen.
   - `/manage-booking/` – the guest's cancel/change page.
3. MotoPress keeps running on `/reservations/` until you switch over.

## Before going live

1. **Email:** make sure `booking@hakkarestaurant.com` and `queenstreet@hakkarestaurant.com` exist and are set up in **Post SMTP**. If Post SMTP forces its own "From" address, allow `booking@`. Then send a test from Reservations → Locations → each location.
2. **Images:** upload a header image for each location (Reservations → Locations → Booking form header image, about 1600 × 600 px). Upload a logo for emails in Settings.
3. **Staff:** add staff accounts (Reservations → Booking staff). Give Marcus the **Reservations Manager** role in Users so he can change hours, the cutoff and capacity.
4. **Test:** make test bookings on `/reservations-test/` at both locations, then confirm them on `/staff-bookings/`.
5. **Switch over:** edit the Reservations page in Elementor and replace the MotoPress "Appointment Form" widget with a **Shortcode** widget containing `[hakka_booking]`. Deactivate MotoPress and its add-ons.

## Notes

- **Links to one location:** `[hakka_booking location="brentwood"]`, or link to `/reservations/?loc=port-of-spain`.
- **Caching:** the form loads availability live, so page caching is safe. The plugin's API and the staff/manage pages are marked "do not cache". Saving a location clears the SiteGround cache.
- **Security:**
  - The plugin hides usernames: author pages and the public user list are turned off (Settings → Hide usernames).
  - Booking staff can't reach wp-admin.
  - SiteGround Security already limits login attempts.
- **WhatsApp** is planned for phase 2. Messages are logged with a `channel` so a WhatsApp sender can be added without changing bookings.
- **Deleting the plugin** keeps all data unless "Also delete all bookings…" is ticked in Settings.
