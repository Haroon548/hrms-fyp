<?php
/*
 * Gallery (FR-WEB-03, FR-WEB-22, FR-UX-03; design.md: Gallery).
 * Photos orbit the heading; tabs filter the grid without reloading (GSAP Flip);
 * any photo opens in the photo viewer. ?tab=rooms opens on a tab.
 */
require_once 'includes/init.php';

$photos = db()->query('SELECT category, file, caption, credit FROM gallery_images ORDER BY sort_order, id')->fetchAll();

// Tabs in Ramada's order, plus our Multan tab; only categories that have photos
$tabOrder = ['Banquet', 'Health & Recreation', 'Lobby', 'Restaurants', 'Rooms', 'Multan'];
$counts = array_count_values(array_column($photos, 'category'));
$tabs = ['all' => ['All', count($photos)]];
foreach ($tabOrder as $category) {
    if (!empty($counts[$category])) {
        $tabs[category_key($category)] = [$category, $counts[$category]];
    }
}
$active = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';

// "Health & Recreation" → "health-recreation"
function category_key($category)
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($category)), '-');
}

// Small copy of a gallery photo for the grid (same path under gallery/thumbs/)
function gallery_thumb($file)
{
    $thumb = 'gallery/thumbs/' . substr($file, strlen('gallery/'));
    return is_file(__DIR__ . '/assets/images/' . $thumb) ? $thumb : $file;
}

// 16 photos for the ring around the heading, spread across the categories
$orbit = [];
$byCategory = [];
foreach ($photos as $p) {
    $byCategory[$p['category']][] = $p;
}
for ($round = 0; count($orbit) < 16 && $round < 10; $round++) {
    foreach ($byCategory as $list) {
        if (isset($list[$round * 2]) && count($orbit) < 16) {
            $orbit[] = $list[$round * 2];
        }
    }
}


$page_title = 'Gallery | ' . HOTEL_NAME;
$page_description = 'Photos of Ramada by Wyndham Multan: rooms, restaurants, the lobby, events, the health club, and the city of Multan.';
$active_page = 'gallery';
$extra_styles = ['assets/vendor/glightbox-3.3.1/glightbox.min.css'];
$page_scripts = [
    'assets/vendor/glightbox-3.3.1/glightbox.min.js',
    'assets/vendor/gsap-3.15.0/gsap.min.js',
    'assets/vendor/gsap-3.15.0/Flip.min.js',
    'assets/js/gallery.js',
];
include 'includes/header.php';
?>

<!-- Heading with hotel photos slowly orbiting it (FR-WEB-22) -->
<section class="gallery-hero" aria-labelledby="galleryHeading">
    <div class="orbit" id="orbit" aria-hidden="true">
        <div class="orbit-ring" style="--n: <?= count($orbit) ?>">
            <?php foreach ($orbit as $i => $p): ?>
                <span class="orbit-card" style="--i: <?= $i ?>"><img src="<?= e(url('assets/images/' . gallery_thumb($p['file']))) ?>" alt=""></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="gallery-hero-text">
        <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> Gallery</nav>
        <h1 id="galleryHeading">Gallery</h1>
        <p><?= count($photos) ?> photos of the hotel and the City of Saints</p>
    </div>
</section>

<section class="gallery-section">
    <div class="gallery-tabs-bar">
        <div class="container">
            <div class="gallery-tabs" role="group" aria-label="Show photos of">
                <?php foreach ($tabs as $key => [$label, $count]): ?>
                    <a href="<?= url('gallery.php' . ($key === 'all' ? '' : '?tab=' . $key)) ?>" class="gallery-tab<?= $key === $active ? ' active' : '' ?>"
                       data-filter="<?= e($key) ?>" aria-pressed="<?= $key === $active ? 'true' : 'false' ?>">
                        <?= e($label) ?> <span><?= $count ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <p class="gallery-count" id="galleryCount" aria-live="polite"></p>
        <div class="gallery-grid" id="galleryGrid">
            <?php foreach ($photos as $p): ?>
                <?php $key = category_key($p['category']); ?>
                <a href="<?= e(url('assets/images/' . $p['file'])) ?>" class="gallery-item<?= $active !== 'all' && $active !== $key ? ' is-hidden' : '' ?>"
                   data-category="<?= e($key) ?>" data-title="<?= e($p['caption']) ?>"
                   <?= $p['credit'] !== '' ? 'data-description="' . e($p['credit']) . '"' : '' ?>>
                    <img src="<?= e(url('assets/images/' . gallery_thumb($p['file']))) ?>" alt="<?= e($p['caption'] . ($p['category'] === 'Multan' ? '' : ', Ramada Multan')) ?>" loading="lazy">
                    <span class="gallery-caption"><?= e($p['caption']) ?></span>
                    <span class="zoom-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                </a>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<?php include 'includes/footer.php'; ?>
