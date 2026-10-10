<?php
/*
 * Admin: the hotel's individual rooms (FR-ROOM-02, FR-ROOM-03).
 * Add rooms (one, or a run such as 105 to 108), change a room's number or type, mark rooms
 * available or under maintenance (one at a time or several at once), and delete rooms
 * that have never been booked. Rooms under maintenance can't be booked (includes/rooms.php).
 *
 *   rooms.php            → all rooms
 *   rooms.php?type=6     → only Pool Wing rooms
 *   rooms.php?edit=12    → edit form for room 12 at the top
 */
require_once '../includes/init.php';
require_once '../includes/rooms.php';
require_once '../includes/admin-layout.php';
require_admin();

const MAX_ROOMS_AT_ONCE = 50;

$typeFilter = (int) ($_GET['type'] ?? 0);
$here = 'admin/rooms.php' . ($typeFilter ? '?type=' . $typeFilter : '');
$types = room_types(true);
$typeNames = array_column($types, 'name', 'id');
$today = date('Y-m-d');

// Room numbers: 1 to 10 letters, digits or dashes, e.g. "101" or "PW-3"
function valid_room_number($number)
{
    return preg_match('/^[A-Za-z0-9-]{1,10}$/', $number) === 1;
}

// Upcoming stays in a room that aren't cancelled (warned about before maintenance or a type change)
function upcoming_bookings($roomId)
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ? AND status IN ('confirmed', 'checked_in') AND check_out > CURDATE()");
    $stmt->execute([$roomId]);
    return (int) $stmt->fetchColumn();
}

// Who is staying in (or has booked) these rooms, for the warning when they go under maintenance.
// Their bookings stay valid; the front desk decides whether to move them.
function guests_in_rooms_note(array $roomIds)
{
    $marks = implode(',', array_fill(0, count($roomIds), '?'));
    $stmt = db()->prepare("SELECT r.room_number, u.name, b.status, b.check_in, b.check_out
        FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN users u ON u.id = b.user_id
        WHERE b.room_id IN ($marks) AND b.status IN ('confirmed', 'checked_in') AND b.check_out > CURDATE()
        ORDER BY b.check_in");
    $stmt->execute($roomIds);
    $lines = array_map(function ($b) {
        return 'room ' . $b['room_number'] . ': ' . $b['name'] . ($b['status'] === 'checked_in' ? ' (staying now, until ' : ' (' . date('j M', strtotime($b['check_in'])) . ' to ')
            . date('j M', strtotime($b['check_out'])) . ')';
    }, $stmt->fetchAll());
    return $lines ? 'Guests still have these rooms: ' . implode('; ', $lines) . '. Their bookings stay as they are. If they need another room, open the booking and use Move to another room.' : '';
}

function find_room($id)
{
    $stmt = db()->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

/* ---------- Actions ---------- */

$errors = [];
$action = admin_action();

switch ($action) {
    case 'add':
        $from = strtoupper(trim($_POST['number'] ?? ''));
        $to = strtoupper(trim($_POST['number_to'] ?? ''));
        $typeId = (int) ($_POST['room_type_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'maintenance' ? 'maintenance' : 'available';

        if (!valid_room_number($from)) {
            $errors['number'] = 'Enter a room number of up to 10 letters, digits or dashes, e.g. 101.';
        }
        if ($to !== '' && (!ctype_digit($from) || !ctype_digit($to) || (int) $to < (int) $from)) {
            $errors['number_to'] = 'For several rooms, give two numbers with the bigger one last, e.g. 105 to 108.';
        } elseif ($to !== '' && (int) $to - (int) $from + 1 > MAX_ROOMS_AT_ONCE) {
            $errors['number_to'] = 'Add up to ' . MAX_ROOMS_AT_ONCE . ' rooms at a time.';
        }
        if (!isset($typeNames[$typeId])) {
            $errors['room_type_id'] = 'Choose the room type.';
        }
        if ($errors) break;

        // Keep leading zeros, e.g. "05" to "08"
        $numbers = $to === '' ? [$from] : array_map(function ($n) use ($from) {
            return str_pad((string) $n, strlen($from), '0', STR_PAD_LEFT);
        }, range((int) $from, (int) $to));
        $exists = db()->prepare('SELECT COUNT(*) FROM rooms WHERE room_number = ?');
        $insert = db()->prepare('INSERT INTO rooms (room_type_id, room_number, status) VALUES (?, ?, ?)');
        $added = $skipped = [];
        foreach ($numbers as $number) {
            $exists->execute([$number]);
            if ($exists->fetchColumn() > 0) {
                $skipped[] = $number;
                continue;
            }
            $insert->execute([$typeId, $number, $status]);
            $added[] = $number;
        }
        if ($added) {
            flash('success', (count($added) === 1 ? 'Room ' . $added[0] . ' has' : count($added) . ' rooms (' . implode(', ', $added) . ') have')
                . ' been added to ' . $typeNames[$typeId] . '.');
        }
        if ($skipped) {
            flash($added ? 'info' : 'error', 'Already there, so not added again: ' . implode(', ', $skipped) . '.');
        }
        redirect($here);

    case 'update':
        $room = find_room($_POST['id'] ?? 0);
        if (!$room) {
            flash('error', 'That room no longer exists.');
            redirect($here);
        }
        $number = strtoupper(trim($_POST['number'] ?? ''));
        $typeId = (int) ($_POST['room_type_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'maintenance' ? 'maintenance' : 'available';
        if (!valid_room_number($number)) {
            $errors['number'] = 'Enter a room number of up to 10 letters, digits or dashes, e.g. 101.';
        } else {
            $stmt = db()->prepare('SELECT COUNT(*) FROM rooms WHERE room_number = ? AND id <> ?');
            $stmt->execute([$number, $room['id']]);
            if ($stmt->fetchColumn() > 0) {
                $errors['number'] = 'Another room already has number ' . $number . '.';
            }
        }
        if (!isset($typeNames[$typeId])) {
            $errors['room_type_id'] = 'Choose the room type.';
        }
        // Guests booked (and paid for) a kind of room: it can't turn into another kind under them
        $upcoming = upcoming_bookings($room['id']);
        if ($upcoming && $typeId !== (int) $room['room_type_id'] && !isset($errors['room_type_id'])) {
            $errors['room_type_id'] = 'Room ' . $room['room_number'] . ' has ' . $upcoming . ' current or upcoming ' . ($upcoming === 1 ? 'booking' : 'bookings')
                . ' as a ' . $typeNames[$room['room_type_id']] . '. Move those guests to another room first (open the booking → Move to another room).';
        }
        if ($errors) {
            $_GET['edit'] = $room['id'];
            break;
        }
        db()->prepare('UPDATE rooms SET room_number = ?, room_type_id = ?, status = ? WHERE id = ?')
            ->execute([$number, $typeId, $status, $room['id']]);
        flash('success', 'Room ' . $number . ' has been saved.');
        if ($upcoming && $status === 'maintenance' && $room['status'] !== 'maintenance') {
            flash('info', guests_in_rooms_note([$room['id']]));
        }
        redirect($here);

    case 'status':
        // One room or several ticked rooms → available / under maintenance
        $status = ($_POST['status'] ?? '') === 'maintenance' ? 'maintenance' : 'available';
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        if (!$ids) {
            flash('error', 'Tick the rooms you want to change first.');
            redirect($here);
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("UPDATE rooms SET status = ? WHERE id IN ($marks)")->execute(array_merge([$status], $ids));
        flash('success', (count($ids) === 1 ? '1 room is' : count($ids) . ' rooms are') . ' now '
            . ($status === 'maintenance' ? 'under maintenance and can\'t be booked.' : 'available for booking.'));
        if ($status === 'maintenance' && ($note = guests_in_rooms_note($ids))) {
            flash('info', $note);
        }
        redirect($here);

    case 'delete':
        $room = find_room($_POST['id'] ?? 0);
        if ($room) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM bookings WHERE room_id = ?');
            $stmt->execute([$room['id']]);
            if ($stmt->fetchColumn() > 0) {
                flash('error', 'Room ' . $room['room_number'] . ' has bookings in its history, so it can\'t be deleted. Mark it under maintenance instead.');
            } else {
                db()->prepare('DELETE FROM rooms WHERE id = ?')->execute([$room['id']]);
                flash('success', 'Room ' . $room['room_number'] . ' has been deleted.');
            }
        }
        redirect($here);
}

/* ---------- Page ---------- */

$editRoom = !empty($_GET['edit']) ? find_room($_GET['edit']) : null;

// Every room with tonight's guest (if any), upcoming bookings and whether it was ever booked
$stmt = db()->prepare(
    "SELECT r.*, rt.name AS type_name,
            (SELECT u.name FROM bookings b JOIN users u ON u.id = b.user_id
             WHERE b.room_id = r.id AND b.status IN ('confirmed', 'checked_in') AND b.check_in <= ? AND b.check_out > ?
             LIMIT 1) AS tonight_guest,
            (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id AND b.status IN ('confirmed', 'checked_in') AND b.check_out > ?) AS upcoming,
            (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.id) AS ever_booked
     FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id
     " . ($typeFilter ? 'WHERE r.room_type_id = ?' : '') . "
     ORDER BY rt.sort_order, rt.name, LENGTH(r.room_number), r.room_number"
);
$stmt->execute(array_merge([$today, $today, $today], $typeFilter ? [$typeFilter] : []));
$rooms = $stmt->fetchAll();

$summary = ['total' => count($rooms), 'available' => 0, 'maintenance' => 0, 'tonight' => 0];
foreach ($rooms as $room) {
    $summary[$room['status'] === 'maintenance' ? 'maintenance' : 'available']++;
    if ($room['tonight_guest'] !== null) $summary['tonight']++;
}

// Room type <select> with the chosen one selected
function type_options($types, $selected)
{
    $html = '<option value="">Choose…</option>';
    foreach ($types as $t) {
        $html .= '<option value="' . $t['id'] . '"' . ((int) $selected === (int) $t['id'] ? ' selected' : '') . '>'
            . e($t['name']) . ($t['is_active'] ? '' : ' (hidden)') . '</option>';
    }
    return $html;
}

function field_error($errors, $field)
{
    return isset($errors[$field]) ? '<p class="field-error" id="e_' . $field . '">' . e($errors[$field]) . '</p>' : '';
}

live_watch('rooms', 'room_types', 'bookings', 'users'); // updates by itself when these change
admin_open('Rooms', 'rooms', 'Room numbers, their type, and which ones can be booked');
?>

<div class="stat-cards stat-cards-small">
    <div class="stat-card"><i class="fa-solid fa-door-open" aria-hidden="true"></i><strong><?= $summary['total'] ?></strong><span><?= $typeFilter ? 'Rooms of this type' : 'Rooms in the hotel' ?></span></div>
    <div class="stat-card"><i class="fa-regular fa-circle-check" aria-hidden="true"></i><strong><?= $summary['available'] ?></strong><span>Available for booking</span></div>
    <div class="stat-card"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><strong><?= $summary['maintenance'] ?></strong><span>Under maintenance</span></div>
    <div class="stat-card"><i class="fa-regular fa-moon" aria-hidden="true"></i><strong><?= $summary['tonight'] ?></strong><span>Booked tonight</span></div>
</div>

<?php if ($editRoom): ?>
    <form method="post" class="admin-card admin-card-highlight" id="edit" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= $editRoom['id'] ?>">
        <h2>Edit room <?= e($editRoom['room_number']) ?></h2>
        <div class="admin-inline-form">
            <div class="form-field<?= isset($errors['number']) ? ' has-error' : '' ?>">
                <label for="e_num">Room number</label>
                <input type="text" id="e_num" name="number" value="<?= old('number', $editRoom['room_number']) ?>" maxlength="10" required<?= isset($errors['number']) ? ' aria-invalid="true" aria-describedby="e_number"' : '' ?>>
                <?= field_error($errors, 'number') ?>
            </div>
            <div class="form-field<?= isset($errors['room_type_id']) ? ' has-error' : '' ?>">
                <label for="e_type">Room type</label>
                <select id="e_type" name="room_type_id" required><?= type_options($types, $_POST['room_type_id'] ?? $editRoom['room_type_id']) ?></select>
                <?php if (!isset($errors['room_type_id']) && ($booked = upcoming_bookings($editRoom['id']))): ?>
                    <p class="field-help">Guests have this room booked (<?= $booked ?>), so its type stays the same until they are moved.</p>
                <?php endif; ?>
                <?= field_error($errors, 'room_type_id') ?>
            </div>
            <div class="form-field">
                <label for="e_status">Status</label>
                <select id="e_status" name="status">
                    <option value="available"<?= ($_POST['status'] ?? $editRoom['status']) === 'available' ? ' selected' : '' ?>>Available</option>
                    <option value="maintenance"<?= ($_POST['status'] ?? $editRoom['status']) === 'maintenance' ? ' selected' : '' ?>>Under maintenance</option>
                </select>
            </div>
            <div class="admin-inline-buttons">
                <button type="submit" class="btn-primary-red"><i class="fa-solid fa-check" aria-hidden="true"></i> Save</button>
                <a href="<?= url($here) ?>" class="admin-link">Cancel</a>
            </div>
        </div>
    </form>
<?php else: ?>
    <form method="post" class="admin-card" id="add" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <h2>Add rooms</h2>
        <div class="admin-inline-form">
            <div class="form-field<?= isset($errors['number']) ? ' has-error' : '' ?>">
                <label for="a_num">Room number</label>
                <input type="text" id="a_num" name="number" value="<?= old('number') ?>" maxlength="10" placeholder="105" required<?= isset($errors['number']) ? ' aria-invalid="true" aria-describedby="e_number"' : '' ?>>
                <?= field_error($errors, 'number') ?>
            </div>
            <div class="form-field<?= isset($errors['number_to']) ? ' has-error' : '' ?>">
                <label for="a_to">To (optional)</label>
                <input type="text" id="a_to" name="number_to" value="<?= old('number_to') ?>" maxlength="10" inputmode="numeric" placeholder="108" aria-describedby="a_to_help<?= isset($errors['number_to']) ? ' e_number_to' : '' ?>"<?= isset($errors['number_to']) ? ' aria-invalid="true"' : '' ?>>
                <p class="field-help" id="a_to_help">Fill in to add a run of rooms at once.</p>
                <?= field_error($errors, 'number_to') ?>
            </div>
            <div class="form-field<?= isset($errors['room_type_id']) ? ' has-error' : '' ?>">
                <label for="a_type">Room type</label>
                <select id="a_type" name="room_type_id" required<?= isset($errors['room_type_id']) ? ' aria-invalid="true" aria-describedby="e_room_type_id"' : '' ?>><?= type_options($types, $_POST['room_type_id'] ?? $typeFilter) ?></select>
                <?= field_error($errors, 'room_type_id') ?>
            </div>
            <div class="form-field">
                <label for="a_status">Status</label>
                <select id="a_status" name="status">
                    <option value="available">Available</option>
                    <option value="maintenance"<?= ($_POST['status'] ?? '') === 'maintenance' ? ' selected' : '' ?>>Under maintenance</option>
                </select>
            </div>
            <div class="admin-inline-buttons">
                <button type="submit" class="btn-primary-red"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add</button>
            </div>
        </div>
    </form>
<?php endif; ?>

<div class="admin-filter">
    <span>Show:</span>
    <a href="<?= url('admin/rooms.php') ?>" class="<?= $typeFilter ? '' : 'active' ?>">All rooms</a>
    <?php foreach ($types as $t): ?>
        <a href="<?= url('admin/rooms.php?type=' . $t['id']) ?>" class="<?= $typeFilter === (int) $t['id'] ? 'active' : '' ?>"><?= e($t['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$rooms): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-door-open" aria-hidden="true"></i></span>
        <h2>No rooms here yet</h2>
        <p>Add room numbers with the form above. Guests can only book a room type that has rooms.</p>
    </div>
<?php else: ?>
    <form method="post" id="roomsForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <div class="bulk-bar" data-bulk-bar>
            <span data-bulk-count aria-live="polite">Tick rooms to change several at once</span>
            <button type="submit" name="status" value="available" class="btn-small-outline"><i class="fa-regular fa-circle-check" aria-hidden="true"></i> Mark available</button>
            <button type="submit" name="status" value="maintenance" class="btn-small-outline"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i> Mark under maintenance</button>
        </div>
    </form>

    <div class="table-responsive admin-table-wrap">
        <table class="table admin-table align-middle">
            <thead>
                <tr>
                    <th scope="col" class="col-check"><input type="checkbox" data-check-all aria-label="Tick all rooms" form="roomsForm"></th>
                    <th scope="col">Room</th>
                    <th scope="col">Type</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tonight</th>
                    <th scope="col">Upcoming</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                    <tr class="<?= $editRoom && $editRoom['id'] == $room['id'] ? 'is-editing' : '' ?>">
                        <td class="col-check"><input type="checkbox" name="ids[]" value="<?= $room['id'] ?>" form="roomsForm" aria-label="Tick room <?= e($room['room_number']) ?>"></td>
                        <th scope="row" class="room-no"><?= e($room['room_number']) ?></th>
                        <td><?= e($room['type_name']) ?></td>
                        <td><?= status_badge($room['status']) ?></td>
                        <td><?= $room['tonight_guest'] !== null ? '<i class="fa-solid fa-user" aria-hidden="true"></i> ' . e($room['tonight_guest']) : '<span class="admin-muted">Free</span>' ?></td>
                        <td><?= $room['upcoming'] ? $room['upcoming'] . ' ' . ($room['upcoming'] == 1 ? 'booking' : 'bookings') : '<span class="admin-muted">None</span>' ?></td>
                        <td class="text-end">
                            <div class="table-actions">
                                <a href="<?= url('admin/rooms.php?' . http_build_query(array_filter(['type' => $typeFilter, 'edit' => $room['id']]))) ?>#edit" class="btn-icon" aria-label="Edit room <?= e($room['room_number']) ?>" title="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                <form method="post"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="ids[]" value="<?= $room['id'] ?>">
                                    <?php if ($room['status'] === 'maintenance'): ?>
                                        <button type="submit" name="status" value="available" class="btn-icon" aria-label="Mark room <?= e($room['room_number']) ?> available" title="Mark available"><i class="fa-regular fa-circle-check" aria-hidden="true"></i></button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="maintenance" class="btn-icon" aria-label="Mark room <?= e($room['room_number']) ?> under maintenance" title="Mark under maintenance"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></button>
                                    <?php endif; ?>
                                </form>
                                <?php if (!$room['ever_booked']): ?>
                                    <form method="post" data-confirm="Delete room <?= e($room['room_number']) ?>?"><?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $room['id'] ?>">
                                        <button type="submit" class="btn-icon tool-danger" aria-label="Delete room <?= e($room['room_number']) ?>" title="Delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php admin_close();
