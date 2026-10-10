<?php
/*
 * Site settings. Copy this file to config/config.php and fill in your own values.
 * config.php is not committed to GitHub because it holds passwords.
 */

// Database (XAMPP defaults: user "root", no password)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);      // XAMPP's MySQL uses 3306
define('DB_NAME', 'hrms');
define('DB_USER', 'root');
define('DB_PASS', '');

// Full address of the site, without a slash at the end.
// Used in emails and table QR codes. Change it when the site moves.
define('SITE_URL', 'http://localhost/hrms-fyp');

// Hotel details shown in the header, footer and contact page
define('HOTEL_NAME', 'Ramada by Wyndham Multan');
define('HOTEL_ADDRESS', '76 Abdali Road, Multan, Pakistan');
define('HOTEL_PHONE', '+92 61 454 0877');
define('HOTEL_PHONE_2', '+92 61 458 7777 – 86');
define('HOTEL_EMAIL', 'info@ramadamultan.com');
define('HOTEL_WHATSAPP', '923218576777'); // digits only, used for wa.me links
define('HOTEL_GSM', '+92 323 624 7777');
define('HOTEL_FAX', '+92 61 454 0877');

// Where new contact messages and event enquiries are announced by email (D13).
// Can differ from HOTEL_EMAIL, which is shown to guests on the site.
define('NOTIFY_EMAIL', 'info@ramadamultan.com');

// Email verification on sign-up (D10). Leave it off until the SMTP settings below work,
// otherwise new customers can't log in. Turn it on for the live site; off for an offline demo.
// (Every email is also saved in logs/emails/, so you can read the code from there when testing.)
define('EMAIL_VERIFICATION', false);

// SMTP for sending email (D9). Use a Gmail app password, not the real password.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-hotel-account@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM_NAME', 'Ramada by Wyndham Multan');

// Live updates (D17): open pages update themselves when something changes. Each open browser
// asks the server once; the server holds the question for up to this many seconds and answers
// as soon as something is saved. If a host complains about long requests, set 0: pages then
// ask every 15 seconds instead.
define('LIVE_WAIT_SECONDS', 20);
