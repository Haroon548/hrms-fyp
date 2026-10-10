<?php
/*
 * Log out (FR-AUTH-03). Only by a form with the CSRF token, so another
 * website can't log people out by linking here.
 */
require_once '../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    log_out_user();
    flash('success', 'You have logged out. See you soon!');
}
redirect('index.php');
