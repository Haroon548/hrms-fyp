<?php
/*
 * Home page (design.md section 4, "Home").
 * Keeps Ramada's brand (red, Fira Sans, real photos) with a livelier layout:
 * full-screen hero, glass search panel, photo collage, room carousel on phones,
 * dining band, facility tiles and a closing call to action (D20).
 */
require_once 'includes/init.php';
require_once 'includes/rooms.php';

$page_title = HOTEL_NAME . ' | The Spot of Serenity & Comfort in Multan';
$page_description = 'Ramada by Wyndham Multan: four-star rooms in the city centre, near the shrine of Bahauddin Zakariya. See prices and book online.';
$active_page = 'home';
$body_class = 'page-home';
$page_scripts = [
    'assets/vendor/gsap-3.15.0/gsap.min.js',
    'assets/vendor/gsap-3.15.0/ScrollTrigger.min.js',
    'assets/js/spotlight.js',
    'assets/js/filmstrip.js',
    'assets/js/intro.js',
    'assets/js/home.js',
];

// Slideshow photos, set by the admin (FR-WEB-10).
$slides = site_photos('home_slide');

// Room types with their main photo. Prices and occupancy are set by the admin (FR-ROOM-01).
$room_types = db()->query(
    'SELECT rt.slug, rt.name, rt.beds, rt.feature, rt.max_adults, rt.max_children, rt.price_per_night,
            p.file AS photo
     FROM room_types rt
     LEFT JOIN room_type_photos p ON p.room_type_id = rt.id AND p.is_main = 1
     WHERE rt.is_active = 1
     ORDER BY rt.sort_order, rt.name'
)->fetchAll();

$restaurants = db()->query('SELECT name, hours FROM restaurants ORDER BY sort_order')->fetchAll();

// Key facts guests look for first (hotel policies, design.md section 6). Not shown on Ramada's home page.
$highlights = [
    ['fa-regular fa-clock', 'Check-in 2 PM', 'Check-out 12 PM'],
    ['fa-solid fa-mug-hot', 'Free breakfast', 'Daily buffet'],
    ['fa-solid fa-wifi', 'Free WiFi', 'In all rooms'],
    ['fa-solid fa-square-parking', 'Free parking', 'For hotel guests'],
    ['fa-solid fa-van-shuttle', 'Airport shuttle', 'Ask at booking'],
    ['fa-solid fa-person-swimming', 'Pool & gym', 'Free for guests'],
];

// Numbers shown in the welcome section. They count up when scrolled into view.
$stats = [
    [count($room_types), 'Room types'],
    [count($restaurants), 'Restaurants & lounges'],
    [4, 'Event halls'],
    ['24/7', 'Room service'],
];

// Facility texts; the photos are set by the admin (FR-WEB-10).
$facilities = [
    ['facility_restaurant', 'Multi Cuisine Restaurant', 'Buffets and à la carte at Buzz 76, from breakfast to midnight supper.', 'dining.php'],
    ['facility_meeting', 'Meeting Facilities', 'Four halls for meetings, weddings and events, up to the 540 m² Zainab Hall.', 'events.php'],
    ['facility_pool', 'Swimming Pool', 'Relax by the pool next to Cafe Saints. Free for every hotel guest.', 'gallery.php'],
    ['facility_lounge', 'Executive Lounge', 'A quiet lounge for business guests, open 8 AM to 11 PM.', 'dining.php'],
];

// How booking works, so guests know they never leave our site or pay online
$booking_steps = [
    ['fa-regular fa-calendar', 'Pick your dates', 'Choose check-in, check-out and how many guests.'],
    ['fa-solid fa-bed', 'Choose a room', 'See live prices and which rooms are free for your dates.'],
    ['fa-solid fa-money-bill-wave', 'Pay at the hotel', 'No card needed online. Confirmation by email and on screen.'],
];

// "Inside the hotel" filmstrip: every room type, then the public spaces.
// All photos are admin-editable (room photos FR-ROOM-08, site photos FR-WEB-10).
$film_cards = [];
foreach ($room_types as $room) {
    $film_cards[] = ['file' => $room['photo'] ?? '', 'name' => $room['name'], 'type' => 'Room', 'href' => 'room.php?type=' . $room['slug']];
}
foreach ([
    ['home_welcome', 'Lobby lounge', 'Public spaces', 'gallery.php'],
    ['facility_restaurant', 'Buzz 76', 'Restaurant', 'dining.php'],
    ['home_dining', 'Cafe Saints', 'Poolside cafe', 'dining.php'],
    ['facility_pool', 'Swimming pool', 'Leisure', 'gallery.php'],
    ['facility_lounge', 'Executive Lounge', 'Lounge', 'dining.php'],
] as [$location, $name, $type, $href]) {
    $film_photo = site_photo($location, $name);
    $film_cards[] = ['file' => $film_photo['file'], 'name' => $name, 'type' => $type, 'href' => $href];
}

$welcome_photo = site_photo('home_welcome', 'Hotel lobby');
$welcome_photo_2 = site_photo('home_welcome_2', 'Reception desk');
$dining_photo = site_photo('home_dining', 'Cafe Saints');
$multan_photo = site_photo('home_multan', 'Multan');

live_watch('room_types', 'room_type_photos'); // room prices and photos change by themselves
include 'includes/header.php';
?>

<!-- Opening intro (FR-UX-08), about 3.5 s: arriving at the hotel. An HD photo (Wyndham's own
     property photo, 2200px) of the building with its RAMADA sign pulls back slowly; the building
     darkens into the hero's colours as the logo rises in; then everything dissolves into the
     Home page. Shown once per visit; ?intro always shows it. The script right after it decides at
     once whether to show it. Without JavaScript, or with "reduce motion", it stays hidden. -->
<div class="intro" id="intro" hidden role="dialog" aria-modal="true" aria-label="Welcome to <?= e(HOTEL_NAME) ?>">
    <div class="intro-bg" aria-hidden="true">
        <img src="<?= e(url('assets/images/intro/exterior.jpg')) ?>" alt="" class="intro-shot" style="--focus: 66% 22%">
        <span class="intro-tint"></span>
    </div>
    <div class="intro-logo-wrap" aria-hidden="true">
        <img src="<?= e(url('assets/images/logo-white.png')) ?>" alt="" class="intro-logo" width="300" height="113">
        <p class="intro-sub"><span class="sub-gold">The Spot of Serenity</span> <span class="sub-dot">&amp;</span> <span class="sub-rose">Comfort</span></p>
    </div>
    <button type="button" class="intro-skip" id="introSkip">Skip intro <i class="fa-solid fa-forward" aria-hidden="true"></i></button>
</div>
<script>
    // Show the intro only on the first visit in this browser tab, and not for "reduce motion".
    // Adding ?intro to the address always plays it (handy for demos).
    (function () {
        var forced = /[?&]intro\b/.test(window.location.search);
        try {
            if (!forced && (sessionStorage.getItem('introSeen') || window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
                return;
            }
            sessionStorage.setItem('introSeen', '1');
        } catch (error) {
            return; // storage blocked: skip the intro rather than show it on every page load
        }
        document.getElementById('intro').hidden = false;
        document.documentElement.classList.add('intro-active');
    })();
</script>

<!-- 1. Hero: full-screen slideshow with headline and booking buttons -->
<section class="hero" aria-label="Welcome">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="7000" data-bs-pause="false">
        <div class="carousel-inner">
            <?php foreach ($slides as $i => $slide): ?>
                <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
                    <?php photo($slide['file'], $slide['alt_text'], 'hero-photo'); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="hero-shade" aria-hidden="true"></div>

    <div class="container hero-content">
        <p class="hero-eyebrow" data-hero-in><span class="stars" aria-label="4 star hotel">★★★★</span> City-centre hotel · Multan</p>
        <h1 data-hero-in>The Spot of Serenity <br class="d-none d-md-inline">&amp; Comfort in Multan</h1>
        <p class="hero-lead" data-hero-in>Four-star rooms, three restaurants and a pool, minutes from the shrines of the City of Saints.</p>
        <div class="hero-actions" data-hero-in>
            <a href="#search" class="btn-primary-red">Book your stay</a>
            <a href="dining.php" class="btn-glass">Explore dining</a>
        </div>
    </div>

    <div class="hero-controls container" data-hero-in>
        <div class="hero-dots" role="group" aria-label="Choose a photo">
            <?php foreach ($slides as $i => $slide): ?>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Photo <?= $i + 1 ?>: <?= e($slide['alt_text']) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="hero-btn" data-bs-target="#heroCarousel" data-bs-slide="prev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="hero-btn" id="heroPause" aria-label="Pause slideshow"><i class="fa-solid fa-pause"></i></button>
        <button type="button" class="hero-btn" data-bs-target="#heroCarousel" data-bs-slide="next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
</section>

<!-- 2. Availability search: a glass panel over the bottom of the hero -->
<div class="container search-wrap" id="search">
    <?php include 'includes/search-bar.php'; ?>
</div>

<!-- 3. Hotel highlights (not on Ramada's site) -->
<section class="highlights">
    <div class="container">
        <ul class="highlights-list">
            <?php foreach ($highlights as [$icon, $title, $text]): ?>
                <li>
                    <i class="<?= e($icon) ?>" aria-hidden="true"></i>
                    <span><strong><?= e($title) ?></strong><?= e($text) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- 4. Welcome: text, counters and a photo collage (text always visible) -->
<section class="section welcome">
    <div class="container">
        <div class="row g-4 gx-lg-5 gy-5 align-items-center">
            <div class="col-lg-6">
                <p class="eyebrow">Welcome to Ramada by Wyndham Multan</p>
                <h2 class="display-heading">A calm stay in the heart of the City of Saints</h2>
                <p>Ramada by Wyndham Multan welcomes you to the centre of the city, close to the shrine of Bahauddin Zakariya, Multan's main sights and its major businesses.</p>
                <p>Renovated over the years to be your top business hotel in Multan, we offer four-star comfort and easy access to the city's favourite attractions, for business and leisure travellers alike.</p>
                <ul class="stats">
                    <?php foreach ($stats as [$number, $label]): ?>
                        <li>
                            <strong<?= is_int($number) ? ' data-count="' . $number . '"' : '' ?>><?= e($number) ?></strong>
                            <span><?= e($label) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="about.php" class="text-link">Discover the hotel</a>
            </div>
            <div class="col-lg-6">
                <div class="collage">
                    <div class="collage-main" data-parallax="-6"><?php photo($welcome_photo['file'], $welcome_photo['alt_text'], 'reveal'); ?></div>
                    <div class="collage-small" data-parallax="8"><?php photo($welcome_photo_2['file'], $welcome_photo_2['alt_text'], 'reveal'); ?></div>
                    <div class="collage-badge"><strong>4★</strong><span>Four-star<br>comfort</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. Rooms: grid on desktop, swipeable row on phones -->
<section class="section section-alt">
    <div class="container" data-live-region="rooms">
        <?php section_heading('ROOMS', 'All Designed To Customize Your Need'); ?>
        <div class="room-grid">
            <?php foreach ($room_types as $room): ?>
                <article class="room-card" data-spotlight>
                    <a href="room.php?type=<?= e($room['slug']) ?>" class="room-photo" tabindex="-1" aria-hidden="true">
                        <?php photo($room['photo'] ?? '', $room['name'], 'reveal'); ?>
                        <?php if ($room['price_per_night'] !== null): ?>
                            <span class="price-chip">From PKR <?= money($room['price_per_night']) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="room-body">
                        <h3><a href="room.php?type=<?= e($room['slug']) ?>"><?= e($room['name']) ?></a></h3>
                        <ul class="room-facts">
                            <li><i class="fa-solid fa-user-group" aria-hidden="true"></i> <?= e($room['max_adults']) ?> <?= $room['max_adults'] == 1 ? 'Adult' : 'Adults' ?><?php if ($room['max_children'] > 0): ?>, <?= e($room['max_children']) ?> <?= $room['max_children'] == 1 ? 'Child' : 'Children' ?><?php endif; ?></li>
                            <li><i class="fa-solid fa-bed" aria-hidden="true"></i> <?= e($room['beds']) ?></li>
                            <?php if ($room['feature'] !== ''): ?>
                                <li class="feature"><i class="fa-solid fa-star" aria-hidden="true"></i> <?= e($room['feature']) ?></li>
                            <?php endif; ?>
                        </ul>
                        <div class="actions">
                            <a href="room.php?type=<?= e($room['slug']) ?>" class="text-link">Room Details</a>
                            <?php if ($room['price_per_night'] !== null): ?>
                                <a href="room.php?type=<?= e($room['slug']) ?>#book" class="btn-small-red">Book</a>
                            <?php else: ?>
                                <a href="contact.php" class="btn-small-outline">Ask for price</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="swipe-hint d-md-none" aria-hidden="true"><i class="fa-solid fa-hand-pointer"></i> Swipe to see all rooms</p>
    </div>
</section>

<!-- 6. How booking works (not on Ramada's site, which sends guests to Wyndham) -->
<section class="section steps">
    <div class="container">
        <?php section_heading('Book in 3 easy steps', 'Directly with the hotel, in under a minute'); ?>
        <ol class="steps-list">
            <?php foreach ($booking_steps as $i => [$icon, $title, $text]): ?>
                <li>
                    <span class="step-icon"><i class="<?= e($icon) ?>" aria-hidden="true"></i><span class="step-number"><?= $i + 1 ?></span></span>
                    <strong><?= e($title) ?></strong>
                    <span><?= e($text) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="text-center mt-4">
            <a href="#search" class="btn-primary-red">Check availability</a>
        </div>
    </div>
</section>

<!-- 7. Dining band (not on Ramada's home page) -->
<section class="dining-band">
    <div class="dining-bg" data-parallax="-10"><?php photo($dining_photo['file'], $dining_photo['alt_text']); ?></div>
    <div class="container">
        <div class="dining-card">
            <p class="eyebrow">Dining</p>
            <h2>Eat in, by the pool or in your room</h2>
            <p>Order from the full menu online, reserve a table, or have room service added straight to your room bill.</p>
            <ul class="dining-list">
                <?php foreach ($restaurants as $restaurant): ?>
                    <li><strong><?= e($restaurant['name']) ?></strong><span><?= e($restaurant['hours']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div class="hero-actions">
                <a href="dining.php" class="btn-primary-red">See the menu</a>
                <a href="reserve-table.php" class="btn-glass">Reserve a table</a>
            </div>
        </div>
    </div>
</section>

<!-- 8. Facilities: photo tiles with the text over the photo -->
<section class="section">
    <div class="container">
        <?php section_heading('Our Facilities & Services', 'Everything you need, under one roof'); ?>
        <div class="facility-grid">
            <?php foreach ($facilities as $i => [$location, $title, $text, $link]): ?>
                <?php $facility_photo = site_photo($location, $title); ?>
                <a href="<?= e($link) ?>" class="facility-tile<?= $i === 0 ? ' facility-tile-large' : '' ?>" data-spotlight data-spotlight-color="rgba(255, 255, 255, 0.18)" data-spotlight-size="300">
                    <?php photo($facility_photo['file'], $facility_photo['alt_text'], 'reveal'); ?>
                    <span class="facility-text">
                        <strong><?= e($title) ?></strong>
                        <span><?= e($text) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 9. Inside the hotel: 3D filmstrip of photo cards (adapted from ThreeUI, MIT) -->
<section class="section film-section" aria-labelledby="filmHeading">
    <div class="container">
        <div class="film-head">
            <div>
                <p class="eyebrow">Take a look inside</p>
                <h2 class="display-heading" id="filmHeading">Every room, every corner</h2>
            </div>
            <p class="film-help">Click a card to bring it forward, click it again to open it. Use the arrows or swipe to browse.</p>
        </div>
    </div>
    <div class="filmstrip" data-filmstrip role="region" aria-label="Hotel photos, use the arrow keys to browse">
        <div class="filmstrip-deck">
            <?php foreach ($film_cards as $i => $card): ?>
                <button type="button" class="film-card" data-href="<?= e($card['href']) ?>" aria-label="<?= e($card['name']) ?>, <?= e($card['type']) ?>">
                    <span class="film-photo"><?php photo($card['file'], $card['name']); ?></span>
                    <span class="film-footer">
                        <span class="film-index"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="film-meta">
                            <span class="film-name"><?= e($card['name']) ?></span>
                            <span class="film-type"><?= e($card['type']) ?></span>
                        </span>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="film-btn film-prev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="film-btn film-next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <div class="text-center mt-4">
        <a href="gallery.php" class="text-link">View the full gallery</a>
    </div>
</section>

<!-- 10. Around Multan | Reviews -->
<section class="section section-alt">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <a href="<?= url('about.php#nearby') ?>" class="multan-card">
                    <?php photo($multan_photo['file'], $multan_photo['alt_text'], 'reveal'); ?>
                    <span class="multan-caption">
                        <span class="eyebrow">Explore</span>
                        <strong>Multan, the City of Saints</strong>
                        <span>Centuries-old shrines, the old fort and Ghanta Ghar, close to the hotel <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                    </span>
                </a>
            </div>
            <div class="col-lg-6">
                <div class="reviews-card">
                    <p class="eyebrow">Guest reviews</p>
                    <h2 class="display-heading">What our guests say</h2>
                    <!-- FR-SIG-REV-03: the average rating and the latest 3 verified reviews go here once the feedback module is built. -->
                    <div class="reviews-empty">
                        <i class="fa-regular fa-comments" aria-hidden="true"></i>
                        <p>No reviews yet. Only guests who have actually stayed or ordered with us can leave a review, so every one you see here is marked <strong>✓ Verified stay</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 11. Closing call to action -->
<section class="cta-band">
    <div class="container">
        <div>
            <h2>Ready for your stay in Multan?</h2>
            <p>Check prices and book in under a minute. Pay at the hotel.</p>
        </div>
        <div class="hero-actions">
            <a href="#search" class="btn-white">Book your stay</a>
            <a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>" target="_blank" rel="noopener" class="btn-glass"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp us</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
