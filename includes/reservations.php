<?php
/*
 * Table reservations (FR-TBL-01 to 06, FR-MAIL-06). Used by reserve-table.php, the
 * reservation page, My Account and the admin panel.
 *
 * Rule (FR-TBL-04): a reservation holds its table for 2 hours. A table is taken for a new
 * time if a reservation that isn't cancelled, on the same date, starts less than 2 hours
 * before or after it. Statuses: confirmed → completed, or cancelled.
 */
require_once __DIR__ . '/mail.php';

const TABLE_HOLD_MINUTES = 120;     // how long one reservation keeps its table
const LAST_SEATING_MINUTES = 90;    // last booking time is this long before closing
const SLOT_MINUTES = 30;            // times offered: every half hour
const RESERVE_DAYS_AHEAD = 60;
const MAX_PARTY = 12;               // bigger groups: call the hotel

function restaurants_list()
{
    return db()->query('SELECT * FROM restaurants ORDER BY sort_order, name')->fetchAll();
}

function find_restaurant($id)
{
    $stmt = db()->prepare('SELECT * FROM restaurants WHERE id = ?');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

// The times guests can choose for a restaurant on a date: "18:00", "18:30", ...
// Today's list starts at least 30 minutes from now.
function reservation_slots($restaurant, $date)
{
    $slots = [];
    $first = strtotime($date . ' ' . $restaurant['opens_at']);
    $last = strtotime($date . ' ' . $restaurant['closes_at']) - LAST_SEATING_MINUTES * 60;
    $earliest = time() + 30 * 60;
    for ($t = $first; $t <= $last; $t += SLOT_MINUTES * 60) {
        if ($t >= $earliest) {
            $slots[] = date('H:i', $t);
        }
    }
    return $slots;
}

// "7:30 PM"
function nice_time($time)
{
    return date('g:i A', strtotime($time));
}

/*
 * Checks a reservation search (FR-TBL-02). Returns ['restaurant', 'date', 'time', 'party'] or a message.
 * Always checked on the server, whatever the form sent.
 */
function check_reservation_search($restaurantId, $date, $time, $party)
{
    $restaurant = find_restaurant($restaurantId);
    if (!$restaurant) {
        return 'Choose a restaurant.';
    }
    $day = DateTime::createFromFormat('!Y-m-d', (string) $date);
    if (!$day || $day->format('Y-m-d') !== $date) {
        return 'Choose a date.';
    }
    if ($date < date('Y-m-d')) {
        return 'The date can\'t be in the past.';
    }
    if ($date > date('Y-m-d', strtotime('+' . RESERVE_DAYS_AHEAD . ' days'))) {
        return 'Tables can be reserved up to ' . RESERVE_DAYS_AHEAD . ' days ahead.';
    }
    $party = (int) $party;
    if ($party < 1) {
        return 'Tell us how many guests.';
    }
    if ($party > MAX_PARTY) {
        return 'For groups of more than ' . MAX_PARTY . ', please call us on ' . HOTEL_PHONE . ' and we\'ll arrange it.';
    }
    $slots = reservation_slots($restaurant, $date);
    if (!$slots) {
        return $restaurant['name'] . ' has no more times left on that day. Please choose another day.';
    }
    if (!in_array($time, $slots, true)) {
        return 'Choose one of the times ' . $restaurant['name'] . ' is open (' . nice_time($slots[0]) . ' to ' . nice_time(end($slots)) . ').';
    }
    return ['restaurant' => $restaurant, 'date' => $date, 'time' => $time, 'party' => $party];
}

// Tables of a restaurant that are free at that time and seat the party, smallest first (FR-TBL-02).
function free_tables($restaurantId, $date, $time, $party, $ignoreReservationId = 0)
{
    $stmt = db()->prepare(
        "SELECT t.id, t.table_number, t.seats
         FROM dining_tables t
         WHERE t.restaurant_id = ? AND t.is_active = 1 AND t.seats >= ?
           AND NOT EXISTS (
               SELECT 1 FROM table_reservations r
               WHERE r.table_id = t.id AND r.status <> 'cancelled' AND r.id <> ?
                 AND r.reservation_date = ?
                 AND r.reservation_time < ADDTIME(?, SEC_TO_TIME(?))
                 AND ADDTIME(r.reservation_time, SEC_TO_TIME(?)) > ?
           )
         ORDER BY t.seats, LENGTH(t.table_number), t.table_number"
    );
    $hold = TABLE_HOLD_MINUTES * 60;
    $stmt->execute([(int) $restaurantId, (int) $party, (int) $ignoreReservationId, $date, $time, $hold, $hold, $time]);
    return $stmt->fetchAll();
}

/*
 * Reserves a table (FR-TBL-03, 04). Returns the new reservation id or a message.
 * The table row is locked inside a transaction, so two guests can't take the same table
 * for the same time, even at the same moment.
 */
function create_reservation($userId, array $search, $tableId, $notes)
{
    $pdo = db();
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM dining_tables WHERE id = ? AND restaurant_id = ? FOR UPDATE');
        $lock->execute([(int) $tableId, $search['restaurant']['id']]);
        $free = array_column(free_tables($search['restaurant']['id'], $search['date'], $search['time'], $search['party']), 'id');
        if (!$lock->fetch() || !in_array((int) $tableId, array_map('intval', $free), true)) {
            $pdo->rollBack();
            return 'Sorry, that table has just been taken for this time. Please choose another table or time.';
        }
        $pdo->prepare('INSERT INTO table_reservations (user_id, table_id, reservation_date, reservation_time, party_size, notes) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, (int) $tableId, $search['date'], $search['time'] . ':00', $search['party'], mb_substr(trim($notes), 0, 255)]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function find_reservation($id)
{
    $stmt = db()->prepare(
        'SELECT r.*, t.table_number, t.seats, rs.id AS restaurant_id, rs.name AS restaurant, rs.slug AS restaurant_slug,
                u.name AS guest_name, u.email AS guest_email, u.phone AS guest_phone
         FROM table_reservations r
         JOIN dining_tables t ON t.id = r.table_id
         JOIN restaurants rs ON rs.id = t.restaurant_id
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ?'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

// "TR-1042": the number guests quote
function reservation_number($reservation)
{
    return 'TR-' . (1000 + (int) $reservation['id']);
}

// Guests can cancel a confirmed reservation until its start time (FR-TBL-05).
function can_cancel_reservation($reservation)
{
    return $reservation['status'] === 'confirmed'
        && time() < strtotime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
}

// Changes a reservation's status (FR-TBL-06). Returns '' or a message.
function change_reservation_status($reservation, $status)
{
    if ($reservation['status'] !== 'confirmed' || !in_array($status, ['completed', 'cancelled'], true)) {
        return 'That change isn\'t possible for this reservation any more.';
    }
    $stmt = db()->prepare("UPDATE table_reservations SET status = ? WHERE id = ? AND status = 'confirmed'");
    $stmt->execute([$status, $reservation['id']]);
    if (!$stmt->rowCount()) {
        return 'Someone else changed this reservation just now.';
    }
    if ($status === 'cancelled') {
        send_reservation_email(find_reservation($reservation['id']), false);
    }
    return '';
}

// D16: no scheduled jobs, so reservations whose time has passed are marked completed
// whenever the admin opens the reservations list.
function tidy_past_reservations()
{
    db()->exec("UPDATE table_reservations SET status = 'completed'
                WHERE status = 'confirmed' AND TIMESTAMP(reservation_date, reservation_time) < NOW() - INTERVAL 3 HOUR");
}

/* ---------- Emails (FR-MAIL-06) ---------- */

function send_reservation_email($r, $confirmed = true)
{
    $first = explode(' ', trim($r['guest_name']))[0];
    $when = date('l j F Y', strtotime($r['reservation_date'])) . ' at ' . nice_time($r['reservation_time']);
    $details = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;">';
    foreach ([
        'Reservation' => '<strong>' . e(reservation_number($r)) . '</strong>',
        'Restaurant'  => e($r['restaurant']),
        'When'        => e($when),
        'Guests'      => (int) $r['party_size'],
        'Table'       => 'Table ' . e($r['table_number']),
    ] as $label => $value) {
        $details .= '<tr><td style="padding:8px 0;border-bottom:1px solid #EEEEEE;color:#707070;width:40%;">' . $label . '</td><td style="padding:8px 0;border-bottom:1px solid #EEEEEE;">' . $value . '</td></tr>';
    }
    $details .= '</table>';
    $link = SITE_URL . '/reservation/view.php?id=' . $r['id'];
    if ($confirmed) {
        $subject = 'Table reserved: ' . $r['restaurant'] . ', ' . date('j M', strtotime($r['reservation_date']));
        $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your table is reserved, ' . e($first) . '</h1>'
            . '<p>We look forward to welcoming you.</p>' . $details
            . '<p>We hold the table for 15 minutes after the reserved time. Plans changed? You can cancel from My Account.</p>'
            . email_button($link, 'View my reservation');
        $text = "Your table is reserved, $first\n\n" . reservation_number($r) . "\n{$r['restaurant']}, $when\n{$r['party_size']} guests, table {$r['table_number']}\n\n$link";
    } else {
        $subject = 'Reservation cancelled: ' . reservation_number($r);
        $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your reservation is cancelled</h1>'
            . '<p>Hello ' . e($first) . ', this table reservation has been cancelled.</p>' . $details
            . email_button(SITE_URL . '/reserve-table.php', 'Reserve another time');
        $text = "Your reservation is cancelled\n\n" . reservation_number($r) . "\n{$r['restaurant']}, $when";
    }
    return send_email($r['guest_email'], $r['guest_name'], $subject, $html, $text);
}
