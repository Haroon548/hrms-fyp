# Requirements

The build checklist for the Hotel & Restaurant Management System, built for **Ramada by Wyndham Multan**. It is taken from the team's SRS, which stays the formal document. This file is the working list: what to build, where it comes from in the SRS, and what has been decided. How each page looks is in [design.md](design.md).

**Priority:** **MVP** = must be in the first working version. **Later** = build once the MVP works. **Optional** = only if time allows. **Future** = out of scope; listed as future work in the SRS.

**Status:** ☐ not started · ◐ in progress · ☑ done

**Build order:** shared layout (header, footer, design tokens) → authentication and email → rooms → room booking + availability calendars → restaurant and food orders → QR table ordering + kitchen screen → table reservation → billing + charge to room → live updates and safety checks → contact and information pages → feedback with verified reviews → admin dashboard → reports and WhatsApp (Later) → motion and polish (Later) → optional extras.

**Signature features** (beyond the SRS and Ramada's site; see the "Signature features" section): charge to room with one bill at check-out, QR table ordering with a kitchen screen, availability calendars, verified-guest reviews.

---

## Decisions

Choices made where the SRS is vague, conflicting, or not practical for this project.

| # | Decision | Reason |
|---|---|---|
| D1 | Backend is plain PHP (no framework), with MySQL and Bootstrap. | The SRS lists "PHP / Java / Python / .NET" and an optional Laravel or Django. Plain PHP is the easiest for the client to set up and host for free, and for the team to explain. |
| D2 | Roles are **admin** and **customer** for now. The `role` column is designed so a **staff** role can be added later without redoing the database. | The SRS mentions hotel manager, restaurant staff and business owner. For this version the admin does all management work. |
| D3 | Customers reset a forgotten password through a link sent by email. The admin can also reset a password from the admin panel, as a backup. | Email works through an outside email service (D9). The admin reset covers cases where the email doesn't arrive. |
| D4 | Confirmations (bookings, orders, reservations) are **always shown on screen** and **also sent by email**. If the email fails, the action still succeeds. | The demo must work without internet, and email can fail or land in spam. |
| D5 | Payment is recorded by the admin as cash / "paid". There is no online payment gateway. | The SRS lists the gateway as future work. |
| D6 | Complaints use the feedback form, with a type of "Feedback" or "Complaint". | Covers "Complaint Submission" (SRS 5.8) without a separate module. |
| D7 | Invoices are printed using the browser's print button on a print-friendly invoice page. | Covers "Invoice Printing" (SRS 5.7) without a PDF library. |
| D8 | Database backups are a manual export from phpMyAdmin, with steps in the setup guide. | Free hosting has no scheduled jobs. Covers SRS NFR 9 as far as is practical. |
| D9 | Email is sent with **PHPMailer** through an outside SMTP service (a Gmail account for the hotel, with an app password; port 587, TLS). PHPMailer's files are copied into the project, with no Composer. The SMTP details live in `config/config.php`. | PHP's `mail()` is blocked on free hosting, but SMTP to an outside provider works on InfinityFree. Copying the files keeps setup simple for the client. |
| D10 | Email verification on sign-up can be turned **on or off** in `config/config.php` (`EMAIL_VERIFICATION`). | On for the live site; off for an offline viva demo, where verification links can't be opened. |
| D11 | The site uses the real **Ramada by Wyndham Multan** name, logo and photos. The hotel gave permission for this university project. Keep that permission in writing. The footer credits the university project. | Agreed with the hotel. |
| D12 | The look and layout follow the official site ramadamultan.com, with the improvements listed in [design.md](design.md). The booking steps follow Wyndham's booking pages, but run on our own site. | The supervisor asked for the Ramada Multan site as the main inspiration. |
| D13 | Contact form messages are saved in the database for the admin to read, and the admin is notified by email. | Same as the Ramada contact page, but the messages don't get lost. |
| D14 | **Everything must work on free hosting with a free subdomain.** No paid servers, domains or services. Nothing that needs SMS, card payments, live connections (websockets) or server access. | The client will host it for free (InfinityFree). |
| D15 | **All libraries and fonts are stored inside the project** (`assets/vendor/`, `assets/fonts/`): Bootstrap, Font Awesome, Chart.js, the QR code library, the photo viewer, the date picker, GSAP (animations) and Fira Sans. Nothing is loaded from a CDN. The only exceptions are the Google Map on the Contact page and email sending. | The viva laptop may be offline, and the client gets one self-contained folder. |
| D16 | **No scheduled jobs.** Free hosting can't run tasks on a timer. Tidy-up work (e.g. marking past reservations as completed, expiring old tokens) runs when a relevant page is opened, such as the admin dashboard. No "reminder" emails that depend on a date arriving. | InfinityFree has no cron jobs. |
| D17 | **Live updates by long polling** (FR-LIVE-01). Open pages change by themselves when something they show is saved. Each browser sends **one** question to `live.php` at a time; the server holds it for up to 20 seconds and answers the moment something changes, so a change shows within about a second while the site gets about 3 requests a minute per open browser (not per tab). Nothing is asked while all tabs are hidden. If a host dislikes long requests, `LIVE_WAIT_SECONDS = 0` in `config.php` switches to a plain check every 15 seconds. | The team wants admin and guest screens to update without refreshing. Websockets aren't possible on free hosting (D14), and checking every few seconds from every tab would use up the ~50,000 requests a day free hosting allows. |
| D18 | **Order types:** *Room service* (in-house guest, charged to the room), *Dine-in* (at a table, from the QR code or the website), *Takeaway* (collect from the restaurant). Dine-in and takeaway are paid at the restaurant. | Needed for charge to room and QR ordering. |
| D19 | **Animation is an extra layer, never a requirement for reading the page.** Small effects use GSAP (stored in the project, D15). Text is never hidden or animated on scroll, every effect is off when the visitor has "reduce motion" turned on or uses a phone, and the page works the same if the script fails to load. | Makes the site feel more modern than Ramada's without repeating its problem of text that only appears after a scroll animation (design.md, section 5). |
| D20 | **The Home page is redesigned to be livelier than Ramada's**, while keeping the brand (red, Fira Sans, real hotel photos): a full-screen photo slideshow with headline and booking buttons, a see-through header, a frosted-glass search panel, photo collage with counters, swipeable room cards on phones, a dining band, facility photo tiles and a closing call to action. Other pages keep Ramada's layout (D12) unless changed later. | The team wanted a landing page that feels modern and alive, not a copy of the official site. |
| D21 | **Photos are honest.** Hotel pages and the gallery only show the hotel's own photos (for now, copies from ramadamultan.com, D11). No stock photos of other hotels, rooms or food presented as Ramada Multan. City photos (the Multan tab) may come from free-licence sources such as Wikimedia Commons, credited with the photographer and licence as the licence requires. | Guests book on what the photos show; misleading photos lead to complaints and copyright problems. |
| D23 | **No separate Multan page** (the team's choice). The city is covered by About Us ("Nearby attractions", with directions) and the Gallery's Multan tab; Home's Multan card links to the attractions. FR-WEB-08 is dropped. | It added little beyond those two sections and wasn't needed. |
| D22 | **Illustrative food photos for now** (the team's choice, with D21's honesty kept): until the hotel sends its own food photos (Q7), the Dining page may use food and dining-mood photos that aren't the hotel's. Each one is marked "illustrative" in the database, shows a small **"Illustrative photo"** label on the page, and can be replaced from the admin panel. The restaurants' own rooms (Buzz 76, Cafe Saints, Executive Lounge) always use the hotel's real photos. The two photos copied from recipe blogs (brunch spread, chicken plate) were copyrighted and **were removed before going public**, replaced by the hotel's own hi-tea, buffet, dessert and pool photos. | The page needs to show the dining experience now; labelling keeps guests from being misled, and the swap list keeps the live site legal. |

---

## Functional requirements

### Authentication (SRS 3.1.1, 3.3.1, 5.3)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-AUTH-01 | A visitor can register with name, email, phone and password. Email must be unique. | MVP | ☑ |
| FR-AUTH-02 | A user can log in with email and password. Wrong details show an error message. | MVP | ☑ |
| FR-AUTH-03 | A logged-in user can log out, which ends the session. | MVP | ☑ |
| FR-AUTH-04 | Each user has a role (admin / customer). Admin pages can only be opened by admins. | MVP | ☑ |
| FR-AUTH-05 | Passwords are stored hashed with `password_hash` and checked with `password_verify`. | MVP | ☑ |
| FR-AUTH-06 | The admin can reset a user's password (see D3). | MVP | ☐ |
| FR-AUTH-07 | A logged-in user can change their own password. | Later | ☑ |
| FR-AUTH-08 | **Email verification with a code** (changed from a link at the team's request): after registering, the customer gets an email with a **6-digit code** ("482913 is your Ramada by Wyndham Multan verification code") and types it on the next page; the right code confirms the email and logs them in, back to the page they came for. Logging in to an unconfirmed account (with the right password) sends a new code. Signing up again with an email that was never confirmed updates that account instead of saying "taken". Can be turned off (D10). | MVP | ☑ |
| FR-AUTH-09 | **Forgot password with a code:** the customer enters their email and gets a 6-digit code, then enters the code and a new password on the next page. The password is checked first, so a weak password doesn't use up the code. A right code also unlocks the account and confirms the email. The pages look exactly the same whether or not the email has an account, so they don't reveal who has one. | MVP | ☑ |
| FR-AUTH-10 | **Login lockout:** after 5 wrong passwords in a row the account is locked for 10 minutes; the message says how long, and the last 2 tries warn first. A password reset unlocks it. (Ramada's "Member Login" doesn't work at all.) | MVP | ☑ |
| FR-AUTH-11 | **Friendlier forms:** show/hide password eye, a live strength bar with hints, errors under each field, the form keeps what was typed after an error, and login sends people back to the page they were going to (`?next=`, only pages on this site). | MVP | ☑ |
| FR-AUTH-13 | **Codes like a bank's** (not in the SRS): codes last 15 minutes, work once, stop after 5 wrong tries ("3 tries left"), are stored only as a hash, and a new one can be sent once a minute ("Send a new code (42 s)"). The code box takes digits only, accepts a pasted "482 913", lets phones fill it from the email notification, and sends itself once 6 digits are in. **Changing the password in My Account** also needs a code emailed to the account (after the current password), and **every password change sends a "Your password was changed" alert** with the time and a reset link. The email address is shown masked ("al•••••@gmail.com"). | MVP | ☑ |
| FR-AUTH-14 | **Change email** (not in the SRS) in My Account → Profile, for guests and the admin: the new email and the current password, then a 6-digit code sent to the **new** address (proving it's theirs); after the right code the account uses the new email to log in, and the **old** address gets a "Your email was changed" alert. An email another account uses is refused (checked again when the code is entered). | MVP | ☑ |
| FR-MAIL-10 | **Real-inbox emails** (not in the SRS): the logo is sent inside the email, so it shows in Gmail even when the site runs on localhost; the sender is the hotel's Gmail with "Ramada by Wyndham Multan" as the name and replies go to the hotel's address; **Email check** in the admin panel says whether email is set up, sends a test email and explains a failure in plain words (e.g. "Gmail refused the login: check the app password"); the dashboard warns while email isn't set up. | MVP | ☑ |
| FR-AUTH-12 | **Form protection:** every form that changes something carries a secret token (CSRF), so another website can't submit it for a logged-in user; log out is a form, not a link. | MVP | ☑ |

### Rooms (SRS 2.4.1, 3.1.2, 5.4)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-ROOM-01 | The admin can add, edit and delete room types: name, description, price per night, beds, feature line, **maximum adults** and **maximum children**. Changes to prices and occupancy show on the site straight away (photos: FR-ROOM-07, FR-ROOM-08). | MVP | ☑ |
| FR-ROOM-02 | The admin can add, edit and delete rooms (room number, type, status). | MVP | ☑ |
| FR-ROOM-03 | The admin can set a room's status: available or under maintenance. Rooms under maintenance can't be booked. | MVP | ☑ |
| FR-ROOM-04 | Anyone can browse room types with photos, price and details. | MVP | ☑ |
| FR-ROOM-05 | A customer can enter check-in and check-out dates, the number of **adults** and **children**, and see which rooms are free for those dates and big enough for that group. | MVP | ☑ |
| FR-ROOM-06 | Each room type has a details page: photo gallery, description, amenities list, price, and a booking box (see design.md). | MVP | ☑ |
| FR-ROOM-07 | The admin can upload several photos per room type and set its amenities. | MVP | ☑ |
| FR-ROOM-08 | **Room photos (admin):** for each room type the admin can choose the **main photo** (the large one at the top of the room page and on the room card), change the order of the photo grid, replace a photo and delete a photo, the same layout as the room pages on ramadamultan.com. Changes show on the site straight away. | MVP | ☑ |
| FR-ROOM-09 | **Search results that help people decide** (Ramada shows no prices at all): with dates, each room type shows the price per night and the **total for the stay**, an "Only 2 rooms left!" tag when few are free, and rooms that are full or too small for the group move to the bottom with the reason. Unpriced rooms say "Price on request" and link to the hotel. | MVP | ☑ |
| FR-ROOM-11 | **Quicker room admin** (not in the SRS): add a run of rooms in one go ("105 to 108"), tick several rooms and mark them available or under maintenance together, see who is in each room tonight and how many upcoming bookings it has, and get a warning when a room with upcoming bookings is put under maintenance. Rooms with booking history can't be deleted (mark them under maintenance instead). | MVP | ☑ |
| FR-ROOM-12 | **Safe, light photo uploads:** uploads are checked to be real JPG/PNG/WebP photos, phone photos are turned the right way up, big photos are shrunk to 1920px and saved as new JPEGs with random names (fast on free hosting, NFR-12), and the uploads folder never runs scripts. Drag-and-drop with previews; replacing or deleting a photo removes the old file. | MVP | ☑ |
| FR-ROOM-13 | The search bar's adults and children choices follow the biggest room type the admin has set up, instead of a fixed 3 adults / 2 children. | MVP | ☑ |
| FR-ROOM-10 | **"Good to know" box** on every room page (check-in/out times, free cancellation until 6 PM, breakfast included, pay at the hotel, parking and shuttle), and a booking box at the top of the right column. | MVP | ☑ |

### Room booking (SRS 2.4.3, 3.1.4, 3.3.2, 5.6, NFR 10)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-BOOK-01 | A logged-in customer can book a free room for chosen dates. The booking saves the number of adults and children, which can't be more than the room type allows (checked on the server). | MVP | ☑ |
| FR-BOOK-02 | Check-out must be after check-in, and check-in can't be in the past. | MVP | ☑ |
| FR-BOOK-03 | **No double booking:** a room can't be booked if the dates overlap an existing booking that isn't cancelled. The check runs on the server when the booking is saved. | MVP | ☑ |
| FR-BOOK-04 | The total price is nights × price per night, worked out on the server. | MVP | ☑ |
| FR-BOOK-05 | After booking, a confirmation page shows the booking details. | MVP | ☑ |
| FR-BOOK-06 | A customer can see their booking history. | MVP | ☑ |
| FR-BOOK-07 | A customer can cancel an upcoming booking. | MVP | ☑ |
| FR-BOOK-08 | The admin can see all bookings and change their status (confirmed, checked in, checked out, cancelled). | MVP | ☑ |
| FR-BOOK-09 | **Arrival time and requests** (not on Ramada): at checkout the guest can give an estimated arrival time and special requests (up to 500 characters); the front desk sees both on the booking. The guest's name and phone are filled in from the account and kept up to date. | MVP | ☑ |
| FR-BOOK-10 | **Booking extras for guests:** the confirmation page has "Add to my calendar" (a calendar file for Google Calendar, Outlook and phones), Get directions, Share on WhatsApp, Message the hotel, Print, and a 3-step progress line (Choose room → Your details → Confirmed). Unpriced or full rooms explain why and link to the calendar or other rooms. | MVP | ☑ |
| FR-BOOK-11 | **Front desk tools** (not in the SRS): booking lists for Arrivals today, In house, Departures today, Upcoming, All and Cancelled with counts; search by booking number, name, phone, email or room; one-click Check in / Check out; warnings for guests who haven't arrived or should have left; "Today" cards on the dashboard; and **Move to another room** (only rooms free for the whole stay; the price stays the same), e.g. when a room needs repairs. | MVP | ☑ |
| FR-BOOK-12 | **Safe under pressure:** two guests booking the last room at the same moment can't both get it (the rooms are locked inside a database transaction), and pressing Complete booking twice or resending the form never makes a second booking. Booking numbers avoid look-alike characters (RM-7K3QX9). Check-in can be at most a year ahead. | MVP | ☑ |

### Restaurant and food orders (SRS 2.4.2, 3.1.3, 3.3.3, 5.5)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-MENU-01 | The admin can add, edit and delete menu categories. | MVP | ☑ |
| FR-MENU-02 | The admin can add, edit and delete menu items (name, category, price, description, image, available yes/no). | MVP | ☑ |
| FR-MENU-03 | Anyone can view the menu, grouped by category. | MVP | ☑ |
| FR-ORD-01 | A logged-in customer can pick items and quantities, choose the order type (D18) and place an order. Room service is only offered to guests who are checked in. | MVP | ☑ |
| FR-ORD-02 | The order total is worked out on the server from current menu prices. | MVP | ☑ |
| FR-ORD-03 | The admin can see all orders and update their status (pending, preparing, ready, completed, cancelled). | MVP | ☑ |
| FR-ORD-04 | A customer can see their order history and each order's current status. | MVP | ☑ |
| FR-ORD-05 | **Ordering that doesn't go wrong** (not in the SRS): the cart is kept on the server (works without JavaScript, survives reloads and logging in); dine-in asks which table you're at; a note for the kitchen ("less spicy"); dishes marked sold out since they were added are dropped with a message; pressing Place order twice never makes two orders; the guest can cancel until the kitchen starts; the order page shows Received → Preparing → Ready → Served/Collected and changes by itself the moment the kitchen moves the order on (FR-LIVE-01). | MVP | ☑ |
| FR-MENU-04 | **Menu admin extras:** a one-click **Sold out** switch per dish (hidden from the menu at once, back with one click), categories moved up/down and renamed in place, an optional round photo per dish (checked and shrunk like room photos), and deleting a dish never changes past orders (their name and price were saved with the order). | MVP | ☑ |
| FR-WEB-23 | **Dining page in the Amici style** (team's choice, inspired by restaurant-amici.com and seiste.com): a slowly drifting wall of food and restaurant photos behind the heading (plain CSS, no 3D), a moving red strip of what's on offer, each restaurant as a tilted menu card over its own photo that straightens as you scroll, a "Moments" row of tilted photo cards that slides sideways while scrolling, the menu as a printed menu card (red checkered edges, dotted price lines, sticky section tabs), a cart button that pops when a dish is added, and a "Reserve a table" card on a table photo. Text is always visible; nothing moves for "reduce motion"; phones get 2 photo columns and a swipeable row. | MVP | ☑ |

### Table reservation (SRS 2.4.2, 3.1.4, 3.3.4, 5.6)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-TBL-01 | The admin can add, edit and delete dining tables (table number, seats). | MVP | ☑ |
| FR-TBL-02 | A logged-in customer can pick a date, time and party size and see tables that are free and big enough. | MVP | ☑ |
| FR-TBL-03 | A customer can pick a table and confirm the reservation. | MVP | ☑ |
| FR-TBL-04 | **No double reservation:** a table can't be reserved twice for the same time slot. | MVP | ☑ |
| FR-TBL-05 | A customer can see and cancel their reservations. | MVP | ☑ |
| FR-TBL-06 | The admin can see all reservations and change their status. | MVP | ☑ |
| FR-TBL-07 | **Smarter reservations** (not in the SRS): a reservation holds its table for 2 hours; times follow each restaurant's opening hours (from opening until 90 minutes before closing, today only later times), which the admin edits on **Tables & hours**; free tables are shown smallest first with seat dots; if nothing is free, the nearest free times are offered; groups over 12 are asked to call; a note ("birthday, high chair"); two guests can't take the same table at the same moment (the table is locked while saving); the Dining page's "Reserve a table" card is a quick form; past reservations are marked completed when the admin opens the list (D16); the host's Today list counts expected guests and has one-tap **Seated**. | MVP | ☑ |

### Billing (SRS 5.7)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-BILL-01 | An invoice can be created for a room booking or a food order, with a unique invoice number. | MVP | ☑ |
| FR-BILL-02 | The invoice shows the customer, the items or nights, and the total. | MVP | ☑ |
| FR-BILL-03 | The admin can mark an invoice as paid (cash). The payment date is saved. | MVP | ☑ |
| FR-BILL-04 | The invoice page can be printed (see D7). | MVP | ☑ |
| FR-BILL-05 | A customer can view their own invoices. | MVP | ☑ |
| FR-BILL-06 | A stay's invoice includes its room service orders (FR-SIG-ROOM-03). | MVP | ☑ |
| FR-BILL-07 | **Billing without extra steps** (not in the SRS): checking a guest out makes the stay invoice by itself and opens it to print; completing a dine-in or takeaway order (on the Orders page or the kitchen screen) makes that order's invoice; cancelled orders never reach a bill; invoices are numbered INV-2026-0001 upwards and never reused, even when several are made at the same moment; each invoice is emailed with a link; a **PAID** / **UNPAID** stamp, payment method (cash or card) and date are shown; the Invoices page shows money waiting, paid today and paid this month, with one-click "Paid cash" / "Paid card"; invoice lines are copied, so later price changes never alter an invoice. | MVP | ☑ |

### Feedback (SRS 5.8)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-FB-01 | A logged-in customer can submit a rating (1–5), a comment and a type (feedback / complaint). Public reviews follow FR-SIG-REV. | MVP | ☐ |
| FR-FB-02 | The admin can view all feedback and complaints. | MVP | ☐ |
| FR-FB-03 | The admin can mark a complaint as resolved. | Later | ☐ |

### Admin dashboard and reports (SRS 3.1.5, 5.9)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-ADM-01 | The dashboard shows counts: customers, rooms, bookings, orders, reservations. | MVP | ◐ |
| FR-ADM-02 | The dashboard shows total revenue from paid invoices. | MVP | ☑ |
| FR-ADM-03 | The dashboard lists recent bookings, orders and feedback. | MVP | ◐ |
| FR-ADM-04 | The admin can see the list of users and change a user's role. | MVP | ☐ |
| FR-ADM-05 | Revenue and booking reports filtered by date range (see FR-RPT). | Later | ☐ |
| FR-ADM-06 | **Everything the hotel changes day to day is done from the admin panel**, never by editing code or the database: rooms, prices, photos, menu, tables, bookings, orders, reservations, invoices, reviews, messages and users. | MVP | ☐ |
| FR-ADM-07 | **"Needs attention" list** on the dashboard (not in the SRS): room types that guests can see but can't book (no price, no rooms, every room under maintenance) or that have no photos, each with a button that goes straight to the fix. The room type list shows the same warnings. | MVP | ☑ |

### Email (D4, D9, D10)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-MAIL-01 | All emails are sent through one shared function (e.g. `send_email()` in `includes/mail.php`) using the SMTP settings in `config/config.php`. | MVP | ☑ |
| FR-MAIL-02 | If an email fails, the booking, order or reservation is still saved, the confirmation still shows on screen, and the error is logged. The user is never shown a technical error. | MVP | ◐ |
| FR-MAIL-03 | Booking confirmation email: booking number, room type, dates, nights, guests, total, "Pay at Hotel", cancellation policy. | MVP | ☑ |
| FR-MAIL-04 | Booking cancellation email. | MVP | ☑ |
| FR-MAIL-05 | Food order email when the order is placed, with the items and total. | MVP | ☑ |
| FR-MAIL-06 | Table reservation confirmation and cancellation emails. | MVP | ☑ |
| FR-MAIL-07 | Emails use a simple HTML template with the hotel logo, red header, and the hotel's address and phone in the footer, plus a plain-text version. | MVP | ☑ |
| FR-MAIL-08 | Email when the admin changes an order's status (e.g. "Your order is ready"). | Later | ☐ |
| FR-MAIL-09 | Invoice email with a link to view and print the invoice. | Later | ☑ |

### Website pages (D12, design.md)

Information pages copied from ramadamultan.com and improved. Content is in design.md, section 6.

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-WEB-01 | Home page: photo slideshow, availability search bar, welcome section, room cards with prices, a Multan card (linking to the nearby attractions, D23) and latest reviews, facilities. | MVP | ◐ |
| FR-WEB-02 | Dining page: each restaurant with description, opening hours and photos, plus the menu and a "Reserve a table" button. | MVP | ☑ |
| FR-WEB-03 | Gallery page with category tabs (Banquet, Health & Recreation, Lobby, Restaurants, Rooms, ★ Multan) and a photo viewer. The admin can upload photos and choose their category (admin part: later chunk). | MVP | ◐ |
| FR-WEB-04 | Contact page: map, contact details, and a contact form (name, email, phone, subject, message) with validation. | MVP | ☑ |
| FR-WEB-05 | Contact form messages are saved; the admin can read them and mark them as handled, and gets an email for each new message (D13). | MVP | ☑ |
| FR-WEB-06 | About Us page: welcome text, core values, nearby attractions. Built in the Home style: building photo with a frosted card, the hotel's own welcome text beside a photo collage with counting numbers, the five values as cards on a dark band, "Inside the hotel" photo tiles, attractions with credited Wikimedia photos and a **Get directions** link each (Google Maps, no API key), and a closing call to action. | Later | ☑ |
| FR-WEB-07 | Meeting & Events page: description and halls table, with a "Contact us" button. Built in the Home style: banquet photo banner, four event types on the hotel's own photos, numbers worked out from the halls, each hall as a card with its **floor plan drawn to scale** (all in the same square, so sizes compare at a glance), Ramada's halls table, a swipeable row of banquet photos and an enquiry form (FR-WEB-24). The halls are edited in **Event halls** in the admin panel. | Later | ☑ |
| FR-WEB-09 | The same header, phone menu, footer and back-to-top button on every page. | MVP | ◐ |
| FR-WEB-10 | **Site photos (admin):** the admin can change the Home slideshow photos (add, remove, reorder) and the photos of the welcome section, facilities, dining and Meeting & Events pages, without editing code. | MVP | ◐ |
| FR-WEB-11 | **Book button in the phone header.** Ramada's phone header has no way to book; ours shows a red BOOK button next to the logo. | MVP | ◐ |
| FR-WEB-12 | **Hotel highlights strip** on Home, under the search bar: check-in/check-out times, free breakfast, WiFi, parking, airport shuttle, pool and gym. Ramada hides these on other pages. | MVP | ◐ |
| FR-WEB-13 | The search bar shows the length of stay as soon as both dates are picked ("3 nights · Fri 16 Oct → Mon 19 Oct"). | MVP | ◐ |
| FR-WEB-14 | **Link previews:** every page has a description and a preview photo, so a shared link shows the hotel name, text and photo on WhatsApp and Facebook. | MVP | ◐ |
| FR-WEB-15 | Footer has a **Get directions** link (opens Google Maps, no API key) and a tappable **WhatsApp us** link. | MVP | ◐ |
| FR-WEB-16 | **Keyboard and screen-reader access:** a "Skip to content" link, visible focus outlines, labels on every form field and icon button, and photo descriptions. | MVP | ◐ |
| FR-WEB-18 | **"Book in 3 easy steps"** on Home: pick dates → choose a room → pay at the hotel, with a "Check availability" button. Tells guests they book directly and don't pay online. | MVP | ◐ |
| FR-WEB-19 | **Around-the-hotel map** on the Contact page: a drawn map of Multan with the hotel and nearby sights (Shah Rukn-e-Alam, Multan Fort, Ghanta Ghar, Bahauddin Zakariya shrine, the airport), each with a "Get directions" link. No map API. | Later | ☐ |
| FR-WEB-20 | **FAQ** on the Contact page: check-in/out times, parking, airport shuttle, cancellation, breakfast, children; answers that open one at a time, under a large heading. The admin can edit the questions (**FAQ** in the admin panel: wording, order, hide, add, delete). | Later | ☑ |
| FR-WEB-21 | **"Inside the hotel" filmstrip** on Home: framed photo cards (every room type, lobby, Buzz 76, Cafe Saints, pool, lounge) on a curved 3D rail that follows the mouse; ‹ › buttons, swipe on phones, arrow keys when focused; click a card to bring it forward, click again to open its page. Page scrolling is never blocked. Adapted from a free ThreeUI component (MIT, credited in the code and README). | MVP | ◐ |
| FR-WEB-24 | **Event enquiry form** (not on Ramada, whose page only has a table): kind of event, date, roughly how many guests, hall ("Not sure yet" allowed), name, phone, email and a note. Saved as a message with the topic "Meetings & events" and a one-line summary ("Wedding or walima · Sat 12 Dec 2026 · 300 guests · Zainab Hall"); "Enquire about this hall" on a hall card picks that hall. Event booking itself stays out of scope. | Later | ☑ |
| FR-WEB-25 | **Contact page extras** (not on Ramada): one-tap Call / WhatsApp / Email / Directions in the banner; "Open right now" for the front desk and each restaurant (from its hours); a topic choice on the form that points event questions to the enquiry form; the logged-in guest's name, email and phone filled in; a live character count; after a failed send, a list of problems that takes the keyboard focus and links to each field; a copy of the message is emailed to the guest; a hidden box that catches spam robots and a limit of 5 messages an hour per visitor; the map card works without internet. **Admin Messages page:** New / Event enquiries / Handled / All with counts, search, Reply by email, Call, WhatsApp, Mark handled; the unread count shows in the sidebar and on the dashboard. | MVP | ☑ |
| FR-WEB-22 | **Gallery page heading:** a slowly turning ring of 16 hotel photos around the "Gallery" heading, shaded for depth, with reflections; hover slows it and it can be dragged (idea from ThreeUI's Gallery Heading, calm version, in Ramada colours; no glitch effect). | Later | ☑ |

---

## Signature features

Features that neither the SRS nor ramadamultan.com has. They make the project stand out, and all of them work on free hosting (D14). Screens are described in design.md, section 4.

### Charge to room and one bill at check-out

Answers the SRS problem "inefficient communication between hotel and restaurant departments" (SRS 1.6).

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-SIG-ROOM-01 | A customer whose booking is **checked in** can order food as **Room service**. The order shows their room number and is **added to the room bill** instead of being paid separately. | MVP | ☑ |
| FR-SIG-ROOM-02 | While a guest is staying, they can see their running room bill (room nights so far plus room service orders) in My Account. | MVP | ☑ |
| FR-SIG-ROOM-03 | At check-out, the admin creates **one invoice** for the stay: room nights plus every room service order, each listed with date and amount, and one grand total. | MVP | ☑ |
| FR-SIG-ROOM-04 | Room service can't be charged to a booking that is checked out or cancelled. Checked on the server. | MVP | ☑ |

### QR code table ordering and kitchen screen

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-SIG-QR-01 | The admin can print a **QR code for each dining table**. The code holds a link to the menu with the table number, built from the site address in `config/config.php`, so the codes still work if the site address changes (reprint them). | MVP | ☑ |
| FR-SIG-QR-02 | Scanning the code opens the menu on the phone with "Table 5" shown at the top. The order type is set to Dine-in for that table. | MVP | ☑ |
| FR-SIG-QR-03 | A guest can order from the table **without an account** by entering just a name, so walk-in diners can use it. Logged-in customers have the order saved to their history. | MVP | ☑ |
| FR-SIG-QR-04 | **Kitchen screen** (admin login): new, preparing and ready orders shown in three columns, each card with table or room number, items, notes and time since ordered. One click moves an order to the next column. | MVP | ☑ |
| FR-SIG-QR-05 | The kitchen screen shows new orders on its own, within about a second (FR-LIVE-01, D17), and plays a short sound when a new order arrives. The "6 min" on each card counts up on the screen itself. | MVP | ☑ |
| FR-SIG-QR-07 | **QR extras** (not in the SRS): the scanned table is remembered on the phone for 4 hours ("Not here?" forgets it); a walk-in can follow their own order without an account, but other phones can't open it; the kitchen screen turns a card red after 10 minutes waiting, flashes new cards, shows the waiting count in the browser tab, says "Live" or goes pink if the Wi-Fi drops, and the sound is switched on with one tap (browsers block sound until then). The QR cards page warns when SITE_URL is still localhost, and prints 3 cards per row with cutting lines. QR codes come from qrcode-generator 1.4.4 (MIT), stored in the project. | MVP | ☑ |
| FR-SIG-QR-06 | The guest's order page shows the current status (Received → Preparing → Ready) and updates itself the same way. | Later | ☑ |

### Availability calendars

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-SIG-CAL-01 | Each room type page shows a **month calendar** with free dates in green and full dates in red, with arrows to move between months. Clicking two dates fills in the booking box. | MVP | ☑ |
| FR-SIG-CAL-02 | **Admin occupancy chart:** rooms listed down the side, dates across the top (two weeks at a time), and each booking drawn as a coloured bar (colour by status). Clicking a bar opens the booking. | MVP | ☑ |
| FR-SIG-CAL-03 | Both calendars use the same availability check as booking (FR-BOOK-03), so what's shown always matches what can be booked. | MVP | ☑ |

### Verified-guest reviews

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-SIG-REV-01 | A customer can leave a **public review** only for a stay that is checked out or an order that is completed, and only one review per stay or order. | MVP | ☐ |
| FR-SIG-REV-02 | Reviews show the guest's first name, the star rating, the date and a **"✓ Verified stay"** or **"✓ Verified order"** badge. | MVP | ☐ |
| FR-SIG-REV-03 | The home page shows the average rating and the latest three reviews (replacing Ramada's TripAdvisor box). | MVP | ☐ |
| FR-SIG-REV-04 | The admin can hide an inappropriate review. Complaints stay private and are never shown publicly. | MVP | ☐ |

### Live updates

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-LIVE-01 | **No refresh button needed, for guests or staff** (the team's request; D17). When something is saved anywhere, every open page that shows it changes within about a second: the guest's order page when the kitchen moves the order on; the kitchen screen when an order comes in; the room and Rooms pages when the admin changes a price or photo, or a room is booked; My Account when the guest is checked in or out; the invoice when it is marked paid; the menu and cart when a dish is sold out or repriced; admin lists (bookings, orders, rooms, reservations, messages, …) when a guest books, orders or writes; the admin sidebar shows new orders and unread messages. How: every database change notes its table (`includes/db.php`), `live.php` answers by long polling, `assets/js/live.js` loads the page again in the background and swaps only its live parts, with a short red glow. | MVP | ☑ |
| FR-LIVE-02 | **Live updates never get in the way:** a part where the visitor is typing, or has a menu open, is not swapped; a small bar says "This page has new information · Update" instead. Pages shown after a form error are never swapped. Sideways scroll positions are kept. One tab per browser asks the server and shares the answer (Web Locks + BroadcastChannel); hidden tabs catch up when shown again; it all works the same without JavaScript (the page is just not live). | MVP | ☑ |

### Safety checks (changes while guests are mid-way)

What happens when the hotel changes something that guests are using at that moment. Prices are copied into each booking, order and invoice, so later changes never alter them (FR-BOOK-04, FR-ORD-02, FR-BILL-07).

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-SAFE-01 | **Price changed while the guest is booking:** the booking is only made at the price the guest saw. If the admin changed it meanwhile, nothing is booked and the guest is shown the new price and total; pressing Complete booking again books at the new price. The checkout's price box updates live, and the form then uses that price. | MVP | ☑ |
| FR-SAFE-02 | **Menu price changed while the guest is ordering:** the same for food: the order is placed only at the total the guest saw; otherwise the new total is shown first. | MVP | ☑ |
| FR-SAFE-03 | **Price, guests or visibility changed for a room type with bookings:** saving shows a note that guests who already booked (and those staying now) keep the price they booked at, which bookings now have more guests than the new limit, and that hidden room types keep their bookings. | MVP | ☑ |
| FR-SAFE-04 | **A room's type can't be changed while it has a guest or upcoming bookings** (they booked, and pay for, that kind of room); the edit form says so and points to Move to another room. Putting an occupied room under maintenance names the guests and their dates; their bookings stay. | MVP | ☑ |
| FR-SAFE-05 | **No check-out while room service is still on its way:** the stay's bill would be wrong. The admin is told which orders to serve or cancel first. | MVP | ☑ |
| FR-SAFE-06 | **Restaurant tables and hours:** taking a reserved table out of use, giving it fewer seats than a reserved party, or changing opening hours so that reservations fall outside them keeps those reservations and lists them (date, time, table, guest) so the host can call or reseat them. | MVP | ☑ |
| FR-SAFE-07 | **Two admins editing the same room type or dish:** the second save doesn't silently overwrite the first. It is stopped with "Someone else saved changes…", the admin's typed version stays in the form to compare, and pressing Save again saves it. | MVP | ☑ |

### Reports and charts

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-RPT-01 | A Reports page with charts (Chart.js): revenue per month, occupancy rate per month, most popular dishes, average rating over time. | Later | ☐ |
| FR-RPT-02 | Each report can be filtered by date range and downloaded as a **CSV file** that opens in Excel. | Later | ☐ |

### WhatsApp

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-WA-01 | A floating "Chat on WhatsApp" button on every public page opens a chat with the hotel's WhatsApp number (a free `wa.me` link; the number is set in `config/config.php`). | Later | ☐ |
| FR-WA-02 | The booking, order and reservation confirmation pages have a "Share on WhatsApp" button with the details filled in. | Later | ◐ |

### Motion and polish

Small animations that make the site feel more modern than Ramada's. All follow D19. How each one looks is in design.md, section 3 ("Motion").

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-UX-01 | **Spotlight hover:** on a computer, a soft glow follows the mouse over room cards and the dark panels of "Our Facilities & Services" (`assets/js/spotlight.js`, turned on with `data-spotlight`). | Later | ◐ |
| FR-UX-02 | Photos (not text) are uncovered from the bottom the first time they scroll into view on the Home, Rooms and Gallery pages. | Later | ◐ |
| FR-UX-03 | Gallery tabs re-arrange the photos with a smooth movement instead of a jump (GSAP Flip). | Later | ☑ |
| FR-UX-04 | Adding a dish to the cart gives quick feedback: the cart count briefly grows and the total updates. | Later | ☑ |
| FR-UX-05 | **Home hero motion:** slow zoom on the slideshow photo, headline and buttons rise in on page load, the header turns from see-through to white on scroll, and the slideshow has dots, arrows and a **pause** button (stops by itself for "reduce motion"). | MVP | ◐ |
| FR-UX-07 | **Digital room key** on the booking confirmation page: the booking number on a card styled like a hotel key card that tilts and catches the light as the mouse moves (CSS 3D, no Three.js). | Later | ☑ |
| FR-UX-08 | **Opening intro** on Home, first visit per browser tab only, about 3.5 s: arriving at the hotel. An HD photo (the hotel's own, from Wyndham's property page, 2200px) of the **building with its RAMADA sign** fades up and pulls back slowly (only the hotel building; the lobby shot was dropped); the building then darkens in the hero's colours as the white logo and "The Spot of Serenity & Comfort" rise in; then everything dissolves into the Home page. "Skip intro" and Esc close it; it always removes itself within 9 s; off without JavaScript or with "reduce motion". `localhost/hrms-fyp/?intro` always plays it, for demos. (Earlier tries — a photo globe, a longer film, a cut-out RAMADA with light beams, a blur-only version — were dropped.) | MVP | ◐ |
| FR-UX-06 | **Depth and numbers:** photos in the welcome collage and dining band move slightly slower than the page (parallax), and the welcome numbers count up when they come into view. Cards lift and photos zoom slightly on hover. | Later | ◐ |

### Optional extras (only if time allows)

| ID | Requirement | Priority | Status |
|---|---|---|---|
| FR-OPT-01 | **Promo codes:** the admin creates codes (e.g. "EID20") with a percentage or fixed discount, valid dates and a usage limit. Customers enter a code at checkout. | Optional | ☐ |
| FR-OPT-02 | **Weekend and season prices:** each room type can have a different price on weekends and during date ranges set by the admin. The total adds up each night's price. | Optional | ☐ |
| FR-OPT-03 | **Activity log:** important admin actions (status changes, price changes, deletions, payments) are recorded with who, what and when, and listed for the admin. | Optional | ☐ |
| FR-OPT-04 | **Change booking dates:** a customer can change the dates of an upcoming booking if the room is free for the new dates. | Optional | ☐ |
| FR-OPT-05 | **Urdu / English switch:** all public page text is available in both languages. | Optional | ☐ |

---

## Non-functional requirements (SRS 3.5)

| ID | Requirement | How it's met |
|---|---|---|
| NFR-01 Security | Hashed passwords, login required for customer actions, admin pages checked by role. | `password_hash`, a session check at the top of each protected page. |
| NFR-02 Security | Protection against SQL injection and XSS. | PDO prepared statements for all queries; all output escaped with `htmlspecialchars`. |
| NFR-03 Data integrity | No double bookings or double reservations; invalid input rejected. | Server-side validation; overlap checks (FR-BOOK-03, FR-TBL-04); foreign keys in the database. |
| NFR-04 Usability | Simple, clear navigation; works on phones, tablets and desktops. | Bootstrap responsive layout. |
| NFR-05 Compatibility | Works in Chrome, Firefox, Edge and Safari. | Standard HTML/CSS/JS, tested in each. |
| NFR-06 Performance | Pages respond within 3 seconds under normal use. | Simple queries with indexes. Measured during testing, not claimed in advance. |
| NFR-07 Maintainability | Modular code that is easy to find and change. | One folder per feature; shared code in `includes/`; documented setup. |
| NFR-08 Portability | Runs on XAMPP and on free PHP + MySQL hosting. | No Composer, npm or build step; libraries such as PHPMailer are copied into the project; one config file; one SQL import. |
| NFR-10 Security | Email codes for verification and password changes can't be guessed or reused. | 6-digit codes from `random_int`, stored hashed, single use, 15-minute expiry, 5 tries, one new code a minute (FR-AUTH-13). |
| NFR-11 Offline demo | Everything except the map and email works with no internet. | All libraries and fonts stored in the project (D15). |
| NFR-12 Free hosting | Runs within free hosting limits. | No scheduled jobs (D16); live updates send one long-poll request per browser about every 20 seconds (D17); photos compressed before upload. |
| NFR-09 Backup | Data can be backed up and restored. | Manual export/import through phpMyAdmin (see D8). |

---

## Open questions for the hotel

Things the site needs from Ramada Multan. Until they're answered, the site uses the placeholder shown, and the admin can fix each one later without code changes.

| # | Question | Placeholder used now |
|---|---|---|
| Q1 | Prices per night for the Presidential Suite, King Bed Business Room and Deluxe Queen Room; confirm the other three. | Empty price: shown as "Ask for price" and can't be booked. |
| Q2 | Beds in the Executive Suite and Pool Wing (their own pages disagree). | Queen and King, as on Ramada's room cards. |
| Q3 | Child policy: how many children each room type allows, what age counts as a child, and whether children cost extra. | 1 child per room type (placeholder, editable by the admin); the search allows 0–2. |
| Q4 | Real room numbers for each room type. | Sample numbers (101–104, 301–304, …). |
| Q5 | The real menu: categories, dishes, prices, and which restaurant serves what. | A 14-dish sample menu with made-up prices. |
| Q6 | Dining table plan: how many tables each restaurant has and how many seats each. | 15 sample tables. |
| Q7 | Original full-size photos (rooms, slideshow, pool, health club, halls, gallery) and the logo as SVG or large PNG. | Small copies from ramadamultan.com; no gym photo. |
| Q8 | A Gmail account (with an app password) for sending booking emails. | Email off; confirmations shown on screen. |
| Q9 | Is the airport shuttle free or charged? Health club opening hours? | "Airport shuttle: Ask at booking". |
| Q10 | Permission to use the name, logo and photos, in writing (D11). | Verbal permission. |
| Q11 | Are taxes (e.g. Punjab sales tax) added on top of the room price? Wyndham's checkout adds "Taxes & Fees" (16% for Pool Wing). | Total = nights × price per night, with no tax line; the admin can enter prices that include tax. |

---

## Future work (out of scope, SRS 4.6 / 7.4)

Mobile app · online payment gateway · AI recommendations · loyalty program · inventory and kitchen management · external booking platform integration · SMS notifications · advanced analytics · event and hall booking · staff roles (hotel manager, restaurant staff, business owner).

---

## Test cases

To be filled in during and after the build (SRS Chapter 6). Each test links to a requirement ID. Record the real result; don't fill in results before running the test.

| Test ID | Requirement | Scenario | Input | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-AUTH-01 | FR-AUTH-01 | Register with bad details | Email "not-an-email", phone "12", password "short" | Errors under each field, nothing saved | "Enter a valid email address.", "Enter a phone number…", "Use at least 8 characters." | Pass |
| TC-AUTH-02 | FR-AUTH-01, 08 | Register with good details (verification on) (link version, replaced by codes: see TC-AUTH-15 to 20) | Valid name, email, phone, password "Multan2026" | Account created, confirmation email sent, sent to login with a message | Redirected to login with "Account created. We've sent a link…"; email saved | Pass |
| TC-AUTH-03 | FR-AUTH-08 | Log in before confirming the email (link version, replaced by codes: see TC-AUTH-15 to 20) | Correct email and password | Refused with "confirm your email first" and a Resend button | As expected | Pass |
| TC-AUTH-04 | FR-AUTH-08, NFR-10 | Open the confirmation link twice (link version, replaced by codes: see TC-AUTH-15 to 20) | Link from the email | First time: confirmed; second time: "This link doesn't work" | As expected | Pass |
| TC-AUTH-05 | FR-AUTH-02 | Log in after confirming | Correct details | My Account opens; header shows the account menu | As expected | Pass |
| TC-AUTH-06 | FR-AUTH-07 | Change password with the wrong current password | Current "wrong" | Error "Your current password is wrong." | As expected; with the right one: "Your password has been changed." | Pass |
| TC-AUTH-07 | FR-AUTH-04 | Customer opens the admin panel | /admin/index.php | Blocked (403) | 403 | Pass |
| TC-AUTH-08 | FR-AUTH-03 | Log out from the account menu | Click "Log out" | Back on Home with "You have logged out." | As expected | Pass |
| TC-AUTH-09 | FR-AUTH-10 | 5 wrong passwords, then the right one | "WrongPass1" ×5, then the real password | Locked for 10 minutes, even with the right password | "…locked for 10 minutes", then "locked for 10 more minutes" | Pass |
| TC-AUTH-10 | FR-AUTH-09 | Forgot password for an unknown email | nobody@example.com | Same message as for a real account | Same message | Pass |
| TC-AUTH-11 | FR-AUTH-09, NFR-10 | Reset password from the emailed link, then reuse the link (link version, replaced by codes: see TC-AUTH-15 to 20) | New password "NewPass2028" | Password changed and account unlocked; link then refused; new password logs in | As expected | Pass |
| TC-AUTH-12 | FR-AUTH-04 | Admin logs in | admin@ramadamultan.com | Admin dashboard with live counts | Admin dashboard, 6 count cards | Pass |
| TC-AUTH-13 | FR-AUTH-13 | **Codes like a bank's** (not in the SRS): codes last 15 minutes, work once, stop after 5 wrong tries ("3 tries left"), are stored only as a hash, and a new one can be sent once a minute ("Send a new code (42 s)"). The code box takes digits only, accepts a pasted "482 913", lets phones fill it from the email notification, and sends itself once 6 digits are in. **Changing the password in My Account** also needs a code emailed to the account (after the current password), and **every password change sends a "Your password was changed" alert** with the time and a reset link. The email address is shown masked ("al•••••@gmail.com"). | MVP | ☑ |
| FR-MAIL-10 | **Real-inbox emails** (not in the SRS): the logo is sent inside the email, so it shows in Gmail even when the site runs on localhost; the sender is the hotel's Gmail with "Ramada by Wyndham Multan" as the name and replies go to the hotel's address; **Email check** in the admin panel says whether email is set up, sends a test email and explains a failure in plain words (e.g. "Gmail refused the login: check the app password"); the dashboard warns while email isn't set up. | MVP | ☑ |
| FR-AUTH-12 | Submit forms without the security token | POST to login, register, forgot, resend, logout | Rejected | 400 "This form has expired…" for all five | Pass |
| TC-AUTH-14 | FR-AUTH-11 | Login link that points to another website | ?next=https://evil.example | Ignored; goes to My Account | Went to account.php | Pass |
| TC-ROOM-01 | FR-ROOM-04 | Open the Rooms page with no dates | rooms.php | All 6 room types with "from" prices or "Price on request" | 6 types shown | Pass |
| TC-ROOM-02 | FR-ROOM-05, 09 | Search 3 nights, 2 adults; 3 of 4 Pool Wing rooms and all Twin Bed rooms booked; a cancelled Executive Suite booking | Test bookings, then the search | Pool Wing "Only 1 room left!", Twin Bed "Fully booked", Executive Suite still free; 3-night totals shown | As expected (PKR 71,985 and 209,985) | Pass |
| TC-ROOM-03 | FR-BOOK-02 | Search with past dates / check-out on the check-in day | 2020-01-01 → 2020-01-03; same day twice | "Check-in can't be in the past." / "Check-out must be after check-in." | As expected | Pass |
| TC-ROOM-04 | FR-ROOM-05 | Search for 3 adults + 1 child | adults=3, children=1 | Every room type "Too small" (max 3 people) | As expected | Pass |
| TC-ROOM-05 | FR-ROOM-06 | Open a room page and click the main photo | room.php?type=pool-wing | Photos, 11 amenities, 5 policies, photo viewer opens | As expected | Pass |
| TC-ROOM-06 | FR-ROOM-06 | Open a room that doesn't exist | room.php?type=nope | "We couldn't find that room" page, status 404 | 404 | Pass |
| TC-ADM-ROOM-01 | FR-ROOM-01 | Add a room type with mistakes, then correct it | Empty name/description/beds, price "abc"; then "Test Family Suite", price "31,500", 4 adults, 2 children | 4 errors and typed values kept; then saved, price shown as 31500, page appears on the site with PKR 31,500 | As expected | Pass |
| TC-ADM-ROOM-02 | FR-ROOM-07, 12 | Upload a text file renamed .jpg, then 3 real photos | fake.jpg; three JPGs | Fake refused ("not a JPG, PNG or WebP photo"); 3 photos added, the first one is the main photo | As expected | Pass |
| TC-ADM-ROOM-03 | FR-ROOM-08 | Reorder, change main photo, edit description, replace, delete the main photo | Buttons on the photo tiles | Order swaps; new main photo moves first; description saved as alt text; old file removed after replace; next photo becomes main after delete | As expected | Pass |
| TC-ADM-ROOM-04 | FR-ROOM-02, 11 | Add rooms 901 to 904, then 901 again, then "905 to 901" | Add rooms form | 4 rooms added; duplicate refused; backwards range refused | As expected | Pass |
| TC-ADM-ROOM-05 | FR-ROOM-03, 11 | Tick 2 rooms, mark under maintenance, then search for those dates | Bulk bar; rooms.php with dates | 2 rooms under maintenance; the site shows "Only 2 rooms left!" for that type | As expected | Pass |
| TC-ADM-ROOM-06 | FR-ROOM-02 | Change a room's number to one that's taken | Room 904 → 902 | "Another room already has number 902." | As expected; 910 saved | Pass |
| TC-ADM-ROOM-07 | FR-ROOM-01 | Delete a room type that has rooms; hide it; delete its rooms, then the type | Delete / Hide buttons | Delete blocked with a reason; hidden type disappears from the Rooms page; after its rooms go, the type and its photo files are deleted | As expected | Pass |
| TC-ADM-ROOM-08 | FR-WEB-16, NFR-04 | Admin pages on a phone (390px) | Dashboard, Room types, Room type, Rooms | No sideways scroll; sidebar opens from the ☰ button | As expected | Pass |
| TC-BOOK-01 | FR-BOOK-03, 12 | Two guests try to book the last room at the same moment | 1 of 2 Executive Suites already booked; 8 bookings started in parallel for the same dates | Exactly one gets the room, the other 7 are refused; no room booked twice | 1 booked, 7 refused; rooms 602 and 603 each booked once | Pass |
| TC-BOOK-02 | FR-SIG-CAL-01 | Choose dates on the room calendar | Next month arrow, click 4 Nov then 7 Nov | Month changes without reloading; booking box filled with 4 → 7 Nov; "3 nights · PKR 71,985" | As expected | Pass |
| TC-BOOK-03 | FR-BOOK-01 | Book while logged out | BOOK NOW from the room page | Login page, then back to checkout with the same dates | As expected | Pass |
| TC-BOOK-04 | FR-BOOK-01 | Checkout with a bad phone and the policy box unticked | Phone "12" | Two errors under the fields, nothing booked | "Enter a phone number…", "Please agree to the cancellation policy to book." | Pass |
| TC-BOOK-05 | FR-BOOK-04, 05, FR-MAIL-03 | Complete a booking | Pool Wing, 3 nights, arrival 14:00–16:00, "High floor please" | Confirmation page with RM-XXXXXX, total PKR 71,985, arrival time and request shown; email saved (and a note on screen because SMTP isn't set up) | As expected | Pass |
| TC-BOOK-06 | FR-BOOK-12 | Send the same checkout form twice | Same form resent | Still one booking; goes to the same confirmation | As expected | Pass |
| TC-BOOK-07 | FR-BOOK-03 | Book a 5th Pool Wing when all 4 are taken | 4 bookings, then a 5th for the same dates | "fully booked"; room box says fully booked; calendar shows the night in red | As expected | Pass |
| TC-BOOK-08 | FR-BOOK-01 | Too many guests / room without a price | adults=9; Presidential Suite | "up to 3 adults"; "no online price yet" | As expected | Pass |
| TC-BOOK-09 | FR-BOOK-05 | Another guest opens someone's booking | Sara opens Ali's confirmation link | "We couldn't find that booking" | As expected | Pass |
| TC-BOOK-10 | FR-BOOK-06, 07, FR-MAIL-04 | Guest cancels from My Bookings | Cancel button, confirm | Moves to "Past and cancelled" with a grey Cancelled badge; cancellation email saved | As expected | Pass |
| TC-BOOK-11 | FR-BOOK-08, 11 | Front desk: arrival today → check in → move room → check out | Arrivals tab, Check in, Move to room 303, Check out | Each step works and shows a message; the move only offers free rooms | "Moved from room 301 to room 303…" | Pass |
| TC-BOOK-12 | FR-BOOK-11 | Search bookings | "Sara"; a booking number | All of Sara's bookings; exactly one row | 6 rows; 1 row | Pass |
| TC-BOOK-13 | FR-SIG-CAL-02 | Occupancy chart | Week with 3 Pool Wing bookings | 3 blue bars with the guest's name; "Full" row 16%; clicking a bar opens the booking | As expected | Pass |
| TC-BOOK-14 | FR-BOOK-10 | Add to my calendar | Button on the confirmation page | A .ics file with the right dates downloads | As expected | Pass |
| TC-BOOK-15 | NFR-04 | Booking pages on a phone (390px) | Room, checkout, confirmation, My Bookings, admin bookings / booking / occupancy | No sideways scroll (the occupancy chart scrolls inside its box) | As expected | Pass |
| TC-ORD-01 | FR-ORD-01, FR-UX-04 | Add dishes from the menu | + on Chicken Karahi twice, Mango Shake once | Cart button appears and pops; 3 items, PKR 5,850; no page reload | As expected | Pass |
| TC-ORD-02 | FR-ORD-01 | Order while logged out | Continue to order | Login page, then back to the order page with the cart kept | As expected | Pass |
| TC-ORD-03 | FR-ORD-01, D18 | Choose dine-in without a table | Dine-in, no table | "Choose the table you are sitting at."; room service greyed out for a guest who isn't checked in | As expected | Pass |
| TC-ORD-04 | FR-ORD-02, FR-MAIL-05 | Place a takeaway order with a note | "Less spicy please" | Order page "Order placed!", total PKR 5,850 from the menu prices, 2 lines saved, cart emptied, email saved | As expected | Pass |
| TC-ORD-05 | FR-ORD-05 | Send the same order form twice | Same form resent | Still one order | As expected | Pass |
| TC-ORD-06 | FR-ORD-05, FR-MENU-04 | A dish in the cart is marked sold out | Fresh Lime in cart, then sold out | Removed with a note naming it | As expected | Pass |
| TC-ORD-07 | FR-ORD-05 | Guest cancels a received order; another guest opens it | Cancel order; Sara opens Ali's order | Cancelled; "We couldn't find that order" | As expected | Pass |
| TC-ORD-08 | FR-ORD-01, FR-SIG-ROOM-01, 04 | Checked-in guest orders room service | Sara, checked in to room 104 | Room service chosen by default, "To room 104"; order linked to her stay | As expected | Pass |
| TC-ORD-09 | FR-ORD-03, FR-SIG-QR-06 | Kitchen moves an order along | Start preparing → Mark ready → Mark served | Guest's page shows Ready and can no longer cancel; completed order leaves the Active tab; a wrong step is refused | As expected | Pass |
| TC-ORD-10 | FR-MENU-02, 04 | Admin marks a dish sold out, changes a price and adds a photo | Mango Shake sold out; Karahi 2,750 + photo | Mango Shake gone from the menu; Karahi shows 2,750 and a photo; the earlier order still says 2,600 | As expected | Pass |
| TC-ORD-11 | FR-MENU-01 | Add, delete and move categories | "Soups" added then deleted; Pakistani moved up | Messages shown; Pakistani first | As expected | Pass |
| TC-ORD-12 | NFR-04 | Dining and order pages on a phone (390px) | Dining, order, order page, My Orders, admin orders / menu / dish | No sideways scroll | As expected | Pass |
| TC-QR-01 | FR-SIG-QR-01 | Print the table QR cards and scan three of them | Table QR codes page; codes read with a QR reader | 15 cards; each code opens that table's menu (t=1, 5, 13); localhost warning shown | As expected | Pass |
| TC-QR-02 | FR-SIG-QR-02, 03 | Walk-in on a phone scans table 5 and orders | Karahi + Fresh Lime, no name, then "Bilal" + note | Banner "Table 5 · Buzz 76"; no login asked; table locked; "Please tell us your name"; then order saved as dine-in, table 5, no account | As expected | Pass |
| TC-QR-03 | FR-SIG-QR-04, 05 | Kitchen screen open while the walk-in orders | Wait up to 20 s | The new order appears in New by itself, flashes, tab shows "(1)" | Appeared within 23 s | Pass |
| TC-QR-04 | FR-SIG-QR-04, 06 | Kitchen taps Start preparing → Ready → Served | Card buttons | Card moves column by column; the guest's page shows Ready; served orders leave the board | As expected | Pass |
| TC-QR-05 | FR-SIG-QR-03 | Walk-in sends "room service" by editing the form | type=room_service | Saved as dine-in for the scanned table | As expected | Pass |
| TC-QR-06 | FR-SIG-QR-07 | Another phone opens the walk-in's order; the walk-in leaves the table | Order link on a new phone; "Not here?" | Asked to log in; after leaving, ordering needs a login | As expected | Pass |
| TC-TBL-01 | FR-TBL-04, 07 | Six guests reserve the same table and time at the same moment | 6 parallel reservations, table 12, 7 PM | 1 reserved, 5 refused | As expected | Pass |
| TC-TBL-02 | FR-TBL-02, 07 | Times follow the opening hours | Cafe Saints, tomorrow | Times 3:00 PM to 9:30 PM only; after the admin changed opening to 5 PM, times start at 5:00 PM | As expected | Pass |
| TC-TBL-03 | FR-TBL-02, 03, FR-MAIL-06 | Reserve while logged out | Buzz 76, 7 PM, 4 guests, "Birthday dinner" | Only tables with 4+ seats, smallest first; log in and come back; confirmation page and email | Tables 4,4,4,6,6,8; as expected | Pass |
| TC-TBL-04 | FR-TBL-04 | 2-hour rule around a 7 PM reservation | 5:00, 5:30, 6:30, 7:00, 8:30, 9:00 PM | Free, taken, taken, taken, taken, free | As expected | Pass |
| TC-TBL-05 | FR-TBL-07 | Every 4-seat table taken at 8 PM | Cafe Saints, 8 PM, 4 guests | "No table" with the nearest free time offered | 6:00 PM offered | Pass |
| TC-TBL-06 | FR-TBL-02 | Wrong input | 7 AM at Cafe Saints; 13 guests; a past date | "Choose one of the times…"; "please call us"; "can't be in the past" | As expected | Pass |
| TC-TBL-07 | FR-TBL-05 | Guest cancels from My Reservations; another guest opens it | Cancel; Sara opens Ali's link | Cancelled + email; "We couldn't find that reservation" | As expected | Pass |
| TC-TBL-08 | FR-TBL-06 | Host marks today's guest seated; admin cancels an upcoming one | Seated; Cancel | "is seated"; "is cancelled and the guest has been emailed" | As expected | Pass |
| TC-TBL-09 | FR-TBL-01 | Add, duplicate and delete a table | Table 20 (10 seats) twice, then delete | Added; "already has a table 20"; deleted | As expected | Pass |
| TC-WEB-06 | FR-WEB-06 | About Us page | about.php | 5 values, 6 attractions each with Get directions, About Us active in the menu; no sideways scroll on a phone | As expected | Pass |
| TC-BILL-01 | FR-SIG-ROOM-01, 02 | Checked-in guest orders room service twice and cancels one | Club Sandwich + Mango Shake; Chicken Karahi (cancelled) | "Your current stay" card: 1 night PKR 24,995 + order #1 PKR 2,150 = PKR 27,145; the cancelled order isn't on it; the admin booking page shows the same | As expected | Pass |
| TC-BILL-02 | FR-SIG-ROOM-03, FR-BILL-01, 02, FR-MAIL-09 | Admin checks the guest out | Check out | Stay invoice INV-2026-0001 opens: 2 room nights (49,990) + room service (2,150) = PKR 52,140; emailed | As expected | Pass |
| TC-BILL-03 | FR-SIG-ROOM-04 | Room service after check-out | Order page | Room service greyed out | As expected | Pass |
| TC-BILL-04 | FR-BILL-01, 05 | Dine-in order completed on the kitchen screen | Biryani, table 2 | Invoice INV-2026-0002 made; the guest sees it in My Invoices; another guest gets "not found" | As expected | Pass |
| TC-BILL-05 | FR-BILL-03, FR-ADM-02 | Mark invoices paid | "Paid cash" in the list; "Card" on the invoice page | Status Paid with method and date, PAID stamp; dashboard revenue today PKR 54,340 | As expected | Pass |
| TC-BILL-06 | FR-BILL-07 | 10 invoices made at the same moment | 10 parallel requests | 10 different invoice numbers, no errors (a first try with 6 showed the retry limit was too low; raised and retested) | As expected | Pass |
| TC-BILL-07 | FR-BILL-04, D7 | Print an invoice | Print view | Only the invoice prints: no bar, buttons or messages | As expected | Pass |
| TC-GAL-01 | FR-WEB-03 | Open the Gallery | gallery.php | 72 photos; tabs with counts (Banquet 22, Health 3, Lobby 6, Restaurants 16, Rooms 16, Multan 9) | As expected | Pass |
| TC-GAL-02 | FR-UX-03 | Click the Rooms tab | Click | 16 photos slide into place, address becomes gallery.php?tab=rooms | As expected | Pass |
| TC-GAL-03 | FR-WEB-03 | Open a photo after filtering | Click the first Rooms photo | Photo viewer steps through only the 16 Rooms photos | 16 slides | Pass |
| TC-GAL-04 | D21 | Open a Multan photo | Multan tab, any photo | Caption with photographer and licence; full credits under the grid | "Photo: ZainShahid117 / Wikimedia Commons, CC BY-SA 3.0"; 9 credit links | Pass |
| TC-GAL-05 | FR-WEB-03 | Open a tab from a link | gallery.php?tab=lobby | Lobby tab active, 6 photos | As expected | Pass |
| TC-LIVE-01 | FR-LIVE-01, FR-SIG-QR-06 | Guest watches their order while the kitchen taps Start preparing | Two browsers: guest on the order page, admin on the kitchen screen | Guest's page shows "Preparing" without reloading | Changed after 1.0 s, 0 page reloads | Pass |
| TC-LIVE-02 | FR-LIVE-01, FR-SIG-QR-05 | Kitchen screen open while a guest places an order | Takeaway order | New card appears by itself | Appeared after 1.1 s | Pass |
| TC-LIVE-03 | FR-LIVE-01 | Guest on the Twin Bed page while the admin changes its price | 24,995 → 25,995 | Price at the top changes without reloading | Changed after 1.0 s, 0 reloads | Pass |
| TC-LIVE-04 | FR-LIVE-01, FR-WEB-05 | Admin on Messages while a guest sends the contact form | Subject "Live test message" | Message appears and the sidebar shows a count, no reload | As expected, 0 reloads | Pass |
| TC-LIVE-05 | FR-LIVE-01 | Guest on My Account / their invoice while the admin checks them out and marks the invoice paid | Check out, Paid cash | Current stay card disappears; PAID stamp appears | 0.4 s and 1.0 s | Pass |
| TC-LIVE-06 | FR-LIVE-02, FR-SAFE-07 | Admin typing in a room type form while another admin saves a change to it, then presses Save | Description "My edit"; other admin changes the feature line | Typed text kept, "new information" bar shown; save stopped with "Someone else saved changes…", typed text still in the form | As expected (a first version swapped the form because it only compared text; fixed to compare field values too, then retested) | Pass |
| TC-SAFE-01 | FR-SAFE-01, FR-SAFE-03 | Admin raises the price while a guest is on the checkout page with a request typed | Twin Bed 24,995 → 26,995; guest's form still carrying the old price | Price box shows the new total live and the typed request stays; first press books nothing and explains; second press books at PKR 53,990; admin sees the "keep the price they booked at" note | As expected | Pass |
| TC-SAFE-02 | FR-SAFE-04 | Change the type of a room with an upcoming booking | Room 301 → Pool Wing | Hint in the form; save refused with "Move those guests to another room first" | As expected | Pass |
| TC-SAFE-03 | FR-SAFE-05 | Check out a guest with room service still being made | Orders #7 and #8 not served | Refused, naming both orders; after serving them, check-out works and the invoice opens | As expected | Pass |
| TC-WEB-04 | FR-WEB-04, FR-WEB-25 | Send the contact form with mistakes | Email "bad", empty subject and message | Errors under each field; the list of problems has the keyboard focus | 3 errors; focus on the list (a first version lost the focus to the page's #anchor jump; fixed and retested) | Pass |
| TC-WEB-05 | FR-WEB-05, D13 | Send a valid contact message | Logged-in guest (details filled in) | Success note on screen; saved; email to the hotel and a copy to the guest saved in logs/emails | As expected | Pass |
| TC-WEB-07 | FR-WEB-07, FR-WEB-24 | Event enquiry from a hall card | "Enquire about this hall" on Maryam Hall, Wedding, 300 guests | Hall chosen in the form; success note; saved with topic Meetings & events | As expected | Pass |
| TC-WEB-08 | NFR-04 | New and live pages on desktop (1440px) and phone (390px) | Contact, Meeting & Events, Home, About, Rooms, room, Dining, admin Messages / Event halls / FAQ / dashboard / kitchen / rooms | No sideways scroll, no console errors, no missing files | As expected | Pass |
| TC-AUTH-15 | FR-AUTH-08, 13 | Register, type a wrong code, then paste the right one with a space | "000000", then "834 534" from the email | Wrong: "That code isn't right… (4 tries left)"; right: sent by itself, logged in and back on the page asked for (reserve-table.php) | As expected | Pass |
| TC-AUTH-16 | FR-AUTH-13 | Change password in My Account | Current password, new "Lahore2027", then the emailed code | Step 2 asks for the code; after it: "Your password has been changed", and a "Your password was changed" email | As expected | Pass |
| TC-AUTH-17 | FR-AUTH-09 | Forgot password for an email with no account | nobody@example.com, any code | Same wording as for a real account; the code is "wrong" | As expected | Pass |
| TC-AUTH-18 | FR-AUTH-09 | Forgot password, weak password first, then a good one with the same code | "short", then "Karachi2028" | "Use at least 8 characters."; then saved with the same code, and logging in with the new password works | As expected | Pass |
| TC-AUTH-19 | FR-MAIL-10 | Admin Email check before Gmail is set up | Send test | Plain message that SMTP_USER/SMTP_PASS still have example values; dashboard warning "Email isn't set up" | As expected | Pass |
| TC-AUTH-20 | FR-MAIL-10 | Real Gmail delivery | Hotel Gmail + app password in config.php, Send test, then a real sign-up | Email arrives in the inbox with the logo; code works | Waiting for the Gmail app password | — |
| TC-AUTH-21 | FR-AUTH-14 | Change email: one that's taken, a wrong password, then a good one | burhan.aamerr@gmail.com; password "wrong"; new.address@example.com + the emailed code | "Another account already uses this email."; "Your password is wrong."; code sent to the new address, email changed, alert sent to the old address, logging in with the new email works | As expected (a first version hid the Email card behind the password card on phones; fixed and retested) | Pass |
