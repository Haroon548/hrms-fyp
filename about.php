<?php
/*
 * About Us (FR-WEB-06; design.md: About Us). Ramada's version puts its text over a pool
 * photo, which is hard to read; ours keeps the photo and the text apart, in the Home style (D20):
 *   1. a photo banner of the building
 *   2. the welcome text (from ramadamultan.com/about-us) next to a photo collage, with numbers
 *   3. the hotel's five core values
 *   4. what's inside the hotel (photo tiles)
 *   5. nearby attractions, each with Get directions (Google Maps link, no API key)
 *   6. a closing call to action
 */
require_once 'includes/init.php';
require_once 'includes/rooms.php';

$exterior = site_photo('exterior', 'Ramada by Wyndham Multan building');
$lobby = site_photo('home_welcome', 'Hotel lobby lounge');
$reception = site_photo('home_welcome_2', 'Reception desk');
$typeCount = count(room_types());
$restaurantCount = (int) db()->query('SELECT COUNT(*) FROM restaurants')->fetchColumn();

// The hotel's core values (ramadamultan.com/about-us), with a line on what each means for guests
$values = [
    ['fa-solid fa-scale-balanced', 'Integrity', 'Honest prices, honest photos and promises we keep.'],
    ['fa-solid fa-clipboard-check', 'Accountability', 'If something isn\'t right, we own it and fix it.'],
    ['fa-solid fa-people-group', 'Inclusive', 'Families, business travellers and pilgrims: everyone is welcome.'],
    ['fa-solid fa-hand-holding-heart', 'Caring', 'Small things done well, from the welcome to check-out.'],
    ['fa-solid fa-face-smile-beam', 'Fun', 'Poolside evenings, buffets and celebrations to remember.'],
];

// Inside the hotel (photos set by the admin, FR-WEB-10)
$facilities = [
    ['facility_restaurant', 'Three places to eat', 'Buzz 76, Cafe Saints by the pool and the Executive Lounge.', 'dining.php'],
    ['facility_pool', 'Pool & health club', 'A swimming pool, gym and health club, free for guests.', 'gallery.php?tab=health'],
    ['facility_meeting', 'Meetings & events', 'Four halls, from a 12-seat board room to weddings.', 'events.php'],
    ['facility_lounge', 'Rooms & suites', $typeCount . ' room types, from Pool Wing rooms to the Presidential Suite.', 'rooms.php'],
];

// Nearby attractions (ramadamultan.com/about-us). Photos: Wikimedia Commons, credited below (D21).
$attractions = [
    ['Shrine of Shah Rukn-e-Alam', 'The great brick tomb of the 14th-century saint, the symbol of Multan.', 'gallery/multan/multan-01.jpg', 'Photo: Smjunaid123 / Wikimedia Commons, CC BY-SA 3.0'],
    ['Multan Fort', 'The old fort hill (Qasim Bagh) where the city\'s shrines stand.', '', ''],
    ['Gates of the City', 'The historic gates of the walled old city, such as Delhi and Bohar Gate.', '', ''],
    ['Ghanta Ghar', 'The clock tower at the heart of the old city and its bazaars.', 'gallery/multan/multan-07.jpg', 'Photo: Junaidahmadj / Wikimedia Commons, CC BY-SA 3.0'],
    ['Mausoleum of Sheikh Bahauddin Zakariya', 'The shrine of the Sufi saint, close to the hotel.', '', ''],
    ['Lal Suhanra National Park', 'A wildlife park and desert lakes, a day trip from Multan.', '', ''],
];

function directions_link($place)
{
    return 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode(HOTEL_NAME . ', ' . HOTEL_ADDRESS) . '&destination=' . urlencode($place . ', Multan, Pakistan');
}

$page_title = 'About Us | ' . HOTEL_NAME;
$page_description = 'Ramada by Wyndham Multan is the only four-star hotel in downtown Multan, near the shrine of Bahauddin Zakariya. Our values, our facilities and what to see nearby.';
$share_image = $exterior['file'] ?: 'slides/slide-1.jpg';
$active_page = 'about';
$page_scripts = ['assets/vendor/gsap-3.15.0/gsap.min.js', 'assets/vendor/gsap-3.15.0/ScrollTrigger.min.js', 'assets/js/spotlight.js', 'assets/js/home.js'];
include 'includes/header.php';
?>

<section class="about-hero">
    <div class="about-hero-photo" data-parallax="12"><?php photo($exterior['file'], $exterior['alt_text']); ?></div>
    <div class="container">
        <div class="about-hero-card" data-hero-in>
            <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> About Us</nav>
            <p class="eyebrow">About us</p>
            <h1>Welcome to Ramada by Wyndham Multan</h1>
            <p>The only four-star hotel in downtown Multan, in the heart of the City of Saints.</p>
        </div>
    </div>
</section>

<section class="section welcome">
    <div class="container">
        <div class="row g-4 gx-lg-5 gy-5 align-items-center">
            <div class="col-lg-6">
                <p class="eyebrow">Our story</p>
                <h2 class="display-heading">In the heart of Multan, close to everything</h2>
                <p>Ramada by Wyndham Multan is the only four-star hotel and downtown property in Multan. It stands in the heart of the city near the shrine of Bahauddin Zakariya, close to the city's main sights and major businesses, which makes it an easy choice for every traveller.</p>
                <p>We offer well-run executive rooms, multi-cuisine dining, a gym, a swimming pool and a health club, and we keep renovating so the hotel stays the best place in Multan for business and leisure stays alike.</p>
                <ul class="stats">
                    <li><strong data-count="<?= $typeCount ?>"><?= $typeCount ?></strong><span>Room types</span></li>
                    <li><strong data-count="<?= $restaurantCount ?>"><?= $restaurantCount ?></strong><span>Restaurants & lounges</span></li>
                    <li><strong data-count="4">4</strong><span>Event halls</span></li>
                    <li><strong>24/7</strong><span>Room service</span></li>
                </ul>
                <div class="hero-actions about-actions">
                    <a href="<?= url('rooms.php') ?>" class="btn-primary-red">See our rooms</a>
                    <a href="<?= url('contact.php') ?>" class="text-link">Contact us</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="collage">
                    <div class="collage-main" data-parallax="-6"><?php photo($lobby['file'], $lobby['alt_text'], 'reveal'); ?></div>
                    <div class="collage-small" data-parallax="8"><?php photo($reception['file'], $reception['alt_text'], 'reveal'); ?></div>
                    <div class="collage-badge"><strong>4★</strong><span>The only four-star<br>downtown hotel</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section values-band" aria-labelledby="valuesHeading">
    <div class="container">
        <p class="eyebrow">What we stand for</p>
        <h2 class="display-heading" id="valuesHeading">Our core values</h2>
        <ol class="values">
            <?php foreach ($values as $i => [$icon, $title, $text]): ?>
                <li class="value-card" data-spotlight data-spotlight-color="rgba(255, 255, 255, 0.14)">
                    <span class="value-number" aria-hidden="true">0<?= $i + 1 ?></span>
                    <i class="<?= $icon ?>" aria-hidden="true"></i>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php section_heading('Inside the hotel', 'Everything you need, under one roof'); ?>
        <div class="facility-grid">
            <?php foreach ($facilities as $i => [$location, $title, $text, $link]): $p = site_photo($location, $title); ?>
                <a href="<?= url($link) ?>" class="facility-tile<?= $i === 0 ? ' facility-tile-large' : '' ?>" data-spotlight data-spotlight-color="rgba(255, 255, 255, 0.18)" data-spotlight-size="300">
                    <?php photo($p['file'], $p['alt_text'], 'reveal'); ?>
                    <span class="facility-text"><strong><?= e($title) ?></strong><span><?= e($text) ?></span></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt" id="nearby" aria-labelledby="nearHeading">
    <div class="container">
        <div class="section-heading">
            <h2 id="nearHeading">Nearby attractions</h2>
            <p class="subtitle">The City of Saints on your doorstep</p>
            <span class="divider"></span>
        </div>
        <div class="attractions">
            <?php foreach ($attractions as $i => [$name, $text, $file, $credit]): ?>
                <article class="attraction<?= $file ? '' : ' no-photo' ?>">
                    <div class="attraction-photo">
                        <?php if ($file): ?>
                            <?php photo($file, $name, 'reveal'); ?>
                        <?php else: ?>
                            <span class="attraction-icon" aria-hidden="true"><i class="fa-solid <?= ['fa-mosque', 'fa-chess-rook', 'fa-archway', 'fa-clock', 'fa-mosque', 'fa-tree'][$i] ?>"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="attraction-text">
                        <h3><?= e($name) ?></h3>
                        <p><?= e($text) ?></p>
                        <a href="<?= e(directions_link($name)) ?>" target="_blank" rel="noopener" class="text-link"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Get directions</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="photo-credits">
            <?php foreach (array_filter($attractions, function ($a) { return $a[3] !== ''; }) as [$name, , , $credit]): ?>
                <span><?= e($name) ?>: <?= e($credit) ?></span>
            <?php endforeach; ?>
        </p>
        <p class="text-center mt-3"><a href="<?= url('gallery.php?tab=multan') ?>" class="text-link">More photos of Multan</a></p>
    </div>
</section>

<section class="cta-band">
    <div class="container">
        <div>
            <h2>Stay in the heart of the City of Saints</h2>
            <p>Book directly with us: pay at the hotel, free cancellation until 6 PM on the day you arrive.</p>
        </div>
        <div class="hero-actions">
            <a href="<?= url('rooms.php') ?>" class="btn-white">Book your stay</a>
            <a href="<?= url('reserve-table.php') ?>" class="btn-glass">Reserve a table</a>
        </div>
    </div>
</section>
<?php include 'includes/footer.php';
