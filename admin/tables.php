<?php
/*
 * Admin: dining tables and restaurant hours (FR-TBL-01, FR-ADM-06).
 * Per restaurant: the hours shown to guests, the reservation hours (first and last booking
 * time follow these), and its tables: number, seats, in use or not. Tables with past
 * reservations or orders can't be deleted, only taken out of use.
 */
require_once '../includes/init.php';
require_once '../includes/reservations.php';
require_once '../includes/admin-layout.php';
require_admin();

function table_in_use_count($tableId)
{
    $stmt = db()->prepare('SELECT (SELECT COUNT(*) FROM table_reservations WHERE table_id = ?) + (SELECT COUNT(*) FROM orders WHERE table_id = ?)');
    $stmt->execute([$tableId, $tableId]);
    return (int) $stmt->fetchColumn();
}

// Upcoming reservations that a change could affect, as "Sat 17 Oct 7:00 PM, Ali (4)"
function upcoming_reservations($where, array $params)
{
    $stmt = db()->prepare("SELECT tr.reservation_date, tr.reservation_time, tr.party_size, u.name, t.table_number
        FROM table_reservations tr JOIN users u ON u.id = tr.user_id JOIN dining_tables t ON t.id = tr.table_id
        WHERE tr.status = 'confirmed' AND tr.reservation_date >= CURDATE() AND $where
        ORDER BY tr.reservation_date, tr.reservation_time LIMIT 6");
    $stmt->execute($params);
    return array_map(function ($r) {
        return date('D j M', strtotime($r['reservation_date'])) . ' ' . date('g:i A', strtotime($r['reservation_time']))
            . ', table ' . $r['table_number'] . ', ' . $r['name'] . ' (' . $r['party_size'] . ')';
    }, $stmt->fetchAll());
}

switch (admin_action()) {
    case 'hours':
        $restaurant = find_restaurant($_POST['restaurant_id'] ?? 0);
        $opens = (string) ($_POST['opens_at'] ?? '');
        $closes = (string) ($_POST['closes_at'] ?? '');
        $hoursText = trim($_POST['hours'] ?? '');
        if (!$restaurant || !preg_match('/^\d{2}:\d{2}$/', $opens) || !preg_match('/^\d{2}:\d{2}$/', $closes) || $closes <= $opens || $hoursText === '') {
            flash('error', 'Enter the hours text, and a closing time after the opening time.');
        } else {
            db()->prepare('UPDATE restaurants SET hours = ?, opens_at = ?, closes_at = ? WHERE id = ?')->execute([mb_substr($hoursText, 0, 255), $opens, $closes, $restaurant['id']]);
            flash('success', $restaurant['name'] . ' hours saved. Reservation times now follow them.');
            // Guests who already reserved a time outside the new hours keep their reservation
            $outside = upcoming_reservations('t.restaurant_id = ? AND (tr.reservation_time < ? OR tr.reservation_time > SUBTIME(?, \'01:30:00\'))', [$restaurant['id'], $opens, $closes]);
            if ($outside) {
                flash('info', 'These reservations are outside the new hours. They stay booked, so call the guests if they need to change: ' . implode('; ', $outside) . '.');
            }
        }
        redirect('admin/tables.php#r' . (int) ($_POST['restaurant_id'] ?? 0));

    case 'add':
        $restaurant = find_restaurant($_POST['restaurant_id'] ?? 0);
        $number = strtoupper(trim($_POST['table_number'] ?? ''));
        $seats = (int) ($_POST['seats'] ?? 0);
        if (!$restaurant || !preg_match('/^[A-Za-z0-9-]{1,10}$/', $number) || $seats < 1 || $seats > 30) {
            flash('error', 'Enter a table number (e.g. 9) and 1 to 30 seats.');
        } else {
            $stmt = db()->prepare('SELECT COUNT(*) FROM dining_tables WHERE restaurant_id = ? AND table_number = ?');
            $stmt->execute([$restaurant['id'], $number]);
            if ($stmt->fetchColumn() > 0) {
                flash('error', $restaurant['name'] . ' already has a table ' . $number . '.');
            } else {
                db()->prepare('INSERT INTO dining_tables (restaurant_id, table_number, seats) VALUES (?, ?, ?)')->execute([$restaurant['id'], $number, $seats]);
                flash('success', 'Table ' . $number . ' added to ' . $restaurant['name'] . '. Print its QR card from Table QR codes.');
            }
        }
        redirect('admin/tables.php#r' . (int) ($_POST['restaurant_id'] ?? 0));

    case 'update':
        $stmt = db()->prepare('SELECT * FROM dining_tables WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        $table = $stmt->fetch();
        $seats = (int) ($_POST['seats'] ?? 0);
        if ($table && $seats >= 1 && $seats <= 30) {
            $inUse = !empty($_POST['is_active']) ? 1 : 0;
            db()->prepare('UPDATE dining_tables SET seats = ?, is_active = ? WHERE id = ?')->execute([$seats, $inUse, $table['id']]);
            flash('success', 'Table ' . $table['table_number'] . ' saved.');
            // Reservations already made for this table stay; the host may need to seat them elsewhere
            if (!$inUse && $table['is_active'] && ($booked = upcoming_reservations('tr.table_id = ?', [$table['id']]))) {
                flash('info', 'Table ' . $table['table_number'] . ' is out of use, but it is reserved: ' . implode('; ', $booked) . '. Seat these guests at another table.');
            } elseif ($seats < $table['seats'] && ($tooBig = upcoming_reservations('tr.table_id = ? AND tr.party_size > ?', [$table['id'], $seats]))) {
                flash('info', 'Table ' . $table['table_number'] . ' now has ' . $seats . ' seats, but these reservations are for more people: ' . implode('; ', $tooBig) . '. Seat them at a bigger table.');
            }
        } else {
            flash('error', 'Seats must be 1 to 30.');
        }
        redirect('admin/tables.php#r' . (int) ($table['restaurant_id'] ?? 0));

    case 'delete':
        $stmt = db()->prepare('SELECT * FROM dining_tables WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        if ($table = $stmt->fetch()) {
            if (table_in_use_count($table['id'])) {
                flash('error', 'Table ' . $table['table_number'] . ' has reservations or orders in its history. Untick "In use" instead.');
            } else {
                db()->prepare('DELETE FROM dining_tables WHERE id = ?')->execute([$table['id']]);
                flash('success', 'Table ' . $table['table_number'] . ' deleted.');
            }
        }
        redirect('admin/tables.php#r' . (int) ($table['restaurant_id'] ?? 0));
}

$restaurants = restaurants_list();
$tables = db()->query('SELECT * FROM dining_tables ORDER BY restaurant_id, LENGTH(table_number), table_number')->fetchAll();

live_watch('dining_tables', 'restaurants', 'table_reservations'); // updates by itself when these change
admin_open('Tables & hours', 'tables', 'Each restaurant\'s tables and opening hours. Reservations and QR ordering use these.',
    '<a href="' . url('admin/table-qr.php') . '" class="btn-small-outline"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Print QR codes</a>');
?>
<?php foreach ($restaurants as $rs):
    $list = array_values(array_filter($tables, function ($t) use ($rs) { return (int) $t['restaurant_id'] === (int) $rs['id']; }));
    $seatsTotal = array_sum(array_map(function ($t) { return $t['is_active'] ? (int) $t['seats'] : 0; }, $list)); ?>
    <section class="admin-card" id="r<?= $rs['id'] ?>" aria-labelledby="rh<?= $rs['id'] ?>">
        <div class="confirm-details-head">
            <h2 id="rh<?= $rs['id'] ?>"><?= e($rs['name']) ?></h2>
            <span class="admin-muted"><?= count($list) ?> tables · <?= $seatsTotal ?> seats in use</span>
        </div>
        <form method="post" class="hours-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hours">
            <input type="hidden" name="restaurant_id" value="<?= $rs['id'] ?>">
            <div class="form-field"><label for="h<?= $rs['id'] ?>">Hours shown to guests</label><input type="text" id="h<?= $rs['id'] ?>" name="hours" value="<?= e($rs['hours']) ?>" maxlength="255"></div>
            <div class="form-field"><label for="o<?= $rs['id'] ?>">Opens</label><input type="time" id="o<?= $rs['id'] ?>" name="opens_at" value="<?= substr($rs['opens_at'], 0, 5) ?>"></div>
            <div class="form-field"><label for="c<?= $rs['id'] ?>">Closes</label><input type="time" id="c<?= $rs['id'] ?>" name="closes_at" value="<?= substr($rs['closes_at'], 0, 5) ?>"></div>
            <button type="submit" class="btn-small-red">Save hours</button>
        </form>
        <p class="field-help mb-3">Guests can reserve from opening time until 90 minutes before closing.</p>

        <ul class="table-admin-list">
            <?php foreach ($list as $t): ?>
                <li class="<?= $t['is_active'] ? '' : 'is-off' ?>">
                    <form method="post" class="table-admin-row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <strong>Table <?= e($t['table_number']) ?></strong>
                        <label class="seats-input"><span>Seats</span><input type="number" name="seats" value="<?= (int) $t['seats'] ?>" min="1" max="30"></label>
                        <label class="admin-check"><input type="checkbox" name="is_active" value="1"<?= $t['is_active'] ? ' checked' : '' ?>> <span>In use</span></label>
                        <button type="submit" class="btn-icon" aria-label="Save table <?= e($t['table_number']) ?>" title="Save"><i class="fa-solid fa-check" aria-hidden="true"></i></button>
                    </form>
                    <?php if (!table_in_use_count($t['id'])): ?>
                        <form method="post" data-confirm="Delete table <?= e($t['table_number']) ?> at <?= e($rs['name']) ?>?"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-icon tool-danger" aria-label="Delete table <?= e($t['table_number']) ?>" title="Delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="category-add mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="restaurant_id" value="<?= $rs['id'] ?>">
            <div class="form-field"><label for="n<?= $rs['id'] ?>">New table number</label><input type="text" id="n<?= $rs['id'] ?>" name="table_number" maxlength="10" placeholder="e.g. 9"></div>
            <div class="form-field seats-field"><label for="s<?= $rs['id'] ?>">Seats</label><input type="number" id="s<?= $rs['id'] ?>" name="seats" value="4" min="1" max="30"></div>
            <button type="submit" class="btn-primary-red"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add table</button>
        </form>
    </section>
<?php endforeach; ?>
<?php admin_close();
