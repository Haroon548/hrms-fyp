<?php
/*
 * One month of a room type's availability calendar, as HTML (FR-SIG-CAL-01).
 * Loaded by assets/js/room.js when the ‹ › arrows are clicked, so the page doesn't reload.
 *   booking/calendar.php?type=pool-wing&month=2026-11
 */
require_once '../includes/init.php';
require_once '../includes/calendar.php';

$type = room_type_by_slug($_GET['type'] ?? '');
if (!$type) {
    http_response_code(404);
    exit;
}
header('Cache-Control: no-store'); // always the latest bookings
room_calendar($type, calendar_month($_GET['month'] ?? ''));
