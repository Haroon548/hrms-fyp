# Ramada by Wyndham Multan: Hotel & Restaurant Management System

A new website and management system for **Ramada by Wyndham Multan**. It keeps the look of the hotel's current website and adds online room booking, food ordering, table reservations, invoices, guest feedback and an admin dashboard.

> **Final year project (BS Computer Science), University of Southern Punjab, Multan,** built with the permission of Ramada by Wyndham Multan.

**Status:** In development. The requirements are written; the application code is being built. The setup steps below describe how the finished project will be installed.

---

## Features

**For guests**
- Register with email verification, log in, log out, reset a forgotten password by email
- Browse rooms with photos, amenities and prices, and check which are free for chosen dates
- Book a room (pay at the hotel), see booking history, cancel a booking
- View the restaurant menu and place a food order
- Reserve a dining table for a date, time and party size
- Get confirmation emails for bookings, orders and reservations
- View and print invoices
- Leave a rating, comment or complaint
- Send a message through the contact form
- Browse the dining, gallery, meeting & events, Multan and about pages

**For the admin**
- Dashboard with counts, revenue and recent activity
- Manage room types, rooms, photos and prices
- Manage the menu, dining tables, gallery and users
- View bookings, orders and reservations and update their status
- Create invoices and mark them as paid
- Read feedback, complaints and contact messages

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
   - Database: `hrms`
   - Username: `root`
   - Password: *(empty)*
5. **Set up email** (optional on your computer). In the same `config.php`, enter the email account the site sends from (see [Setting up email](#setting-up-email)). Without it, the site still works: confirmations are shown on screen. To use the site without email at all, set `EMAIL_VERIFICATION` to `false` so new accounts don't need to verify their email.
6. **Open the site** at [localhost/hrms-fyp](http://localhost/hrms-fyp).

Login details for the sample admin account will be listed here once the database file is ready. Change that password after your first login.

---

## Putting it online (free hosting)

These steps use [InfinityFree](https://www.infinityfree.com), which offers free PHP and MySQL hosting. Other PHP hosts work the same way.

1. Create an account and a website. Note the **MySQL host, database name, username and password** shown in the control panel.
2. In the control panel, open **phpMyAdmin** for your database and import `database/schema.sql`.
3. Edit `config/config.php` on your computer: enter the hosting database details from step 1 and the email settings (below).
4. Upload all the project files into the `htdocs` folder on the host, using the **File Manager** in the control panel or an FTP program such as [FileZilla](https://filezilla-project.org).
5. Open your site's address in the browser.

**Limits of free hosting:** it can be slow, and its own email function is switched off, so the site sends email through an outside account instead (below). For a hotel taking real bookings, cheap paid hosting is more reliable. The same steps apply.

---

## Setting up email

The site sends verification, password reset and confirmation emails through an ordinary email account. A Gmail account for the hotel works and is free (up to about 500 emails a day).

1. Sign in to the Gmail account and turn on **2-Step Verification** (Google Account → Security).
2. In the same place, open **App passwords**, create one named "Hotel website", and copy the 16-character password.
3. In `config/config.php`, fill in:
   - SMTP host: `smtp.gmail.com`
   - SMTP port: `587`
   - SMTP username: the full Gmail address
   - SMTP password: the app password from step 2 (not the normal Gmail password)
   - From name: `Ramada by Wyndham Multan`
4. Register a test account on the site and check that the verification email arrives. Look in the spam folder the first time.

If an email can't be sent, the booking, order or reservation still goes through and the confirmation is shown on screen.

---

## Backing up the database

In phpMyAdmin, select the database, open the **Export** tab and click **Export**. Keep the downloaded `.sql` file somewhere safe. To restore it, use the **Import** tab.

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
