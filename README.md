# HAKKA-Res-BOOK

Table bookings and the website plan for **HAKKA Restaurant** (hakkarestaurant.com): HAKKA Port of Spain and HAKKA Brentwood.

| Folder | What's in it |
|---|---|
| [`hakka-reservations/`](hakka-reservations/) | The **HAKKA Reservations** WordPress plugin (booking form, staff booking screen, emails, admin). See its [README](hakka-reservations/README.md). |
| [`docs/README.md`](docs/README.md) | The full plan: audit fixes, SEO, two locations, booking plugin |
| [`docs/booking-plugin-spec.md`](docs/booking-plugin-spec.md) | The booking plugin's detailed spec and decisions |
| [`docs/order-site-brief.md`](docs/order-site-brief.md) | Brief for the separate work on order.hakkarestaurant.com |
| [`docs/hakka-reservations.zip`](docs/hakka-reservations.zip) | The latest plugin build, ready to upload |

## Install or update the plugin

1. Download `docs/hakka-reservations.zip`.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, then **Install** (when updating, choose **Replace current with uploaded**).
3. Purge the SiteGround cache.

To make a fresh zip from the source:

```bash
zip -r docs/hakka-reservations.zip hakka-reservations -x '*.DS_Store'
```

Current version: **1.0.7**.
