<?php
/*
 * Admin: add or edit one room type (FR-ROOM-01), its amenities and its photos
 * (FR-ROOM-07, FR-ROOM-08). Changes show on the Rooms and room pages straight away.
 *
 *   room-type.php         → add a new room type
 *   room-type.php?id=4    → edit room type 4, manage its photos, or delete it
 */
require_once '../includes/init.php';
require_once '../includes/rooms.php';
require_once '../includes/uploads.php';
require_once '../includes/admin-layout.php';
require_admin();

const MAX_TYPE_PHOTOS = 20;

$id = (int) ($_GET['id'] ?? 0);
$type = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM room_types WHERE id = ?');
    $stmt->execute([$id]);
    $type = $stmt->fetch() ?: null;
    if (!$type) {
        flash('error', 'That room type no longer exists.');
        redirect('admin/room-types.php');
    }
}
$here = 'admin/room-type.php' . ($type ? '?id=' . $type['id'] : '');

/* ---------- Helpers ---------- */

// "Deluxe Queen Room" → "deluxe-queen-room", unique among room types (used in room.php?type=...)
function unique_room_slug($name)
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'room';
    $slug = $base;
    $check = db()->prepare('SELECT COUNT(*) FROM room_types WHERE slug = ?');
    for ($n = 2; $check->execute([$slug]) && $check->fetchColumn() > 0; $n++) {
        $slug = $base . '-' . $n;
    }
    return $slug;
}

// Checks the form. Returns [clean values, errors by field].
function check_room_type_form(array $in, $currentId)
{
    $v = [
        'name'         => trim($in['name'] ?? ''),
        'description'  => trim($in['description'] ?? ''),
        'price'        => trim(str_replace([',', ' '], '', $in['price'] ?? '')),
        'beds'         => trim($in['beds'] ?? ''),
        'feature'      => trim($in['feature'] ?? ''),
        'max_adults'   => trim($in['max_adults'] ?? ''),
        'max_children' => trim($in['max_children'] ?? ''),
        'amenities'    => implode("\n", amenity_list($in['amenities'] ?? '')),
        'sort_order'   => max(1, (int) ($in['sort_order'] ?? 1)),
        'is_active'    => !empty($in['is_active']) ? 1 : 0,
    ];
    $errors = [];

    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 100) {
        $errors['name'] = 'Enter a name of 2 to 100 characters.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM room_types WHERE name = ? AND id <> ?');
        $stmt->execute([$v['name'], $currentId]);
        if ($stmt->fetchColumn() > 0) {
            $errors['name'] = 'There is already a room type with this name.';
        }
    }
    if ($v['description'] === '' || mb_strlen($v['description']) > 2000) {
        $errors['description'] = 'Write a short description (up to 2,000 characters).';
    }
    if ($v['price'] === '') {
        $v['price'] = null; // "Price on request"
    } elseif (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v['price']) || (float) $v['price'] <= 0) {
        $errors['price'] = 'Enter the price in rupees, e.g. 23995, or leave it empty for "Price on request".';
    }
    if ($v['beds'] === '' || mb_strlen($v['beds']) > 100) {
        $errors['beds'] = 'Enter the beds, e.g. "1 King Size Bed".';
    }
    if (mb_strlen($v['feature']) > 150) {
        $errors['feature'] = 'Keep the feature line under 150 characters.';
    }
    if (!ctype_digit($v['max_adults']) || $v['max_adults'] < 1 || $v['max_adults'] > 10) {
        $errors['max_adults'] = 'Enter 1 to 10 adults.';
    }
    if (!ctype_digit($v['max_children']) || $v['max_children'] > 10) {
        $errors['max_children'] = 'Enter 0 to 10 children.';
    }
    if (mb_strlen($v['amenities']) > 3000) {
        $errors['amenities'] = 'The amenities list is too long.';
    }
    return [$v, $errors];
}

// A price for the form box: 23995.00 → "23995", 23995.50 → "23995.5"; empty for "Price on request"
function price_input_value($price)
{
    $price = (string) $price;
    return strpos($price, '.') !== false ? rtrim(rtrim($price, '0'), '.') : $price;
}

/*
 * What a change means for guests who already booked this room type. Their bookings keep the
 * price and guests they booked with (the price is copied into each booking), so the note
 * says so, and points out bookings that no longer fit new limits.
 */
function booked_guests_note($old, $new)
{
    $stmt = db()->prepare("SELECT b.status, b.adults, b.children FROM bookings b JOIN rooms r ON r.id = b.room_id
        WHERE r.room_type_id = ? AND b.status IN ('confirmed', 'checked_in') AND b.check_out > CURDATE()");
    $stmt->execute([$old['id']]);
    $booked = $stmt->fetchAll();
    if (!$booked) {
        return '';
    }
    $staying = count(array_filter($booked, function ($b) { return $b['status'] === 'checked_in'; }));
    $who = count($booked) . ' current or upcoming ' . (count($booked) === 1 ? 'booking' : 'bookings') . ($staying ? ' (' . $staying . ' staying now)' : '');
    $notes = [];
    $priceChanged = ($old['price_per_night'] === null) !== ($new['price'] === null)
        || ($new['price'] !== null && (float) $old['price_per_night'] !== (float) $new['price']);
    if ($priceChanged) {
        $notes[] = 'The new price is for new bookings only. Guests who already booked (' . $who . ') keep the price they booked at, and so do their bills.';
    }
    $tooBig = count(array_filter($booked, function ($b) use ($new) {
        return $b['adults'] > $new['max_adults'] || $b['children'] > $new['max_children'];
    }));
    if ($tooBig) {
        $notes[] = $tooBig . ' of these bookings (' . $who . ') ' . ($tooBig === 1 ? 'has' : 'have') . ' more guests than the new limit. They stay valid; check with those guests that the room still suits them.';
    }
    if ($old['is_active'] && !$new['is_active']) {
        $notes[] = 'The room type is hidden from new guests, but existing bookings (' . $who . ') stay valid.';
    }
    return implode(' ', $notes);
}

// Saves the order of the non-main photos as 1, 2, 3 ...
function save_photo_order(array $ids)
{
    $stmt = db()->prepare('UPDATE room_type_photos SET sort_order = ? WHERE id = ?');
    foreach (array_values($ids) as $i => $photoId) {
        $stmt->execute([$i + 1, $photoId]);
    }
}

/* ---------- Actions ---------- */

$errors = [];
$values = $type ?: [
    'name' => '', 'description' => '', 'price_per_night' => null, 'beds' => '', 'feature' => '',
    'max_adults' => 2, 'max_children' => 1, 'amenities' => '', 'sort_order' => count(room_types(true)) + 1, 'is_active' => 1,
];
$action = admin_action();

// Every photo action needs a saved room type, and the photo must belong to it
$photo = null;
if (in_array($action, ['main', 'move', 'replace', 'delete_photo', 'alt'], true)) {
    $stmt = db()->prepare('SELECT * FROM room_type_photos WHERE id = ? AND room_type_id = ?');
    $stmt->execute([(int) ($_POST['photo_id'] ?? 0), $type['id'] ?? 0]);
    $photo = $stmt->fetch();
    if (!$photo) {
        flash('error', 'That photo no longer exists. The page has been refreshed.');
        redirect($here);
    }
}

switch ($action) {
    case 'save':
        [$clean, $errors] = check_room_type_form($_POST, $type['id'] ?? 0);
        $stale = edited_meanwhile($type);
        if ($errors || $stale) {
            $values = array_merge($values, $clean, ['price_per_night' => $_POST['price'] ?? '']);
            break;
        }
        $fields = [$clean['name'], $clean['description'], $clean['price'], $clean['beds'], $clean['feature'],
            $clean['max_adults'], $clean['max_children'], $clean['amenities'], $clean['sort_order'], $clean['is_active']];
        if ($type) {
            $stmt = db()->prepare('UPDATE room_types SET name = ?, description = ?, price_per_night = ?, beds = ?, feature = ?,
                max_adults = ?, max_children = ?, amenities = ?, sort_order = ?, is_active = ? WHERE id = ?');
            $stmt->execute(array_merge($fields, [$type['id']]));
            place_room_type($type['id'], $clean['sort_order']);
            flash('success', 'Saved. The website now shows the new details for ' . $clean['name'] . '.');
            if ($note = booked_guests_note($type, $clean)) {
                flash('info', $note);
            }
            redirect($here);
        }
        $stmt = db()->prepare('INSERT INTO room_types (name, description, price_per_night, beds, feature, max_adults, max_children,
            amenities, sort_order, is_active, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array_merge($fields, [unique_room_slug($clean['name'])]));
        $newId = (int) db()->lastInsertId();
        place_room_type($newId, $clean['sort_order']);
        flash('success', $clean['name'] . ' has been added. Now upload its photos below, then add its rooms on the Rooms page.');
        redirect('admin/room-type.php?id=' . $newId . '#photos');

    case 'upload':
        if (!$type) break;
        $files = uploaded_files('photos');
        $stmt = db()->prepare('SELECT COUNT(*), COALESCE(MAX(sort_order), 0), COALESCE(MAX(is_main), 0) FROM room_type_photos WHERE room_type_id = ?');
        $stmt->execute([$type['id']]);
        [$count, $lastOrder, $hasMain] = $stmt->fetch(PDO::FETCH_NUM);
        if (!$files) {
            flash('error', 'Choose one or more photos to upload.');
        } elseif ($count + count($files) > MAX_TYPE_PHOTOS) {
            flash('error', 'A room type can have up to ' . MAX_TYPE_PHOTOS . ' photos. It has ' . $count . ' now, so you can add ' . max(0, MAX_TYPE_PHOTOS - $count) . ' more.');
        } else {
            $alt = trim($_POST['alt_text'] ?? '') ?: $type['name'];
            $insert = db()->prepare('INSERT INTO room_type_photos (room_type_id, file, alt_text, is_main, sort_order) VALUES (?, ?, ?, ?, ?)');
            $added = 0;
            foreach ($files as $file) {
                $result = save_photo_upload($file, 'rooms');
                if (is_string($result)) {
                    flash('error', $result);
                    continue;
                }
                $insert->execute([$type['id'], $result['file'], mb_substr($alt, 0, 150), $hasMain ? 0 : 1, ++$lastOrder]);
                $hasMain = 1; // the first photo of a room type becomes its main photo
                $added++;
            }
            if ($added) {
                flash('success', $added . ($added === 1 ? ' photo has' : ' photos have') . ' been added to ' . $type['name'] . '.');
            }
        }
        redirect($here . '#photos');

    case 'main':
        db()->prepare('UPDATE room_type_photos SET is_main = (id = ?) WHERE room_type_id = ?')->execute([$photo['id'], $type['id']]);
        flash('success', 'Main photo changed. It now leads the room page and the room card.');
        redirect($here . '#photos');

    case 'move':
        // Swap the photo with its neighbour in the grid (the main photo always stays first)
        $ids = array_column(array_filter(room_type_photos($type['id']), function ($p) { return !$p['is_main']; }), 'id');
        $at = array_search($photo['id'], $ids);
        $to = $at + (($_POST['direction'] ?? '') === 'left' ? -1 : 1);
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            save_photo_order($ids);
        }
        redirect($here . '#photo-' . $photo['id']);

    case 'replace':
        $result = save_photo_upload($_FILES['photo'] ?? [], 'rooms');
        if (is_string($result)) {
            flash('error', $result);
        } else {
            db()->prepare('UPDATE room_type_photos SET file = ? WHERE id = ?')->execute([$result['file'], $photo['id']]);
            delete_photo_file($photo['file']);
            flash('success', 'Photo replaced.');
        }
        redirect($here . '#photo-' . $photo['id']);

    case 'alt':
        $alt = trim($_POST['alt_text'] ?? '');
        db()->prepare('UPDATE room_type_photos SET alt_text = ? WHERE id = ?')->execute([mb_substr($alt ?: $type['name'], 0, 150), $photo['id']]);
        flash('success', 'Photo description saved.');
        redirect($here . '#photo-' . $photo['id']);

    case 'delete_photo':
        db()->prepare('DELETE FROM room_type_photos WHERE id = ?')->execute([$photo['id']]);
        delete_photo_file($photo['file']);
        // If the main photo was deleted, the next photo takes its place
        if ($photo['is_main'] && ($next = room_type_photos($type['id']))) {
            db()->prepare('UPDATE room_type_photos SET is_main = 1 WHERE id = ?')->execute([$next[0]['id']]);
        }
        flash('success', 'Photo deleted.');
        redirect($here . '#photos');

    case 'delete_type':
        if (!$type) break;
        $stmt = db()->prepare('SELECT COUNT(*) FROM rooms WHERE room_type_id = ?');
        $stmt->execute([$type['id']]);
        if ($stmt->fetchColumn() > 0) {
            flash('error', $type['name'] . ' still has rooms. Delete or move its rooms on the Rooms page first, or hide the room type instead.');
            redirect($here . '#delete');
        }
        foreach (room_type_photos($type['id']) as $p) {
            delete_photo_file($p['file']);
        }
        db()->prepare('DELETE FROM room_types WHERE id = ?')->execute([$type['id']]); // its photo rows go with it
        flash('success', $type['name'] . ' has been deleted.');
        redirect('admin/room-types.php');
}

/* ---------- Page ---------- */

$photos = $type ? room_type_photos($type['id']) : [];
$roomCount = 0;
if ($type) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM rooms WHERE room_type_id = ?');
    $stmt->execute([$type['id']]);
    $roomCount = (int) $stmt->fetchColumn();
}

// A form field with its label, help text and error (admin forms)
function admin_field($name, $label, $control, $errors, $help = '')
{
    $id = 'f_' . $name;
    $error = $errors[$name] ?? '';
    $describedBy = trim(($help ? $id . '_help ' : '') . ($error ? $id . '_error' : ''));
    $control = str_replace('{attrs}', 'id="' . $id . '" name="' . $name . '"'
        . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '')
        . ($error ? ' aria-invalid="true"' : ''), $control);
    echo '<div class="form-field' . ($error ? ' has-error' : '') . '">';
    echo '<label for="' . $id . '">' . e($label) . '</label>' . $control;
    if ($help) echo '<p class="field-help" id="' . $id . '_help">' . e($help) . '</p>';
    if ($error) echo '<p class="field-error" id="' . $id . '_error">' . e($error) . '</p>';
    echo '</div>';
}

$actions = $type && $type['is_active']
    ? '<a href="' . url('room.php?type=' . $type['slug']) . '" class="btn-small-outline" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View on site</a>'
    : '';
live_watch('room_types', 'room_type_photos', 'rooms', 'bookings'); // updates by itself when these change
admin_open($type ? $type['name'] : 'Add a room type', 'room-types',
    $type ? 'Edit the details guests see, and manage the photos' : 'Details first; photos can be added after saving', $actions);
?>

<nav class="crumbs crumbs-dark admin-crumbs" aria-label="You are here">
    <a href="<?= url('admin/room-types.php') ?>">Room types</a> <span aria-hidden="true">/</span> <?= e($type ? $type['name'] : 'New') ?>
</nav>

<?php if (!empty($stale)): ?>
    <?= edited_meanwhile_notice($type['name']) ?>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span>Please fix the <?= count($errors) === 1 ? 'field' : count($errors) . ' fields' ?> marked in red.</span></div>
<?php endif; ?>

<form method="post" class="admin-card" novalidate>
    <?= csrf_field() ?>
    <?= edit_stamp_field($type) ?>
    <input type="hidden" name="action" value="save">
    <h2>Details</h2>
    <div class="row g-3">
        <div class="col-md-8">
            <?php admin_field('name', 'Name', '<input type="text" {attrs} value="' . e($values['name']) . '" maxlength="100" required>', $errors); ?>
        </div>
        <div class="col-md-4">
            <?php admin_field('price', 'Price per night (PKR)', '<input type="text" inputmode="decimal" {attrs} value="' . e(price_input_value($values['price_per_night'])) . '" placeholder="Price on request">', $errors,
                'Leave empty for "Price on request". Bookings already made keep their old price.'); ?>
        </div>
        <div class="col-12">
            <?php admin_field('description', 'Description', '<textarea {attrs} rows="3" maxlength="2000" required>' . e($values['description']) . '</textarea>', $errors); ?>
        </div>
        <div class="col-md-4">
            <?php admin_field('beds', 'Beds', '<input type="text" {attrs} value="' . e($values['beds']) . '" maxlength="100" placeholder="1 King Size Bed" required>', $errors); ?>
        </div>
        <div class="col-6 col-md-2">
            <?php admin_field('max_adults', 'Max adults', '<input type="number" {attrs} value="' . e($values['max_adults']) . '" min="1" max="10" required>', $errors); ?>
        </div>
        <div class="col-6 col-md-2">
            <?php admin_field('max_children', 'Max children', '<input type="number" {attrs} value="' . e($values['max_children']) . '" min="0" max="10" required>', $errors); ?>
        </div>
        <div class="col-md-4">
            <?php admin_field('feature', 'Feature line (optional)', '<input type="text" {attrs} value="' . e($values['feature']) . '" maxlength="150" placeholder="Access to Executive Lounge">', $errors,
                'Shown in red on the room card.'); ?>
        </div>
        <div class="col-md-8">
            <?php admin_field('amenities', 'Amenities', '<textarea {attrs} rows="8">' . e($values['amenities']) . '</textarea>', $errors,
                'One per line. Shown as the "Room Details" list on the room page.'); ?>
        </div>
        <div class="col-md-4">
            <?php admin_field('sort_order', 'Position in lists', '<input type="number" {attrs} value="' . e($values['sort_order']) . '" min="1" max="999">', $errors,
                '1 = first on the Rooms and Home pages; the others move to make room. Or use ↑ ↓ on the Room types list.'); ?>
            <label class="admin-check">
                <input type="checkbox" name="is_active" value="1"<?= $values['is_active'] ? ' checked' : '' ?>>
                <span>Show on the website</span>
            </label>
        </div>
    </div>
    <div class="admin-form-foot">
        <button type="submit" class="btn-primary-red"><i class="fa-solid fa-check" aria-hidden="true"></i> <?= $type ? 'Save changes' : 'Add room type' ?></button>
        <a href="<?= url('admin/room-types.php') ?>" class="admin-link">Cancel</a>
    </div>
</form>

<?php if ($type): ?>
    <section class="admin-card" id="photos" aria-labelledby="photosHeading">
        <div class="admin-card-head">
            <h2 id="photosHeading">Photos <span class="tab-count"><?= count($photos) ?></span></h2>
            <p>The <strong>main photo</strong> is the large one at the top of the room page and on the room card. The others follow in this order.</p>
        </div>

        <?php if ($photos): ?>
            <ol class="photo-manager">
                <?php
                $others = array_values(array_filter($photos, function ($p) { return !$p['is_main']; }));
                foreach ($photos as $p):
                    $pos = array_search($p, $others, true); // place among the non-main photos
                ?>
                    <li class="photo-tile<?= $p['is_main'] ? ' is-main' : '' ?>" id="photo-<?= $p['id'] ?>">
                        <div class="photo-tile-img">
                            <?php photo($p['file'], $p['alt_text']); ?>
                            <?php if ($p['is_main']): ?>
                                <span class="photo-main-badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Main photo</span>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="photo-alt">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="alt">
                            <input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                            <label class="visually-hidden" for="alt<?= $p['id'] ?>">Description of this photo</label>
                            <input type="text" id="alt<?= $p['id'] ?>" name="alt_text" value="<?= e($p['alt_text']) ?>" maxlength="150" placeholder="What the photo shows">
                            <button type="submit" aria-label="Save description" title="Save description"><i class="fa-solid fa-check" aria-hidden="true"></i></button>
                        </form>
                        <div class="photo-tools">
                            <?php if (!$p['is_main']): ?>
                                <form method="post"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="main"><input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                                    <button type="submit" title="Make this the main photo"><i class="fa-regular fa-star" aria-hidden="true"></i> Main</button>
                                </form>
                                <form method="post"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="left"><input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                                    <button type="submit" aria-label="Move earlier" title="Move earlier"<?= $pos === 0 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                                </form>
                                <form method="post"><?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move"><input type="hidden" name="direction" value="right"><input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                                    <button type="submit" aria-label="Move later" title="Move later"<?= $pos === count($others) - 1 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data" class="photo-replace"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="replace"><input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                                <label title="Replace with another photo"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="visually-hidden" data-auto-submit>
                                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Replace</label>
                                <button type="submit" class="no-js-only">Upload</button>
                            </form>
                            <form method="post" data-confirm="Delete this photo? This can't be undone."><?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_photo"><input type="hidden" name="photo_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="tool-danger" aria-label="Delete photo" title="Delete photo"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if (count($photos) < MAX_TYPE_PHOTOS): ?>
            <form method="post" enctype="multipart/form-data" class="upload-box">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <label class="drop-zone" data-drop-zone>
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                    <strong>Drop photos here or click to choose</strong>
                    <span>JPG, PNG or WebP, up to 10 MB each. Big photos are made smaller automatically.<?= $photos ? '' : ' The first photo becomes the main photo.' ?></span>
                    <span class="drop-zone-files" data-file-list aria-live="polite"></span>
                </label>
                <div class="upload-box-foot">
                    <div class="form-field">
                        <label for="f_alt_text">Description for these photos (optional)</label>
                        <input type="text" id="f_alt_text" name="alt_text" maxlength="150" placeholder="<?= e($type['name']) ?>">
                    </div>
                    <button type="submit" class="btn-primary-red"><i class="fa-solid fa-upload" aria-hidden="true"></i> Upload photos</button>
                </div>
            </form>
        <?php else: ?>
            <p class="admin-muted">This room type has the most photos allowed (<?= MAX_TYPE_PHOTOS ?>). Delete one to add another.</p>
        <?php endif; ?>
    </section>

    <section class="admin-card admin-danger" id="delete" aria-labelledby="deleteHeading">
        <h2 id="deleteHeading">Delete this room type</h2>
        <?php if ($roomCount > 0): ?>
            <p>It has <?= $roomCount ?> <?= $roomCount === 1 ? 'room' : 'rooms' ?>, so it can't be deleted. To stop guests booking it, untick
                <strong>Show on the website</strong> above. To delete it, first delete or move its rooms on the
                <a href="<?= url('admin/rooms.php?type=' . $type['id']) ?>">Rooms page</a>.</p>
        <?php else: ?>
            <p>This removes <?= e($type['name']) ?> and its <?= count($photos) ?> <?= count($photos) === 1 ? 'photo' : 'photos' ?> for good.</p>
            <form method="post" data-confirm="Delete <?= e($type['name']) ?> and all its photos? This can't be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_type">
                <button type="submit" class="btn-small-outline"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete room type</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php admin_close();
