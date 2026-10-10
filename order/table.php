<?php
/*
 * The menu opened by scanning a table's QR code (FR-SIG-QR-02, design.md: QR table menu).
 *   order/table.php?t=5
 * Shows "Table 5 · Buzz 76" at the top and the menu with the cart; the order is dine-in
 * for this table, and guests can order without an account (FR-SIG-QR-03).
 * The table is remembered on the phone for a few hours; ?leave=1 forgets it.
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';
require_once '../includes/menu-ui.php';

if (isset($_GET['leave'])) {
    unset($_SESSION['qr_table']);
    redirect('dining.php#menu');
}

$table = find_table($_GET['t'] ?? ($_SESSION['qr_table']['id'] ?? 0));
if (!$table) {
    http_response_code(404);
    $page_title = 'Table not found | ' . HOTEL_NAME;
    include '../includes/header.php';
    ?>
    <section class="section"><div class="container"><div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></span>
        <h1 class="h2">We couldn't find this table</h1>
        <p>The code may be old. Please ask a waiter, or see the full menu here.</p>
        <a href="<?= url('dining.php#menu') ?>" class="btn-primary-red">See the menu</a>
    </div></div></section>
    <?php
    include '../includes/footer.php';
    exit;
}
$_SESSION['qr_table'] = ['id' => (int) $table['id'], 'at' => time()];

$here = 'order/table.php?t=' . $table['id'];
$menuSections = array_values(array_filter(menu_by_category(), function ($category) { return $category['items']; }));
$cart = cart_details();

$page_title = 'Table ' . $table['table_number'] . ' · ' . $table['restaurant'] . ' | ' . HOTEL_NAME;
$active_page = 'dining';
$body_class = 'page-dining page-table';
live_watch('menu_items', 'menu_categories'); // sold-out dishes and new prices show at once
$page_scripts = ['assets/js/dining.js'];
include '../includes/header.php';
?>
<section class="table-banner">
    <div class="container">
        <span class="table-badge"><i class="fa-solid fa-utensils" aria-hidden="true"></i> Table <?= e($table['table_number']) ?></span>
        <h1><?= e($table['restaurant']) ?></h1>
        <p>Choose your dishes and we'll bring them to your table. No account needed; you pay at the restaurant.</p>
    </div>
</section>

<section class="section dine-menu" id="menu" aria-labelledby="menuHeading">
    <div class="container">
        <div class="menu-paper">
            <header class="menu-paper-head">
                <h2 id="menuHeading">The Menu</h2>
                <p>Prices in PKR. Tap <strong>+</strong> to add a dish.</p>
                <?php show_flash(); ?>
            </header>
            <?php if ($menuSections): ?>
                <?php menu_sections($menuSections, $here); ?>
            <?php else: ?>
                <p class="text-center admin-muted">The menu is being updated. Please ask a waiter.</p>
            <?php endif; ?>
        </div>
        <p class="table-leave"><a href="<?= url('order/table.php?leave=1') ?>">Not at table <?= e($table['table_number']) ?>? See the full menu</a></p>
    </div>
</section>

<?php cart_ui($cart, $here); ?>
<?php include '../includes/footer.php';
