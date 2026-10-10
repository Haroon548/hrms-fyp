<?php
/*
 * Admin: printable QR code cards, one per dining table (FR-SIG-QR-01; design.md: Printable table QR cards).
 * Each code holds the link to that table's menu, built from SITE_URL in config.php, so after
 * the site moves to a new address the cards just need printing again.
 * The codes are drawn in the browser by qrcode-generator (MIT, stored in assets/vendor/, D15).
 *   table-qr.php            → all tables
 *   table-qr.php?restaurant=2 → one restaurant's tables
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';
require_once '../includes/admin-layout.php';
require_admin();

$restaurants = db()->query('SELECT id, name FROM restaurants ORDER BY sort_order, name')->fetchAll();
$only = (int) ($_GET['restaurant'] ?? 0);
$stmt = db()->prepare(
    'SELECT t.id, t.table_number, t.seats, r.name AS restaurant
     FROM dining_tables t JOIN restaurants r ON r.id = t.restaurant_id
     WHERE t.is_active = 1' . ($only ? ' AND r.id = ?' : '') . '
     ORDER BY r.sort_order, LENGTH(t.table_number), t.table_number'
);
$stmt->execute($only ? [$only] : []);
$tables = $stmt->fetchAll();
$isLocal = preg_match('#//(localhost|127\.0\.0\.1)#', SITE_URL);

live_watch('dining_tables', 'restaurants'); // updates by itself when these change
admin_open('Table QR codes', 'table-qr', 'Print these and put one on each table. Guests scan it to see the menu and order to that table.',
    '<button type="button" class="btn-small-red" data-print><i class="fa-solid fa-print" aria-hidden="true"></i> Print cards</button>');
?>

<?php if ($isLocal): ?>
    <div class="notice notice-info no-print" role="status"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>These codes point to <strong><?= e(SITE_URL) ?></strong>, which only works on this computer. Phones can't open it, so print the real cards after the site is online and <code>SITE_URL</code> in config.php has the live address.</span></div>
<?php endif; ?>

<div class="admin-filter no-print">
    <span>Show:</span>
    <a href="<?= url('admin/table-qr.php') ?>" class="<?= $only ? '' : 'active' ?>">All tables</a>
    <?php foreach ($restaurants as $r): ?>
        <a href="<?= url('admin/table-qr.php?restaurant=' . $r['id']) ?>" class="<?= $only === (int) $r['id'] ? 'active' : '' ?>"><?= e($r['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$tables): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></span>
        <h2>No tables</h2>
        <p>Dining tables are added with table reservations.</p>
    </div>
<?php else: ?>
    <div class="qr-cards">
        <?php foreach ($tables as $table): $link = table_qr_link($table['id']); ?>
            <article class="qr-card">
                <img src="<?= url('assets/images/logo.png') ?>" alt="<?= e(HOTEL_NAME) ?>" class="qr-logo" width="140" height="53">
                <p class="qr-title">Scan to order</p>
                <div class="qr-code" data-qr="<?= e($link) ?>" role="img" aria-label="QR code for table <?= e($table['table_number']) ?>">
                    <noscript><span class="qr-fallback"><?= e($link) ?></span></noscript>
                </div>
                <p class="qr-table">Table <?= e($table['table_number']) ?></p>
                <p class="qr-place"><?= e($table['restaurant']) ?> · <?= (int) $table['seats'] ?> seats</p>
                <p class="qr-help">Point your phone camera at the code. No app or account needed.</p>
                <a href="<?= e($link) ?>" class="qr-link no-print" target="_blank" rel="noopener">Open this table's menu <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="<?= url('assets/vendor/qrcode-generator-1.4.4/qrcode.js') ?>"></script>
<script>
    // Draw each table's QR code as a sharp SVG (prints cleanly at any size)
    document.querySelectorAll('[data-qr]').forEach(function (box) {
        if (!window.qrcode) return;
        var qr = qrcode(0, 'M'); // size chosen automatically, medium error correction
        qr.addData(box.getAttribute('data-qr'));
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
    });
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });
</script>
<?php admin_close();
