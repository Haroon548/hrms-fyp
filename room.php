<?php
/*
 * Room detail page (FR-ROOM-06; design.md: Room detail).
 * Photos (with the photo viewer), description, amenities, hotel policies,
 * a booking box that checks this room's availability, an availability calendar
 * (FR-SIG-CAL-01) and the other room types.
 */
require_once 'includes/init.php';
require_once 'includes/calendar.php';

$type = room_type_by_slug($_GET['type'] ?? '');
if (!$type) {
    http_response_code(404);
    $page_title = 'Room not found | ' . HOTEL_NAME;
    include 'includes/header.php';
    ?>
    <section class="section"><div class="container"><div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-bed" aria-hidden="true"></i></span>
        <h1 class="h2">We couldn't find that room</h1>
        <p>It may have been renamed or removed. Here are all our rooms instead.</p>
        <a href="<?= url('rooms.php') ?>" class="btn-primary-red">See all rooms</a>
    </div></div></section>
    <?php
    include 'includes/footer.php';
    exit;
}

$photos = room_type_photos($type['id']);
$amenities = amenity_list($type['amenities']);
$allTypes = room_types();

// The booking box starts with tomorrow → the day after, unless the visitor already searched
$search = [
    'check_in'  => $_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day')),
    'check_out' => $_GET['check_out'] ?? date('Y-m-d', strtotime('+2 days')),
    'adults'    => max(1, min((int) $type['max_adults'], (int) ($_GET['adults'] ?? 2))),
    'children'  => max(0, min((int) $type['max_children'], (int) ($_GET['children'] ?? 0))),
];

// "Check availability" in the booking box: is a room of this type free for these dates?
$check = null;
if (isset($_GET['check_in']) && $type['price_per_night'] !== null) {
    $stay = check_stay($search['check_in'], $search['check_out']);
    $problem = booking_problem($type, $stay, $search['adults'], $search['children']);
    if ($problem !== '') {
        $check = ['state' => 'problem', 'message' => $problem];
    } else {
        $free = count(free_rooms($type['id'], $stay['check_in'], $stay['check_out']));
        $check = ['state' => $free ? 'free' : 'full', 'free' => $free, 'stay' => $stay,
            'link' => url('booking/checkout.php?' . http_build_query(['type' => $type['slug']] + $search))];
    }
}
$calendarMonth = calendar_month($_GET['month'] ?? substr($search['check_in'], 0, 7));

// Hotel policies (design.md section 6)
$policies = [
    ['fa-regular fa-clock', 'Check-in from 2:00 PM, check-out by 12:00 PM'],
    ['fa-solid fa-rotate-left', 'Free cancellation until 6:00 PM on the day of arrival'],
    ['fa-solid fa-mug-hot', 'Breakfast buffet included'],
    ['fa-solid fa-money-bill-wave', 'No card needed online: pay at the hotel'],
    ['fa-solid fa-square-parking', 'Free parking · airport shuttle on request'],
];

$page_title = $type['name'] . ' | ' . HOTEL_NAME;
$page_description = $type['name'] . ' at Ramada by Wyndham Multan: ' . $type['beds'] . ', up to ' . occupancy_text($type['max_adults'], $type['max_children']) . '. ' . $type['description'];
$share_image = $photos[0]['file'] ?? 'slides/slide-1.jpg';
$active_page = 'rooms';
$page_scripts = ['assets/vendor/glightbox-3.3.1/glightbox.min.js', 'assets/js/room.js'];
live_watch('room_types', 'room_type_photos', 'rooms', 'bookings'); // price, photos and calendar change by themselves
$extra_styles = ['assets/vendor/glightbox-3.3.1/glightbox.min.css'];
include 'includes/header.php';
?>

<section class="room-detail-head">
    <div class="container" data-live-region="head">
        <nav class="crumbs crumbs-dark" aria-label="You are here">
            <a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span>
            <a href="<?= url('rooms.php') ?>">Rooms</a> <span aria-hidden="true">/</span> <?= e($type['name']) ?>
        </nav>
        <div class="room-title-row">
            <div>
                <h1><?= e($type['name']) ?></h1>
                <ul class="room-facts room-facts-inline">
                    <li><i class="fa-solid fa-user-group" aria-hidden="true"></i> Up to <?= e(occupancy_text($type['max_adults'], $type['max_children'])) ?></li>
                    <li><i class="fa-solid fa-bed" aria-hidden="true"></i> <?= e($type['beds']) ?></li>
                    <?php if ($type['feature'] !== ''): ?>
                        <li class="feature"><i class="fa-solid fa-star" aria-hidden="true"></i> <?= e($type['feature']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="room-title-price">
                <?php if ($type['price_per_night'] !== null): ?>
                    <span>From</span> <strong>PKR <?= money($type['price_per_night']) ?></strong> <span>/ night</span>
                <?php else: ?>
                    <strong>Price on request</strong>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section room-detail">
    <div class="container">
        <div class="row g-4 gx-lg-5">
            <div class="col-lg-8">
                <div data-live-region="about">
                <!-- Photos: the main one large, the rest in a grid; any of them opens the photo viewer -->
                <div class="room-gallery<?= count($photos) > 1 ? ' has-grid' : '' ?>">
                    <?php if (!$photos): ?>
                        <div class="photo-frame"><?php photo('', $type['name']); ?></div>
                    <?php endif; ?>
                    <?php foreach ($photos as $i => $p): ?>
                        <a href="<?= e(url('assets/images/' . $p['file'])) ?>" class="room-gallery-item<?= $i === 0 ? ' is-main' : '' ?> glightbox"
                           data-gallery="room" data-title="<?= e($p['alt_text'] ?: $type['name']) ?>">
                            <?php photo($p['file'], $p['alt_text'] ?: $type['name']); ?>
                            <span class="zoom-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <h2 class="detail-heading">About this room</h2>
                <p class="room-description"><?= nl2br(e($type['description'])) ?></p>

                <?php if ($amenities): ?>
                    <h2 class="detail-heading">Room Details</h2>
                    <ul class="amenities">
                        <?php foreach ($amenities as $amenity): ?>
                            <li><i class="fa-solid fa-circle-chevron-right" aria-hidden="true"></i> <?= e($amenity) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                </div>

                <h2 class="detail-heading">Good to know</h2>
                <ul class="policy-list">
                    <?php foreach ($policies as [$icon, $text]): ?>
                        <li><i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($text) ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($type['price_per_night'] !== null): ?>
                    <section class="availability" id="availability" aria-labelledby="availabilityHeading"
                             data-calendar-url="<?= e(url('booking/calendar.php?type=' . urlencode($type['slug']))) ?>"
                             data-price="<?= (float) $type['price_per_night'] ?>">
                        <h2 class="detail-heading" id="availabilityHeading">Availability</h2>
                        <p class="availability-help">Choose your check-in day, then your check-out day. Prices are per night.</p>
                        <div data-calendar-holder><?php room_calendar($type, $calendarMonth); ?></div>
                        <ul class="cal-legend" aria-label="Colour key">
                            <li><span class="key key-free"></span> Free</li>
                            <li><span class="key key-few"></span> 1–2 rooms left</li>
                            <li><span class="key key-full"></span> Fully booked</li>
                            <li><span class="key key-picked"></span> Your stay</li>
                        </ul>
                        <div class="cal-pick" data-cal-pick hidden aria-live="polite">
                            <p data-cal-pick-text></p>
                            <a href="#book" class="btn-primary-red" data-cal-pick-go>CHECK THESE DATES</a>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div data-live-region="box">
                <aside class="booking-box" id="book" aria-label="Book this room" data-room-price="<?= e((string) $type['price_per_night']) ?>">
                    <?php if ($type['price_per_night'] !== null): ?>
                        <p class="booking-box-price"><strong>PKR <?= money($type['price_per_night']) ?></strong> / night</p>
                        <form action="<?= url('room.php') ?>#book" method="get" class="booking-box-form" data-booking-form>
                            <input type="hidden" name="type" value="<?= e($type['slug']) ?>">
                            <div class="row g-2">
                                <div class="col-6 form-field">
                                    <label for="check_in">Check-in</label>
                                    <input type="date" id="check_in" name="check_in" value="<?= e($search['check_in']) ?>" required>
                                </div>
                                <div class="col-6 form-field">
                                    <label for="check_out">Check-out</label>
                                    <input type="date" id="check_out" name="check_out" value="<?= e($search['check_out']) ?>" required>
                                </div>
                                <div class="col-6 form-field">
                                    <label for="adults">Adults</label>
                                    <select id="adults" name="adults">
                                        <?php for ($i = 1; $i <= $type['max_adults']; $i++): ?>
                                            <option value="<?= $i ?>"<?= $i === $search['adults'] ? ' selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-6 form-field">
                                    <label for="children">Children</label>
                                    <select id="children" name="children"<?= (int) $type['max_children'] === 0 ? ' disabled' : '' ?>>
                                        <?php for ($i = 0; $i <= $type['max_children']; $i++): ?>
                                            <option value="<?= $i ?>"<?= $i === $search['children'] ? ' selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <p class="nights-note" id="nightsNote" aria-live="polite"></p>
                            <button type="submit" class="btn-primary-red w-100">CHECK AVAILABILITY</button>
                        </form>
                        <?php if ($check): ?>
                            <div class="avail-result is-<?= $check['state'] ?>" role="status">
                                <?php if ($check['state'] === 'free'): ?>
                                    <p class="avail-title"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Available
                                        <?php if ($check['free'] <= 2): ?><span class="avail-left">Only <?= $check['free'] ?> left!</span><?php endif; ?></p>
                                    <p><?= $check['stay']['nights'] ?> <?= $check['stay']['nights'] === 1 ? 'night' : 'nights' ?> · <?= e(short_date($check['stay']['check_in'])) ?> → <?= e(short_date($check['stay']['check_out'])) ?></p>
                                    <p class="avail-total">PKR <?= money($type['price_per_night'] * $check['stay']['nights']) ?> <span>total, pay at the hotel</span></p>
                                    <a href="<?= e($check['link']) ?>" class="btn-primary-red w-100">BOOK NOW</a>
                                <?php elseif ($check['state'] === 'full'): ?>
                                    <p class="avail-title"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Fully booked for these dates</p>
                                    <p>Pick other dates in the <a href="#availability">availability calendar</a>, or <a href="<?= e(url('rooms.php?' . http_build_query($search))) ?>">see other rooms</a> for these dates.</p>
                                <?php else: ?>
                                    <p class="avail-title"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <?= e($check['message']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <p class="booking-box-note"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Free cancellation until 6 PM on the day of arrival</p>
                    <?php else: ?>
                        <p class="booking-box-price"><strong>Price on request</strong></p>
                        <p>The hotel hasn't set an online price for this room yet. Call or message us and we'll help you book.</p>
                        <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>" class="btn-primary-red w-100"><i class="fa-solid fa-phone"></i> <?= e(HOTEL_PHONE) ?></a>
                        <a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>" target="_blank" rel="noopener" class="btn-small-outline w-100 mt-2 text-center"><i class="fa-brands fa-whatsapp"></i> WhatsApp us</a>
                    <?php endif; ?>
                </aside>
                </div>

                <nav class="other-rooms" aria-label="All rooms">
                    <h2>Rooms</h2>
                    <?php foreach ($allTypes as $other): ?>
                        <a href="<?= url('room.php?type=' . $other['slug']) ?>" class="<?= $other['id'] == $type['id'] ? 'active' : '' ?>"<?= $other['id'] == $type['id'] ? ' aria-current="page"' : '' ?>>
                            <?php photo($other['photo'] ?? '', $other['name']); ?>
                            <span><?= e($other['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
