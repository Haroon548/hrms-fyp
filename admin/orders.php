<?php
/*
 * Admin: food orders (FR-ORD-03). Each order is a card with its dishes, where it goes
 * (table, room or takeaway), the kitchen note, the customer and how long ago it came in,
 * plus buttons for the next step: Start preparing → Mark ready → Mark served / collected.
 * The kitchen itself uses the full-screen kitchen.php (three columns, refreshes itself, sound).
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_once '../includes/admin-layout.php';
require_admin();

$views = [
    'active'    => ['Active', "o.status IN ('pending', 'preparing', 'ready')", 'o.created_at'],
    'today'     => ['Today', 'DATE(o.created_at) = CURDATE()', 'o.created_at DESC'],
    'completed' => ['Completed', "o.status = 'completed'", 'o.updated_at DESC'],
    'cancelled' => ['Cancelled', "o.status = 'cancelled'", 'o.updated_at DESC'],
    'all'       => ['All orders', '1 = 1', 'o.created_at DESC'],
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'active';
$here = 'admin/orders.php?view=' . $view;

if (admin_action() === 'status') {
    $order = find_order((int) ($_POST['id'] ?? 0));
    $status = (string) ($_POST['status'] ?? '');
    if (!$order) {
        flash('error', 'That order no longer exists.');
    } elseif ($problem = change_order_status($order, $status)) {
        flash('error', $problem);
    } else {
        if ($status === 'completed') {
            invoice_for_order($order['id']); // dine-in / takeaway get their own invoice (FR-BILL-01)
        }
        flash('success', 'Order ' . order_number($order) . ': ' . [
            'preparing' => 'being prepared.', 'ready' => 'ready.', 'completed' => 'completed.', 'cancelled' => 'cancelled.',
        ][$status]);
    }
    redirect($here . '#order-' . (int) ($_POST['id'] ?? 0));
}

$tabCounts = [];
foreach ($views as $key => [$label, $where]) {
    $tabCounts[$key] = (int) db()->query("SELECT COUNT(*) FROM orders o WHERE $where")->fetchColumn();
}
[$label, $where, $orderBy] = $views[$view];
$ids = db()->query("SELECT o.id FROM orders o WHERE $where ORDER BY $orderBy LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$orders = array_map('find_order', $ids);

// "5 min ago", "2 h ago"
function time_ago($datetime)
{
    $minutes = max(0, (int) floor((time() - strtotime($datetime)) / 60));
    if ($minutes < 1) return 'just now';
    if ($minutes < 60) return $minutes . ' min ago';
    if ($minutes < 24 * 60) return floor($minutes / 60) . ' h ' . ($minutes % 60) . ' min ago';
    return date('j M, g:i A', strtotime($datetime));
}

live_watch('orders', 'invoices'); // updates by itself when these change
admin_open('Orders', 'orders', 'Food orders from the website: move each one along as the kitchen works on it',
    '<a href="' . url('admin/kitchen.php') . '" class="btn-small-red"><i class="fa-solid fa-fire-burner" aria-hidden="true"></i> Kitchen screen</a>'
    . '<a href="' . url('admin/menu.php') . '" class="btn-small-outline"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Edit menu</a>');
?>

<nav class="account-tabs admin-tabs" aria-label="Order views">
    <?php foreach ($views as $key => [$tabLabel]): ?>
        <a href="<?= url('admin/orders.php?view=' . $key) ?>" class="<?= $key === $view ? 'active' : '' ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>>
            <?= e($tabLabel) ?><?php if ($tabCounts[$key] && in_array($key, ['active', 'today'], true)): ?> <span class="tab-count"><?= $tabCounts[$key] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$orders): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-utensils" aria-hidden="true"></i></span>
        <h2><?= $view === 'active' ? 'No orders waiting' : 'No orders here' ?></h2>
        <p>Orders placed on the website show up here straight away.</p>
    </div>
<?php else: ?>
    <div class="order-cards">
        <?php foreach ($orders as $o): [$typeLabel, $typeIcon] = ORDER_TYPES[$o['order_type']]; ?>
            <article class="order-card is-<?= e($o['status']) ?>" id="order-<?= $o['id'] ?>">
                <header>
                    <span class="order-card-place"><i class="<?= $typeIcon ?>" aria-hidden="true"></i> <?= e(order_place($o)) ?></span>
                    <?= status_badge($o['status']) ?>
                </header>
                <p class="order-card-meta">Order <?= e(order_number($o)) ?> · <?= e($typeLabel) ?> · <time datetime="<?= e(date('c', strtotime($o['created_at']))) ?>"><?= e(time_ago($o['created_at'])) ?></time></p>
                <ul class="order-card-items">
                    <?php foreach ($o['items'] as $item): ?>
                        <li><span><?= (int) $item['quantity'] ?>×</span> <?= e($item['item_name']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($o['notes'] !== ''): ?>
                    <p class="order-card-note"><i class="fa-solid fa-message" aria-hidden="true"></i> <?= e($o['notes']) ?></p>
                <?php endif; ?>
                <p class="order-card-guest"><i class="fa-regular fa-user" aria-hidden="true"></i> <?= e($o['guest_name']) ?><?= $o['guest_phone'] ? ' · <a href="tel:' . e(preg_replace('/[^0-9+]/', '', $o['guest_phone'])) . '">' . e($o['guest_phone']) . '</a>' : '' ?>
                    <span class="order-card-total">PKR <?= money($o['total']) ?></span></p>
                <?php if ($o['status'] === 'completed' && $o['order_type'] !== 'room_service'):
                    $inv = db()->prepare('SELECT id, invoice_number, status FROM invoices WHERE order_id = ?'); $inv->execute([$o['id']]); $inv = $inv->fetch(); ?>
                    <?php if ($inv): ?><p class="order-card-invoice"><a href="<?= url('invoice/view.php?id=' . $inv['id']) ?>"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> <?= e($inv['invoice_number']) ?></a> <?= status_badge($inv['status']) ?></p><?php endif; ?>
                <?php elseif ($o['order_type'] === 'room_service' && $o['status'] !== 'cancelled'): ?>
                    <p class="order-card-invoice admin-muted"><i class="fa-solid fa-bed" aria-hidden="true"></i> On the room bill (<?= e($o['booking_reference']) ?>)</p>
                <?php endif; ?>
                <?php $moves = next_order_statuses($o); if ($moves): ?>
                    <div class="order-card-actions">
                        <?php foreach ($moves as $status => $buttonLabel): ?>
                            <form method="post"<?= $status === 'cancelled' ? ' data-confirm="Cancel order ' . e(order_number($o)) . '?"' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="status" value="<?= $status ?>">
                                <button type="submit" class="<?= $status === 'cancelled' ? 'btn-small-outline' : 'btn-small-red' ?>"><?= e($buttonLabel) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php admin_close();
