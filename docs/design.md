# Design

How the site looks and behaves. The design is based on the official **Ramada by Wyndham Multan** website ([ramadamultan.com](https://ramadamultan.com)) and on the Wyndham booking pages its "Book Now" button leads to. We copy its look and layout, fix its weak points, and add the features in [requirements.md](requirements.md).

All values below were measured from the live site in October 2026. Screenshots of every page and step are in [design-reference/](design-reference/). **Look at the matching screenshot before building or changing a page.**

**Rule:** where this file and the Ramada site differ, follow this file. Where this file and `requirements.md` differ on *what* to build, follow `requirements.md`.

---

## 1. Brand

- **Name:** Ramada by Wyndham Multan. The hotel has given permission to use its name, logo and photos for this project (see D11 in requirements.md).
- **Logo and photos:** ask the hotel for the original files (logo as PNG/SVG, photos in full size). Don't use low-quality copies saved from their website. Store them in `assets/images/`.
- **Tagline:** "The Spot of Serenity & Comfort in Multan". Footer slogan: "SAY HELLO TO RED".
- **Footer credit:** "Developed as a final year project, University of Southern Punjab, Multan."

---

## 2. Design tokens

Put these in one CSS file (`assets/css/style.css`) as CSS variables. Use the variables everywhere; don't type colour codes into pages.

### Colours

| Variable | Value | Used for | Ramada value |
|---|---|---|---|
| `--red` | `#D21242` | Main brand colour: section titles, buttons, active links, footer, icons | same |
| `--red-dark` | `#A80E35` | Hover and pressed state of red buttons and links | *(not on Ramada)* |
| `--text` | `#333333` | Body text | `#9C9C9C`, too light; **changed** |
| `--heading` | `#3D3935` | Dark headings and labels | same |
| `--subtitle` | `#222222` | Section subtitles | same |
| `--muted` | `#707070` | Hints, placeholders, small print | same |
| `--white` | `#FFFFFF` | Page background, header | same |
| `--bg-alt` | `#F5F6F6` | Background of every second section (e.g. room cards) | same |
| `--bg-card` | `#EDEDEE` | Room cards, room sidebar box | same |
| `--bg-input` | `#F5F5F5` | Form fields, tab bar | same |
| `--line` | `#EDEDED` | Section divider, photo frames | same |
| `--line-light` | `#EEEEEE` | Lines between list items | same |
| `--icon-circle` | `#E6E7E8` | Grey circles behind contact icons | same |
| `--dark-bar` | `#352126` | Booking summary bar on the checkout page | same (Wyndham) |
| `--success` / `--danger` / `--warning` | Bootstrap defaults | Status badges and alerts in the account and admin pages | *(not on Ramada)* |

### Font

- **Fira Sans** from Google Fonts, weights **300, 400, 600**. One font for the whole site.
- Ramada's headings *look* thin because only weight 300 is loaded. To match that look, use **400** for headings, not bold.

| Element | Size | Weight | Colour | Extra |
|---|---|---|---|---|
| Section title | 37px (28px on phone) | 400 | `--red` | centred |
| Section subtitle | 28px (20px on phone) | 300 | `--subtitle` | centred, letter-spacing 1.2px |
| Card title / sub-heading | 25px | 400 | `--red` | |
| Nav link | 18px | 400 | `#3C3134` | letter-spacing −0.5px |
| Body text | 16px | 300 | `--text` | line-height 1.8, letter-spacing 0.5px |
| Small text / card details | 15px | 300 | `--text` | letter-spacing 1.2px |
| Form label (booking pages) | 12px | 600 | `--heading` | UPPERCASE |

**Change from Ramada:** body text is `#333` and left-aligned, never justified (justified text leaves big gaps on phones).

### Layout

- Content width **1170px**, centred. Use Bootstrap 5's `.container` and set its max width to 1170px on large screens.
- Sections have about **60px** of space above and below, and alternate white and `--bg-alt`.
- Corners are **square** (`border-radius: 0`) on buttons, inputs, cards and photos. The only rounded items are the back-to-top button (5px), the "Join Now" button (pill shape) and the icon circles.
- Photos sit in a thin frame: 6px `--line` padding/border around the image.
- Grids: 3 columns for room cards, 4 for the gallery, 1 column on phones.

---

## 3. Shared components

Build each of these once in `includes/` (or as a CSS class) and reuse it on every page.

### Header (`includes/header.php`) — screenshot 01

- White, 104px tall, soft shadow underneath (`0 2px 6px rgba(0,0,0,.2)`). Stays at the top when scrolling.
- **Left:** logo, about 175×66px, links to Home.
- **Middle:** Home · About Us · Rooms · Dining · Meeting & Events · Multan · Gallery · Contact Us. The current page is red with a **3px red underline**.
- **Right:** **BOOK NOW** button: red background, white uppercase text, 2px red border, 10px padding, square corners. Goes to our booking search (not to Wyndham).
- **Added (not on Ramada):** after Book Now, a **Login** link when logged out, or an account menu when logged in (My Bookings, My Orders, My Reservations, My Invoices, Logout; plus Admin Panel for admins).

### Mobile menu — screenshots 13, 14

- On phones: ☰ button on the left, logo in the centre.
- Tapping ☰ slides in a **red panel from the left** (about 290px wide) with white links, one per line. The current page has a white underline. The page behind dims. Book Now and Login are at the bottom of the panel.
- Use Bootstrap's **Offcanvas** component for this.

### Section heading — screenshots 01, 04

Used at the start of almost every section:

1. Title: centred, 37px, red ("ROOMS").
2. Subtitle: centred, 28px, light, dark grey ("All Designed To Customize Your Need").
3. Divider: a short grey line, **138px wide × 4px**, `--line`, centred, with about 30px space below.

Make it one PHP function, e.g. `section_heading($title, $subtitle)`.

### Room card — screenshot 02

- Background `--bg-card`, no border, square corners. Photo on top (about 5:3).
- Below the photo, centred: room name (25px, red), then lines like **"Max occupancy: <span red>3 Adults</span>"** and **"Beds: <span red>1 King Size Bed</span>"**, then an optional feature line in red ("Access to Executive Lounge").
- **Added:** price line **"From PKR 23,995 / night"**, and two links: **"Room Details ›"** (red text) and a small red **Book** button.
- Don't print filler like "." when a room has no feature line. Just leave the line out.

### Buttons

- **Primary:** red background, white text, weight 600, padding 16px 30px, square. Hover `--red-dark`. Uppercase on booking and checkout pages ("SEARCH ROOMS", "COMPLETE BOOKING"), normal case elsewhere ("Send").
- **Outline (footer):** white 2px border, white text, pill shape ("Join Now").
- **Text link:** red, with a small ▸ arrow ("Room Details ▸").

### Forms — screenshots 11, 12

- Inputs and textareas: background `--bg-input`, **2px solid red border**, padding 13px 20px, square corners, placeholder text inside the field.
- Errors: small red text under the field ("This field is required."), plus a red alert box above the button. Check on the server as well, not only in the browser.
- **Booking-style forms** (search bar, checkout): small uppercase labels above white fields with a thin grey border, as on Wyndham's pages (screenshots 15, 18).

### Amenities list — screenshot 05

- Each item on its own line, 8px padding top and bottom, 1px `--line-light` line underneath.
- A red circle-arrow icon before each item (Font Awesome `fa-circle-chevron-right`).

### Photo viewer (lightbox) — screenshot 10

- Clicking any gallery or room photo opens it large on a dark see-through background, with ← → arrows, a round white ✕ to close, and a row of small thumbnails underneath. Hovering a photo shows a round black "+" in its centre (screenshot 05).
- Use a small free library such as GLightbox (one CSS and one JS file in `assets/`), or Bootstrap's Modal + Carousel.

### Footer (`includes/footer.php`) — screenshot 03

- Red background (`--red`), white text, 4 columns:
  1. White logo, "LET'S GET SOCIAL", Instagram / Facebook / X icons.
  2. Address, phones, email.
  3. Quick links in a 2-column grid (About Us, Contact Us, Gallery, Multan, Rooms, Dining), with "SAY HELLO TO RED" underneath (20px, weight 600, letter-spacing 2px, white lines above and below).
  4. Wyndham Rewards logo and an outline "Join Now" button linking to Wyndham's sign-up page.
- Bottom line: copyright and the FYP credit.

### Back-to-top button

- 35×35px, red, 5px rounded corners, white ^ arrow, fixed at bottom right. Appears after scrolling down. **Must actually scroll to the top** (on Ramada's site it didn't respond in testing).

---

## 4. Pages

Each page lists the Ramada layout and what we change or add. Pages marked ★ don't exist on Ramada's site.

### Home — screenshots 01, 02

1. **Photo slideshow,** full width, about 650px tall (smaller on phones), with ‹ › arrows. Bootstrap Carousel.
2. **★ Availability search bar,** overlapping the bottom of the slideshow: Check-in, Check-out, Guests, red **SEARCH ROOMS** button. Goes to the room search results.
3. **Welcome:** section heading "Ramada By Wyndham Multan" / "The Spot of Serenity & Comfort in Multan", then the intro text on the left and the lobby photo on the right. **The text must always be visible.** On Ramada's site it only fades in on scroll and sometimes doesn't appear at all.
4. **Rooms:** section heading "ROOMS" / "All Designed To Customize Your Need" and the 6 room cards on a `--bg-alt` background.
5. **Multan | Reviews:** two columns separated by a thin red line on top. Left: a Multan photo linking to the Multan page. Right: **★ the latest 3 reviews from our own feedback module** with star ratings and the average rating, instead of the TripAdvisor box.
6. **Our Facilities & Services:** photo cards with a dark text panel on the right half (Multi Cuisine Restaurant, Meeting Facilities, Swimming Pool, Health Club).
7. Footer.

### Rooms — screenshot 04

- One large framed photo, then the section heading "ROOMS" / "All designs are customized as per your need", then the room cards.
- **★** When the visitor has searched with dates, show only rooms free for those dates, with the dates shown at the top and a "Change dates" link.

### Room detail — screenshot 05

- **Left (8 columns):** large framed photo → 6-photo grid (3×2, opens the photo viewer) → description → "Room Details" sub-heading → amenities list.
- **Right (4 columns):**
  - **★ Booking box** at the top: price per night, check-in and check-out dates, guests, red **BOOK THIS ROOM** button, and a line like "Free cancellation until 6 PM on the day of arrival".
  - Below it, the **"Rooms" box** (`--bg-card`): every room type with a 40×40 thumbnail and name. The current room is red.
- Show the **correct room name** everywhere. Ramada's pages have copy-paste mistakes, e.g. the Executive Suite page says "Business Room".

### ★ Booking flow — screenshots 15–19 (copied from Wyndham, kept on our site)

1. **Search results:** search bar at the top (check-in, check-out, guests, **UPDATE**). Below it, one wide card per free room type: photo on the left; name, short description and "Room Details" link in the middle; price in large red (**23,995 PKR / NIGHT**) and a red **BOOK** button on the right. A dark tag such as **"Only 2 rooms left!"** when few rooms of that type are free.
2. **Date picker:** a calendar showing two months side by side, with past dates greyed out and the chosen days in red. A small library such as flatpickr in range mode, or the browser's date input as a fallback.
3. **Cancellation info:** a "Cancellation details" link opens a small popup with the policy.
4. **Checkout page:**
   - Dark summary bar at the top (`--dark-bar`, white text): hotel name, dates, nights, room type, guests; on the right, price per night × nights = **Total for Stay** and **"Pay at Hotel"**.
   - Guest details: name, phone and email, filled in from the account. Special requests box.
   - "I agree to the cancellation policy" tickbox, then a large red **COMPLETE BOOKING** button.
   - **No card details, no rewards offers, no extra rate options.** Payment is at the hotel (D5).
5. **★ Confirmation page:** "Booking confirmed", booking number, all details, buttons for "View my bookings" and "Print". A confirmation email is sent as well (requirements: email section).

### Dining — screenshot 06

- One section per restaurant (Buzz 76, Cafe Saints, Executive Lounge): section heading, description, **opening times**, and a row of 3 photos.
- Meal icons for Breakfast, Lunch, Hi-Tea and Dinner under Buzz 76.
- **★ Menu:** below the restaurants, the full menu with category tabs (same style as the gallery tabs). Each item shows its name, short description, price and an **Add** button. A cart summary (sticky on the right on desktop, a bottom bar on phones) with **PLACE ORDER**.
- **★** A **RESERVE A TABLE** button in each restaurant section, going to the table reservation page.

### ★ Table reservation

- Same style as the booking search: date, time, party size → list of free tables with their seats → **CONFIRM RESERVATION** → confirmation page and email.

### Meeting & Events — screenshot 07

- Large framed photo, section heading "Meeting and Events", a paragraph, and the halls table (Hall, Area (SQM), Height, Length, Width) in a simple bordered table.
- Information only, with a "Contact us to book an event" button. Event booking is not in scope.

### Multan — screenshot 08

- Large framed photo, section heading "MULTANI LIFE STYLE", then photo cards with a red caption (Blue Pottery, Mangoes, Sohan Halwa, Darbar, Ghanta Ghar, Multani Khussa, and so on). Information only.

### Gallery — screenshots 09, 10

- Section heading "Gallery", then a tab bar (`--bg-input` background, full width): All · Banquet · Health & Recreation · Lobby · Restaurants · Rooms. Tabs are 20px grey; the active one is red with a 3px red underline.
- 4-column photo grid; each photo has a 4px `--line` frame and opens the photo viewer.
- Filtering is done with a few lines of JavaScript (show and hide by category). No page reload.

### Contact — screenshots 11, 12

- Full-width Google Map (embedded iframe, free) about 450px tall.
- **Left:** "Contact Information"; one row per item with a 60px grey circle icon: Address, Phone, WhatsApp, GSM, Fax, Email. The label is bold dark, the value is underneath.
- **Right:** "**Write** Us" ("Write" in red, "Us" in grey), with fields Name, Email, Phone, Subject and Message, and a red **Send** button.
- **★** Messages are saved for the admin to read in the admin panel. A success message is shown on screen.

### About Us

- Ramada's version puts the text over a photo of the pool, which is hard to read. **Ours:** the photo first, then the text on a white background, then two columns: "Our Core Values" (Integrity, Accountability, Inclusive, Caring, Fun) and "Nearby Attractions".

### ★ Login / Register / Forgot password

- A centred box (about 450px wide) on a `--bg-alt` background, with the section heading style, red-bordered inputs and a full-width red button. Links to switch between login and register.

### ★ My Account

- Tabs: My Bookings · My Orders · My Reservations · My Invoices · Profile.
- Each tab shows a table with a coloured status badge (Confirmed = green, Pending = yellow, Cancelled = grey) and action buttons (Cancel, View invoice).

### ★ Admin panel

- Separate simple layout: dark sidebar on the left with the hotel logo and links (Dashboard, Rooms, Room Types, Bookings, Menu, Orders, Tables, Reservations, Invoices, Feedback, Messages, Users). White content area on the right.
- Dashboard: count cards across the top (red numbers), revenue total, then tables of recent bookings, orders and feedback.
- Keep the same font and red. Use plain Bootstrap tables, forms and badges.

---

## 5. Ramada problems we fix

| Problem on ramadamultan.com | What we do |
|---|---|
| Body text is light grey (`#9C9C9C`), thin and letter-spaced, so it's hard to read | `#333`, 16px, left-aligned |
| Some text only appears after a scroll animation, and sometimes doesn't appear at all | No scroll animations on text; content is always visible |
| No prices or booking on the site; "Book Now" sends guests to Wyndham | Search, prices and booking on our own site |
| Dining has no menu or prices | Full menu with ordering, and table reservations |
| Reviews come from a TripAdvisor box | Our own reviews from the feedback module |
| Room pages say the wrong room name (copy-paste mistakes); an unlinked "Standard" room page exists | Room pages are generated from the database, so names are always right |
| Typos ("senerity", "While & Brown Sugar", "BreakFast") | Proof-read all text |
| "Member Login" exists but does nothing | Working login and account menu |
| Back-to-top button didn't respond in testing | Working back-to-top |
| Slow: loads 21 fonts and icon sets; one page didn't load in 60 seconds | One font, Bootstrap, Font Awesome, and small images (compress photos before upload) |
| About page text sits on top of a photo | Text on a plain background |

---

## 6. Real content for the seed data

Taken from ramadamultan.com and the Wyndham booking page. Use it in `database/schema.sql` so the site looks real from day one. Ask the hotel to confirm prices.

**Room types**

| Room type | Max guests | Beds | Feature | Sample price (PKR / night)* |
|---|---|---|---|---|
| Presidential Suite | 2 adults | 1 king | Access to Executive Lounge; living room for 15–20 people, kitchenette, balcony | ask the hotel |
| King Bed, Business Room | 3 adults | 1 king | Access to Executive Lounge | ask the hotel |
| Deluxe Queen Room | 2 adults | 1 queen | | ask the hotel |
| Executive Suite | 3 adults | 1 king or queen† | Balcony | 69,995 |
| Twin Bed | 3 adults | 2 single | | 24,995 |
| Pool Wing | 3 adults | 1 king or queen† | Balcony with pool view | 23,995 |

\*Standard rate shown on the Wyndham booking page for one night in October 2026.
†The hotel's own pages disagree about these beds. Ask the hotel which is right.

**Amenities (all rooms):** pipe-system air-conditioning with temperature control · smart Android LED TV · free WiFi · in-room safe (fits a laptop) · 24/7 room service · minibar · iron and ironing board · complimentary tea, coffee, white and brown sugar, 2 water bottles · business desk · access to swimming pool and health club · laundry on request.

**Hotel policies:** check-in 2:00 PM · check-out 12:00 PM · cancel before 6:00 PM on the day of arrival to avoid a one-night charge · free breakfast buffet · airport shuttle · free parking.

**Restaurants**

| Restaurant | Description | Hours |
|---|---|---|
| Buzz 76 | All-day dining from breakfast to midnight supper; lunch and dinner buffet with international and local cuisine | 6:30 AM – 11:30 PM; Sunday brunch 11:30 AM – 2:30 PM |
| Cafe Saints | Modern outdoor cafe by the pool; all-day international and traditional dining | 3:00 PM – 11:00 PM |
| Executive Lounge | Fresh cuisine in a warm, comfortable setting | 8:00 AM – 11:00 PM |

**Halls**

| Hall | Area (m²) | Height (m) | Length (m) | Width (m) |
|---|---|---|---|---|
| Zainab Hall | 542.7 | 2.7 | 24.6 | 22.1 |
| Maryam Hall | 194.9 | 4.2 | 15.7 | 12.4 |
| Mehfil Hall | 79.2 | 2.8 | 8.7 | 9.1 |
| Zakariya Board Room | 33.1 | 2.6 | 7.3 | 4.6 |

**Contact:** 76 Abdali Road, Multan, Pakistan · Phone +92 61 454 0877 / +92 61 458 7777–86 · WhatsApp +92 321 857 6777 · GSM +92 323 624 7777 · Fax +92 61 454 0877 · info@ramadamultan.com · Instagram `ramadamultan` · Facebook `RamadaMultan` · X `ramadamultan`.

**Nearby attractions:** Shrine of Shah Rukn-e-Alam · Multan Fort · Gates of the City · Ghanta Ghar · Mausoleum of Sheikh Bahauddin Zakariya · Lal Suhanra National Park.

---

## 7. Reference screenshots

In [design-reference/](design-reference/). Taken at 1440px wide (desktop) and 390px (phone).

| File | Shows |
|---|---|
| 01-home-hero-intro | Header, slideshow, welcome section, start of the room cards |
| 02-home-room-cards-reviews | Room cards, Multan and Reviews, Facilities & Services |
| 03-footer | Footer |
| 04-rooms-page | Rooms page |
| 05-room-detail | Room detail: photo, sidebar, photo grid, description, amenities |
| 06-dining | Dining: restaurants, hours, meal icons, photos |
| 07-meeting-events | Meeting & Events with the halls table |
| 08-multan | Multan page |
| 09-gallery-filter-tabs | Gallery with the "Restaurants" tab selected |
| 10-gallery-lightbox | Photo viewer |
| 11-contact | Contact page |
| 12-contact-form-errors | Contact form after pressing Send with empty fields |
| 13-mobile-home | Home page on a phone |
| 14-mobile-menu | Phone menu open |
| 15-booking-search-and-room-list | Wyndham: search bar and room list with prices |
| 16-booking-date-picker | Wyndham: two-month date picker |
| 17-booking-cancellation-popup | Wyndham: cancellation details popup |
| 18-booking-checkout-summary | Wyndham: checkout summary bar and guest form |
| 19-booking-complete-reservation | Wyndham: agree tickbox and Complete Reservation button |
