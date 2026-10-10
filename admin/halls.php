<?php
/*
 * Admin: event halls on the Meeting & Events page (FR-WEB-07, FR-ADM-06).
 * Name, a line of description, area, ceiling height, length and width, and the order they
 * are shown in. The page's floor plans are drawn from these sizes, and update live.
 */
require_once '../includes/init.php';
require_once '../includes/admin-layout.php';
require_admin();

// Checks a hall form. Returns [values, error message or ''].
function check_hall(array $in)
{
    $v = [
        'name'        => trim($in['name'] ?? ''),
        'description' => trim($in['description'] ?? ''),
        'length'      => trim($in['length'] ?? ''),
        'width'       => trim($in['width'] ?? ''),
        'height'      => trim($in['height'] ?? ''),
        'area'        => trim($in['area'] ?? ''),
        'sort_order'  => (int) ($in['sort_order'] ?? 0),
    ];
    $number = '/^\d{1,4}(\.\d)?$/';
    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 100) {
        return [$v, 'Enter the hall\'s name.'];
    }
    if (mb_strlen($v['description']) > 255) {
        return [$v, 'Keep the description under 255 characters.'];
    }
    foreach (['length' => 'length', 'width' => 'width', 'height' => 'ceiling height'] as $field => $label) {
        if (!preg_match($number, $v[$field]) || (float) $v[$field] <= 0 || (float) $v[$field] > 500) {
            return [$v, 'Enter the ' . $label . ' in metres, e.g. 12.4.'];
        }
    }
    if ($v['area'] === '') {
        $v['area'] = (string) round($v['length'] * $v['width'], 1); // worked out when left empty
    } elseif (!preg_match($number, $v['area']) || (float) $v['area'] <= 0) {
        return [$v, 'Enter the area in square metres, or leave it empty to work it out.'];
    }
    return [$v, ''];
}

switch (admin_action()) {
    case 'save':
        [$v, $problem] = check_hall($_POST);
        $id = (int) ($_POST['id'] ?? 0);
        if ($problem) {
            flash('error', $problem);
        } elseif ($id) {
            db()->prepare('UPDATE halls SET name = ?, description = ?, area = ?, height = ?, length = ?, width = ?, sort_order = ? WHERE id = ?')
                ->execute([$v['name'], $v['description'], $v['area'], $v['height'], $v['length'], $v['width'], $v['sort_order'], $id]);
            flash('success', $v['name'] . ' saved. The Meeting & Events page shows it now.');
        } else {
            db()->prepare('INSERT INTO halls (name, description, area, height, length, width, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$v['name'], $v['description'], $v['area'], $v['height'], $v['length'], $v['width'], $v['sort_order']]);
            flash('success', $v['name'] . ' added to the Meeting & Events page.');
        }
        redirect('admin/halls.php');

    case 'delete':
        $stmt = db()->prepare('SELECT name FROM halls WHERE id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        if ($name = $stmt->fetchColumn()) {
            db()->prepare('DELETE FROM halls WHERE id = ?')->execute([(int) $_POST['id']]);
            flash('success', $name . ' removed from the Meeting & Events page. Enquiries that named it keep the name.');
        }
        redirect('admin/halls.php');
}

$halls = db()->query('SELECT * FROM halls ORDER BY sort_order, id')->fetchAll();

// The fields of one hall form (empty for "Add a hall")
function hall_fields($h, $prefix)
{
    $value = function ($field) use ($h) {
        $v = $h[$field] ?? '';
        return e(is_numeric($v) && $field !== 'sort_order' ? rtrim(rtrim((string) $v, '0'), '.') : $v);
    };
    ?>
    <div class="row g-3">
        <div class="col-md-4"><div class="form-field"><label for="<?= $prefix ?>n">Name</label><input type="text" id="<?= $prefix ?>n" name="name" value="<?= $value('name') ?>" maxlength="100" required></div></div>
        <div class="col-md-8"><div class="form-field"><label for="<?= $prefix ?>d">Description (one line)</label><input type="text" id="<?= $prefix ?>d" name="description" value="<?= $value('description') ?>" maxlength="255"></div></div>
        <div class="col-6 col-md-2"><div class="form-field"><label for="<?= $prefix ?>l">Length (m)</label><input type="text" inputmode="decimal" id="<?= $prefix ?>l" name="length" value="<?= $value('length') ?>" required></div></div>
        <div class="col-6 col-md-2"><div class="form-field"><label for="<?= $prefix ?>w">Width (m)</label><input type="text" inputmode="decimal" id="<?= $prefix ?>w" name="width" value="<?= $value('width') ?>" required></div></div>
        <div class="col-6 col-md-2"><div class="form-field"><label for="<?= $prefix ?>h">Ceiling (m)</label><input type="text" inputmode="decimal" id="<?= $prefix ?>h" name="height" value="<?= $value('height') ?>" required></div></div>
        <div class="col-6 col-md-3"><div class="form-field"><label for="<?= $prefix ?>a">Area (m²)</label><input type="text" inputmode="decimal" id="<?= $prefix ?>a" name="area" value="<?= $value('area') ?>" aria-describedby="<?= $prefix ?>ah"><p class="field-help" id="<?= $prefix ?>ah">Empty: length × width</p></div></div>
        <div class="col-6 col-md-3"><div class="form-field"><label for="<?= $prefix ?>o">Position</label><input type="number" id="<?= $prefix ?>o" name="sort_order" value="<?= (int) ($h['sort_order'] ?? count($GLOBALS['halls']) + 1) ?>" min="0" max="999"></div></div>
    </div>
    <?php
}

live_watch('halls');
admin_open('Event halls', 'halls', 'The halls on the Meeting & Events page, with their sizes. Changes show on the page straight away.',
    '<a href="' . url('events.php#halls') . '" class="btn-small-outline" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View page</a>');
?>
<?php foreach ($halls as $h): ?>
    <section class="admin-card" aria-labelledby="hall<?= $h['id'] ?>">
        <div class="confirm-details-head">
            <h2 id="hall<?= $h['id'] ?>"><?= e($h['name']) ?></h2>
            <form method="post" data-confirm="Remove <?= e($h['name']) ?> from the Meeting & Events page?"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $h['id'] ?>">
                <button type="submit" class="btn-icon tool-danger" aria-label="Remove <?= e($h['name']) ?>" title="Remove"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
            </form>
        </div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $h['id'] ?>">
            <?php hall_fields($h, 'h' . $h['id']); ?>
            <button type="submit" class="btn-small-red"><i class="fa-solid fa-check" aria-hidden="true"></i> Save</button>
        </form>
    </section>
<?php endforeach; ?>

<section class="admin-card admin-card-highlight" aria-labelledby="addHall">
    <h2 id="addHall"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add a hall</h2>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <?php hall_fields(null, 'new'); ?>
        <button type="submit" class="btn-primary-red">Add hall</button>
    </form>
</section>
<?php admin_close();
