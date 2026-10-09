# Grand Multan Hotel: Hotel & Restaurant Management System

A website and management system for a hotel with a restaurant. Guests can book rooms, reserve tables, order food and leave feedback. The admin manages rooms, menu, orders, bookings and invoices from a dashboard.

> **Final year project (BS Computer Science), University of Southern Punjab, Multan.**
> "Grand Multan Hotel" is a made-up name. This is not a real hotel.

**Status:** In development. The requirements are written; the application code is being built. The setup steps below describe how the finished project will be installed.

---

## Features

**For guests**
- Register, log in and log out
- Browse rooms and check which are free for chosen dates
- Book a room, see booking history, cancel a booking
- View the restaurant menu and place a food order
- Reserve a dining table for a date, time and party size
- View and print invoices
- Leave a rating, comment or complaint

**For the admin**
- Dashboard with counts, revenue and recent activity
- Manage room types, rooms and prices
- Manage the menu, dining tables and users
- View bookings, orders and reservations and update their status
- Create invoices and mark them as paid
- Read feedback and complaints

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
5. **Open the site** at [localhost/hrms-fyp](http://localhost/hrms-fyp).

Login details for the sample admin account will be listed here once the database file is ready. Change that password after your first login.

---

## Putting it online (free hosting)

These steps use [InfinityFree](https://www.infinityfree.com), which offers free PHP and MySQL hosting. Other PHP hosts work the same way.

1. Create an account and a website. Note the **MySQL host, database name, username and password** shown in the control panel.
2. In the control panel, open **phpMyAdmin** for your database and import `database/schema.sql`.
3. Edit `config/config.php` on your computer and enter the hosting database details from step 1.
4. Upload all the project files into the `htdocs` folder on the host, using the **File Manager** in the control panel or an FTP program such as [FileZilla](https://filezilla-project.org).
5. Open your site's address in the browser.

**Limits of free hosting:** it can be slow and doesn't send email, so confirmations are shown on screen. For a hotel taking real bookings, cheap paid hosting is more reliable. The same steps apply.

---

## Backing up the database

In phpMyAdmin, select the database, open the **Export** tab and click **Export**. Keep the downloaded `.sql` file somewhere safe. To restore it, use the **Import** tab.

---

## Project documents

- [docs/requirements.md](docs/requirements.md): requirements checklist, decisions and test cases
- [docs/SRS Final.pdf](docs/SRS%20Final.pdf): Software Requirements Specification

---

## Team

- Haroon Sarfraz
- Noor Ishtiaq
- Abdullah

**Supervisor:** Imran Ali
