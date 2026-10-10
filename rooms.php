<?php
/*
 * Rooms page (FR-ROOM-04, FR-ROOM-05; design.md: Rooms and Booking flow step 1).
 * Without dates: every room type with its "from" price.
 * With dates and guests: which room types are free and big enough, with the price for the stay.
 */
require_once 'includes/init.php';
require_once 'includes/rooms.php';

$types = room_types();

// The visitor's search, if any (guests limited to what the biggest room type takes)
$limits = guest_limits();
$search = [
    'check_in'  => $_GET['check_in'] ?? '',
    'check_out' => $_GET['check_out'] ?? '',
    'adults'    => max(1, min($limits['adults'], (int) ($_GET['adults'] ?? 2))),
    'children'  => max(0, min($limits['children'], (int) ($_GET['children'] ?? 0))),
];
$searched = $search['check_in'] !== '' || $search['check_out'] !== '';
$stay = null;
$searchError = '';
if ($searched) {
    $result = check_stay($search['check_in'], $search['check_out']);
    if (is_array($result)) {
        $stay = $result;
    } else {
        $searchError = $result;
    }
}

// Work out each room type's state for this search
$freeCounts = $stay ? free_room_counts($stay['check_in'], $stay['check_out']) : [];
foreach ($types as &$type) {
    $type['free'] = $freeCounts[$type['id']] ?? 0;
    $type['fits'] = $search['adults'] <= $type['max_adults'] && $search['children'] <= $type['max_children'];
    if (!$stay) {
        $type['state'] = 'browse';
    } elseif (!$type['fits']) {
        $type['state'] = 'too_small';
    } elseif ($type['price_per_night'] === null) {
        $type['state'] = 'no_price';
    } elseif ($type['free'] === 0) {
        $type['state'] = 'full';
    } else {
        $type['state'] = 'available';
    }
}
unset($type);

// Bookable rooms first, then the rest in their usual order
if ($stay) {
    $rank = ['available' => 0, 'no_price' => 1, 'full' => 2, 'too_small' => 3];
    usort($types, function ($a, $b) use ($rank) {
        return [$rank[$a['state']], $a['sort_order']] <=> [$rank[$b['state']], $b['sort_order']];
    });
}
$availableCount = count(array_filter($types, function ($type) { return $type['state'] === 'available'; }));

// Link to the booking step with this search
function book_link($type, $stay, $search)
{
    return url('booking/checkout.php?' . http_build_query([
        'type' => $type['slug'],
        'check_in' => $stay['check_in'],
        'check_out' => $stay['check_out'],
        'adults' => $search['adults'],
        'children' => $search['children'],
    ]));
}

$page_title = 'Rooms & Suites | ' . HOTEL_NAME;
$page_description = 'Six room types at Ramada by Wyndham Multan, from Pool Wing rooms to the Presidential Suite. Check which rooms are free and book online.';
$active_page = 'rooms';
$page_scripts = ['assets/vendor/gsap-3.15.0/gsap.min.js', 'assets/js/spotlight.js'];
live_watch('room_types', 'room_type_photos', 'rooms', 'bookings'); // prices, photos and free rooms change by themselves
include 'includes/header.php';
?>

<section class="page-banner">
    <?php photo('slides/slide-4.jpg', 'Suite living room', 'page-banner-photo'); ?>
    <div class="container page-banner-text">
        <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> Rooms</nav>
        <h1>Rooms &amp; Suites</h1>
        <p>All designs are customized as per your need.</p>
    </div>
</section>

<div class="container search-wrap search-wrap-page" id="search">
    <?php
    $search_button = $stay ? 'UPDATE' : 'CHECK AVAILABILITY';
    include 'includes/search-bar.php';
    ?>
</div>

<section class="section section-alt rooms-results">
    <div class="container" data-live-region="rooms">
        <?php if ($searchError): ?>
            <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= e($searchError) ?></span></div>
        <?php elseif ($stay): ?>
            <div class="stay-summary">
                <div>
                    <strong><?= $stay['nights'] ?> <?= $stay['nights'] === 1 ? 'night' : 'nights' ?></strong>
                    <span><?= e(short_date($stay['check_in'])) ?> → <?= e(short_date($stay['check_out'])) ?> · <?= e(occupancy_text($search['adults'], $search['children'])) ?></span>
                </div>
                <p><?= $availableCount ?> of <?= count($types) ?> room types can be booked for these dates · <a href="#search">Change dates</a></p>
            </div>
        <?php else: ?>
            <?php section_heading('ROOMS', 'Choose your dates to see what\'s free and the price for your stay'); ?>
        <?php endif; ?>

        <div class="room-list">
            <?php foreach ($types as $type): ?>
                <?php $nights = $stay['nights'] ?? 0; ?>
                <article class="room-row room-row-<?= e($type['state']) ?>" data-spotlight>
                    <a href="<?= url('room.php?type=' . $type['slug']) ?>" class="room-row-photo" tabindex="-1" aria-hidden="true">
                        <?php photo($type['photo'] ?? '', $type['name']); ?>
                        <?php if ($type['state'] === 'available' && $type['free'] <= 2): ?>
                            <span class="few-left">Only <?= $type['free'] ?> <?= $type['free'] === 1 ? 'room' : 'rooms' ?> left!</span>
                        <?php endif; ?>
                    </a>
                    <div class="room-row-info">
                        <h2><a href="<?= url('room.php?type=' . $type['slug']) ?>"><?= e($type['name']) ?></a></h2>
                        <ul class="room-facts">
                            <li><i class="fa-solid fa-user-group" aria-hidden="true"></i> Up to <?= e(occupancy_text($type['max_adults'], $type['max_children'])) ?></li>
                            <li><i class="fa-solid fa-bed" aria-hidden="true"></i> <?= e($type['beds']) ?></li>
                            <?php if ($type['feature'] !== ''): ?>
                                <li class="feature"><i class="fa-solid fa-star" aria-hidden="true"></i> <?= e($type['feature']) ?></li>
                            <?php endif; ?>
                        </ul>
                        <p class="room-row-text"><?= e($type['description']) ?></p>
                        <a href="<?= url('room.php?type=' . $type['slug']) ?>" class="text-link">Room Details</a>
                    </div>
                    <div class="room-row-price">
                        <?php if ($type['state'] === 'browse'): ?>
                            <?php if ($type['price_per_night'] !== null): ?>
                                <p class="price-from">From</p>
                                <p class="price-big">PKR <?= money($type['price_per_night']) ?></p>
                                <p class="price-unit">per night</p>
                            <?php else: ?>
                                <p class="price-big price-ask">Price on request</p>
                            <?php endif; ?>
                            <a href="#search" class="btn-primary-red">CHECK AVAILABILITY</a>
                        <?php elseif ($type['state'] === 'available'): ?>
                            <p class="price-big">PKR <?= money($type['price_per_night']) ?></p>
                            <p class="price-unit">per night</p>
                            <p class="price-total"><?= $nights ?> <?= $nights === 1 ? 'night' : 'nights' ?>: <strong>PKR <?= money($type['price_per_night'] * $nights) ?></strong></p>
                            <a href="<?= e(book_link($type, $stay, $search)) ?>" class="btn-primary-red">BOOK</a>
                            <p class="price-note"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> Pay at the hotel</p>
                        <?php elseif ($type['state'] === 'no_price'): ?>
                            <p class="price-big price-ask">Price on request</p>
                            <p class="price-note">This room can't be booked online yet.</p>
                            <a href="<?= url('contact.php') ?>" class="btn-small-outline">Ask the hotel</a>
                        <?php elseif ($type['state'] === 'full'): ?>
                            <p class="state-label"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i> Fully booked for these dates</p>
                            <a href="#search" class="btn-small-outline">Try other dates</a>
                        <?php else: ?>
                            <p class="state-label"><i class="fa-solid fa-user-group" aria-hidden="true"></i> Too small for <?= e(occupancy_text($search['adults'], $search['children'])) ?></p>
                            <p class="price-note">Fits up to <?= e(occupancy_text($type['max_adults'], $type['max_children'])) ?>.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
