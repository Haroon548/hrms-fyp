<?php
/*
 * Admin: questions and answers on the Contact page (FR-WEB-20, FR-ADM-06).
 * Edit the wording, hide a question for a while, change the order, add or delete.
 * The Contact page shows the changes straight away (live updates).
 */
require_once '../includes/init.php';
require_once '../includes/admin-layout.php';
require_admin();

switch (admin_action()) {
    case 'save':
        $id = (int) ($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $order = (int) ($_POST['sort_order'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        if (mb_strlen($question) < 5 || mb_strlen($question) > 200 || mb_strlen($answer) < 5 || mb_strlen($answer) > 2000) {
            flash('error', 'Write a question (up to 200 characters) and its answer (up to 2,000).');
        } elseif ($id) {
            db()->prepare('UPDATE faqs SET question = ?, answer = ?, sort_order = ?, is_active = ? WHERE id = ?')->execute([$question, $answer, $order, $active, $id]);
            flash('success', 'Question saved.' . ($active ? '' : ' It is hidden from the Contact page.'));
        } else {
            db()->prepare('INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, ?)')->execute([$question, $answer, $order, $active]);
            flash('success', 'Question added to the Contact page.');
        }
        redirect('admin/faqs.php');

    case 'delete':
        db()->prepare('DELETE FROM faqs WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Question deleted.');
        redirect('admin/faqs.php');
}

$faqs = db()->query('SELECT * FROM faqs ORDER BY sort_order, id')->fetchAll();

function faq_fields($f, $prefix, $nextOrder)
{
    ?>
    <div class="row g-3">
        <div class="col-md-9"><div class="form-field"><label for="<?= $prefix ?>q">Question</label><input type="text" id="<?= $prefix ?>q" name="question" value="<?= e($f['question'] ?? '') ?>" maxlength="200" required></div></div>
        <div class="col-md-3"><div class="form-field"><label for="<?= $prefix ?>o">Position</label><input type="number" id="<?= $prefix ?>o" name="sort_order" value="<?= (int) ($f['sort_order'] ?? $nextOrder) ?>" min="0" max="999"></div></div>
        <div class="col-12"><div class="form-field"><label for="<?= $prefix ?>a">Answer</label><textarea id="<?= $prefix ?>a" name="answer" rows="3" maxlength="2000" required><?= e($f['answer'] ?? '') ?></textarea></div></div>
    </div>
    <label class="admin-check mb-3"><input type="checkbox" name="is_active" value="1"<?= !$f || $f['is_active'] ? ' checked' : '' ?>> <span>Show on the Contact page</span></label>
    <?php
}

live_watch('faqs');
admin_open('FAQ', 'faqs', 'The questions and answers on the Contact page. Keep answers short and in plain words.',
    '<a href="' . url('contact.php#faqHeading') . '" class="btn-small-outline" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View page</a>');
?>
<?php foreach ($faqs as $f): ?>
    <section class="admin-card<?= $f['is_active'] ? '' : ' is-off' ?>" aria-label="<?= e($f['question']) ?>">
        <div class="confirm-details-head">
            <h2><?= e($f['question']) ?></h2>
            <div class="d-flex align-items-center gap-2">
                <?= status_badge($f['is_active'] ? 'shown' : 'hidden') ?>
                <form method="post" data-confirm="Delete this question?"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="submit" class="btn-icon tool-danger" aria-label="Delete question" title="Delete"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                </form>
            </div>
        </div>
        <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $f['id'] ?>">
            <?php faq_fields($f, 'f' . $f['id'], 0); ?>
            <button type="submit" class="btn-small-red"><i class="fa-solid fa-check" aria-hidden="true"></i> Save</button>
        </form>
    </section>
<?php endforeach; ?>

<section class="admin-card admin-card-highlight" aria-labelledby="addFaq">
    <h2 id="addFaq"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add a question</h2>
    <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <?php faq_fields(null, 'new', count($faqs) + 1); ?>
        <button type="submit" class="btn-primary-red">Add question</button>
    </form>
</section>
<?php admin_close();
