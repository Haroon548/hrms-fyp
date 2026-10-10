<?php
/*
 * The running room bill (FR-SIG-ROOM-02): room nights so far, then each room service order.
 * Included where $bill = running_bill($booking) and $booking are set (My Account, admin booking page).
 */
?>
<ul class="bill-lines">
    <li>
        <span><strong>Room nights so far</strong><small><?= $bill['nights_so_far'] ?> × PKR <?= money($booking['price_per_night']) ?></small></span>
        <b>PKR <?= money($bill['room_so_far']) ?></b>
    </li>
    <?php foreach ($bill['orders'] as $o): ?>
        <li>
            <span><strong><?= e(date('j M, g:i A', strtotime($o['created_at']))) ?> · Room service <?= e(order_number($o)) ?></strong><small><?= e(order_summary($o)) ?></small></span>
            <b>PKR <?= money($o['total']) ?></b>
        </li>
    <?php endforeach; ?>
    <li class="bill-total"><span>Total so far</span><b>PKR <?= money($bill['total_so_far']) ?></b></li>
</ul>
