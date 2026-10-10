<?php
/*
 * The menu with "+" buttons and the cart (button + side panel), shared by the Dining page
 * and the table QR menu (FR-MENU-03, FR-ORD-01, FR-SIG-QR-02). The forms post to
 * order/cart.php and come back to $return; assets/js/dining.js makes them work in the background.
 * The tabs, dishes and cart are live parts (FR-LIVE-01): a dish marked sold out or a new price
 * shows at once on every open menu.
 */

// The menu sections (from menu_by_category(), empty ones left out) with their dishes.
function menu_sections($menuSections, $return)
{
    ?>
                <nav class="menu-tabs" aria-label="Menu sections" data-live-region="menu-tabs">
                    <?php foreach ($menuSections as $category): ?>
                        <a href="#cat-<?= $category['id'] ?>"><?= e($category['name']) ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="menu-columns" data-live-region="menu">
                    <?php foreach ($menuSections as $category): ?>
                        <section class="menu-section" id="cat-<?= $category['id'] ?>" aria-labelledby="catTitle<?= $category['id'] ?>">
                            <h3 id="catTitle<?= $category['id'] ?>"><?= e($category['name']) ?></h3>
                            <ul class="menu-list">
                                <?php foreach ($category['items'] as $item): $inCart = cart()[$item['id']] ?? 0; ?>
                                    <li class="dish<?= $item['image'] ? ' has-photo' : '' ?>">
                                        <?php if ($item['image']): ?>
                                            <span class="dish-photo"><?php photo($item['image'], $item['name']); ?></span>
                                        <?php endif; ?>
                                        <div class="dish-text">
                                            <p class="dish-line"><span class="dish-name"><?= e($item['name']) ?></span><span class="dish-dots" aria-hidden="true"></span><span class="dish-price"><?= money($item['price']) ?></span></p>
                                            <?php if ($item['description'] !== ''): ?><p class="dish-desc"><?= e($item['description']) ?></p><?php endif; ?>
                                        </div>
                                        <form action="<?= url('order/cart.php') ?>" method="post" class="dish-add" data-add-form>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="return" value="<?= e($return) ?>">
                                            <button type="submit" aria-label="Add <?= e($item['name']) ?> to your order">
                                                <i class="fa-solid fa-plus" aria-hidden="true"></i><span class="dish-count" data-item-count="<?= $item['id'] ?>"><?= $inCart ?: '' ?></span>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </div>
    <?php
}

// The cart button and panel. $checkout is where "Continue to order" goes.
function cart_ui($cart, $return, $checkout = 'order/checkout.php')
{
    ?>
<!-- Cart: a button that appears once something is added, and a panel with the order -->
<div data-live-region="cart">
<button type="button" class="cart-fab<?= $cart['count'] ? ' is-visible' : '' ?>" data-bs-toggle="offcanvas" data-bs-target="#cartPanel" aria-controls="cartPanel" data-cart-fab>
    <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i>
    <span class="cart-fab-count" data-cart-count><?= $cart['count'] ?></span>
    <span class="cart-fab-text">Your order · <strong data-cart-total><?= 'PKR ' . money($cart['total']) ?></strong></span>
</button>

<div class="offcanvas offcanvas-end cart-panel" tabindex="-1" id="cartPanel" aria-labelledby="cartTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title" id="cartTitle">Your order</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="cart-lines" data-cart-lines>
            <?php foreach ($cart['lines'] as $line): ?>
                <li data-line="<?= $line['id'] ?>">
                    <div><strong><?= e($line['name']) ?></strong><small><?= 'PKR ' . money($line['price']) ?> each</small></div>
                    <form action="<?= url('order/cart.php') ?>" method="post" class="qty" data-qty-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set">
                        <input type="hidden" name="item_id" value="<?= $line['id'] ?>">
                        <input type="hidden" name="return" value="<?= e($return) ?>">
                        <button type="submit" name="quantity" value="<?= $line['quantity'] - 1 ?>" aria-label="One less <?= e($line['name']) ?>"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
                        <span aria-live="polite"><?= $line['quantity'] ?></span>
                        <button type="submit" name="quantity" value="<?= $line['quantity'] + 1 ?>" aria-label="One more <?= e($line['name']) ?>"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                    </form>
                    <span class="cart-line-total"><?= 'PKR ' . money($line['line_total']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="cart-empty" data-cart-empty<?= $cart['lines'] ? ' hidden' : '' ?>>Nothing here yet. Tap <strong>+</strong> next to a dish to add it.</p>
        <div class="cart-foot">
            <p class="cart-total"><span>Total</span> <strong data-cart-total><?= 'PKR ' . money($cart['total']) ?></strong></p>
            <a href="<?= url($checkout) ?>" class="btn-primary-red w-100<?= $cart['lines'] ? '' : ' disabled' ?>" data-cart-checkout>CONTINUE TO ORDER</a>
            <p class="cart-small">You choose takeaway, dine-in or room service next. Nothing is paid online.</p>
        </div>
    </div>
</div>

</div>

<script type="application/json" id="cartConfig"><?= json_encode(['url' => url('order/cart.php'), 'csrf' => csrf_token(), 'return' => $return]) ?></script>
    <?php
}
