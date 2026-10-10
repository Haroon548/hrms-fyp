<?php
/*
 * Food order: choose the order type and place the order (FR-ORD-01, FR-ORD-02; D18).
 *   Takeaway     – collect from Buzz 76, pay there
 *   Dine-in      – choose the table you are at, pay at the restaurant
 *   Room service – only for guests who are checked in; added to the room bill (FR-SIG-ROOM-01)
 * After scanning a table's QR code the order is dine-in for that table, and a walk-in guest
 * only needs to give a name (FR-SIG-QR-02, 03).
 * The total is worked out on the server from today's menu prices.
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';

// After scanning a table's QR code, the order goes to that table and no account is needed (FR-SIG-QR-02, 03)
$scanned = scanned_table();
if (!$scanned) {
    require_login();
}
$user = current_user();
$stay = $user ? current_stay($user['id']) : null;
$tables = dining_tables_by_restaurant();
$errors = [];
$values = [
    'type'       => $scanned ? 'dine_in' : ($stay ? 'room_service' : 'takeaway'),
    'table_id'   => $scanned ? (int) $scanned['id'] : 0,
    'notes'      => '',
    'guest_name' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    // A second press of "Place order" goes to the order already placed
    $formId = (string) ($_POST['form_id'] ?? '');
    if (isset($_SESSION['ordered_forms'][$formId])) {
        redirect('order/view.php?id=' . $_SESSION['ordered_forms'][$formId]);
    }
    $values = [
        'type'       => $scanned ? 'dine_in' : (string) ($_POST['type'] ?? ''),
        'table_id'   => $scanned ? (int) $scanned['id'] : (int) ($_POST['table_id'] ?? 0),
        'notes'      => trim((string) ($_POST['notes'] ?? '')),
        'guest_name' => trim((string) ($_POST['guest_name'] ?? '')),
    ];
    if (mb_strlen($values['notes']) > 255) {
        $errors['notes'] = 'Keep the note under 255 characters.';
    }
    // The guest only orders at the total they saw: if the kitchen changed a price meanwhile, show it first
    $quoted = (string) ($_POST['quoted_total'] ?? '');
    $now = cart_details();
    if (!$errors && $now['lines'] && $quoted !== '' && round((float) $quoted) !== round($now['total'])) {
        $errors['type'] = 'A price on the menu changed while you were ordering. Your total is now PKR ' . money($now['total'])
            . ' (it was PKR ' . money($quoted) . '). Please check your dishes and press Place order again.';
    }
    if (!$errors) {
        $result = place_order($user, $values);
        if (is_int($result)) {
            $_SESSION['ordered_forms'][$formId] = $result;
            if (!$user) {
                $_SESSION['guest_orders'][] = $result; // lets this phone follow the order without an account
            }
            send_order_confirmation(find_order($result)); // never stops the order (D4)
            redirect('order/view.php?id=' . $result . '&new=1');
        }
        $errors[strpos($result, 'name') !== false ? 'guest_name' : 'type'] = $result;
    }
}

$cart = cart_details();
if ($cart['removed']) {
    flash('info', 'Taken off the menu since you added it, so removed from your order: ' . implode(', ', $cart['removed']) . '.');
}

$page_title = 'Your order | ' . HOTEL_NAME;
$active_page = 'dining';
$flash_inline = true;
$page_scripts = ['assets/js/checkout.js'];
live_watch('menu_items', 'bookings', 'dining_tables'); // prices, sold-out dishes, room service once checked in
include '../includes/header.php';
?>
<section class="checkout-bar">
    <div class="container">
        <ol class="steps-mini" aria-label="Order steps">
            <li class="is-done"><i class="fa-solid fa-check" aria-hidden="true"></i> Choose dishes</li>
            <li class="is-current" aria-current="step"><span>2</span> How you'd like it</li>
            <li><span>3</span> Order placed</li>
        </ol>
        <h1>Your order</h1>
        <?php $menuLink = $scanned ? 'order/table.php?t=' . $scanned['id'] . '#menu' : 'dining.php#menu'; ?>
        <p class="checkout-stay" data-live-region="count"><?= $cart['count'] ?> <?= $cart['count'] === 1 ? 'item' : 'items' ?> · PKR <?= money($cart['total']) ?> · <a href="<?= url($menuLink) ?>">Add more</a></p>
    </div>
</section>

<section class="section checkout-section">
    <div class="container">
        <?php show_flash(); ?>
        <div data-live-region="order">
        <?php if (!$cart['lines']): ?>
            <div class="empty-state">
                <span class="empty-icon"><i class="fa-solid fa-basket-shopping" aria-hidden="true"></i></span>
                <h2>Your order is empty</h2>
                <p>Choose dishes from the menu, then come back here to place your order.</p>
                <a href="<?= url($menuLink) ?>" class="btn-primary-red">See the menu</a>
            </div>
        <?php else: ?>
            <div class="row g-4 gx-lg-5">
                <div class="col-lg-7">
                    <form method="post" class="checkout-form" novalidate data-checkout-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_id" value="<?= e($_POST['form_id'] ?? bin2hex(random_bytes(8))) ?>">
                        <input type="hidden" name="quoted_total" value="<?= e((string) $cart['total']) ?>">
                        <h2>How would you like it?</h2>
                        <?php if (isset($errors['type'])): ?>
                            <div class="notice notice-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= e($errors['type']) ?></span></div>
                        <?php endif; ?>
                        <?php if ($scanned): ?>
                            <div class="table-locked">
                                <i class="fa-solid fa-utensils" aria-hidden="true"></i>
                                <div><strong>Dine-in · Table <?= e($scanned['table_number']) ?>, <?= e($scanned['restaurant']) ?></strong>
                                    <small>We'll bring it to your table. You pay at the restaurant.</small></div>
                                <a href="<?= url('order/table.php?leave=1') ?>" class="admin-link">Not here?</a>
                            </div>
                            <?php if (!$user): ?>
                                <div class="form-field<?= isset($errors['guest_name']) ? ' has-error' : '' ?>">
                                    <label for="f_guest_name">Your name</label>
                                    <input type="text" id="f_guest_name" name="guest_name" value="<?= e($values['guest_name']) ?>" autocomplete="name" maxlength="100" required aria-describedby="f_guest_name_help<?= isset($errors['guest_name']) ? ' f_guest_name_error' : '' ?>">
                                    <p class="field-help" id="f_guest_name_help">So the waiter knows whose order it is. <a href="<?= url('account/login.php?next=' . urlencode('order/checkout.php')) ?>">Log in</a> to keep it in your order history.</p>
                                    <?php if (isset($errors['guest_name'])): ?><p class="field-error" id="f_guest_name_error"><?= e($errors['guest_name']) ?></p><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                        <fieldset class="order-types">
                            <legend class="visually-hidden">Order type</legend>
                            <?php foreach (ORDER_TYPES as $key => [$label, $icon, $help]):
                                $disabled = $key === 'room_service' && !$stay; ?>
                                <label class="order-type<?= $disabled ? ' is-disabled' : '' ?>">
                                    <input type="radio" name="type" value="<?= $key ?>"<?= $values['type'] === $key ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?> data-order-type>
                                    <span class="order-type-box">
                                        <i class="<?= $icon ?>" aria-hidden="true"></i>
                                        <strong><?= e($label) ?></strong>
                                        <small><?= $key === 'room_service' && $stay ? 'To room ' . e($stay['room_number']) . ', added to your room bill.' : ($disabled ? 'For guests who are checked in.' : e($help)) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>

                        <div class="form-field table-choice" data-table-choice>
                            <label for="f_table">Your table</label>
                            <select id="f_table" name="table_id" aria-describedby="f_table_help">
                                <option value="">Choose your table…</option>
                                <?php foreach ($tables as $restaurant => $list): ?>
                                    <optgroup label="<?= e($restaurant) ?>">
                                        <?php foreach ($list as $table): ?>
                                            <option value="<?= $table['id'] ?>"<?= $values['table_id'] === (int) $table['id'] ? ' selected' : '' ?>>Table <?= e($table['table_number']) ?> (<?= (int) $table['seats'] ?> seats)</option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-help" id="f_table_help">The number is on the stand on your table.</p>
                        </div>
                        <?php endif; ?>

                        <div class="form-field<?= isset($errors['notes']) ? ' has-error' : '' ?>">
                            <label for="f_notes">Note for the kitchen (optional)</label>
                            <textarea id="f_notes" name="notes" rows="3" maxlength="255" placeholder="e.g. less spicy, no onions, extra raita"><?= e($values['notes']) ?></textarea>
                            <?php if (isset($errors['notes'])): ?><p class="field-error"><?= e($errors['notes']) ?></p><?php endif; ?>
                        </div>
                        <div class="checkout-agree">
                            <button type="submit" class="btn-primary-red btn-complete" data-busy="PLACING YOUR ORDER…">PLACE ORDER · PKR <?= money($cart['total']) ?></button>
                            <p class="checkout-small">Nothing is paid online.<?= $user ? ' Ordering as ' . e($user['name']) . ' (' . e($user['phone']) . ').' : '' ?></p>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <aside class="order-summary" aria-label="Your dishes">
                        <h2>Your dishes</h2>
                        <ul class="cart-lines">
                            <?php foreach ($cart['lines'] as $line): ?>
                                <li>
                                    <div><strong><?= e($line['name']) ?></strong><small><?= $line['quantity'] ?> × PKR <?= money($line['price']) ?></small></div>
                                    <span class="cart-line-total">PKR <?= money($line['line_total']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="cart-total"><span>Total</span> <strong>PKR <?= money($cart['total']) ?></strong></p>
                        <a href="<?= url($menuLink) ?>" class="text-link">Change dishes</a>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>
</section>
<?php include '../includes/footer.php';
