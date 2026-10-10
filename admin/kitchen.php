<?php
/*
 * Kitchen screen (FR-SIG-QR-04, 05; design.md: Kitchen screen). For a TV or tablet in the kitchen.
 * Three columns: New → Preparing → Ready. Each card shows the table or room, the dishes,
 * the note and how long ago the order came in; one tap moves it to the next column.
 * The board updates the moment an order is placed or changed anywhere (live updates, FR-LIVE-01)
 * and plays a short sound when a new order arrives (after "Sound on" is pressed once,
 * because browsers only allow sound after a tap).
 *   kitchen.php            → the screen
 *   kitchen.php?board=1    → just the columns (what the screen loads when orders change)
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_once '../includes/admin-layout.php';
require_admin();

if (admin_action() === 'status') {
    $order = find_order((int) ($_POST['id'] ?? 0));
    $status = (string) ($_POST['status'] ?? '');
    if ($order && ($problem = change_order_status($order, $status))) {
        flash('error', $problem);
    } elseif ($order && $status === 'completed') {
        invoice_for_order($order['id']); // dine-in / takeaway get their own invoice (FR-BILL-01)
    }
    redirect('admin/kitchen.php');
}

// Minutes since the order came in, and whether it's been waiting too long
function waited($datetime)
{
    return max(0, (int) floor((time() - strtotime($datetime)) / 60));
}

function kitchen_board()
{
    $columns = [
        'pending'   => ['New', 'fa-solid fa-bell', 'preparing', 'Start preparing'],
        'preparing' => ['Preparing', 'fa-solid fa-fire-burner', 'ready', 'Ready'],
        'ready'     => ['Ready', 'fa-solid fa-circle-check', 'completed', 'Served / collected'],
    ];
    $ids = db()->query("SELECT id FROM orders WHERE status IN ('pending', 'preparing', 'ready') ORDER BY created_at, id")->fetchAll(PDO::FETCH_COLUMN);
    $orders = array_map('find_order', $ids);
    $latest = (int) db()->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn();
    ?>
    <div class="kitchen-board" data-now="<?= time() ?>" data-latest="<?= $latest ?>" data-waiting="<?= count(array_filter($orders, function ($o) { return $o['status'] === 'pending'; })) ?>">
        <?php foreach ($columns as $status => [$title, $icon, $next, $button]):
            $list = array_values(array_filter($orders, function ($o) use ($status) { return $o['status'] === $status; })); ?>
            <section class="kitchen-col is-<?= $status ?>" aria-label="<?= e($title) ?>">
                <h2><i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($title) ?> <span><?= count($list) ?></span></h2>
                <?php if (!$list): ?>
                    <p class="kitchen-empty">Nothing here</p>
                <?php endif; ?>
                <?php foreach ($list as $o): $mins = waited($o['created_at']); ?>
                    <article class="kitchen-card<?= $status === 'pending' && $mins >= 10 ? ' is-late' : '' ?>" data-placed="<?= strtotime($o['created_at']) ?>">
                        <header>
                            <strong><?= e(order_place($o)) ?></strong>
                            <span class="kitchen-time"><?= $mins < 1 ? 'now' : $mins . ' min' ?></span>
                        </header>
                        <p class="kitchen-meta"><?= e(order_number($o)) ?> · <?= e(ORDER_TYPES[$o['order_type']][0]) ?> · <?= e($o['guest_name']) ?></p>
                        <ul>
                            <?php foreach ($o['items'] as $item): ?>
                                <li><b><?= (int) $item['quantity'] ?>×</b> <?= e($item['item_name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($o['notes'] !== ''): ?>
                            <p class="kitchen-note"><i class="fa-solid fa-message" aria-hidden="true"></i> <?= e($o['notes']) ?></p>
                        <?php endif; ?>
                        <form method="post" action="<?= url('admin/kitchen.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                            <input type="hidden" name="status" value="<?= $next ?>">
                            <button type="submit"><?= e($button) ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <?php
}

// The screen asks for just the columns when orders change
if (isset($_GET['board'])) {
    header('Cache-Control: no-store');
    kitchen_board();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Kitchen screen | <?= e(HOTEL_NAME) ?></title>
    <meta name="theme-color" content="#1B1416">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/fontawesome-7.3.1/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<?php live_watch('orders'); ?>
<body class="kitchen-body"<?= live_body_attrs() ?>>
<header class="kitchen-top">
    <a href="<?= url('admin/orders.php') ?>" class="kitchen-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Admin</a>
    <h1>Kitchen</h1>
    <span class="kitchen-clock" data-clock><?= date('g:i A') ?></span>
    <span class="kitchen-live" data-live><i class="fa-solid fa-circle" aria-hidden="true"></i> Live</span>
    <button type="button" class="kitchen-sound" data-sound aria-pressed="false"><i class="fa-solid fa-volume-xmark" aria-hidden="true"></i> Sound off</button>
</header>
<?php show_flash(); ?>
<main id="board" data-board-url="<?= e(url('admin/kitchen.php?board=1')) ?>" aria-live="polite">
    <?php kitchen_board(); ?>
</main>
<script src="<?= url('assets/js/kitchen.js') ?>"></script>
<script src="<?= url('assets/js/live.js') ?>"></script>
</body>
</html>
