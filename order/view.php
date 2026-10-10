<?php
/*
 * One food order for the guest (FR-ORD-04, FR-SIG-QR-06): what was ordered, where it goes,
 * and a progress line (Received → Preparing → Ready → Completed). The page changes by itself
 * the moment the kitchen moves the order on (live updates, FR-LIVE-01).
 * The guest can cancel until the kitchen starts on it.
 *   order/view.php?id=45
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';

// Walk-in guests who ordered from a table's QR code can follow their order without an account
$order = find_order((int) ($_GET['id'] ?? 0));
if (!$order || !can_see_order($order)) {
    if (!is_logged_in() && empty($_SESSION['guest_orders'])) {
        require_login();
    }
    http_response_code(404);
    $page_title = 'Order not found | ' . HOTEL_NAME;
    include '../includes/header.php';
    ?>
    <section class="section"><div class="container"><div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-utensils" aria-hidden="true"></i></span>
        <h1 class="h2">We couldn't find that order</h1>
        <p>All your food orders are in My Account.</p>
        <a href="<?= url('account/index.php?tab=orders') ?>" class="btn-primary-red">My orders</a>
    </div></div></section>
    <?php
    include '../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $isAdminViewing = is_admin() && (int) $order['user_id'] !== (int) (current_user()['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'cancel' && !$isAdminViewing) {
        if (!guest_can_cancel_order($order)) {
            flash('error', 'The kitchen has already started on this order, so it can\'t be cancelled online. Please call ' . HOTEL_PHONE . '.');
        } elseif ($problem = change_order_status($order, 'cancelled')) {
            flash('error', $problem);
        } else {
            flash('success', 'Order ' . order_number($order) . ' is cancelled.');
        }
    }
    redirect('order/view.php?id=' . $order['id']);
}

$isNew = isset($_GET['new']);
$active = in_array($order['status'], ['pending', 'preparing', 'ready'], true);
$steps = array_keys(ORDER_FLOW);
$reached = array_search($order['status'], $steps, true);
[$typeLabel, $typeIcon, $typeHelp] = ORDER_TYPES[$order['order_type']];

$page_title = 'Order ' . order_number($order) . ' | ' . HOTEL_NAME;
$active_page = 'dining';
$flash_inline = true;
live_watch('orders', 'invoices');
include '../includes/header.php';
?>
<div data-live-region="hero">
<section class="confirm-hero<?= $order['status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
    <div class="container">
        <?php if ($isNew): ?>
            <ol class="steps-mini" aria-label="Order steps">
                <li class="is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> Choose dishes</li>
                <li class="is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> How you'd like it</li>
                <li class="is-current" aria-current="step"><span>3</span> Order placed</li>
            </ol>
        <?php endif; ?>
        <span class="confirm-icon<?= $isNew ? ' is-pop' : '' ?>" aria-hidden="true"><i class="<?= $order['status'] === 'cancelled' ? 'fa-solid fa-ban' : $typeIcon ?>"></i></span>
        <h1><?= $order['status'] === 'cancelled' ? 'This order is cancelled' : ($isNew ? 'Order placed!' : 'Order ' . e(order_number($order))) ?></h1>
        <p><?= $isNew || $order['status'] === 'cancelled' ? 'Order ' . e(order_number($order)) . ' · ' : '' ?><?= e(order_heading($order)) ?> · placed <?= e(date('D j M, g:i A', strtotime($order['created_at']))) ?></p>
    </div>
</section>
</div>

<section class="section confirm-section order-page">
    <div class="container">
        <?php show_flash(); ?>
        <div class="row g-4" data-live-region="order">
            <div class="col-lg-7">
                <?php if ($order['status'] !== 'cancelled'): ?>
                    <div class="account-card mb-4">
                        <h2>Progress</h2>
                        <ol class="order-progress" data-order-status="<?= e($order['status']) ?>">
                            <?php foreach (ORDER_FLOW as $status => $label):
                                $i = array_search($status, $steps, true);
                                $state = $i < $reached ? 'is-done' : ($i === $reached ? 'is-current' : ''); ?>
                                <li class="<?= $state ?>"<?= $i === $reached ? ' aria-current="step"' : '' ?>>
                                    <span class="op-dot" aria-hidden="true"><?= $i < $reached || ($i === $reached && $status === 'completed') ? '<i class="fa-solid fa-check"></i>' : $i + 1 ?></span>
                                    <span class="op-label"><?= e($status === 'completed' ? ($order['order_type'] === 'takeaway' ? 'Collected' : 'Served') : $label) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <p class="order-progress-note">
                            <?php if ($order['status'] === 'pending'): ?>The kitchen has your order and will start on it shortly.
                            <?php elseif ($order['status'] === 'preparing'): ?>Your food is being prepared.
                            <?php elseif ($order['status'] === 'ready'): ?><?= $order['order_type'] === 'takeaway' ? 'Ready! Please collect it from Buzz 76.' : 'Ready! It is on its way to you.' ?>
                            <?php else: ?>Enjoy your meal!<?php endif; ?>
                            <?php if ($active): ?><span class="admin-muted d-block mt-1"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Live: this page changes as soon as the kitchen moves your order on.</span><?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <div class="account-card">
                    <div class="confirm-details-head"><h2>What you ordered</h2><?= status_badge($order['status']) ?></div>
                    <ul class="cart-lines">
                        <?php foreach ($order['items'] as $item): ?>
                            <li>
                                <div><strong><?= e($item['item_name']) ?></strong><small><?= (int) $item['quantity'] ?> × PKR <?= money($item['unit_price']) ?></small></div>
                                <span class="cart-line-total">PKR <?= money($item['line_total']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="cart-total"><span>Total</span> <strong>PKR <?= money($order['total']) ?></strong></p>
                    <?php if ($order['notes'] !== ''): ?><p class="admin-muted mb-0"><strong>Your note:</strong> <?= e($order['notes']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="account-card">
                    <h2><?= e($typeLabel) ?></h2>
                    <p><?= e($order['order_type'] === 'room_service' ? 'To room ' . $order['room_number'] . '. The total is added to your room bill, paid at check-out.' : $typeHelp) ?></p>
                    <div class="quick-actions">
                        <?php if (is_logged_in()): ?>
                            <a href="<?= url('account/index.php?tab=orders') ?>"><i class="fa-solid fa-receipt" aria-hidden="true"></i> All my orders</a>
                        <?php endif; ?>
                        <a href="<?= url($order['table_id'] && !$order['user_id'] ? 'order/table.php?t=' . $order['table_id'] . '#menu' : 'dining.php#menu') ?>"><i class="fa-solid fa-utensils" aria-hidden="true"></i> Order something else</a>
                        <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e(HOTEL_PHONE) ?></a>
                    </div>
                    <?php if (guest_can_cancel_order($order) && !(is_admin() && (int) $order['user_id'] !== (int) current_user()['id'])): ?>
                        <form method="post" class="mt-3" data-confirm="Cancel order <?= e(order_number($order)) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn-small-outline w-100">Cancel order</button>
                        </form>
                        <p class="checkout-small">You can cancel until the kitchen starts preparing it.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include '../includes/footer.php';
