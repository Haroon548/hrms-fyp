<?php
/*
 * Admin: the menu (FR-MENU-01, FR-MENU-02, FR-ADM-06).
 * Categories: add, rename, move up/down, delete (only when empty).
 * Dishes: listed by category with price and photo; "Sold out" hides a dish from the
 * menu straight away (and it can't be ordered); edit and delete on menu-item.php.
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';
require_once '../includes/admin-layout.php';
require_admin();

function find_category($id)
{
    $stmt = db()->prepare('SELECT * FROM menu_categories WHERE id = ?');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

$errors = [];
switch (admin_action()) {
    case 'add_category':
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['category'] = 'Enter a category name of 2 to 100 characters, e.g. "Soups".';
            break;
        }
        $last = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM menu_categories')->fetchColumn();
        db()->prepare('INSERT INTO menu_categories (name, sort_order) VALUES (?, ?)')->execute([$name, $last + 1]);
        flash('success', 'Category "' . $name . '" added. Add its dishes below.');
        redirect('admin/menu.php');

    case 'rename_category':
        $category = find_category($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($category && mb_strlen($name) >= 2 && mb_strlen($name) <= 100) {
            db()->prepare('UPDATE menu_categories SET name = ? WHERE id = ?')->execute([$name, $category['id']]);
            flash('success', 'Renamed to "' . $name . '".');
        } else {
            flash('error', 'Enter a category name of 2 to 100 characters.');
        }
        redirect('admin/menu.php');

    case 'move_category':
        // Swap places with the category above or below
        $ids = db()->query('SELECT id FROM menu_categories ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);
        $at = array_search((int) ($_POST['id'] ?? 0), array_map('intval', $ids), true);
        $to = $at + (($_POST['direction'] ?? '') === 'up' ? -1 : 1);
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            $stmt = db()->prepare('UPDATE menu_categories SET sort_order = ? WHERE id = ?');
            foreach ($ids as $i => $id) {
                $stmt->execute([$i + 1, $id]);
            }
        }
        redirect('admin/menu.php');

    case 'delete_category':
        $category = find_category($_POST['id'] ?? 0);
        if ($category) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM menu_items WHERE category_id = ?');
            $stmt->execute([$category['id']]);
            if ($stmt->fetchColumn() > 0) {
                flash('error', '"' . $category['name'] . '" still has dishes. Move or delete them first.');
            } else {
                db()->prepare('DELETE FROM menu_categories WHERE id = ?')->execute([$category['id']]);
                flash('success', 'Category "' . $category['name'] . '" deleted.');
            }
        }
        redirect('admin/menu.php');

    case 'toggle_item':
        $stmt = db()->prepare('UPDATE menu_items SET is_available = 1 - is_available WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        $stmt = db()->prepare('SELECT name, is_available FROM menu_items WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        if ($item = $stmt->fetch()) {
            flash('success', $item['name'] . ($item['is_available'] ? ' is back on the menu.' : ' is marked sold out and hidden from the menu.'));
        }
        redirect('admin/menu.php#item-' . (int) $_POST['id']);
}

$categories = menu_by_category(true);
$itemCount = array_sum(array_map(function ($c) { return count($c['items']); }, $categories));

live_watch('menu_items', 'menu_categories'); // updates by itself when these change
admin_open('Menu', 'menu', $itemCount . ' dishes in ' . count($categories) . ' categories. Changes show on the Dining page straight away.',
    '<a href="' . url('admin/menu-item.php') . '" class="btn-small-red"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add dish</a>'
    . '<a href="' . url('dining.php#menu') . '" class="btn-small-outline" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View menu</a>');
?>

<section class="admin-card" aria-labelledby="catHeading">
    <h2 id="catHeading">Categories</h2>
    <ul class="category-list">
        <?php foreach ($categories as $i => $category): ?>
            <li>
                <form method="post" class="category-rename">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="rename_category">
                    <input type="hidden" name="id" value="<?= $category['id'] ?>">
                    <label class="visually-hidden" for="cat<?= $category['id'] ?>">Name of category <?= $i + 1 ?></label>
                    <input type="text" id="cat<?= $category['id'] ?>" name="name" value="<?= e($category['name']) ?>" maxlength="100" required>
                    <button type="submit" class="btn-icon" aria-label="Save name" title="Save name"><i class="fa-solid fa-check" aria-hidden="true"></i></button>
                </form>
                <span class="admin-muted category-count"><?= count($category['items']) ?> <?= count($category['items']) === 1 ? 'dish' : 'dishes' ?></span>
                <div class="table-actions">
                    <?php foreach (['up' => 'fa-arrow-up', 'down' => 'fa-arrow-down'] as $dir => $icon): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="move_category"><input type="hidden" name="id" value="<?= $category['id'] ?>"><input type="hidden" name="direction" value="<?= $dir ?>">
                            <button type="submit" class="btn-icon" aria-label="Move <?= e($category['name']) ?> <?= $dir ?>" title="Move <?= $dir ?>"<?= ($dir === 'up' && $i === 0) || ($dir === 'down' && $i === count($categories) - 1) ? ' disabled' : '' ?>><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></button>
                        </form>
                    <?php endforeach; ?>
                    <?php if (!$category['items']): ?>
                        <form method="post" data-confirm="Delete the category &quot;<?= e($category['name']) ?>&quot;?"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= $category['id'] ?>">
                            <button type="submit" class="btn-icon tool-danger" aria-label="Delete <?= e($category['name']) ?>" title="Delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" class="category-add" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <div class="form-field<?= isset($errors['category']) ? ' has-error' : '' ?>">
            <label for="newCategory">New category</label>
            <input type="text" id="newCategory" name="name" value="<?= old('name') ?>" maxlength="100" placeholder="e.g. Soups"<?= isset($errors['category']) ? ' aria-invalid="true" aria-describedby="newCategoryError"' : '' ?>>
            <?php if (isset($errors['category'])): ?><p class="field-error" id="newCategoryError"><?= e($errors['category']) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="btn-primary-red"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add</button>
    </form>
</section>

<?php foreach ($categories as $category): ?>
    <section class="admin-card" aria-labelledby="items<?= $category['id'] ?>">
        <div class="confirm-details-head">
            <h2 id="items<?= $category['id'] ?>"><?= e($category['name']) ?></h2>
            <a href="<?= url('admin/menu-item.php?category=' . $category['id']) ?>" class="admin-link"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add a dish here</a>
        </div>
        <?php if (!$category['items']): ?>
            <p class="admin-muted mb-0">No dishes yet. Empty categories don't show on the menu.</p>
        <?php else: ?>
            <ul class="dish-admin-list">
                <?php foreach ($category['items'] as $item): ?>
                    <li id="item-<?= $item['id'] ?>" class="<?= $item['is_available'] ? '' : 'is-off' ?>">
                        <span class="dish-admin-photo"><?php if ($item['image']): photo($item['image'], $item['name']); else: ?><i class="fa-solid fa-utensils" aria-hidden="true"></i><?php endif; ?></span>
                        <div class="dish-admin-text">
                            <strong><?= e($item['name']) ?></strong>
                            <small><?= e($item['description']) ?></small>
                        </div>
                        <span class="dish-admin-price">PKR <?= money($item['price']) ?></span>
                        <?= status_badge($item['is_available'] ? 'on_menu' : 'sold_out') ?>
                        <div class="table-actions">
                            <form method="post"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_item"><input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-small-outline text-nowrap"><?= $item['is_available'] ? 'Sold out' : 'Back on menu' ?></button>
                            </form>
                            <a href="<?= url('admin/menu-item.php?id=' . $item['id']) ?>" class="btn-icon" aria-label="Edit <?= e($item['name']) ?>" title="Edit"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php admin_close();
