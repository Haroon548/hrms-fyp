<?php
/*
 * Room bookings (FR-BOOK-01 to 08), availability calendars (FR-SIG-CAL) and booking emails
 * (FR-MAIL-03, 04). Used by the booking pages, My Account and the admin panel.
 *
 * Statuses: confirmed → checked_in → checked_out, or confirmed → cancelled.
 * Every check here runs on the server, whatever the browser sends.
 */
require_once __DIR__ . '/rooms.php';
require_once __DIR__ . '/mail.php';

const CANCEL_UNTIL_HOUR = 18;          // free cancellation until 6 PM on the day of arrival
const CHECK_IN_TIME = '2:00 PM';
const CHECK_OUT_TIME = '12:00 PM';

// Choices for "Estimated arrival time" at checkout (an extra, so reception knows when to expect guests).
const ARRIVAL_TIMES = ['', 'Before 12:00', '12:00 – 14:00', '14:00 – 16:00', '16:00 – 18:00', '18:00 – 20:00', '20:00 – 22:00', 'After 22:00'];

/* ---------- Reading bookings ---------- */

// One booking with its guest, room and room type, found by id or reference.
function find_booking($idOrReference)
{
    $stmt = db()->prepare(
        'SELECT b.*, u.name AS guest_name, u.email AS guest_email, u.phone AS guest_phone,
                r.room_number, r.room_type_id, rt.name AS type_name, rt.slug AS type_slug,
                (SELECT p.file FROM room_type_photos p WHERE p.room_type_id = rt.id AND p.is_main = 1 LIMIT 1) AS photo
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         JOIN rooms r ON r.id = b.room_id
         JOIN room_types rt ON rt.id = r.room_type_id
         WHERE ' . (is_int($idOrReference) ? 'b.id = ?' : 'b.reference = ?')
    );
    $stmt->execute([$idOrReference]);
    return $stmt->fetch() ?: null;
}

function booking_nights($booking)
{
    return (int) (new DateTime($booking['check_in']))->diff(new DateTime($booking['check_out']))->days;
}

// "Sat 17 Oct 2026"
function long_date($date)
{
    return date('D j M Y', strtotime($date));
}

// A guest can cancel a confirmed booking free of charge until 6 PM on the arrival day.
function can_cancel($booking)
{
    if ($booking['status'] !== 'confirmed') {
        return false;
    }
    return time() < strtotime($booking['check_in'] . ' ' . CANCEL_UNTIL_HOUR . ':00:00');
}

/* ---------- Making a booking ---------- */

// A booking number guests can read out on the phone, e.g. "RM-7K3QX9" (no 0/O or 1/I mix-ups).
function new_booking_reference()
{
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $check = db()->prepare('SELECT COUNT(*) FROM bookings WHERE reference = ?');
    do {
        $reference = 'RM-';
        for ($i = 0; $i < 6; $i++) {
            $reference .= $letters[random_int(0, strlen($letters) - 1)];
        }
        $check->execute([$reference]);
    } while ($check->fetchColumn() > 0);
    return $reference;
}

/*
 * Checks a booking request (FR-BOOK-01, 02). Returns a message saying what's wrong, or ''.
 * $type is a room_types row, $stay comes from check_stay().
 */
function booking_problem($type, $stay, $adults, $children)
{
    if (!$type || !$type['is_active']) {
        return 'This room type can\'t be booked online.';
    }
    if ($type['price_per_night'] === null) {
        return 'This room has no online price yet. Please contact the hotel to book it.';
    }
    if (!is_array($stay)) {
        return $stay; // the date problem from check_stay()
    }
    if ($adults < 1 || $adults > $type['max_adults']) {
        return $type['name'] . ' is for up to ' . $type['max_adults'] . ' adults.';
    }
    if ($children < 0 || $children > $type['max_children']) {
        return $type['max_children'] ? $type['name'] . ' is for up to ' . $type['max_children'] . ' ' . ($type['max_children'] == 1 ? 'child' : 'children') . '.'
            : $type['name'] . ' isn\'t available for children. Please choose another room.';
    }
    return '';
}

/*
 * Books the first free room of a type for a stay (FR-BOOK-01, 03, 04).
 * Returns the new booking's reference, or a message if no room is free any more.
 *
 * No double booking: inside one transaction, the rooms of this type are locked
 * (SELECT ... FOR UPDATE), so two guests booking at the same moment are handled one
 * after the other; the second one only sees the rooms the first one left free.
 */
function create_booking($userId, $type, $stay, $adults, $children, $requests, $arrival)
{
    $pdo = db();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); // always read the latest bookings
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT id FROM rooms WHERE room_type_id = ? AND status = 'available' FOR UPDATE");
        $lock->execute([$type['id']]);

        $free = free_rooms($type['id'], $stay['check_in'], $stay['check_out']);
        if (!$free) {
            $pdo->rollBack();
            return 'Sorry, the last ' . $type['name'] . ' for these dates has just been booked. Please choose other dates or another room.';
        }

        $price = (float) $type['price_per_night'];
        $reference = new_booking_reference();
        $pdo->prepare(
            'INSERT INTO bookings (reference, user_id, room_id, check_in, check_out, adults, children,
                                   price_per_night, total, status, special_requests, arrival_time)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'confirmed\', ?, ?)'
        )->execute([
            $reference, $userId, $free[0]['id'], $stay['check_in'], $stay['check_out'], $adults, $children,
            $price, $price * $stay['nights'], $requests !== '' ? $requests : null, $arrival,
        ]);
        $pdo->commit();
        return ['reference' => $reference];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

/* ---------- Changing a booking ---------- */

// Status changes the admin can make (FR-BOOK-08), with the button text. Checked again on save.
function next_statuses($booking)
{
    $today = date('Y-m-d');
    $moves = [];
    if ($booking['status'] === 'confirmed') {
        if ($booking['check_in'] <= $today && $booking['check_out'] > $today) {
            $moves['checked_in'] = 'Check in';
        }
        if ($booking['check_out'] > $today) {
            $moves['cancelled'] = 'Cancel booking';
        }
    }
    if ($booking['status'] === 'checked_in') {
        $moves['checked_out'] = 'Check out';
    }
    return $moves;
}

// Sets a new status. Returns '' or a message if that change isn't allowed now.
function change_booking_status($booking, $status)
{
    if (!isset(next_statuses($booking)[$status])) {
        return 'That change isn\'t possible for this booking any more. The page has been refreshed.';
    }
    // A guest can't be checked in to a room that is under maintenance (FR-SAFE-08)
    if ($status === 'checked_in') {
        $stmt = db()->prepare('SELECT status FROM rooms WHERE id = ?');
        $stmt->execute([$booking['room_id']]);
        if ($stmt->fetchColumn() === 'maintenance') {
            return 'Room ' . $booking['room_number'] . ' is under maintenance, so ' . $booking['guest_name'] . ' can\'t be checked in to it. '
                . 'Move the guest to another room first (Move to another room on the booking), or mark the room available on the Rooms page if it\'s ready.';
        }
    }
    // Room service still being made would miss the stay's bill, or be charged before it arrives
    if ($status === 'checked_out') {
        $stmt = db()->prepare("SELECT id FROM orders WHERE booking_id = ? AND status IN ('pending', 'preparing', 'ready') ORDER BY id");
        $stmt->execute([$booking['id']]);
        if ($open = $stmt->fetchAll(PDO::FETCH_COLUMN)) {
            return $booking['guest_name'] . ' still has room service on its way (order ' . implode(', ', array_map(function ($id) { return '#' . $id; }, $open))
                . '). Mark ' . (count($open) === 1 ? 'it' : 'them') . ' served or cancel ' . (count($open) === 1 ? 'it' : 'them') . ' on the Orders page first, so the stay\'s bill is complete.';
        }
    }
    $stmt = db()->prepare('UPDATE bookings SET status = ?, cancelled_at = ' . ($status === 'cancelled' ? 'NOW()' : 'cancelled_at') . ' WHERE id = ? AND status = ?');
    $stmt->execute([$status, $booking['id'], $booking['status']]);
    if ($stmt->rowCount() === 0) {
        return 'Someone else changed this booking just now. The page has been refreshed.';
    }
    if ($status === 'cancelled') {
        send_booking_cancellation(find_booking((int) $booking['id']));
    }
    return '';
}

// Rooms a booking could move to: free for its dates (the booking's own room doesn't count as taken).
function rooms_to_move_to($booking)
{
    $stmt = db()->prepare(
        "SELECT r.id, r.room_number, rt.name AS type_name, rt.id AS type_id
         FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id
         WHERE r.status = 'available' AND r.id <> ?
           AND NOT EXISTS (
               SELECT 1 FROM bookings b
               WHERE b.room_id = r.id AND b.status <> 'cancelled' AND b.id <> ?
                 AND b.check_in < ? AND b.check_out > ?
           )
         ORDER BY (rt.id = ?) DESC, rt.sort_order, LENGTH(r.room_number), r.room_number"
    );
    $stmt->execute([$booking['room_id'], $booking['id'], $booking['check_out'], $booking['check_in'], $booking['room_type_id']]);
    return $stmt->fetchAll();
}

/* ---------- Availability calendars (FR-SIG-CAL) ---------- */

/*
 * How many rooms of a type are free on each night from $from up to (not including) $to:
 * ['2026-11-01' => 3, ...]. Uses the same rule as booking (FR-SIG-CAL-03): a night is taken
 * in a room if a booking that isn't cancelled has check_in <= night < check_out.
 */
function nightly_free_counts($typeId, $from, $to)
{
    $stmt = db()->prepare("SELECT id FROM rooms WHERE room_type_id = ? AND status = 'available'");
    $stmt->execute([$typeId]);
    $roomIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $taken = []; // night => [room id => true]
    if ($roomIds) {
        $marks = implode(',', array_fill(0, count($roomIds), '?'));
        $stmt = db()->prepare("SELECT room_id, check_in, check_out FROM bookings
            WHERE room_id IN ($marks) AND status <> 'cancelled' AND check_in < ? AND check_out > ?");
        $stmt->execute(array_merge($roomIds, [$to, $from]));
        foreach ($stmt->fetchAll() as $b) {
            for ($night = max($b['check_in'], $from); $night < $b['check_out'] && $night < $to; $night = date('Y-m-d', strtotime($night . ' +1 day'))) {
                $taken[$night][$b['room_id']] = true;
            }
        }
    }

    $counts = [];
    for ($night = $from; $night < $to; $night = date('Y-m-d', strtotime($night . ' +1 day'))) {
        $counts[$night] = count($roomIds) - count($taken[$night] ?? []);
    }
    return $counts;
}

/* ---------- Emails (FR-MAIL-03, FR-MAIL-04) ---------- */

// The booking's details as rows of an email table.
function booking_email_rows($b)
{
    $nights = booking_nights($b);
    $rows = [
        'Booking number' => '<strong style="font-size:17px;letter-spacing:1px;">' . e($b['reference']) . '</strong>',
        'Room'           => e($b['type_name']),
        'Check-in'       => e(long_date($b['check_in'])) . ', from ' . CHECK_IN_TIME,
        'Check-out'      => e(long_date($b['check_out'])) . ', by ' . CHECK_OUT_TIME,
        'Nights'         => $nights,
        'Guests'         => e(occupancy_text($b['adults'], $b['children'])),
        'Total'          => '<strong>PKR ' . money($b['total']) . '</strong> (PKR ' . money($b['price_per_night']) . ' × ' . $nights . ')',
        'Payment'        => 'Pay at the hotel',
    ];
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;">';
    foreach ($rows as $label => $value) {
        $html .= '<tr><td style="padding:8px 0;border-bottom:1px solid #EEEEEE;color:#707070;width:40%;">' . $label . '</td>'
            . '<td style="padding:8px 0;border-bottom:1px solid #EEEEEE;">' . $value . '</td></tr>';
    }
    return $html . '</table>';
}

function send_booking_confirmation($b)
{
    $first = explode(' ', trim($b['guest_name']))[0];
    $link = SITE_URL . '/booking/confirmation.php?ref=' . urlencode($b['reference']);
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your stay is booked, ' . e($first) . '</h1>'
        . '<p>Thank you for booking with ' . e(HOTEL_NAME) . '. Here are your booking details:</p>'
        . booking_email_rows($b)
        . '<p><strong>Cancellation:</strong> free until 6:00 PM on ' . e(long_date($b['check_in'])) . '. You can cancel from My Account.</p>'
        . email_button($link, 'View my booking')
        . '<p>Please bring a photo ID (CNIC or passport) when you check in. We look forward to welcoming you.</p>';
    $text = "Your stay is booked, $first\n\nBooking number: {$b['reference']}\nRoom: {$b['type_name']}\n"
        . 'Check-in: ' . long_date($b['check_in']) . ', from ' . CHECK_IN_TIME . "\n"
        . 'Check-out: ' . long_date($b['check_out']) . ', by ' . CHECK_OUT_TIME . "\n"
        . 'Guests: ' . occupancy_text($b['adults'], $b['children']) . "\n"
        . 'Total: PKR ' . money($b['total']) . " (pay at the hotel)\n\n"
        . 'Free cancellation until 6:00 PM on ' . long_date($b['check_in']) . ".\n\nView your booking: $link";
    return send_email($b['guest_email'], $b['guest_name'], 'Booking confirmed: ' . $b['reference'], $html, $text);
}

function send_booking_cancellation($b)
{
    $first = explode(' ', trim($b['guest_name']))[0];
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your booking is cancelled</h1>'
        . '<p>Hello ' . e($first) . ', your booking ' . e($b['reference']) . ' at ' . e(HOTEL_NAME) . ' has been cancelled. Nothing is charged.</p>'
        . booking_email_rows($b)
        . email_button(SITE_URL . '/rooms.php', 'Book another stay')
        . '<p>If you didn\'t ask for this, please call us on ' . e(HOTEL_PHONE) . '.</p>';
    $text = "Your booking is cancelled\n\nHello $first, your booking {$b['reference']} ({$b['type_name']}, "
        . long_date($b['check_in']) . ' to ' . long_date($b['check_out']) . ") has been cancelled. Nothing is charged.\n\n"
        . 'If you didn\'t ask for this, please call us on ' . HOTEL_PHONE . '.';
    return send_email($b['guest_email'], $b['guest_name'], 'Booking cancelled: ' . $b['reference'], $html, $text);
}
