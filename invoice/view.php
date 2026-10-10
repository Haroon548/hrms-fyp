<?php
/*
 * One invoice, ready to print (FR-BILL-02, 04, 05; D7; design.md: Invoice).
 * Only its guest or an admin can open it. Admins can mark it paid here (FR-BILL-03).
 * The browser's Print button prints just the invoice: no header, footer or buttons.
 *   invoice/view.php?id=7
 */
require_once '../includes/init.php';
require_once '../includes/billing.php';
require_login();

$invoice = find_invoice((int) ($_GET['id'] ?? 0));
if (!$invoice || !can_see_invoice($invoice)) {
    http_response_code(404);
    exit('Invoice not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    check_csrf();
    if ($problem = mark_invoice_paid($invoice, (string) ($_POST['method'] ?? ''))) {
        flash('error', $problem);
    } else {
        flash('success', $invoice['invoice_number'] . ' is marked paid.');
    }
    redirect('invoice/view.php?id=' . $invoice['id']);
}

$paid = $invoice['status'] === 'paid';
$back = is_admin() ? 'admin/invoices.php' : 'account/index.php?tab=invoices';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($invoice['invoice_number']) ?> | <?= e(HOTEL_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/fontawesome-7.3.1/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<?php live_watch('invoices'); // the PAID stamp appears as soon as the desk marks it paid ?>
<body class="invoice-body"<?= live_body_attrs() ?>>
<div class="invoice-bar no-print">
    <a href="<?= url($back) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>
    <div class="invoice-bar-actions" data-live-region="actions">
        <?php if (is_admin() && !$paid): ?>
            <form method="post" class="invoice-pay">
                <?= csrf_field() ?>
                <label for="method" class="visually-hidden">Paid by</label>
                <select id="method" name="method">
                    <?php foreach (PAYMENT_METHODS as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="btn-small-red"><i class="fa-solid fa-check" aria-hidden="true"></i> Mark paid</button>
            </form>
        <?php endif; ?>
        <button type="button" class="btn-small-outline" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
    </div>
</div>
<?php show_flash(); ?>

<main class="invoice-paper" id="main">
    <header class="invoice-head">
        <div>
            <img src="<?= url('assets/images/logo.png') ?>" alt="<?= e(HOTEL_NAME) ?>" width="170" height="64">
            <p><?= e(HOTEL_ADDRESS) ?><br><?= e(HOTEL_PHONE) ?> · <?= e(HOTEL_EMAIL) ?></p>
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <p><strong><?= e($invoice['invoice_number']) ?></strong><br>Date: <?= e(date('j M Y', strtotime($invoice['created_at']))) ?></p>
        </div>
    </header>

    <section class="invoice-to" data-live-region="to">
        <div>
            <p class="invoice-label">Billed to</p>
            <p><strong><?= e($invoice['customer']) ?></strong>
                <?php if ($invoice['guest_email']): ?><br><?= e($invoice['guest_email']) ?><?php endif; ?>
                <?php if ($invoice['guest_phone']): ?><br><?= e($invoice['guest_phone']) ?><?php endif; ?></p>
        </div>
        <div>
            <p class="invoice-label">For</p>
            <p><strong><?= e(invoice_subject($invoice)) ?></strong><br>
                <?= $invoice['booking_id'] ? 'Room stay and room service' : e(ORDER_TYPES[$invoice['order_type']][0] ?? 'Restaurant order') ?></p>
        </div>
        <div class="invoice-stamp <?= $paid ? 'is-paid' : 'is-unpaid' ?>">
            <?= $paid ? 'PAID' : 'UNPAID' ?>
            <?php if ($paid): ?><small><?= e(PAYMENT_METHODS[$invoice['payment_method']] ?? $invoice['payment_method']) ?> · <?= e(date('j M Y', strtotime($invoice['paid_at']))) ?></small><?php endif; ?>
        </div>
    </section>

    <table class="invoice-lines">
        <thead>
            <tr><th scope="col">Date</th><th scope="col">Description</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Price (PKR)</th><th scope="col" class="num">Amount (PKR)</th></tr>
        </thead>
        <tbody>
            <?php foreach ($invoice['items'] as $item): ?>
                <tr>
                    <td class="text-nowrap"><?= $item['item_date'] ? e(date('j M', strtotime($item['item_date']))) : '' ?></td>
                    <td><?= e($item['description']) ?></td>
                    <td class="num"><?= (int) $item['quantity'] ?></td>
                    <td class="num"><?= money($item['unit_price']) ?></td>
                    <td class="num"><?= money($item['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="invoice-total"><th scope="row" colspan="4">Total</th><td class="num">PKR <?= money($invoice['total']) ?></td></tr>
        </tfoot>
    </table>

    <footer class="invoice-foot" data-live-region="foot">
        <p><?= $paid ? 'Thank you. This invoice has been paid in full.' : 'Please pay at the hotel reception, by cash or card.' ?></p>
        <p class="invoice-muted"><?= e(HOTEL_NAME) ?> · <?= e(SITE_URL) ?></p>
    </footer>
</main>
<script src="<?= asset('assets/js/live.js') ?>"></script>
</body>
</html>
