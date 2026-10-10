<?php
/*
 * "Add to my calendar": downloads the stay as a calendar file (.ics) that opens in
 * Google Calendar, Outlook and phone calendars. Only for the guest's own booking.
 *   booking/calendar-file.php?ref=RM-7K3QX9
 */
require_once '../includes/init.php';
require_once '../includes/bookings.php';
require_login();

$booking = find_booking((string) ($_GET['ref'] ?? ''));
if (!$booking || ((int) $booking['user_id'] !== (int) current_user()['id'] && !is_admin())) {
    http_response_code(404);
    exit('Booking not found.');
}

// Calendar files need commas, semicolons and line breaks escaped
function ics_text($text)
{
    return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $text);
}

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//' . ics_text(HOTEL_NAME) . '//Bookings//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:' . $booking['reference'] . '@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost'),
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($booking['check_in'])),
    'DTEND;VALUE=DATE:' . date('Ymd', strtotime($booking['check_out'])),
    'SUMMARY:' . ics_text('Stay at ' . HOTEL_NAME . ' (' . $booking['reference'] . ')'),
    'LOCATION:' . ics_text(HOTEL_NAME . ', ' . HOTEL_ADDRESS),
    'DESCRIPTION:' . ics_text($booking['type_name'] . ', ' . occupancy_text($booking['adults'], $booking['children'])
        . ".\nCheck-in from " . CHECK_IN_TIME . ', check-out by ' . CHECK_OUT_TIME
        . ".\nFree cancellation until 6:00 PM on the day of arrival.\nHotel: " . HOTEL_PHONE
        . "\n" . SITE_URL . '/booking/confirmation.php?ref=' . $booking['reference']),
    'END:VEVENT',
    'END:VCALENDAR',
];

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="ramada-multan-' . $booking['reference'] . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
