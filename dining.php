<?php
/*
 * Dining page (FR-WEB-02, FR-MENU-03, FR-ORD-01; design.md: Dining).
 * In the style the team chose (warm, editorial, like restaurant-amici.com, in Ramada red):
 *   1. a slowly drifting wall of food and restaurant photos behind the heading
 *   2. a moving strip of what's on offer
 *   3. each restaurant as a tilted menu card over its own photo
 *   4. a row of photos that slides sideways while scrolling
 *   5. the menu, printed-menu style, with "Add" buttons and a cart
 *   6. a "Reserve a table" card on a table photo
 * Photos are set by the admin (site_photos, FR-WEB-10); ones that aren't the hotel's own
 * are labelled "Illustrative photo" (D22). All text is visible without JavaScript or motion (D19).
 */
require_once 'includes/init.php';
require_once 'includes/orders.php';
require_once 'includes/menu-ui.php';

$restaurants = db()->query('SELECT * FROM restaurants ORDER BY sort_order, name')->fetchAll();
$menuSections = array_values(array_filter(menu_by_category(), function ($category) { return $category['items']; }));
$cart = cart_details();
$heroPhotos = site_photos('dining_hero');
$stripPhotos = site_photos('dining_strip');
$reservePhoto = site_photo('dining_reserve', 'A table laid for dinner');

// A few words about each restaurant, by its address name (the rest comes from the database)
$restaurantExtras = [
    'buzz-76'          => ['All-day dining', ['Breakfast buffet', 'Lunch & dinner buffet', 'À la carte', 'Sunday brunch']],
    'cafe-saints'      => ['Poolside café', ['Outdoor seating', 'International & traditional', 'Evenings by the pool']],
    'executive-lounge' => ['For business guests', ['Quiet lounge', 'Fresh cuisine', 'Tea & coffee']],
];
$ticker = ['Breakfast buffet', 'Sunday brunch', 'Hi-tea', 'Poolside evenings', 'Room service 24/7', 'Pakistani', 'Continental', 'Chinese', 'Desserts'];

// The hero wall: the photos split into 4 columns
$columns = [[], [], [], []];
foreach ($heroPhotos as $i => $p) {
    $columns[$i % 4][] = $p;
}

$page_title = 'Dining | ' . HOTEL_NAME;
$page_description = 'Buzz 76, Cafe Saints by the pool and the Executive Lounge at Ramada by Wyndham Multan. See the menu and order online for takeaway, your table or your room.';
$share_image = $heroPhotos[0]['file'] ?? 'home/cafe-saints.jpg';
$active_page = 'dining';
$body_class = 'page-dining';
$page_scripts = ['assets/vendor/gsap-3.15.0/gsap.min.js', 'assets/vendor/gsap-3.15.0/ScrollTrigger.min.js', 'assets/js/dining.js'];
live_watch('menu_items', 'menu_categories'); // sold-out dishes and new prices show at once
include 'includes/header.php';
?>

<!-- 1. Drifting photo wall behind the heading (idea from seiste.com, made light: plain CSS, no 3D) -->
<section class="dine-hero">
    <div class="dine-wall" aria-hidden="true">
        <?php foreach ($columns as $c => $column): if (!$column) continue; ?>
            <div class="dine-col<?= $c % 2 ? ' is-down' : '' ?>">
                <?php for ($copy = 0; $copy < 2; $copy++): // twice, so the loop has no gap ?>
                    <?php foreach ($column as $p): ?>
                        <figure class="dine-tile"><?php photo_row($p); ?></figure>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="dine-hero-text">
        <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> Dining</nav>
        <p class="eyebrow">Ramada by Wyndham Multan</p>
        <h1>Dining that feels<br>like a <em>celebration</em></h1>
        <p>Three places to eat, from breakfast buffets to evenings by the pool. Order online for takeaway, your table or your room.</p>
        <div class="hero-actions">
            <a href="#menu" class="btn-primary-red">See the menu</a>
            <a href="<?= url('reserve-table.php') ?>" class="btn-glass">Reserve a table</a>
        </div>
    </div>
</section>

<!-- 2. Moving strip of what's on offer -->
<div class="dine-ticker" aria-label="On offer: <?= e(implode(', ', $ticker)) ?>">
    <div class="dine-ticker-track" aria-hidden="true">
        <?php for ($copy = 0; $copy < 2; $copy++): ?>
            <span><?php foreach ($ticker as $word): ?><?= e($word) ?> <i class="fa-solid fa-star-of-life"></i> <?php endforeach; ?></span>
        <?php endfor; ?>
    </div>
</div>

<!-- 3. The restaurants: a tilted menu card over each restaurant's own photo -->
<section class="section dine-places" id="restaurants" aria-labelledby="placesHeading">
    <div class="container">
        <div class="section-heading">
            <h2 id="placesHeading">Where to eat</h2>
            <p class="subtitle">Three settings, one kitchen that never sleeps</p>
            <span class="divider"></span>
        </div>
        <?php foreach ($restaurants as $i => $place):
            $photos = site_photos('dining_' . $place['slug']);
            [$kind, $tags] = $restaurantExtras[$place['slug']] ?? ['Restaurant', []];
        ?>
            <article class="place<?= $i % 2 ? ' is-flipped' : '' ?>" id="<?= e($place['slug']) ?>">
                <div class="place-photos">
                    <figure class="place-photo" data-parallax-photo><?php photo_row($photos[0] ?? ['file' => '', 'alt_text' => $place['name']]); ?></figure>
                    <?php if (isset($photos[1])): ?>
                        <figure class="place-inset"><?php photo_row($photos[1]); ?></figure>
                    <?php endif; ?>
                </div>
                <div class="place-card" data-tilt-card>
                    <span class="place-number" aria-hidden="true">0<?= $i + 1 ?></span>
                    <p class="place-kind"><?= e($kind) ?></p>
                    <h3><?= e($place['name']) ?></h3>
                    <p><?= e($place['description']) ?></p>
                    <p class="place-hours"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e($place['hours']) ?></p>
                    <?php if ($tags): ?>
                        <ul class="place-tags">
                            <?php foreach ($tags as $tag): ?><li><?= e($tag) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="place-actions">
                        <a href="#menu" class="btn-small-red">See the menu</a>
                        <a href="<?= url('reserve-table.php?restaurant=' . $place['id']) ?>" class="btn-small-outline">Reserve a table</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- 4. A row of photos that slides sideways while scrolling (scrolls by hand without JavaScript) -->
<?php if ($stripPhotos): ?>
<section class="dine-strip" aria-labelledby="stripHeading">
    <div class="container">
        <p class="eyebrow">Moments</p>
        <h2 id="stripHeading">A taste of Ramada Multan</h2>
    </div>
    <div class="dine-strip-scroller">
        <div class="dine-strip-row" data-strip>
            <?php foreach ($stripPhotos as $i => $p): ?>
                <figure class="strip-card" style="--tilt: <?= [-3, 2, -1.5, 3, -2.5, 1.5, -2, 2.5][$i % 8] ?>deg">
                    <div class="strip-photo"><?php photo_row($p); ?></div>
                    <figcaption><?= e($p['alt_text']) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 5. The menu: printed-menu style, with Add buttons (FR-MENU-03, FR-ORD-01) -->
<section class="section dine-menu" id="menu" aria-labelledby="menuHeading">
    <div class="container">
        <div class="menu-paper">
            <header class="menu-paper-head">
                <p class="eyebrow">Order online</p>
                <h2 id="menuHeading">The Menu</h2>
                <p>Takeaway, to your table, or to your room if you're staying with us. Prices in PKR.</p>
                <?php show_flash(); ?>
            </header>

            <?php if (!$menuSections): ?>
                <p class="text-center admin-muted">The menu is being updated. Please call <?= e(HOTEL_PHONE) ?> to order.</p>
            <?php else: ?>
                <?php menu_sections($menuSections, 'dining.php'); ?>
            <?php endif; ?>
            <ul class="menu-notes">
                <li><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Takeaway: collect from Buzz 76</li>
                <li><i class="fa-solid fa-utensils" aria-hidden="true"></i> Dine-in: we bring it to your table</li>
                <li><i class="fa-solid fa-bell-concierge" aria-hidden="true"></i> Room service: added to your room bill</li>
            </ul>
        </div>
    </div>
</section>

<!-- 6. Reserve a table: a card on a table photo -->
<section class="dine-reserve" id="reserve" aria-labelledby="reserveHeading">
    <div class="dine-reserve-bg" data-parallax-photo><?php photo_row($reservePhoto); ?></div>
    <div class="container">
        <div class="reserve-card" data-tilt-card>
            <p class="place-kind">Buzz 76 · Cafe Saints · Executive Lounge</p>
            <h2 id="reserveHeading">Reserve a table</h2>
            <p>Birthdays, family dinners or a quiet business lunch: tell us the day, the time and how many of you, and we'll keep a table ready.</p>
            <form action="<?= url('reserve-table.php') ?>#tables" method="get" class="reserve-quick">
                <div class="reserve-quick-fields">
                    <label><span>Restaurant</span>
                        <select name="restaurant"><?php foreach ($restaurants as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Date</span><input type="date" name="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>"></label>
                    <label><span>Guests</span><select name="party"><?php for ($i = 1; $i <= 12; $i++): ?><option<?= $i === 2 ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
                </div>
                <button type="submit" class="btn-primary-red w-100">FIND A TABLE</button>
                <p class="reserve-or">or call <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>"><?= e(HOTEL_PHONE) ?></a></p>
            </form>
        </div>
    </div>
</section>

<?php cart_ui($cart, 'dining.php'); ?>
<?php include 'includes/footer.php'; ?>
