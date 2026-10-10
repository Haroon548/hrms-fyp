# Ramada by Wyndham Multan: Hotel & Restaurant Management System

A new website and management system for **Ramada by Wyndham Multan**. It keeps the look of the hotel's current website and adds online room booking, food ordering, table reservations, invoices, guest feedback and an admin dashboard.

> **Final year project (BS Computer Science), University of Southern Punjab, Multan,** built with the permission of Ramada by Wyndham Multan.

**Status:** In development. The requirements are written; the application code is being built. The setup steps below describe how the finished project will be installed.

---

## Features

**For guests**
- Register with email verification, log in, log out, reset a forgotten password by email
- Browse rooms with photos, amenities and prices, and check which are free for chosen dates
- Book a room (pay at the hotel) with an availability calendar, see booking history, cancel a booking
- View the restaurant menu and order food for takeaway, dine-in (at your table) or room service, and follow the order's progress
- Scan the QR code on a restaurant table to order to that table from a phone, without an account
- Reserve a dining table for a date, time and party size
- Get confirmation emails for bookings, orders and reservations
- See the running room bill during a stay (room nights plus room service), and view and print invoices
- Leave a rating, comment or complaint
- Send a message through the contact form, see who is open right now, and read answers to common questions
- Send an event enquiry (wedding, meeting, family event) and compare the halls on floor plans drawn to scale
- Browse the dining, gallery, meeting & events and about pages

**For the admin**
- Dashboard with counts, revenue and recent activity
- Manage room types, rooms, photos and prices
- Manage the menu, dining tables, gallery and users
- View bookings, orders and reservations and update their status (check in / check out, kitchen steps), with an occupancy chart
- Invoices are made automatically at check-out and for finished restaurant orders; mark them paid (cash or card) and see revenue on the dashboard
- Read feedback, complaints, contact messages and event enquiries, and edit the event halls and the FAQ

**Live updates:** pages change by themselves when something is saved, for guests and staff alike. For example, the guest's order page moves to "Ready" as soon as the kitchen taps it, and the admin's lists show a new booking or message without refreshing.

**Safety checks:** guests only book or order at the price they saw; a room's type can't change under a booked guest; a guest can't be checked out while room service is still on its way; and two admins can't silently overwrite each other's changes.

Double booking is prevented: a room can't be booked for dates that overlap an existing booking, and a table can't be reserved twice for the same time.

The full list of requirements is in [docs/requirements.md](docs/requirements.md).

---

## Tech stack

| Part | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap |
| Backend | PHP 8 (plain PHP, no framework) |
| Database | MySQL / MariaDB |
| Local server | XAMPP |

| Email | PHPMailer, through a Gmail (or other SMTP) account |

No Composer, npm or build step is needed. Copy the files, import the database and edit one config file.

---

## Setup on your computer (XAMPP)

1. **Install XAMPP** from [apachefriends.org](https://www.apachefriends.org). Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. **Copy the project** into XAMPP's `htdocs` folder so it's at:
   - Windows: `C:\xampp\htdocs\hrms-fyp`
   - macOS: `/Applications/XAMPP/htdocs/hrms-fyp`
3. **Create the database.** Open [localhost/phpmyadmin](http://localhost/phpmyadmin), click **New**, name the database `hrms`, and click **Create**. Select it, go to the **Import** tab, choose `database/schema.sql` from the project folder, and click **Import**.
4. **Set up the config file.** In the `config` folder, make a copy of `config.example.php` and name it `config.php`. Open it and check the database details. The XAMPP defaults are usually correct:
   - Host: `localhost`
   - Port: `3306`
   - Database: `hrms`
   - Username: `root`
   - Password: *(empty)*
5. **Set up email** (optional on your computer). In the same `config.php`, enter the email account the site sends from (see [Setting up email](#setting-up-email)). Without it, the site still works: confirmations are shown on screen, and a copy of every email is saved in `logs/emails/` (open the file in a browser to read the code). `EMAIL_VERIFICATION` starts as `false`, so new accounts can log in straight away; set it to `true` once email works, so new customers must confirm their email with a 6-digit code.
6. **Open the site** at [localhost/hrms-fyp](http://localhost/hrms-fyp).

**Admin login** (created by `schema.sql`):
- Email: `admin@ramadamultan.com`
- Password: `ChangeMe@123`

Change this password after your first login.

**Photos:** the photos and logos in `assets/images/` are temporary copies from ramadamultan.com, used with the hotel's permission. Replace them with the hotel's original, full-size files (same file names), or upload new ones from the admin panel once it's built.

**Starting data:** the room types, amenities, restaurants and opening hours are the hotel's real details. The room numbers, dining tables and menu are **samples** for testing; replace them from the admin panel. Prices the hotel hasn't confirmed are left empty, and those rooms can't be booked until a price is set.

> **Warning:** importing `schema.sql` again deletes all existing data (bookings, orders, users) and starts fresh. On a live site, back up first (see below).

---

## Putting it online (free hosting)

These steps use [InfinityFree](https://www.infinityfree.com), which offers free PHP and MySQL hosting. Other PHP hosts work the same way.

1. Create an account and a website. Note the **MySQL host, database name, username and password** shown in the control panel.
2. In the control panel, open **phpMyAdmin** for your database and import `database/schema.sql`.
3. Edit `config/config.php` on your computer: enter the hosting database details from step 1 and the email settings (below).
4. Upload all the project files into the `htdocs` folder on the host, using the **File Manager** in the control panel or an FTP program such as [FileZilla](https://filezilla-project.org).
5. Open your site's address in the browser.

**Limits of free hosting:** on InfinityFree, set `LIVE_WAIT_SECONDS` to `0` in `config/config.php`. Pages then check for changes every 15 seconds with a quick request, instead of keeping one request open for up to 20 seconds; free accounts only allow a few busy requests at a time and otherwise show "This site has used all available resources". On your own computer or paid hosting, `20` makes changes show within about a second. Free hosting can also be slow, and its own email function is switched off, so the site sends email through an outside account instead (below). For a hotel taking real bookings, cheap paid hosting is more reliable. The same steps apply.

---

## Setting up email

The site emails guests their sign-up code, password codes, booking and order confirmations and invoices, through an ordinary Gmail account for the hotel (free, up to about 500 emails a day).

1. Sign in to the hotel's Gmail account and turn on **2-Step Verification** (Google Account → Security). Google only offers app passwords once this is on.
2. Open **https://myaccount.google.com/apppasswords**, type a name such as "Hotel website" and press **Create**. Copy the 16-letter password Google shows (it is only shown once).
3. In `config/config.php`, fill in:
   - `SMTP_USER`: the full Gmail address
   - `SMTP_PASS`: the 16-letter app password, without spaces (not the normal Gmail password)
   - `SMTP_HOST` `smtp.gmail.com` and `SMTP_PORT` `587` are already set
   - `NOTIFY_EMAIL`: where new contact messages and event enquiries are announced (can differ from `HOTEL_EMAIL`, which guests see on the site)
4. Log in as admin and open **Email check** in the side menu. Press **Send test**: it says whether Gmail accepted it, and if not, what to fix.
5. Turn on `EMAIL_VERIFICATION` if it is off, and create a test account: a 6-digit code should arrive. Look in the spam folder the first time, and mark it "Not spam".

If an email can't be sent, the booking, order or reservation still goes through and the confirmation is shown on screen.

**Security note:** the `config`, `includes`, `database` and `logs` folders each contain a `.htaccess` file that stops them being opened in a browser. Keep these files when uploading.

---

## Backing up the database

In phpMyAdmin, select the database, open the **Export** tab and click **Export**. Keep the downloaded `.sql` file somewhere safe. To restore it, use the **Import** tab.

---

## Credits

The site uses these free libraries and fonts, stored in `assets/vendor/` and `assets/fonts/`:

- [Bootstrap](https://getbootstrap.com) 5.3.8, MIT License
- [Font Awesome Free](https://fontawesome.com) 7.3.1, icons CC BY 4.0, fonts SIL OFL 1.1, code MIT
- [GSAP](https://gsap.com) 3.15.0, GSAP Standard "No Charge" License
- [Fira Sans](https://fonts.google.com/specimen/Fira+Sans), SIL Open Font License 1.1
- The "Inside the hotel" photo filmstrip is adapted from [ThreeUI](https://threeui.com)'s free Character Filmstrip component, MIT License

- [GLightbox](https://biati-digital.github.io/glightbox/) 3.3.1 (photo viewer), MIT License
- [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) 1.4.4 (table QR codes), MIT License
- Dining page photos in `assets/images/dining/` are temporary food photos (not the hotel's own dishes). Replace them with the hotel's own photos before the site goes public (from the admin panel once its site-photos page is built, FR-WEB-10; until then by replacing the files).

Photos and logos belong to Ramada by Wyndham Multan and are used with permission. The Multan photos in the gallery come from [Wikimedia Commons](https://commons.wikimedia.org) under Creative Commons licences; each photographer is credited on the Gallery page.

---

## Project documents

- [docs/requirements.md](docs/requirements.md): requirements checklist, decisions and test cases
- [docs/design.md](docs/design.md): colours, fonts, page layouts and hotel content, based on ramadamultan.com
- [docs/design-reference/](docs/design-reference/): screenshots of the current hotel website

---

## Team

- Haroon Sarfraz
- Noor Ishtiaq
- Abdullah

**Supervisor:** Imran Ali
