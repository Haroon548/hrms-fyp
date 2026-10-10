<?php
/*
 * Admin: list of room types (FR-ROOM-01, FR-ADM-06). Each row shows the main photo, price,
 * guests and how many rooms the type has, plus warnings when something guests need is missing.
 * Adding and editing happens on room-type.php.
 */
require_once '../includes/init.php';
require_once '../includes/rooms.php';
require_once '../includes/admin-layout.php';
require_admin();

$action = admin_action();

// Move a room type one place up or down in the list (the order on the Rooms and Home pages)
if ($action === 'move') {
    $ids = db()->query('SELECT id FROM room_types ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);
    $at = array_search((int) ($_POST['id'] ?? 0), array_map('intval', $ids), true);
    if ($at !== false) {
        place_room_type($ids[$at], $at + 1 + (($_POST['direction'] ?? '') === 'up' ? -1 : 1));
    }
    redirect('admin/room-types.php#type-' . (int) ($_POST['id'] ?? 0));
}

// Show or hide a room type on the website, straight from the list
if ($action === 'toggle') {
    $stmt = db()->prepare('UPDATE room_types SET is_active = 1 - is_active WHERE id = ?');
    $stmt->execute([(int) $_POST['id']]);
    $stmt = db()->prepare('SELECT name, is_active FROM room_types WHERE id = ?');
    $stmt->execute([(int) $_POST['id']]);
    if ($type = $stmt->fetch()) {
        flash('success', $type['name'] . ($type['is_active'] ? ' is now shown on the website.' : ' is now hidden from the website.'));
    }
    redirect('admin/room-types.php');
}

$types = db()->query(
    "SELECT rt.*, p.file AS photo,
            (SELECT COUNT(*) FROM room_type_photos x WHERE x.room_type_id = rt.id) AS photo_count,
            (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id) AS room_count,
            (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id AND r.status = 'available') AS rooms_open
     FROM room_types rt
     LEFT JOIN room_type_photos p ON p.room_type_id = rt.id AND p.is_main = 1
     ORDER BY rt.sort_order, rt.name"
)->fetchAll();

live_watch('room_types', 'room_type_photos', 'rooms', 'bookings'); // updates by itself when these change
admin_open('Room types', 'room-types', 'Prices, guests, amenities and photos for each kind of room',
    '<a href="' . url('admin/room-type.php') . '" class="btn-small-red"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add room type</a>');
?>

<?php if (!$types): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-bed" aria-hidden="true"></i></span>
        <h2>No room types yet</h2>
        <p>Add the first kind of room, e.g. "Deluxe Queen Room", with its price and photos.</p>
        <a href="<?= url('admin/room-type.php') ?>" class="btn-primary-red">Add room type</a>
    </div>
<?php else: ?>
    <div class="admin-list">
        <?php foreach ($types as $i => $type):
            // Things that stop guests booking this room type
            $warnings = [];
            if ($type['price_per_night'] === null) $warnings[] = 'No price yet: guests see "Price on request" and can\'t book';
            if ((int) $type['photo_count'] === 0) $warnings[] = 'No photos';
            if ((int) $type['room_count'] === 0) $warnings[] = 'No rooms of this type, so it can\'t be booked';
            elseif ((int) $type['rooms_open'] === 0) $warnings[] = 'Every room of this type is under maintenance';
        ?>
            <article id="type-<?= (int) $type['id'] ?>" class="admin-row<?= $type['is_active'] ? '' : ' is-hidden' ?>">
                <a href="<?= url('admin/room-type.php?id=' . $type['id']) ?>" class="admin-row-photo" tabindex="-1" aria-hidden="true">
                    <?php photo($type['photo'] ?? '', $type['name']); ?>
                </a>
                <div class="admin-row-main">
                    <h2><a href="<?= url('admin/room-type.php?id=' . $type['id']) ?>"><?= e($type['name']) ?></a></h2>
                    <ul class="admin-facts">
                        <li><i class="fa-solid fa-tag" aria-hidden="true"></i>
                            <?= $type['price_per_night'] !== null ? 'PKR ' . money($type['price_per_night']) . ' / night' : 'Price on request' ?></li>
                        <li><i class="fa-solid fa-user-group" aria-hidden="true"></i> Up to <?= e(occupancy_text($type['max_adults'], $type['max_children'])) ?></li>
                        <li><i class="fa-solid fa-bed" aria-hidden="true"></i> <?= e($type['beds']) ?></li>
                        <li><i class="fa-regular fa-images" aria-hidden="true"></i> <?= (int) $type['photo_count'] ?> <?= $type['photo_count'] == 1 ? 'photo' : 'photos' ?></li>
                        <li><i class="fa-solid fa-door-open" aria-hidden="true"></i>
                            <a href="<?= url('admin/rooms.php?type=' . $type['id']) ?>"><?= (int) $type['room_count'] ?> <?= $type['room_count'] == 1 ? 'room' : 'rooms' ?></a></li>
                    </ul>
                    <?php foreach ($warnings as $warning): ?>
                        <p class="admin-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <?= e($warning) ?></p>
                    <?php endforeach; ?>
                </div>
                <div class="admin-row-side">
                    <?= status_badge($type['is_active'] ? 'shown' : 'hidden') ?>
                    <div class="admin-row-buttons">
                        <?php foreach (['up' => ['fa-arrow-up', 'Move up', $i > 0], 'down' => ['fa-arrow-down', 'Move down', $i < count($types) - 1]] as $dir => [$icon, $label, $can]): ?>
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $type['id'] ?>"><input type="hidden" name="direction" value="<?= $dir ?>">
                                <button type="submit" class="btn-icon" aria-label="<?= e($label . ': ' . $type['name']) ?>" title="<?= $label ?>"<?= $can ? '' : ' disabled' ?>><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></button>
                            </form>
                        <?php endforeach; ?>
                        <a href="<?= url('admin/room-type.php?id=' . $type['id']) ?>" class="btn-small-red"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $type['id'] ?>">
                            <button type="submit" class="btn-small-outline">
                                <?= $type['is_active'] ? '<i class="fa-regular fa-eye-slash" aria-hidden="true"></i> Hide' : '<i class="fa-regular fa-eye" aria-hidden="true"></i> Show' ?>
                            </button>
                        </form>
                        <?php if ($type['is_active']): ?>
                            <a href="<?= url('room.php?type=' . $type['slug']) ?>" class="admin-link" target="_blank" rel="noopener">View on site <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php admin_close();
