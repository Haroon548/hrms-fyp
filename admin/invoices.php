<?php
/*
 * Admin: invoices (FR-BILL-01, 03). Unpaid first, with totals; mark an invoice paid (cash or
 * card) straight from the list, or open it to print. Stay invoices are made at check-out,
 * order invoices when a dine-in or takeaway order is completed.
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_once '../includes/admin-layout.php';
require_admin();

$views = [
    'unpaid' => ['Unpaid', "i.status = 'unpaid'", 'i.created_at'],
    'paid'   => ['Paid', "i.status = 'paid'", 'i.paid_at DESC'],
    'all'    => ['All invoices', '1 = 1', 'i.created_at DESC'],
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'unpaid';
$q = trim((string) ($_GET['q'] ?? ''));
$here = 'admin/invoices.php?' . http_build_query(array_filter(['view' => $view, 'q' => $q]));

if (admin_action() === 'paid') {
    $invoice = find_invoice((int) ($_POST['id'] ?? 0));
    if ($invoice && !($problem = mark_invoice_paid($invoice, (string) ($_POST['method'] ?? '')))) {
        flash('success', $invoice['invoice_number'] . ' is marked paid (' . PAYMENT_METHODS[$_POST['method']] . ').');
    } else {
        flash('error', $problem ?? 'That invoice no longer exists.');
    }
    redirect($here);
}

[$label, $where, $orderBy] = $views[$view];
$params = [];
if ($q !== '') {
    $where .= ' AND (i.invoice_number LIKE ? OR u.name LIKE ? OR o.guest_name LIKE ? OR b.reference LIKE ?)';
    $params = array_fill(0, 4, '%' . $q . '%');
}
$stmt = db()->prepare("SELECT i.id FROM invoices i LEFT JOIN users u ON u.id = i.user_id LEFT JOIN orders o ON o.id = i.order_id
    LEFT JOIN bookings b ON b.id = i.booking_id WHERE $where ORDER BY $orderBy LIMIT 200");
$stmt->execute($params);
$invoices = array_map('find_invoice', $stmt->fetchAll(PDO::FETCH_COLUMN));

$totals = db()->query("SELECT
    COALESCE(SUM(CASE WHEN status = 'unpaid' THEN total END), 0) AS unpaid,
    COALESCE(SUM(CASE WHEN status = 'paid' AND DATE(paid_at) = CURDATE() THEN total END), 0) AS today,
    COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN total END), 0) AS month,
    SUM(status = 'unpaid') AS unpaid_count
    FROM invoices")->fetch();

live_watch('invoices'); // updates by itself when these change
admin_open('Invoices', 'invoices', 'Bills for stays and restaurant orders. Mark them paid when the guest pays at the desk.');
?>
<div class="stat-cards stat-cards-small">
    <div class="stat-card"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i><strong>PKR <?= money($totals['unpaid']) ?></strong><span><?= (int) $totals['unpaid_count'] ?> unpaid <?= $totals['unpaid_count'] == 1 ? 'invoice' : 'invoices' ?></span></div>
    <div class="stat-card"><i class="fa-solid fa-sack-dollar" aria-hidden="true"></i><strong>PKR <?= money($totals['today']) ?></strong><span>Paid today</span></div>
    <div class="stat-card"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><strong>PKR <?= money($totals['month']) ?></strong><span>Paid this month</span></div>
</div>

<nav class="account-tabs admin-tabs" aria-label="Invoice views">
    <?php foreach ($views as $key => [$tabLabel]): ?>
        <a href="<?= url('admin/invoices.php?view=' . $key) ?>" class="<?= $key === $view ? 'active' : '' ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>><?= e($tabLabel) ?></a>
    <?php endforeach; ?>
</nav>

<form method="get" class="admin-search" role="search">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <label for="q" class="visually-hidden">Search invoices</label>
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Invoice number, guest name or booking number">
    <button type="submit" class="btn-small-red">Search</button>
</form>

<?php if (!$invoices): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i></span>
        <h2><?= $view === 'unpaid' ? 'Nothing waiting to be paid' : 'No invoices here' ?></h2>
        <p>Stay invoices are made when a guest checks out; order invoices when a dine-in or takeaway order is completed.</p>
    </div>
<?php else: ?>
    <div class="table-responsive admin-table-wrap">
        <table class="table admin-table align-middle">
            <thead><tr><th scope="col">Invoice</th><th scope="col">Guest</th><th scope="col">For</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <th scope="row"><a href="<?= url('invoice/view.php?id=' . $inv['id']) ?>" class="booking-ref"><?= e($inv['invoice_number']) ?></a>
                            <small class="d-block admin-muted"><?= e(date('j M Y', strtotime($inv['created_at']))) ?></small></th>
                        <td><?= e($inv['customer']) ?></td>
                        <td><?= e(invoice_subject($inv)) ?><small class="d-block admin-muted"><?= count($inv['items']) ?> <?= count($inv['items']) === 1 ? 'line' : 'lines' ?></small></td>
                        <td class="text-nowrap"><strong>PKR <?= money($inv['total']) ?></strong></td>
                        <td><?= status_badge($inv['status']) ?><?php if ($inv['paid_at']): ?><small class="d-block admin-muted"><?= e(PAYMENT_METHODS[$inv['payment_method']] ?? '') ?> · <?= e(date('j M', strtotime($inv['paid_at']))) ?></small><?php endif; ?></td>
                        <td class="text-end">
                            <div class="table-actions">
                                <?php if ($inv['status'] === 'unpaid'): ?>
                                    <?php foreach (PAYMENT_METHODS as $method => $methodLabel): ?>
                                        <form method="post"><?= csrf_field() ?>
                                            <input type="hidden" name="action" value="paid"><input type="hidden" name="id" value="<?= $inv['id'] ?>"><input type="hidden" name="method" value="<?= $method ?>">
                                            <button type="submit" class="btn-small-red text-nowrap" title="Mark paid: <?= e($methodLabel) ?>"><?= $method === 'cash' ? 'Paid cash' : 'Paid card' ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <a href="<?= url('invoice/view.php?id=' . $inv['id']) ?>" class="btn-icon" aria-label="Open <?= e($inv['invoice_number']) ?> to print" title="Open / print"><i class="fa-solid fa-print" aria-hidden="true"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_close();
