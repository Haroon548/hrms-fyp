# Design

How the site looks and behaves. The design is based on the official **Ramada by Wyndham Multan** website ([ramadamultan.com](https://ramadamultan.com)) and on the Wyndham booking pages its "Book Now" button leads to. We keep its brand (colours, font, photos, page structure), fix its weak points, add the features in [requirements.md](requirements.md), and make it **feel far more modern and lively** than the original: motion, frosted glass, photo-led layouts (D19, D20). The redesigned Home page (section 4) is the model for the other pages.

All values below were measured from the live site in October 2026. Screenshots of every page and step are in [design-reference/](design-reference/). **Look at the matching screenshot before building or changing a page.**

**Rule:** where this file and the Ramada site differ, follow this file. Ramada's site is the starting point, not the limit: if something can be done better, do it better and record it here. Where this file and `requirements.md` differ on *what* to build, follow `requirements.md`.

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

- **Fira Sans**, weights **300, 400, 600**. One font for the whole site. Download the font files from Google Fonts once and store them in `assets/fonts/`, loaded with `@font-face`, so the site looks right offline (D15).

### Libraries

All stored in `assets/vendor/`, never loaded from a CDN (D15): Bootstrap 5 (CSS + JS), Font Awesome (free icons), GLightbox (photo viewer), flatpickr (date picker), Chart.js (report charts), and a QR code library such as qrcode.js. Keep the version number in each folder name, e.g. `assets/vendor/bootstrap-5.3.3/`.
- **GSAP 3.15.0** (`assets/vendor/gsap-3.15.0/`: `gsap.min.js`, plus `ScrollTrigger.min.js` and `Flip.min.js` only on pages that need them) for the small animations in section 3, "Motion" (D19).
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
- **Middle:** Home · About Us · Rooms · Dining · Meeting & Events · Gallery · Contact Us (no Multan page, D23). The current page is red with a **3px red underline**.
- **Right:** **BOOK NOW** button: red background, white uppercase text, 2px red border, 10px padding, square corners. Goes to our booking search (not to Wyndham).
- **Added (not on Ramada):** after Book Now, a **Login** link when logged out, or an account menu when logged in (My Bookings, My Orders, My Reservations, My Invoices, Logout; plus Admin Panel for admins).

### Mobile menu — screenshots 13, 14

- On phones: ☰ button on the left, logo in the centre, **★ a small red BOOK button on the right** (FR-WEB-11).
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
- Below the photo, centred: room name (25px, red), then lines like **"Max occupancy: <span red>3 Adults, 1 Child</span>"** (the children part only when the room type allows children) and **"Beds: <span red>1 King Size Bed</span>"**, then an optional feature line in red ("Access to Executive Lounge").
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
- Use GLightbox (stored in `assets/vendor/`).

### Footer (`includes/footer.php`) — screenshot 03

- Red background (`--red`), white text, 4 columns:
  1. White logo, "LET'S GET SOCIAL", Instagram / Facebook / X icons.
  2. Address, phones, email.
  3. Quick links in a 2-column grid (About Us, Contact Us, Gallery, Events, Rooms, Dining), with "SAY HELLO TO RED" underneath (20px, weight 600, letter-spacing 2px, white lines above and below).
  4. Wyndham Rewards logo and an outline "Join Now" button linking to Wyndham's sign-up page.
- Bottom line: copyright and the FYP credit.

### Back-to-top button

- 35×35px, red, 5px rounded corners, white ^ arrow, fixed at bottom right. Appears after scrolling down. **Must actually scroll to the top** (on Ramada's site it didn't respond in testing).

### ★ Motion (Later) — D19, FR-UX

- **Rules:** never hide or animate text on scroll (section 5); keep animations short (0.2–0.4s); nothing moves when the visitor has "reduce motion" turned on (`prefers-reduced-motion`); the page looks complete if the script fails to load.
- **Spotlight hover** (`assets/js/spotlight.js`): add `data-spotlight` to a box. Room cards use the default soft white glow; the facility tiles on Home use `data-spotlight-color="rgba(255,255,255,0.18)"` and `data-spotlight-size="300"`. Only on computers with a mouse. The glow sits behind the text and photos.
- **Photo reveal:** photos are uncovered from the bottom over about 1s the first time they scroll into view (ScrollTrigger, once). Text beside them is always visible.
- **Home hero and depth:** see Home in section 4 (FR-UX-05, FR-UX-06). Home's code is in `assets/js/home.js`.
- **Gallery tabs:** photos slide into their new places when a tab is clicked (Flip).
- **Add to cart:** the cart count grows slightly and settles back (0.2s).

### ★ Live updates (FR-LIVE-01, 02; D17)

- Parts of a page that change by themselves get a short soft red glow (1.6 s, none for "reduce motion"), so people notice what moved without anything jumping.
- If the visitor is typing in that part, it isn't swapped: a dark pill at the bottom centre says "This page has new information" with a red **Update** button and ✕.
- Admin sidebar: a small red count pill after **Orders** (new orders) and **Messages** (unread).
- Kitchen screen: "● Live" in the top bar turns pink when the connection drops.

### ★ WhatsApp button (Later)

- Round green button (WhatsApp green `#25D366`, white WhatsApp icon), 56px, fixed at bottom left so it doesn't clash with back-to-top. Opens `https://wa.me/<number>` in a new tab. Only on public pages, not in the admin panel.

### ★ Status badges

Used in My Account, the admin panel and the kitchen screen. Small rounded Bootstrap badges:
- Pending / New → yellow · Confirmed / Preparing → blue · Checked in / Ready → green · Completed / Checked out → dark grey · Cancelled → light grey with grey text.

### ★ Star rating and verified badge

- Stars in `--red` (filled and empty Font Awesome stars), with the number next to them ("4.6").
- Verified badge: small text "✓ Verified stay" or "✓ Verified order" in green, under the guest's first name.

---

## 4. Pages

Each page lists the Ramada layout and what we change or add. Pages marked ★ don't exist on Ramada's site.

### Home — redesigned (D20); Ramada's version is in screenshots 01, 02, 13

Brand stays the same (red, Fira Sans, real photos); the layout is livelier than Ramada's.

0. **★ Opening intro** (FR-UX-08), first visit per tab, about 3.5 s: (1) the HD building photo fades up from dark, starting close on the RAMADA sign, and the camera pulls back slowly; (2) the hero's own shading (wine-dark with a soft red glow) deepens over the building as the white Ramada logo rises in from a blur, with "THE SPOT OF SERENITY & COMFORT" (gold `#F4C14E` / white) below; (3) the whole intro dissolves into the Home page and the hero text rises in. Photo: `assets/images/intro/exterior.jpg` (2200px; the lobby shot was dropped at the team's request, from Wyndham's property page for this hotel). Round dark-glass "Skip intro ⏭" at the top right. Code: inline script in `index.php` + `assets/js/intro.js`.
1. **Hero slideshow,** full screen (100vh, 620–920px; 88vh on phones), photos cross-fade every 7 s with a slow zoom. Dark shading on the top (for the menu) and left (for the text). On top, bottom-left: eyebrow "★★★★ City-centre hotel · Multan", the **h1** "The Spot of Serenity & Comfort in Multan" (white, light weight, up to 66px), one line of text, and two buttons: red **Book your stay** (jumps to the search panel) and a frosted-glass **Explore dining**. Bottom-right: dots, ‹ › and a pause button.
2. **Header on Home** is see-through over the hero with the white logo and white menu, and turns white (with the red logo) once the visitor scrolls 40px.
3. **★ Search panel,** frosted glass (white 78% with blur), overlapping the bottom of the hero by 100px: Check-in, Check-out, **Adults**, **Children**, red **SEARCH ROOMS**. Dropdowns use our own arrow. Under the fields, once both dates are picked: "3 nights · Fri 16 Oct → Mon 19 Oct" (FR-WEB-13).
4. **★ Highlights strip:** six items (3 on tablets, 2 on phones), each a red icon in a 44px grey circle that turns red on hover: Check-in 2 PM / Check-out 12 PM · Free breakfast · Free WiFi · Free parking · Airport shuttle · Pool & gym (FR-WEB-12).
5. **Welcome:** left: red eyebrow, heading "A calm stay in the heart of the City of Saints", intro text (**always visible**), four counters (room types, restaurants, event halls, 24/7 room service) and "Discover the hotel ▸". Right: a collage of the lobby photo with the reception photo overlapping its bottom-left corner and a red "4★ Four-star comfort" badge.
6. **Rooms:** section heading, then white cards in 3 columns (2 on tablets; on phones a row you swipe through). Each card: photo with a frosted price chip ("From PKR 23,995"), room name in red, icon lines for guests, beds and the feature, then "Room Details ▸" and **Book** (or **Ask for price** while no price is set).
7. **★ Book in 3 easy steps** (FR-WEB-18): section heading, then three steps in a row joined by a dashed line, each a 78px red-outlined circle with an icon and a small dark number badge (fills red on hover): Pick your dates · Choose a room · Pay at the hotel. Red **Check availability** button below. One column on phones.
8. **★ Dining band:** Cafe Saints photo full width with slight parallax; a dark frosted card with "Eat in, by the pool or in your room", the three restaurants and their hours, **See the menu** and **Reserve a table**.
9. **Our Facilities & Services:** photo tiles with the title and one line over a dark fade: one large tile (restaurant) and three smaller ones (meeting, pool, Executive Lounge). Photos zoom slightly on hover.
10. **★ Inside the hotel** (FR-WEB-21): warm paper-tone section (`#EFE7DA`), eyebrow "Take a look inside", heading "Every room, every corner", a one-line how-to, then the filmstrip (520px tall, 440px on phones): cards about 240px wide with a cream frame, the photo, and a dark footer with a red-ringed number, the place name and its type. Glass ‹ › buttons at the sides; "View the full gallery ▸" below. Reuse it on the Gallery page. Code: `assets/js/filmstrip.js`.
11. **Multan | Reviews:** a Multan photo card with a caption linking to About Us → Nearby attractions (D23); a white reviews card with the average rating and the latest verified reviews (FR-SIG-REV-03), or a short "No reviews yet" message. Once reviews exist, show **one large quote at a time** (big text, stars, first name, "✓ Verified stay") with ‹ › buttons, rather than small cards.
12. **★ Closing call to action:** red gradient band, "Ready for your stay in Multan?", white **Book your stay** and a glass **WhatsApp us** button.
13. Footer.

### Rooms — screenshot 04 (redesigned to match Home, D20)

- **Banner:** a 380px photo with a dark fade from the left, breadcrumb "Home / Rooms", "Rooms & Suites" (white, light weight) and "All designs are customized as per your need."
- **Glass search bar** overlapping the banner (same as Home): button "CHECK AVAILABILITY", or "UPDATE" after a search.
- **★ After a search:** a dark bar (`--dark-bar`): "3 nights · Sat 17 Oct → Tue 20 Oct · 2 Adults" and "2 of 6 room types can be booked for these dates · Change dates".
- **Room rows** (white cards, lift on hover): photo left (340px), middle: name in red, guests / beds / feature with icons, two lines of description, "Room Details ▸"; right: price per night in red, "3 nights: PKR 71,985", red **BOOK** and "Pay at the hotel". A dark "Only N rooms left!" tag on the photo when 2 or fewer are free. Rooms that can't be booked sit at the bottom: "Fully booked for these dates" (greyed photo) + "Try other dates", "Too small for 3 Adults, 1 Child", or "Price on request" + "Ask the hotel". Without dates: "From PKR … per night" + CHECK AVAILABILITY. On tablets the price part moves under the row; on phones everything stacks.

### Room detail — screenshot 05

- **Top band** (`--bg-alt`): breadcrumb, room name (red, up to 46px), guests / beds / feature with icons, and "From PKR 23,995 / night" on the right.
- **Left (8 columns):** the main photo large (16:9), the other photos in a 3-column grid; hovering shows a round black "+", clicking opens the photo viewer (GLightbox, dark caption). Then "About this room", "Room Details" (amenities in two columns with red circle-arrow icons and thin lines between), and **★ "Good to know"**: check-in/out, free cancellation, breakfast, pay at the hotel, parking/shuttle, each with an icon in a grey circle.
- **Right (4 columns):** **★ booking box** (red top border; scrolls with the page, so it never covers the Rooms box under it): price per night, check-in, check-out, adults, children (limited to what the room allows), the nights line, CHECK AVAILABILITY, "Free cancellation until 6 PM on the day of arrival". Unpriced rooms show "Price on request" with phone and WhatsApp buttons instead. Under it, the **"Rooms" box** (`--bg-card`): every room type with a 48px thumbnail; the current one in red.
- **★ Availability calendar** (as built: amber = 1–2 rooms left; the nights inside the chosen stay are a stronger pink-red with white text so they can't be confused with full nights; a dark bar under the calendar says "Wed 4 Nov → Sat 7 Nov · 3 nights · PKR 71,985" with CHECK THESE DATES. Full nights can still be picked as a check-out day.) (full width, under "Good to know"): built with booking (FR-SIG-CAL-01): one month at a time with ‹ › arrows. Each day is a square: free = light green with the price underneath in small text, full = light red and crossed out, past = grey. Clicking a free check-in day and then a check-out day highlights the range in `--red` and fills in the booking box. On phones the squares shrink and the price is hidden.
- Show the **correct room name** everywhere (pages come from the database).

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

**As built (D20 style):**
- **Room page booking box:** CHECK AVAILABILITY reloads the room page and shows the answer under the button: green "✓ Available" (+ dark "Only 2 left!" tag), nights and dates, total in large red, red BOOK NOW; red "Fully booked for these dates" with links to the calendar and other rooms; or the reason (past date, too many guests).
- **Checkout:** a 3-step line on the dark bar (✓ Choose room · ② Your details · ③ Confirmed). Dark bar (`--dark-bar` with a soft red glow): hotel name, gold ★★★★, dates | rooms, nights | guests + "Change"; right side: room × nights, **Total for stay**, "Pay at hotel · Cancellation details" (opens the policy pop-up). White form card: GUEST INFORMATION (name, phone, email read-only, estimated arrival time, special requests), three "why book with us" icons, then a bordered box with the policy tickbox, COMPLETE BOOKING and the flexible-rate line. Right: a sticky card with the room photo, beds, guests, feature, and check-in / check-out tiles, plus "Only N rooms left" when few are free. The button turns into "BOOKING YOUR ROOM…" with a spinner once pressed.
- **Confirmation:** dark hero with the steps, a green tick that pops in, "Your stay is booked!", My bookings (white) and Print (glass) buttons, and the **digital room key** on the right: a red gradient card (logo, contactless icon, booking number, dates, room type) that tilts towards the mouse with a moving light. Below: "Booking details" list with a status badge, and a "Before you arrive" card (Add to my calendar, Get directions, Share on WhatsApp, Message the hotel, phone). Cancelled bookings use a grey hero. Printing hides the header, footer, key and buttons.

### Dining — screenshot 06

- One section per restaurant (Buzz 76, Cafe Saints, Executive Lounge): section heading, description, **opening times**, and a row of 3 photos.
- Meal icons for Breakfast, Lunch, Hi-Tea and Dinner under Buzz 76.
- **★ Menu:** below the restaurants, the full menu with category tabs (same style as the gallery tabs). Each item shows its name, short description, price and an **Add** button. A cart summary (sticky on the right on desktop, a bottom bar on phones) with **PLACE ORDER**.
- **★ Order type** in the cart: three large option buttons. **Room service** (only shown to checked-in guests; shows "Room 214 — added to your room bill"), **Dine-in** (pick a table, or set automatically from the QR code), **Takeaway**. Plus a notes box ("no onions").
- **★** A **RESERVE A TABLE** button in each restaurant section, going to the table reservation page.

**As built (FR-WEB-23, the team chose the restaurant-amici.com style; D22 for photos):** tokens `--paper` (#FBF6EE cream) and `--paper-line`; red checkered edges on the menu card, the reserve card and the moving strip.
1. **Hero:** 4 columns of rounded photo tiles (2 on phones) drifting up and down slowly behind a dark vignette, tilted −4°; centred breadcrumb, pink eyebrow, "Dining that feels like a *celebration*" (the last word pink), text and See the menu / Reserve a table.
2. **Moving strip:** red band, cream checkered edges, uppercase words with ✱ between ("Breakfast buffet ✱ Sunday brunch ✱ Hi-tea ✱ …"); pauses on hover.
3. **Where to eat** (cream background): for each restaurant a large photo with a small rotated inset photo in a cream frame, and a white menu card (thin red inner line, "01", red kind label, name, description, hours between dashed lines, outline tags, See the menu / Reserve a table) overlapping the photo at −1.5° (sides alternate). On scroll the card straightens and the photo drifts.
4. **Moments** (dark `--dark-bar`): tilted cream "polaroid" cards with captions in a row that slides sideways while scrolling (swiped by hand on phones).
5. **The Menu:** a white card on cream with red checkered top and bottom edges, a big light red "The Menu", sticky round section tabs that follow the section on screen, two columns of sections (red uppercase heading with a red rule), each dish as name · dotted line · price in red, description below, and a round red-outline **+** that spins on hover and shows a small dark count. Under it: Takeaway / Dine-in / Room service notes.
6. **Reserve a table:** a cream card (checkered top, red inner line, tilted) on a darkened table photo with phone and WhatsApp buttons (the table reservation form replaces these in its chunk).
- **Cart:** a red pill button bottom right ("🧺 3 · Your order · PKR 5,850", full width on phones) that pops when a dish is added and opens a right-hand panel with − / + per line, the total and CONTINUE TO ORDER.
- **Order page** (`order/checkout.php`): dark bar with the 3 steps, then three large option cards (Takeaway / Dine-in / Room service; room service greyed out unless checked in, showing "To room 104…" when allowed), the table list (only for dine-in), a kitchen note, and PLACE ORDER · PKR total; dish summary on the right.
- **Order status page** (`order/view.php`): dark hero with the order type icon, a 4-step progress line (current step red with a soft pulse, done steps green), "Live: this page changes as soon as the kitchen moves your order on", the dishes, and a side card with links and Cancel order (only while Received).
- **Illustrative photos** carry a small dark "Illustrative photo" label in their corner, and a line under the page explains it.

### ★ QR table menu (opened by scanning a table's QR code)

- Phone-first page. A red bar at the top: hotel logo and **"Table 5 · Buzz 76"**.
- The menu with category tabs that scroll sideways, items as compact rows (photo, name, price, + button).
- A fixed bottom bar: "3 items · PKR 4,350 · **VIEW ORDER**". The order screen asks only for a name if the guest isn't logged in, then **PLACE ORDER**.
- After ordering: a big tick, the order number and a status line (Received → Preparing → Ready).

### ★ Printable table QR cards (admin)

- One card per table, sized to print 4 on an A4 page: hotel logo, large QR code, "Table 5", and "Scan to see the menu and order". A **Print** button uses the browser's print.

### ★ Kitchen screen (admin)

- Full-screen page meant for a tablet or monitor in the kitchen. No sidebar. Dark grey background so cards stand out.
- Three columns: **NEW** (yellow header), **PREPARING** (blue), **READY** (green), with a count in each header.
- Each order is a white card: big "Table 5" or "Room 214" or "Takeaway — Ali", the time since it was ordered ("6 min"; turns red after 20 min), the items with quantities in large text, notes in red, and one large button to move it to the next column ("Start" → "Ready" → "Done").
- A small line at the top: "Updated 10 seconds ago". A short sound plays when a new order arrives.

### ★ Table reservation

- Same style as the booking search: date, time, party size → list of free tables with their seats → **CONFIRM RESERVATION** → confirmation page and email.
- **As built (`reserve-table.php`):** photo banner of a laid table; a glass search bar (Restaurant, Date, Time, Guests, FIND A TABLE / UPDATE) with a line "Buzz 76 takes reservations from 6:30 AM to 10:00 PM"; before a search, one card per restaurant with its hours. After a search: a dark summary bar, then "Choose your table" cards with dots for the seats (the chosen one red, the first marked "best fit"), a note box and CONFIRM RESERVATION (or LOG IN TO RESERVE). No free table: an empty state with round time chips for the nearest free times. Confirmation (`reservation/view.php`): dark hero with a glasses icon, details list (number TR-10xx), and a "Before you come" card (menu, directions, share, cancel). My Reservations: rows with a red date tile.

### Meeting & Events — screenshot 07

- Large framed photo, section heading "Meeting and Events", a paragraph, and the halls table (Hall, Area (SQM), Height, Length, Width) in a simple bordered table.
- Information only, with a "Contact us to book an event" button. Event booking is not in scope.
- **As built (`events.php`, D20 style):** banner of the banquet hall (slow parallax) with a frosted dark card, "Your celebration or conference, in the heart of Multan", **Plan your event** (red) and **See the halls** (glass). Then the "Meeting and Events" heading, the intro, four number tiles with a red top edge (halls, largest hall in m², restaurants, 24/7), and four tall photo cards on the hotel's own banquet photos (weddings & walimas, meetings & conferences, poolside evenings, family celebrations; dark fade, red icon circle, photo zooms on hover). **Our halls:** two-column white cards, each with a **floor plan to scale** on the left (a light grid square as wide as the biggest hall; the hall as a pink rectangle with a red edge and its length and width in metres) and the name, one line, area / size / ceiling and "Enquire about this hall ▸" on the right; under them, Ramada's table with a dark header (scrolls inside its box on phones). A row of banquet photos in cream frames that you swipe or scroll sideways (snaps to each photo). **Enquiry band** on `--ink` with a red glow: text and three points on the left, a white form card with a red top edge on the right. Photos: `site_photos` `events_hero`, `events_wedding`, `events_meeting`, `events_poolside`, `events_family` (FR-WEB-10).

### Gallery — screenshots 09, 10 (redesigned, D20)

- **★ Heading inside a ring of photos** (FR-WEB-22): dark `--ink` band (580px; 400px on phones) with a soft red glow. 16 portrait photo cards (168×228px; 92×128px on phones) with 14px rounded corners, a thin glass edge and sheen, and a soft reflection underneath, sit side by side to form one cylinder around the big light "Gallery" heading. It turns once a minute; each card darkens as it goes round the back (brightness 30% → 85% at the front), so it reads as a solid 3D ring. Hover slows it; drag sideways to spin it (vertical swipes still scroll). A dark wash behind the heading keeps it readable. Still for "reduce motion"; turns with CSS alone without JavaScript.
- **Tab bar** (`--bg-input`, full width, stays under the header while scrolling): All · Banquet · Health & Recreation · Lobby · Restaurants · Rooms · ★ Multan, each with a small count pill; the active one is red with a 3px red underline and a red pill. Scrolls sideways on phones.
- **Grid:** 4 columns (3 on tablets, 2 on phones), each photo in a 4px `--line` frame at 4:3; on hover it zooms slightly, a caption fades up from the bottom and a round "+" appears. "Showing 16 photos" above the grid.
- **Filtering** without reloading: photos slide into their new places (GSAP Flip, FR-UX-03). The grid keeps its height until every photo has landed and then eases to the new height, so the footer never shows behind moving photos. The address changes to `gallery.php?tab=rooms`, so tabs can be linked to (works without JavaScript too).
- **Photo viewer** (GLightbox, dark caption): only steps through the photos in the current tab; credited photos show "Photo: name / Wikimedia Commons, licence" under the caption.
- **Credits line** under the grid for the Multan photos (D21). Grid photos are small copies (`assets/images/gallery/thumbs/`, 480px) so the page stays light; the viewer loads the large copy.

### Contact — screenshots 11, 12

- Full-width Google Map (embedded iframe, free) about 450px tall.
- **Left:** "Contact Information"; one row per item with a 60px grey circle icon: Address, Phone, WhatsApp, GSM, Fax, Email. The label is bold dark, the value is underneath.
- **Right:** "**Write** Us" ("Write" in red, "Us" in grey), with fields Name, Email, Phone, Subject and Message, and a red **Send** button.
- **★** Messages are saved for the admin to read in the admin panel. A success message is shown on screen.
- **As built (`contact.php`):** photo banner of the building with four glass pill buttons (Call, WhatsApp, Email, Directions). Left: "Contact information" rows with 60px grey icon circles that turn red on hover, then **"Open right now"** (front desk 24 h and each restaurant, a green edge when open). Right: a white card with a red top edge, "**Write** Us", topic chips (red when chosen), Name / Email / Phone (optional) / Subject / Message with a live count, red **Send** ("SENDING…" with a spinner once pressed). Errors: a red box listing the problems (it takes the keyboard focus, each links to its field) plus a message under each field. The map is a full-width band (450px) with a frosted "Find us" card and **Get directions**; while it loads or with no internet the band shows a plain map-paper grid. Last: "Questions guests ask" (FAQ) as white rows that open one at a time with a + that turns into ×.

### About Us

- Ramada's version puts the text over a photo of the pool, which is hard to read. **Ours:** the photo first, then the text on a white background, then two columns: "Our Core Values" (Integrity, Accountability, Inclusive, Caring, Fun) and "Nearby Attractions".
- **As built (D20 style):** building photo (slow parallax) with a frosted dark card ("Welcome to Ramada by Wyndham Multan"); "Our story" with the hotel's own text, counting numbers and the lobby/reception collage with a "4★ The only four-star downtown hotel" badge; "Our core values" as five glassy cards on a dark band (red icon circle, faint big number, one line each, spotlight hover); "Inside the hotel" photo tiles; "Nearby attractions" cards (Wikimedia photo or a soft pink tile with an icon, a line of text, Get directions) with photo credits under them; red closing band with Book your stay / Reserve a table.

### ★ Login / Register / Forgot password

- **Two halves:** left, a full-height hotel photo that stays put while the form scrolls, with a dark frosted card ("★★★★ The Spot of Serenity & Comfort in Multan" and the address); right, on `--bg-alt`, a white card (460px) with a red heading, a grey one-line subtitle and the form. On phones: a 180px photo strip, then the card.
- **Fields:** small uppercase labels above white 50px boxes with a thin grey border; red border and a soft red glow on focus; red border and a red message with an icon under the field on error. 16px text so phones don't zoom.
- **Password:** an eye button to show/hide; new passwords get a strength bar (red → amber → green) with a one-line hint.
- Full-width red button in capitals (LOG IN, CREATE ACCOUNT, SEND RESET LINK, SAVE NEW PASSWORD). Links to switch between login and register under the card.
- **Messages** (success green, error red, info blue): a tinted box with a coloured left border and an icon. After actions on other pages (e.g. log out) the same message appears as a pop-up note under the header and fades after 6 s.
- **Emails** (FR-MAIL-07): red header with the white logo, white body with a red heading and a big red button, the plain link under it, dark footer with the hotel's address, phone and email.
- **★ Code pages** (FR-AUTH-08, 09, 13): "Check your email", the masked address ("co•••••@gmail.com"), one big code box (30px digits spaced apart, grey dots as placeholder), "The code works for 15 minutes", the red button, and under it "Didn't get it? Check your spam folder, or Send a new code (60 s)" counting down. Forgot password puts the code box above the new-password fields. In My Account the Change password card turns into "Confirm it's you" with the code box and "Cancel: keep my current password".
- **★ Code email:** the subject starts with the code ("482913 is your Ramada by Wyndham Multan verification code") so it can be read from the phone notification; inside, the code in a large grey box ("482 913"), how long it lasts and "Never share it".

### ★ My Account

- **Header strip:** dark gradient (`--ink` to deep red), red eyebrow "My Account", "Hello, Ali", the email and a green "Confirmed" chip.
- **Header menu:** when logged in, "Login" becomes a red initial circle + first name with a dropdown (Admin Panel for admins, My Bookings, My Orders, My Reservations, My Invoices, Profile & password, Log out).
- Tabs: My Bookings · My Orders · My Reservations · My Invoices · Profile.
- Each tab shows a table with a status badge and action buttons (Cancel, View invoice, Leave a review).
- **★ Current stay card** at the top while a guest is checked in: room number, dates, and the **running room bill**: room nights so far plus each room service order (date, items, amount), with the total so far. A red **ORDER ROOM SERVICE** button goes to the menu with Room service already selected.
- **My Bookings (as built):** "Upcoming stays" then "Past and cancelled". Each stay is a white row: photo (grey when cancelled), booking number + status badge, room name in red, dates and nights, guests, the free-cancellation note; right: total ("Pay at the hotel" / "Not charged") and View, 📅 add-to-calendar and Cancel (only while free cancellation is open; asks "Are you sure?").
- **★ Leave a review** button appears next to checked-out stays and completed orders that haven't been reviewed yet.

### ★ Invoice (print-friendly)

- White page, hotel logo and address at the top left, "INVOICE", invoice number and date at the top right, the guest's details below.
- A table of lines. For a stay: "Room nights — Pool Wing × 3 nights", then one line per room service order ("12 Nov — Room service order #45"), then subtotal and **Total** in bold, and "Paid — Cash — 14 Nov" or "Unpaid".
- **Print** button (hidden when printing). The header, footer and menus don't print.
- **As built (`invoice/view.php`):** a dark bar at the top (Back; for admins a cash/card choice and **Mark paid**; Print), then the invoice "paper": red top edge, logo and address left, light red spaced "INVOICE" with the number and date right; Billed to / For columns and a tilted rubber-stamp **PAID** (green, with method and date) or **UNPAID** (amber); lines with date, description, qty, price, amount; a large red total. Printing shows only the paper.
- **Current stay card** (as built): green top edge, "Your current stay", room and dates, red ORDER ROOM SERVICE, then the bill lines (room nights so far, each room service order with time and dishes) and "Total so far" in red.

### ★ Admin panel

- Separate simple layout: dark sidebar on the left with the hotel logo and links (Dashboard, Rooms, Room Types, Bookings, Menu, Orders, Tables, Reservations, Invoices, Feedback, Messages, Event halls, FAQ, Users). White content area on the right.
- Sidebar links also include **Kitchen Screen**, **Occupancy**, **Table QR Codes** and (Later) **Reports**.
- Dashboard: count cards across the top (red numbers), revenue total, then tables of recent bookings, orders and feedback. **★ "Needs attention"** card: amber warning lines (e.g. "Presidential Suite has no price, so guests can't book it.") with a small outline button that goes to the fix.
- On phones and tablets the sidebar is hidden behind a ☰ button and slides in (Bootstrap offcanvas). Work area: white top bar with the page title, grey subtitle and the main button on the right ("+ Add room type").
- **Room types:** one white card per type (photo 200px, name in red, price / guests / beds / photo count / room count with red icons, amber warnings), a "On the website" or "Hidden" badge, Edit, Hide/Show and "View on site". Hidden types have a grey photo.
- **Room type page:** Details form (name, price, description, beds, max adults, max children, feature line, amenities one per line, position, "Show on the website"); then **Photos**: tiles in a grid, the main photo with a red border and "★ Main photo" label, each tile with its description box and buttons (★ Main, ← →, Replace, delete). Under the grid, a dashed drop zone ("Drop photos here or click to choose") that previews the chosen photos. A red-edged "Delete this room type" card at the bottom explains why it can't be deleted while it has rooms.
- **Rooms:** four summary cards (rooms, available, under maintenance, booked tonight), an "Add rooms" form (number, optional "to" number, type, status), round filter chips per room type, a bar for changing ticked rooms, and a table (room, type, status badge, tonight's guest, upcoming bookings, icon buttons for edit / maintenance / delete).
- **★ Occupancy chart:** a grid with room numbers down the left (grouped by room type) and 14 days across the top, today's column highlighted. Each booking is a coloured bar across its nights, coloured by status (confirmed blue, checked in green, checked out grey), showing the guest's name. ‹ › buttons move by a week. Clicking a bar opens the booking.
- **Dashboard (as built):** "TODAY" cards (Arriving today, Guests in house, Leaving today; red left edge when there are any), "AT A GLANCE" count cards, **Latest bookings** table, then Needs attention.
- **Bookings:** tabs with counts (Arrivals today, In house, Departures today, Upcoming, All bookings, Cancelled), a search box, and a table (booking number, guest + phone, room + type, stay + nights + guests, total, status with amber "Not arrived yet" / "Should have left" notes, red Check in / Check out buttons and an open arrow).
- **Booking page:** left: Stay details list and "Move to another room" (rooms grouped by type, only free ones); right: Guest card (call, email, WhatsApp) and Actions (Check in / Check out in red, Cancel booking outline, with a line explaining what's possible).
- **Occupancy (as built):** ‹ week › and "Today" buttons, "Go to date", colour key; weekend columns slightly grey, today's column pink; rooms under maintenance striped amber; bars are rounded pills (square on a side that runs past the chart's edge); a bottom "Full" row with a small red meter and the percentage for each night. On phones the chart scrolls sideways inside its box with the room numbers fixed on the left.
- **Orders (as built):** tabs Active / Today / Completed / Cancelled / All with counts; order cards with a coloured top edge by status (amber received, blue preparing, green ready), the place in large text ("Room 104", "Table 3, Buzz 76", "Takeaway"), order number, type and "5 min ago", the dishes with red quantities, the kitchen note on a yellow strip, guest name and phone, total, and red buttons for the next step (Cancel as outline).
- **Messages (as built):** tabs New / Event enquiries / Handled / All, a search box, and one white card per message with a red left edge (grey once handled): topic pill (pink for events), "5 min ago", New/Completed badge, subject, message, sender with email and phone, and Reply by email (red), Call, WhatsApp, Mark handled.
- **Event halls / FAQ (as built):** one white card per hall or question with its form (Save, and a bin icon to remove), and an "Add" card with a red edge at the bottom.
- **Menu (as built):** a Categories card (rename in place, ↑ ↓, delete when empty, "New category" form), then one card per category listing each dish (round photo or fork icon, name and description, price in red, On the menu / Sold out badge, a Sold out / Back on menu button and edit). The dish page has name, price, category, position, description, optional photo (with the current one and "Remove this photo") and "On the menu".
- **★ Reports (Later):** date-range filter at the top, then chart cards (revenue per month as bars, occupancy rate as a line, top dishes as horizontal bars, average rating as a line), each with a **Download CSV** button. Charts use the brand red with greys.
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

| Room type | Max guests (children: ask the hotel) | Beds | Feature | Sample price (PKR / night)* |
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
