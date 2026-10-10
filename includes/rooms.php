<?php
/*
 * Rooms: room types, their photos, and which rooms are free for some dates.
 * Used by the Rooms and Room Detail pages, the booking steps and the admin panel.
 *
 * Availability rule (FR-BOOK-03, FR-SIG-CAL-03): a room is taken for a stay if it is
 * under maintenance, or if a booking that isn't cancelled overlaps the stay:
 *     booking.check_in < stay.check_out  AND  booking.check_out > stay.check_in
 * (Someone checking out on the day another guest checks in doesn't count as overlap.)
 */

const MAX_NIGHTS = 30;

// All room types (active ones only, unless $includeHidden), with their main photo.
function room_types($includeHidden = false)
{
    return db()->query(
        'SELECT rt.*, p.file AS photo
         FROM room_types rt
         LEFT JOIN room_type_photos p ON p.room_type_id = rt.id AND p.is_main = 1
         ' . ($includeHidden ? '' : 'WHERE rt.is_active = 1') . '
         ORDER BY rt.sort_order, rt.name'
    )->fetchAll();
}

function room_type_by_slug($slug)
{
    $stmt = db()->prepare('SELECT * FROM room_types WHERE slug = ? AND is_active = 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

// Photos of one room type: the main photo first, then the rest in the admin's order.
function room_type_photos($typeId)
{
    $stmt = db()->prepare('SELECT * FROM room_type_photos WHERE room_type_id = ? ORDER BY is_main DESC, sort_order, id');
    $stmt->execute([$typeId]);
    return $stmt->fetchAll();
}

// Amenities are stored one per line.
function amenity_list($text)
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text))));
}

// "3 Adults" / "2 Adults, 1 Child"
function occupancy_text($adults, $children)
{
    $text = $adults . ' ' . ($adults == 1 ? 'Adult' : 'Adults');
    if ($children > 0) {
        $text .= ', ' . $children . ' ' . ($children == 1 ? 'Child' : 'Children');
    }
    return $text;
}

/*
 * Checks a stay typed by a visitor. Returns ['check_in', 'check_out', 'nights'] or a
 * message saying what's wrong (FR-BOOK-02). Always checked on the server, not only in the browser.
 */
function check_stay($checkIn, $checkOut)
{
    $in = DateTime::createFromFormat('!Y-m-d', (string) $checkIn);
    $out = DateTime::createFromFormat('!Y-m-d', (string) $checkOut);
    if (!$in || !$out || $in->format('Y-m-d') !== $checkIn || $out->format('Y-m-d') !== $checkOut) {
        return 'Choose your check-in and check-out dates.';
    }
    $today = new DateTime('today');
    if ($in < $today) {
        return 'Check-in can\'t be in the past.';
    }
    if ($in > (clone $today)->modify('+1 year')) {
        return 'Online bookings open up to a year ahead. For later dates, please contact the hotel.';
    }
    if ($out <= $in) {
        return 'Check-out must be after check-in.';
    }
    $nights = (int) $in->diff($out)->days;
    if ($nights > MAX_NIGHTS) {
        return 'Online bookings are for up to ' . MAX_NIGHTS . ' nights. For a longer stay, please contact the hotel.';
    }
    return ['check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $nights];
}

// How many rooms of each type are free for a stay: [room_type_id => count].
function free_room_counts($checkIn, $checkOut)
{
    $stmt = db()->prepare(
        "SELECT r.room_type_id, COUNT(*) AS free
         FROM rooms r
         WHERE r.status = 'available'
           AND NOT EXISTS (
               SELECT 1 FROM bookings b
               WHERE b.room_id = r.id AND b.status <> 'cancelled'
                 AND b.check_in < ? AND b.check_out > ?
           )
         GROUP BY r.room_type_id"
    );
    $stmt->execute([$checkOut, $checkIn]);
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['room_type_id']] = (int) $row['free'];
    }
    return $counts;
}

// The free rooms of one type for a stay (used when a booking is saved).
function free_rooms($typeId, $checkIn, $checkOut)
{
    $stmt = db()->prepare(
        "SELECT r.id, r.room_number
         FROM rooms r
         WHERE r.room_type_id = ? AND r.status = 'available'
           AND NOT EXISTS (
               SELECT 1 FROM bookings b
               WHERE b.room_id = r.id AND b.status <> 'cancelled'
                 AND b.check_in < ? AND b.check_out > ?
           )
         ORDER BY r.room_number"
    );
    $stmt->execute([$typeId, $checkOut, $checkIn]);
    return $stmt->fetchAll();
}

// The most adults and children any room type takes, for the search bar's choices.
function guest_limits()
{
    $row = db()->query('SELECT MAX(max_adults) AS adults, MAX(max_children) AS children FROM room_types WHERE is_active = 1')->fetch();
    return ['adults' => max(1, (int) $row['adults']), 'children' => (int) $row['children']];
}

// "Fri 16 Oct"
function short_date($date)
{
    return date('D j M', strtotime($date));
}

/*
 * Puts a room type at a place in the list (1 = first) and numbers all room types 1, 2, 3 …
 * so "Position" always means the real place on the Rooms and Home pages, with no ties.
 */
function place_room_type($typeId, $position)
{
    $ids = db()->prepare('SELECT id FROM room_types WHERE id <> ? ORDER BY sort_order, name');
    $ids->execute([(int) $typeId]);
    $ids = $ids->fetchAll(PDO::FETCH_COLUMN);
    array_splice($ids, max(0, min(count($ids), (int) $position - 1)), 0, [(int) $typeId]);
    $save = db()->prepare('UPDATE room_types SET sort_order = ? WHERE id = ?');
    foreach ($ids as $i => $id) {
        $save->execute([$i + 1, $id]);
    }
}
