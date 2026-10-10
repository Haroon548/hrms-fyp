<?php
/*
 * Booking step 3: confirmation (FR-BOOK-05; design.md: Booking flow 5).
 *   booking/confirmation.php?ref=RM-7K3QX9
 * Only the guest who made the booking (or an admin) can open it.
 * Extras: the "digital room key" card (FR-UX-07), Add to calendar, Share on WhatsApp (FR-WA-02),
 * Get directions, and a print-friendly layout.
 */
require_once '../includes/init.php';
require_once '../includes/bookings.php';
require_login();

$booking = find_booking((string) ($_GET['ref'] ?? ''));
if (!$booking || ((int) $booking['user_id'] !== (int) current_user()['id'] && !is_admin())) {
    http_response_code(404);
    $page_title = 'Booking not found | ' . HOTEL_NAME;
    include '../includes/header.php';
    ?>
    <section class="section"><div class="container"><div class="empty-state">
        <span class="empty-icon"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i></span>
        <h1 class="h2">We couldn't find that booking</h1>
        <p>Check the booking number, or find all your stays in My Account.</p>
        <a href="<?= url('account/index.php?tab=bookings') ?>" class="btn-primary-red">My bookings</a>
    </div></div></section>
    <?php
    include '../includes/footer.php';
    exit;
}

$isNew = isset($_GET['new']);
$emailSent = $_SESSION['email_note'][$booking['reference']] ?? null;
unset($_SESSION['email_note'][$booking['reference']]);
$nights = booking_nights($booking);
$first = explode(' ', trim($booking['guest_name']))[0];
$directions = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(HOTEL_NAME . ', ' . HOTEL_ADDRESS);
$share = 'My stay at ' . HOTEL_NAME . ': ' . $booking['type_name'] . ', ' . long_date($booking['check_in']) . ' to '
    . long_date($booking['check_out']) . '. Booking number ' . $booking['reference'] . '. ' . HOTEL_ADDRESS;

$headline = [
    'confirmed'   => ['Your stay is booked!', 'fa-solid fa-circle-check'],
    'checked_in'  => ['Welcome! You are checked in', 'fa-solid fa-door-open'],
    'checked_out' => ['Thank you for staying with us', 'fa-solid fa-heart'],
    'cancelled'   => ['This booking is cancelled', 'fa-solid fa-ban'],
][$booking['status']] ?? ['Your booking', 'fa-solid fa-bed'];

$page_title = 'Booking ' . $booking['reference'] . ' | ' . HOTEL_NAME;
$active_page = 'rooms';
$body_class = 'page-confirmation';
$page_scripts = ['assets/js/confirmation.js'];
live_watch('bookings', 'rooms', 'invoices');
include '../includes/header.php';
?>

<section class="confirm-hero<?= $booking['status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
    <div class="container">
        <?php if ($isNew): ?>
            <ol class="steps-mini" aria-label="Booking steps">
                <li class="is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> Choose room</li>
                <li class="is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> Your details</li>
                <li class="is-current" aria-current="step"><span>3</span> Confirmed</li>
            </ol>
        <?php endif; ?>
        <div class="confirm-grid">
            <div class="confirm-text">
                <span class="confirm-icon<?= $isNew ? ' is-pop' : '' ?>" aria-hidden="true"><i class="<?= $headline[1] ?>"></i></span>
                <h1><?= e($headline[0]) ?></h1>
                <p>
                    <?php if ($booking['status'] === 'confirmed'): ?>
                        Thank you, <?= e($first) ?>. Your room is reserved and you pay at the hotel.
                        <?php if ($emailSent === true): ?>
                            A confirmation has been sent to <strong><?= e($booking['guest_email']) ?></strong>.
                        <?php elseif ($emailSent === false): ?>
                            <span class="confirm-note">We couldn't send the confirmation email just now, so please keep this page or your booking number.</span>
                        <?php endif; ?>
                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                        Cancelled on <?= e(long_date($booking['cancelled_at'])) ?>. Nothing is charged.
                    <?php else: ?>
                        Room <?= e($booking['room_number']) ?> · <?= e($booking['type_name']) ?>
                    <?php endif; ?>
                </p>
                <div class="confirm-actions no-print">
                    <a href="<?= url('account/index.php?tab=bookings') ?>" class="btn-white"><i class="fa-solid fa-bed" aria-hidden="true"></i> My bookings</a>
                    <button type="button" class="btn-glass" data-print><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
                </div>
            </div>

            <!-- Digital room key (FR-UX-07): tilts and catches the light as the mouse moves -->
            <div class="room-key-wrap" aria-hidden="true">
                <div class="room-key" data-room-key>
                    <span class="room-key-shine"></span>
                    <div class="room-key-top">
                        <img src="<?= url('assets/images/logo-white.png') ?>" alt="" width="120" height="45">
                        <i class="fa-solid fa-wifi room-key-chip"></i>
                    </div>
                    <p class="room-key-label">Booking number</p>
                    <p class="room-key-ref"><?= e($booking['reference']) ?></p>
                    <div class="room-key-foot">
                        <span><?= e(short_date($booking['check_in'])) ?> → <?= e(short_date($booking['check_out'])) ?></span>
                        <span><?= e($booking['type_name']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section confirm-section">
    <div class="container">
        <div class="row g-4" data-live-region="details">
            <div class="col-lg-8">
                <div class="account-card confirm-details">
                    <div class="confirm-details-head">
                        <h2>Booking details</h2>
                        <?= status_badge($booking['status']) ?>
                    </div>
                    <dl class="detail-list">
                        <div><dt>Booking number</dt><dd><strong><?= e($booking['reference']) ?></strong></dd></div>
                        <div><dt>Guest</dt><dd><?= e($booking['guest_name']) ?> · <?= e($booking['guest_phone']) ?></dd></div>
                        <div><dt>Room</dt><dd><?= e($booking['type_name']) ?><?= $booking['status'] !== 'confirmed' ? ' · Room ' . e($booking['room_number']) : '' ?></dd></div>
                        <div><dt>Check-in</dt><dd><?= e(long_date($booking['check_in'])) ?>, from <?= CHECK_IN_TIME ?><?= $booking['arrival_time'] !== '' ? ' (arriving ' . e($booking['arrival_time']) . ')' : '' ?></dd></div>
                        <div><dt>Check-out</dt><dd><?= e(long_date($booking['check_out'])) ?>, by <?= CHECK_OUT_TIME ?></dd></div>
                        <div><dt>Guests</dt><dd><?= e(occupancy_text($booking['adults'], $booking['children'])) ?></dd></div>
                        <?php if ($booking['special_requests']): ?>
                            <div><dt>Special requests</dt><dd><?= nl2br(e($booking['special_requests'])) ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Price</dt><dd>PKR <?= money($booking['price_per_night']) ?> × <?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?></dd></div>
                        <div class="detail-total"><dt>Total for stay</dt><dd>PKR <?= money($booking['total']) ?> <small>Pay at the hotel</small></dd></div>
                    </dl>
                    <?php if ($booking['status'] === 'confirmed'): ?>
                        <p class="confirm-cancel"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <?php if (can_cancel($booking)): ?>
                                Free cancellation until 6:00 PM on <?= e(long_date($booking['check_in'])) ?>, from <a href="<?= url('account/index.php?tab=bookings') ?>">My bookings</a>.
                            <?php else: ?>
                                The free cancellation time has passed. To change this booking, please call <?= e(HOTEL_PHONE) ?>.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4 no-print">
                <div class="account-card">
                    <h2>Before you arrive</h2>
                    <div class="quick-actions">
                        <?php if ($booking['status'] === 'confirmed'): ?>
                            <a href="<?= url('booking/calendar-file.php?ref=' . urlencode($booking['reference'])) ?>"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i> Add to my calendar</a>
                        <?php endif; ?>
                        <a href="<?= e($directions) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Get directions</a>
                        <a href="https://wa.me/?text=<?= e(urlencode($share)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Share on WhatsApp</a>
                        <a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>?text=<?= e(urlencode('Hello, about my booking ' . $booking['reference'] . ': ')) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Message the hotel</a>
                        <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e(HOTEL_PHONE) ?></a>
                    </div>
                    <p class="confirm-bring"><i class="fa-regular fa-id-card" aria-hidden="true"></i> Bring a photo ID (CNIC or passport) to check in.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include '../includes/footer.php';
