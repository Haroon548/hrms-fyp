<?php
/*
 * Reserve a table (FR-TBL-02, 03, 04; design.md: Table reservation).
 *   1. Choose restaurant, date, time and guests (anyone can search).
 *   2. Pick one of the free tables that seat the party; if none is free, nearby free times are suggested.
 *   3. Log in (if needed) and CONFIRM RESERVATION → reservation/view.php and an email.
 * Everything is checked again on the server when the reservation is saved.
 */
require_once 'includes/init.php';
require_once 'includes/reservations.php';

$restaurants = restaurants_list();
$input = [
    'restaurant' => (int) ($_REQUEST['restaurant'] ?? ($restaurants[0]['id'] ?? 0)),
    'date'       => (string) ($_REQUEST['date'] ?? date('Y-m-d')),
    'time'       => (string) ($_REQUEST['time'] ?? ''),
    'party'      => (int) ($_REQUEST['party'] ?? 2),
];
$searched = isset($_REQUEST['time']) && $input['time'] !== '';
$search = $searched ? check_reservation_search($input['restaurant'], $input['date'], $input['time'], $input['party']) : null;
$problem = is_string($search) ? $search : '';
$tables = is_array($search) ? free_tables($search['restaurant']['id'], $search['date'], $search['time'], $search['party']) : [];
$errors = [];

// Confirm (logged-in guests only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    require_login();
    $formId = (string) ($_POST['form_id'] ?? '');
    if (isset($_SESSION['reserved_forms'][$formId])) {
        redirect('reservation/view.php?id=' . $_SESSION['reserved_forms'][$formId]);
    }
    if (is_array($search)) {
        $tableId = (int) ($_POST['table_id'] ?? 0);
        if (!$tableId) {
            $errors['table'] = 'Choose a table.';
        } elseif (mb_strlen(trim($_POST['notes'] ?? '')) > 255) {
            $errors['notes'] = 'Keep the note under 255 characters.';
        } else {
            $result = create_reservation(current_user()['id'], $search, $tableId, $_POST['notes'] ?? '');
            if (is_int($result)) {
                $_SESSION['reserved_forms'][$formId] = $result;
                send_reservation_email(find_reservation($result)); // never stops the reservation (D4)
                redirect('reservation/view.php?id=' . $result . '&new=1');
            }
            $errors['table'] = $result;
            $tables = free_tables($search['restaurant']['id'], $search['date'], $search['time'], $search['party']);
        }
    }
}

// Nothing free at that time? Look for the closest times that do have a table
$suggestions = [];
if (is_array($search) && !$tables) {
    $slots = reservation_slots($search['restaurant'], $search['date']);
    $at = array_search($search['time'], $slots, true);
    foreach ([-1, 1, -2, 2, -3, 3, -4, 4] as $step) {
        $slot = $slots[$at + $step] ?? null;
        if ($slot && free_tables($search['restaurant']['id'], $search['date'], $slot, $search['party'])) {
            $suggestions[] = $slot;
        }
        if (count($suggestions) === 4) break;
    }
    sort($suggestions);
}

// All half-hour times from the earliest opening to the latest last seating; the page's script hides
// the ones a restaurant doesn't offer (and the server checks again).
$allSlots = [];
foreach ($restaurants as $r) {
    for ($t = strtotime('2000-01-01 ' . $r['opens_at']); $t <= strtotime('2000-01-01 ' . $r['closes_at']) - LAST_SEATING_MINUTES * 60; $t += SLOT_MINUTES * 60) {
        $allSlots[date('H:i', $t)] = true;
    }
}
ksort($allSlots);
$photo = site_photo('dining_reserve', 'A table laid for dinner');

$page_title = 'Reserve a table | ' . HOTEL_NAME;
$page_description = 'Reserve a table at Buzz 76, Cafe Saints or the Executive Lounge at Ramada by Wyndham Multan.';
$active_page = 'dining';
$flash_inline = true;
$page_scripts = ['assets/js/reserve.js', 'assets/js/checkout.js'];
live_watch('table_reservations', 'dining_tables', 'restaurants'); // a table taken by someone else disappears at once
include 'includes/header.php';
?>
<section class="page-banner reserve-banner">
    <?php photo($photo['file'], $photo['alt_text'], 'page-banner-photo'); ?>
    <div class="container page-banner-text">
        <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> <a href="<?= url('dining.php') ?>">Dining</a> <span aria-hidden="true">/</span> Reserve a table</nav>
        <h1>Reserve a table</h1>
        <p>Pick a time, choose your table, and we'll have it ready.</p>
    </div>
</section>

<div class="container search-wrap-page">
    <form class="search-bar reserve-search" action="<?= url('reserve-table.php') ?>#tables" method="get" data-reserve-form>
        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-lg-3">
                <label for="r_restaurant">Restaurant</label>
                <select id="r_restaurant" name="restaurant">
                    <?php foreach ($restaurants as $r): ?>
                        <option value="<?= $r['id'] ?>" data-opens="<?= substr($r['opens_at'], 0, 5) ?>" data-last="<?= date('H:i', strtotime('2000-01-01 ' . $r['closes_at']) - LAST_SEATING_MINUTES * 60) ?>"<?= $input['restaurant'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="r_date">Date</label>
                <input type="date" id="r_date" name="date" value="<?= e($input['date']) ?>" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+' . RESERVE_DAYS_AHEAD . ' days')) ?>" required>
            </div>
            <div class="col-6 col-lg-2">
                <label for="r_time">Time</label>
                <select id="r_time" name="time" required>
                    <option value="">Choose…</option>
                    <?php foreach (array_keys($allSlots) as $slot): ?>
                        <option value="<?= $slot ?>"<?= $input['time'] === $slot ? ' selected' : '' ?>><?= nice_time($slot) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="r_party">Guests</label>
                <select id="r_party" name="party">
                    <?php for ($i = 1; $i <= MAX_PARTY; $i++): ?>
                        <option value="<?= $i ?>"<?= $input['party'] === $i ? ' selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-12 col-lg-2">
                <button type="submit" class="btn-primary-red"><?= $searched ? 'UPDATE' : 'FIND A TABLE' ?></button>
            </div>
        </div>
        <p class="nights-note" data-hours-note aria-live="polite"></p>
    </form>
</div>

<section class="section reserve-results" id="tables">
    <div class="container" data-live-region="tables">
        <?php if (!$searched): ?>
            <div class="reserve-intro">
                <?php foreach ($restaurants as $r): ?>
                    <div class="reserve-place">
                        <h2><?= e($r['name']) ?></h2>
                        <p><?= e($r['description']) ?></p>
                        <p class="place-hours"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e($r['hours']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($problem !== ''): ?>
            <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= e($problem) ?></span></div>
        <?php else: ?>
            <div class="stay-summary">
                <div><strong><?= e($search['restaurant']['name']) ?></strong>
                    <span><?= e(date('D j M', strtotime($search['date']))) ?> · <?= e(nice_time($search['time'])) ?> · <?= $search['party'] ?> <?= $search['party'] === 1 ? 'guest' : 'guests' ?></span></div>
                <p><?= count($tables) ?> <?= count($tables) === 1 ? 'table is' : 'tables are' ?> free · <a href="#r_date">Change</a></p>
            </div>

            <?php if (!$tables): ?>
                <div class="empty-state">
                    <span class="empty-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                    <h2>No table for <?= $search['party'] ?> at <?= e(nice_time($search['time'])) ?></h2>
                    <?php if ($suggestions): ?>
                        <p>These times still have a table:</p>
                        <div class="time-chips">
                            <?php foreach ($suggestions as $slot): ?>
                                <a href="<?= url('reserve-table.php?' . http_build_query(['restaurant' => $search['restaurant']['id'], 'date' => $search['date'], 'time' => $slot, 'party' => $search['party']])) ?>#tables"><?= nice_time($slot) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>That day is fully reserved. Please try another day, or call <?= e(HOTEL_PHONE) ?>.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="post" class="reserve-pick" novalidate data-checkout-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_id" value="<?= e($_POST['form_id'] ?? bin2hex(random_bytes(8))) ?>">
                    <?php foreach ($input as $key => $value): ?><input type="hidden" name="<?= $key ?>" value="<?= e($value) ?>"><?php endforeach; ?>
                    <h2>Choose your table</h2>
                    <?php if (isset($errors['table'])): ?>
                        <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= e($errors['table']) ?></span></div>
                    <?php endif; ?>
                    <fieldset class="table-options">
                        <legend class="visually-hidden">Free tables</legend>
                        <?php foreach ($tables as $i => $table): ?>
                            <label class="order-type">
                                <input type="radio" name="table_id" value="<?= $table['id'] ?>"<?= (int) ($_POST['table_id'] ?? $tables[0]['id']) === (int) $table['id'] ? ' checked' : '' ?>>
                                <span class="order-type-box table-box">
                                    <span class="table-seats" aria-hidden="true"><?php for ($s = 0; $s < min(8, $table['seats']); $s++): ?><i></i><?php endfor; ?></span>
                                    <strong>Table <?= e($table['table_number']) ?></strong>
                                    <small><?= (int) $table['seats'] ?> seats<?= $i === 0 ? ' · best fit' : '' ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <div class="form-field<?= isset($errors['notes']) ? ' has-error' : '' ?>">
                        <label for="r_notes">Anything we should know? (optional)</label>
                        <input type="text" id="r_notes" name="notes" value="<?= e($_POST['notes'] ?? '') ?>" maxlength="255" placeholder="e.g. birthday, high chair, by the window">
                        <?php if (isset($errors['notes'])): ?><p class="field-error"><?= e($errors['notes']) ?></p><?php endif; ?>
                    </div>
                    <div class="checkout-agree">
                        <?php if (is_logged_in()): ?>
                            <button type="submit" class="btn-primary-red btn-complete" data-busy="RESERVING…">CONFIRM RESERVATION</button>
                            <p class="checkout-small">Reserving as <?= e(current_user()['name']) ?>. We hold the table for 15 minutes after your time. Free to cancel.</p>
                        <?php else: ?>
                            <a href="<?= url('account/login.php?next=' . urlencode(current_page())) ?>" class="btn-primary-red btn-complete">LOG IN TO RESERVE</a>
                            <p class="checkout-small">You come straight back here. No account yet? <a href="<?= url('account/register.php') ?>">Create one</a> in a minute.</p>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include 'includes/footer.php';
