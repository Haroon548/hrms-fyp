<?php
/*
 * Booking step 2: guest details and "Complete booking" (FR-BOOK-01 to 05;
 * design.md: Booking flow, screenshots 18 and 19).
 *   booking/checkout.php?type=pool-wing&check_in=2026-11-02&check_out=2026-11-04&adults=2&children=0
 *
 * Everything is checked again on the server when the form is sent: the dates, the guests,
 * the price (taken from the database, never from the form) and whether a room is still free.
 */
require_once '../includes/init.php';
require_once '../includes/bookings.php';
require_login(); // comes back here after logging in, with the same dates

$user = current_user();
$type = room_type_by_slug($_REQUEST['type'] ?? '');
$search = [
    'check_in'  => (string) ($_REQUEST['check_in'] ?? ''),
    'check_out' => (string) ($_REQUEST['check_out'] ?? ''),
    'adults'    => (int) ($_REQUEST['adults'] ?? 2),
    'children'  => (int) ($_REQUEST['children'] ?? 0),
];
$stay = check_stay($search['check_in'], $search['check_out']);
$problem = booking_problem($type, $stay, $search['adults'], $search['children']);
$freeCount = $problem === '' ? count(free_rooms($type['id'], $stay['check_in'], $stay['check_out'])) : 0;
if ($problem === '' && $freeCount === 0) {
    $problem = 'Sorry, ' . $type['name'] . ' is fully booked for these dates. Please choose other dates or another room.';
}

$errors = [];
$values = [
    'name'     => $user['name'],
    'phone'    => $user['phone'],
    'arrival'  => '',
    'requests' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // Pressing "Complete booking" twice (or going back and resending) must not book twice
    $formId = (string) ($_POST['form_id'] ?? '');
    if (isset($_SESSION['booked_forms'][$formId])) {
        redirect('booking/confirmation.php?ref=' . urlencode($_SESSION['booked_forms'][$formId]));
    }

    $values = [
        'name'     => trim($_POST['name'] ?? ''),
        'phone'    => trim($_POST['phone'] ?? ''),
        'arrival'  => in_array($_POST['arrival'] ?? '', ARRIVAL_TIMES, true) ? $_POST['arrival'] : '',
        'requests' => trim($_POST['requests'] ?? ''),
    ];
    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Enter the name the booking is for.';
    }
    if (!preg_match('/^\+?[0-9 \-]{7,20}$/', $values['phone'])) {
        $errors['phone'] = 'Enter a phone number, so the hotel can reach you, e.g. 0300 1234567.';
    }
    if (mb_strlen($values['requests']) > 500) {
        $errors['requests'] = 'Keep special requests under 500 characters.';
    }
    if (empty($_POST['agree'])) {
        $errors['agree'] = 'Please agree to the cancellation policy to book.';
    }
    // The guest only books at the price they saw: if the hotel changed it meanwhile, show the new one first
    $quoted = (string) ($_POST['quoted_price'] ?? '');
    $priceChanged = $problem === '' && $quoted !== '' && round((float) $quoted) !== round((float) $type['price_per_night']);

    if (!$errors && $problem === '' && !$priceChanged) {
        // Keep the guest's details up to date for next time
        db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$values['name'], $values['phone'], $user['id']]);

        $result = create_booking($user['id'], $type, $stay, $search['adults'], $search['children'], $values['requests'], $values['arrival']);
        if (is_array($result)) {
            $_SESSION['booked_forms'][$formId] = $result['reference'];
            // The email never stops the booking (D4, FR-MAIL-02)
            $sent = send_booking_confirmation(find_booking($result['reference']));
            $_SESSION['email_note'][$result['reference']] = $sent;
            redirect('booking/confirmation.php?ref=' . urlencode($result['reference']) . '&new=1');
        }
        $problem = $result; // the last room went while the guest was filling in the form
    }
}

$page_title = 'Complete your booking | ' . HOTEL_NAME;
$active_page = 'rooms';
$body_class = 'page-checkout';
$flash_inline = true;
$page_scripts = ['assets/js/checkout.js'];
live_watch('room_types', 'rooms', 'bookings'); // a new price or the last room going shows at once
include '../includes/header.php';

if ($problem !== ''):
    ?>
    <section class="section">
        <div class="container">
            <div class="empty-state">
                <span class="empty-icon"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i></span>
                <h1 class="h2">This stay can't be booked</h1>
                <p><?= e($problem) ?></p>
                <a href="<?= url('rooms.php?' . http_build_query(['check_in' => $search['check_in'], 'check_out' => $search['check_out'], 'adults' => $search['adults'], 'children' => $search['children']])) ?>" class="btn-primary-red">See free rooms</a>
                <?php if ($type): ?>
                    <p class="mt-3 mb-0"><a href="<?= url('room.php?type=' . urlencode($type['slug']) . '#availability') ?>" class="text-link">Check the <?= e($type['name']) ?> calendar</a></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    include '../includes/footer.php';
    exit;
endif;

$total = $type['price_per_night'] * $stay['nights'];
$photos = room_type_photos($type['id']);
$changeLink = url('room.php?' . http_build_query(['type' => $type['slug']] + $search) . '#book');
?>

<!-- Dark summary bar (Wyndham's checkout, screenshot 18) -->
<section class="checkout-bar">
    <div class="container">
        <ol class="steps-mini" aria-label="Booking steps">
            <li class="is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> Choose room</li>
            <li class="is-current" aria-current="step"><span>2</span> Your details</li>
            <li><span>3</span> Confirmed</li>
        </ol>
        <div class="checkout-bar-row">
            <div>
                <h1><?= e(HOTEL_NAME) ?></h1>
                <p class="checkout-stars" aria-label="Four-star hotel">★★★★</p>
                <p class="checkout-stay">
                    <?= e(long_date($stay['check_in'])) ?> – <?= e(long_date($stay['check_out'])) ?>
                    <span aria-hidden="true">|</span> 1 room, <?= $stay['nights'] ?> <?= $stay['nights'] === 1 ? 'night' : 'nights' ?>
                    <span aria-hidden="true">|</span> <?= e(occupancy_text($search['adults'], $search['children'])) ?>
                    <a href="<?= e($changeLink) ?>">Change</a>
                </p>
            </div>
            <div class="checkout-price" data-live-region="price">
                <span hidden data-quote="<?= e((string) $type['price_per_night']) ?>"></span>
                <p><span><?= e($type['name']) ?><br><small>PKR <?= money($type['price_per_night']) ?> × <?= $stay['nights'] ?> <?= $stay['nights'] === 1 ? 'night' : 'nights' ?></small></span>
                    <span>PKR <?= money($total) ?></span></p>
                <p class="checkout-total"><span>Total for stay</span> <strong>PKR <?= money($total) ?></strong></p>
                <p class="checkout-pay"><i class="fa-solid fa-hotel" aria-hidden="true"></i> Pay at hotel · <button type="button" class="link-button" data-bs-toggle="modal" data-bs-target="#cancelPolicy">Cancellation details</button></p>
            </div>
        </div>
    </div>
</section>

<section class="section checkout-section">
    <div class="container">
        <div class="row g-4 gx-lg-5">
            <div class="col-lg-8">
                <?php if (!empty($priceChanged)): ?>
                    <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span>The hotel changed the price of <?= e($type['name']) ?> while you were booking. It is now <strong>PKR <?= money($type['price_per_night']) ?> a night</strong> (PKR <?= money($total) ?> for your stay) instead of PKR <?= money($quoted) ?>. Nothing has been booked yet: check the new total and press Complete booking again.</span></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span>Please check the <?= count($errors) === 1 ? 'field' : count($errors) . ' fields' ?> marked in red.</span></div>
                <?php endif; ?>
                <form method="post" class="checkout-form" novalidate data-checkout-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_id" value="<?= e($_POST['form_id'] ?? bin2hex(random_bytes(8))) ?>">
                    <input type="hidden" name="quoted_price" value="<?= e((string) $type['price_per_night']) ?>">
                    <?php foreach (['type' => $type['slug']] + $search as $key => $value): ?>
                        <input type="hidden" name="<?= $key ?>" value="<?= e($value) ?>">
                    <?php endforeach; ?>

                    <h2>Guest information</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                                <label for="f_name">Full name</label>
                                <input type="text" id="f_name" name="name" value="<?= e($values['name']) ?>" autocomplete="name" maxlength="100" required<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="f_name_error"' : '' ?>>
                                <?php if (isset($errors['name'])): ?><p class="field-error" id="f_name_error"><?= e($errors['name']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                                <label for="f_phone">Phone</label>
                                <input type="tel" id="f_phone" name="phone" value="<?= e($values['phone']) ?>" autocomplete="tel" maxlength="20" required<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="f_phone_error"' : '' ?>>
                                <?php if (isset($errors['phone'])): ?><p class="field-error" id="f_phone_error"><?= e($errors['phone']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-field">
                                <label for="f_email">Email</label>
                                <input type="email" id="f_email" value="<?= e($user['email']) ?>" disabled aria-describedby="f_email_help">
                                <p class="field-help" id="f_email_help">Your confirmation is sent here.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-field">
                                <label for="f_arrival">Estimated arrival time (optional)</label>
                                <select id="f_arrival" name="arrival" aria-describedby="f_arrival_help">
                                    <?php foreach (ARRIVAL_TIMES as $time): ?>
                                        <option value="<?= e($time) ?>"<?= $time === $values['arrival'] ? ' selected' : '' ?>><?= $time === '' ? 'I don\'t know yet' : e($time) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="field-help" id="f_arrival_help">Check-in is from <?= CHECK_IN_TIME ?>. This helps us have your room ready.</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-field<?= isset($errors['requests']) ? ' has-error' : '' ?>">
                                <label for="f_requests">Special requests (optional)</label>
                                <textarea id="f_requests" name="requests" rows="3" maxlength="500" placeholder="e.g. a high floor, an extra pillow, airport pick-up" aria-describedby="f_requests_help<?= isset($errors['requests']) ? ' f_requests_error' : '' ?>"><?= e($values['requests']) ?></textarea>
                                <p class="field-help" id="f_requests_help">We'll do our best; requests can't be guaranteed.</p>
                                <?php if (isset($errors['requests'])): ?><p class="field-error" id="f_requests_error"><?= e($errors['requests']) ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <ul class="confidence" aria-label="Why book with us">
                        <li><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i><span>No card needed<br><small>Pay when you arrive</small></span></li>
                        <li><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Free cancellation<br><small>Until 6 PM on arrival day</small></span></li>
                        <li><i class="fa-solid fa-mug-hot" aria-hidden="true"></i><span>Breakfast included<br><small>Buffet at Buzz 76</small></span></li>
                    </ul>

                    <div class="checkout-agree<?= isset($errors['agree']) ? ' has-error' : '' ?>">
                        <label class="agree-check">
                            <input type="checkbox" name="agree" value="1" required<?= !empty($_POST['agree']) ? ' checked' : '' ?><?= isset($errors['agree']) ? ' aria-invalid="true" aria-describedby="f_agree_error"' : '' ?>>
                            <span>I agree to the <button type="button" class="link-button" data-bs-toggle="modal" data-bs-target="#cancelPolicy">cancellation policy</button>, and understand that I pay at the hotel.</span>
                        </label>
                        <?php if (isset($errors['agree'])): ?><p class="field-error" id="f_agree_error"><?= e($errors['agree']) ?></p><?php endif; ?>
                        <button type="submit" class="btn-primary-red btn-complete">COMPLETE BOOKING</button>
                        <p class="checkout-small">Good news! You are booking a flexible rate: free cancellation until 6 PM on <?= e(long_date($stay['check_in'])) ?>.</p>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <aside class="stay-card" aria-label="Your stay" data-live-region="stay">
                    <?php photo($photos[0]['file'] ?? '', $type['name'], 'stay-card-photo'); ?>
                    <div class="stay-card-body">
                        <h2><?= e($type['name']) ?></h2>
                        <ul class="room-facts">
                            <li><i class="fa-solid fa-bed" aria-hidden="true"></i> <?= e($type['beds']) ?></li>
                            <li><i class="fa-solid fa-user-group" aria-hidden="true"></i> <?= e(occupancy_text($search['adults'], $search['children'])) ?></li>
                            <?php if ($type['feature'] !== ''): ?><li class="feature"><i class="fa-solid fa-star" aria-hidden="true"></i> <?= e($type['feature']) ?></li><?php endif; ?>
                        </ul>
                        <dl class="stay-dates">
                            <div><dt>Check-in</dt><dd><?= e(short_date($stay['check_in'])) ?><small>from <?= CHECK_IN_TIME ?></small></dd></div>
                            <div><dt>Check-out</dt><dd><?= e(short_date($stay['check_out'])) ?><small>by <?= CHECK_OUT_TIME ?></small></dd></div>
                        </dl>
                        <?php if ($freeCount <= 2): ?>
                            <p class="stay-left"><i class="fa-solid fa-fire" aria-hidden="true"></i> Only <?= $freeCount ?> <?= $freeCount === 1 ? 'room' : 'rooms' ?> left for these dates</p>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>

<!-- Cancellation details pop-up (screenshot 17) -->
<div class="modal fade" id="cancelPolicy" tabindex="-1" aria-labelledby="cancelPolicyTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content policy-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="cancelPolicyTitle">Cancellation details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="policy-list">
                    <li><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Cancel free of charge until 6:00 PM on <?= e(long_date($stay['check_in'])) ?>, from My Account or by calling the hotel.</li>
                    <li><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> Nothing is paid online. You pay at the hotel, by cash or card.</li>
                    <li><i class="fa-regular fa-id-card" aria-hidden="true"></i> Please bring a photo ID (CNIC or passport) to check in.</li>
                    <li><i class="fa-regular fa-clock" aria-hidden="true"></i> Check-in from <?= CHECK_IN_TIME ?>, check-out by <?= CHECK_OUT_TIME ?>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php';
