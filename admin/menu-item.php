<?php
/*
 * Admin: add or edit one dish (FR-MENU-02): name, category, price, description,
 * photo (optional), "on the menu" and position. Deleting a dish keeps old orders intact
 * (each order saved the dish's name and price when it was placed).
 *   menu-item.php            → add a dish
 *   menu-item.php?id=7       → edit dish 7
 *   menu-item.php?category=2 → add a dish to category 2
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';
require_once '../includes/uploads.php';
require_once '../includes/admin-layout.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$item = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM menu_items WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch() ?: null;
    if (!$item) {
        flash('error', 'That dish no longer exists.');
        redirect('admin/menu.php');
    }
}
$categories = db()->query('SELECT id, name FROM menu_categories ORDER BY sort_order, name')->fetchAll();
if (!$categories) {
    flash('info', 'Add a menu category first, e.g. "Pakistani", then add its dishes.');
    redirect('admin/menu.php');
}
$values = $item ?: ['name' => '', 'category_id' => (int) ($_GET['category'] ?? $categories[0]['id']), 'price' => '', 'description' => '', 'image' => '', 'is_available' => 1, 'sort_order' => 0];
$errors = [];

switch (admin_action()) {
    case 'save':
        $values = [
            'name'         => trim($_POST['name'] ?? ''),
            'category_id'  => (int) ($_POST['category_id'] ?? 0),
            'price'        => trim(str_replace([',', ' '], '', $_POST['price'] ?? '')),
            'description'  => trim($_POST['description'] ?? ''),
            'image'        => $item['image'] ?? '',
            'is_available' => !empty($_POST['is_available']) ? 1 : 0,
            'sort_order'   => (int) ($_POST['sort_order'] ?? 0),
        ];
        if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 150) {
            $errors['name'] = 'Enter the dish name (2 to 150 characters).';
        }
        if (!in_array($values['category_id'], array_map('intval', array_column($categories, 'id')), true)) {
            $errors['category_id'] = 'Choose a category.';
        }
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $values['price']) || (float) $values['price'] <= 0) {
            $errors['price'] = 'Enter the price in rupees, e.g. 1450.';
        }
        if (mb_strlen($values['description']) > 255) {
            $errors['description'] = 'Keep the description under 255 characters.';
        }
        $stale = edited_meanwhile($item);
        if ($errors || $stale) break;
        // Optional photo: a new one replaces the old one; "Remove photo" clears it
        $newPhoto = null;
        if (!$errors && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $result = save_photo_upload($_FILES['photo'], 'menu');
            if (is_string($result)) {
                $errors['photo'] = $result;
            } else {
                $newPhoto = $result['file'];
            }
        }
        if ($errors) break;

        $oldPhoto = $values['image'];
        if ($newPhoto) {
            $values['image'] = $newPhoto;
        } elseif (!empty($_POST['remove_photo'])) {
            $values['image'] = '';
        }
        $fields = [$values['category_id'], $values['name'], $values['description'], $values['price'], $values['image'], $values['is_available'], $values['sort_order']];
        if ($item) {
            db()->prepare('UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?, image = ?, is_available = ?, sort_order = ? WHERE id = ?')
                ->execute(array_merge($fields, [$item['id']]));
            if ($oldPhoto !== $values['image']) {
                delete_photo_file($oldPhoto);
            }
            flash('success', $values['name'] . ' saved. The menu shows the change now.');
        } else {
            db()->prepare('INSERT INTO menu_items (category_id, name, description, price, image, is_available, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute($fields);
            flash('success', $values['name'] . ' added to the menu.');
        }
        redirect('admin/menu.php#item-' . ($item['id'] ?? db()->lastInsertId()));

    case 'delete':
        if ($item) {
            db()->prepare('DELETE FROM menu_items WHERE id = ?')->execute([$item['id']]);
            delete_photo_file($item['image']);
            flash('success', $item['name'] . ' deleted from the menu. Past orders still show it.');
        }
        redirect('admin/menu.php');
}

function item_field($name, $label, $control, $errors, $help = '')
{
    $id = 'f_' . $name;
    $error = $errors[$name] ?? '';
    $describedBy = trim(($help ? $id . '_help ' : '') . ($error ? $id . '_error' : ''));
    echo '<div class="form-field' . ($error ? ' has-error' : '') . '"><label for="' . $id . '">' . e($label) . '</label>'
        . str_replace('{attrs}', 'id="' . $id . '" name="' . $name . '"' . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '') . ($error ? ' aria-invalid="true"' : ''), $control)
        . ($help ? '<p class="field-help" id="' . $id . '_help">' . e($help) . '</p>' : '')
        . ($error ? '<p class="field-error" id="' . $id . '_error">' . e($error) . '</p>' : '') . '</div>';
}

$options = '';
foreach ($categories as $c) {
    $options .= '<option value="' . $c['id'] . '"' . ((int) $values['category_id'] === (int) $c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>';
}
$price = (string) $values['price'];
if (strpos($price, '.') !== false) {
    $price = rtrim(rtrim($price, '0'), '.');
}

live_watch('menu_items', 'menu_categories'); // updates by itself when these change
admin_open($item ? $item['name'] : 'Add a dish', 'menu', $item ? 'Edit the dish guests see on the menu' : 'It appears on the Dining page as soon as it is saved');
?>
<nav class="crumbs crumbs-dark admin-crumbs" aria-label="You are here">
    <a href="<?= url('admin/menu.php') ?>">Menu</a> <span aria-hidden="true">/</span> <?= e($item ? $item['name'] : 'New dish') ?>
</nav>

<?php if (!empty($stale)): ?>
    <?= edited_meanwhile_notice($item['name']) ?>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>Please fix the fields marked in red.</span></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="admin-card" novalidate>
    <?= csrf_field() ?>
    <?= edit_stamp_field($item) ?>
    <input type="hidden" name="action" value="save">
    <div class="row g-3">
        <div class="col-md-8"><?php item_field('name', 'Dish name', '<input type="text" {attrs} value="' . e($values['name']) . '" maxlength="150" required>', $errors); ?></div>
        <div class="col-md-4"><?php item_field('price', 'Price (PKR)', '<input type="text" inputmode="decimal" {attrs} value="' . e($price) . '" required>', $errors, 'Orders already placed keep their price.'); ?></div>
        <div class="col-md-6"><?php item_field('category_id', 'Category', '<select {attrs}>' . $options . '</select>', $errors); ?></div>
        <div class="col-md-6"><?php item_field('sort_order', 'Position in its category', '<input type="number" {attrs} value="' . (int) $values['sort_order'] . '" min="0" max="999">', $errors, 'Lower numbers come first.'); ?></div>
        <div class="col-12"><?php item_field('description', 'Short description (optional)', '<input type="text" {attrs} value="' . e($values['description']) . '" maxlength="255" placeholder="e.g. Half, cooked in a wok with tomatoes and green chillies">', $errors); ?></div>
        <div class="col-md-8">
            <?php item_field('photo', 'Photo (optional)', '<input type="file" {attrs} accept="image/jpeg,image/png,image/webp" class="file-input">', $errors, 'JPG, PNG or WebP, up to 10 MB. Shown as a small round photo next to the dish.'); ?>
            <?php if ($values['image']): ?>
                <div class="current-photo">
                    <?php photo($values['image'], $values['name']); ?>
                    <label class="admin-check"><input type="checkbox" name="remove_photo" value="1"> <span>Remove this photo</span></label>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="admin-check mt-4">
                <input type="checkbox" name="is_available" value="1"<?= $values['is_available'] ? ' checked' : '' ?>>
                <span>On the menu (untick for "sold out")</span>
            </label>
        </div>
    </div>
    <div class="admin-form-foot">
        <button type="submit" class="btn-primary-red"><i class="fa-solid fa-check" aria-hidden="true"></i> <?= $item ? 'Save dish' : 'Add dish' ?></button>
        <a href="<?= url('admin/menu.php') ?>" class="admin-link">Cancel</a>
    </div>
</form>

<?php if ($item): ?>
    <section class="admin-card admin-danger">
        <h2>Delete this dish</h2>
        <p>It disappears from the menu. Orders already placed keep its name and price. To hide it for a while, untick "On the menu" instead.</p>
        <form method="post" data-confirm="Delete <?= e($item['name']) ?> from the menu?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-small-outline"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete dish</button>
        </form>
    </section>
<?php endif; ?>
<?php admin_close();
