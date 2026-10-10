<?php
/*
 * The availability search bar (Home and Rooms pages). Sends the dates and guests
 * to rooms.php, which shows what's free. Before including, a page may set
 * $search = ['check_in' => ..., 'check_out' => ..., 'adults' => ..., 'children' => ...]
 * to fill it in, and $search_button to change the button text.
 */
$search = $search ?? [];
$search_button = $search_button ?? 'SEARCH ROOMS';
$adults = (int) ($search['adults'] ?? 2);
$children = (int) ($search['children'] ?? 0);
$limits = guest_limits(); // follows the biggest room type the admin has set up
?>
<form class="search-bar" action="<?= url('rooms.php') ?>" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-3">
            <label for="check_in">Check-in</label>
            <input type="date" id="check_in" name="check_in" value="<?= e($search['check_in'] ?? '') ?>" required>
        </div>
        <div class="col-md-6 col-lg-3">
            <label for="check_out">Check-out</label>
            <input type="date" id="check_out" name="check_out" value="<?= e($search['check_out'] ?? '') ?>" required>
        </div>
        <div class="col-6 col-lg-2">
            <label for="adults">Adults</label>
            <select id="adults" name="adults">
                <?php for ($i = 1; $i <= $limits['adults']; $i++): ?>
                    <option value="<?= $i ?>"<?= $i === $adults ? ' selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="children">Children</label>
            <select id="children" name="children">
                <?php for ($i = 0; $i <= $limits['children']; $i++): ?>
                    <option value="<?= $i ?>"<?= $i === $children ? ' selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-12 col-lg-2">
            <button type="submit" class="btn-primary-red"><?= e($search_button) ?></button>
        </div>
    </div>
    <p class="nights-note" id="nightsNote" aria-live="polite"></p>
</form>
