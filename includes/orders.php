<?php
/*
 * Menu, cart and food orders (FR-MENU, FR-ORD, FR-MAIL-05; order types D18).
 * Used by the Dining page, the order pages, My Account and the admin panel.
 *
 * The cart lives in the session as [menu item id => quantity], so it works without
 * JavaScript and survives page reloads. Prices are never taken from the browser:
 * the total is worked out from the current menu prices when the order is placed (FR-ORD-02).
 *
 * Order statuses: pending → preparing → ready → completed, or cancelled.
 */
require_once __DIR__ . '/mail.php';

const MAX_PER_ITEM = 20;
const ORDER_TYPES = [
    'takeaway'     => ['Takeaway', 'fa-solid fa-bag-shopping', 'Collect it from Buzz 76 and pay there.'],
    'dine_in'      => ['Dine-in', 'fa-solid fa-utensils', 'Brought to your table. Pay at the restaurant.'],
    'room_service' => ['Room service', 'fa-solid fa-bell-concierge', 'Brought to your room and added to your room bill.'],
];
const ORDER_FLOW = ['pending' => 'Received', 'preparing' => 'Preparing', 'ready' => 'Ready', 'completed' => 'Completed'];

/* ---------- Menu ---------- */

// Categories with their dishes: [['id', 'name', 'items' => [...]], ...]. Hidden dishes only for the admin.
function menu_by_category($includeUnavailable = false)
{
    $categories = db()->query('SELECT * FROM menu_categories ORDER BY sort_order, name')->fetchAll();
    $items = db()->query('SELECT * FROM menu_items ' . ($includeUnavailable ? '' : 'WHERE is_available = 1 ') . 'ORDER BY sort_order, name')->fetchAll();
    foreach ($categories as &$category) {
        $category['items'] = array_values(array_filter($items, function ($item) use ($category) {
            return (int) $item['category_id'] === (int) $category['id'];
        }));
    }
    unset($category); // end the reference, so later loops over $categories can't change the last one
    return $categories;
}

/* ---------- Cart ---------- */

function cart()
{
    return $_SESSION['cart'] ?? [];
}

// Sets one dish's quantity (0 removes it). Only dishes that exist and are available.
function cart_set($itemId, $quantity)
{
    $itemId = (int) $itemId;
    $quantity = max(0, min(MAX_PER_ITEM, (int) $quantity));
    $stmt = db()->prepare('SELECT id FROM menu_items WHERE id = ? AND is_available = 1');
    $stmt->execute([$itemId]);
    if (!$stmt->fetch() || $quantity === 0) {
        unset($_SESSION['cart'][$itemId]);
        return;
    }
    $_SESSION['cart'][$itemId] = $quantity;
}

function cart_clear()
{
    unset($_SESSION['cart']);
}

/*
 * The cart with today's prices: ['lines' => [...], 'count' => 5, 'total' => 6400.0, 'removed' => [names]].
 * Dishes the kitchen has taken off the menu since they were added are dropped (and named in 'removed').
 */
function cart_details()
{
    $cart = cart();
    $result = ['lines' => [], 'count' => 0, 'total' => 0.0, 'removed' => []];
    if (!$cart) {
        return $result;
    }
    $marks = implode(',', array_fill(0, count($cart), '?'));
    $stmt = db()->prepare("SELECT id, name, price, image, is_available FROM menu_items WHERE id IN ($marks)");
    $stmt->execute(array_keys($cart));
    $rows = array_column($stmt->fetchAll(), null, 'id');
    foreach ($cart as $id => $quantity) {
        $row = $rows[$id] ?? null;
        if (!$row || !$row['is_available']) {
            if ($row) $result['removed'][] = $row['name'];
            unset($_SESSION['cart'][$id]);
            continue;
        }
        $line = (float) $row['price'] * $quantity;
        $result['lines'][] = ['id' => (int) $id, 'name' => $row['name'], 'price' => (float) $row['price'], 'quantity' => $quantity, 'line_total' => $line, 'image' => $row['image']];
        $result['count'] += $quantity;
        $result['total'] += $line;
    }
    return $result;
}

/* ---------- Placing orders ---------- */

// The guest's current stay, if they are checked in (room service is only for them, FR-ORD-01, FR-SIG-ROOM-04).
function current_stay($userId)
{
    $stmt = db()->prepare(
        "SELECT b.id, b.reference, b.check_in, b.check_out, r.room_number
         FROM bookings b JOIN rooms r ON r.id = b.room_id
         WHERE b.user_id = ? AND b.status = 'checked_in'
         ORDER BY b.check_in DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

// Dining tables guests can choose for dine-in, grouped by restaurant.
function dining_tables_by_restaurant()
{
    $rows = db()->query(
        'SELECT t.id, t.table_number, t.seats, r.name AS restaurant
         FROM dining_tables t JOIN restaurants r ON r.id = t.restaurant_id
         WHERE t.is_active = 1 ORDER BY r.sort_order, LENGTH(t.table_number), t.table_number'
    )->fetchAll();
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['restaurant']][] = $row;
    }
    return $grouped;
}

/*
 * Saves an order from the cart (FR-ORD-01, 02). $details: type, notes, table_id.
 * $user is the logged-in customer, or null for a walk-in guest ordering from a table's
 * QR code with just a name (FR-SIG-QR-03): then $details['guest_name'] is used and only dine-in is allowed.
 * Returns the new order id, or a message saying why it can't be placed.
 */
function place_order($user, array $details)
{
    if (!$user) {
        $name = trim($details['guest_name'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return 'Please tell us your name, so we know whose order it is.';
        }
        if (($details['type'] ?? '') !== 'dine_in') {
            return 'Without an account you can order to your table. Please log in for takeaway or room service.';
        }
        $user = ['id' => null, 'name' => $name];
    }
    $cart = cart_details();
    if (!$cart['lines']) {
        return 'Your cart is empty.';
    }
    $type = $details['type'] ?? '';
    if (!isset(ORDER_TYPES[$type])) {
        return 'Choose how you would like your order.';
    }
    $bookingId = null;
    $tableId = null;
    if ($type === 'room_service') {
        $stay = $user['id'] ? current_stay($user['id']) : null;
        if (!$stay) {
            return 'Room service is for guests who are checked in. Please choose takeaway or dine-in.';
        }
        $bookingId = $stay['id'];
    }
    if ($type === 'dine_in') {
        $stmt = db()->prepare('SELECT id FROM dining_tables WHERE id = ? AND is_active = 1');
        $stmt->execute([(int) ($details['table_id'] ?? 0)]);
        $tableId = $stmt->fetchColumn() ?: null;
        if (!$tableId) {
            return 'Choose the table you are sitting at.';
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO orders (user_id, guest_name, order_type, booking_id, table_id, notes, total) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$user['id'], $user['name'], $type, $bookingId, $tableId, mb_substr(trim($details['notes'] ?? ''), 0, 255), $cart['total']]);
        $orderId = (int) $pdo->lastInsertId();
        $line = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($cart['lines'] as $item) {
            $line->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['line_total']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    cart_clear();
    return $orderId;
}

/* ---------- Reading and changing orders ---------- */

// One order with its lines, customer, table and room.
function find_order($id)
{
    $stmt = db()->prepare(
        'SELECT o.*, u.email AS guest_email, u.phone AS guest_phone,
                t.table_number, rs.name AS restaurant, r.room_number, b.reference AS booking_reference
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN dining_tables t ON t.id = o.table_id
         LEFT JOIN restaurants rs ON rs.id = t.restaurant_id
         LEFT JOIN bookings b ON b.id = o.booking_id
         LEFT JOIN rooms r ON r.id = b.room_id
         WHERE o.id = ?'
    );
    $stmt->execute([(int) $id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll();
    return $order;
}

// "Order #45"
function order_number($order)
{
    return '#' . $order['id'];
}

// Where the order goes, e.g. "Table 5, Buzz 76", "Room 104", "Takeaway"
function order_place($order)
{
    if ($order['order_type'] === 'dine_in') {
        return 'Table ' . $order['table_number'] . ($order['restaurant'] ? ', ' . $order['restaurant'] : '');
    }
    if ($order['order_type'] === 'room_service') {
        return 'Room ' . $order['room_number'];
    }
    return 'Takeaway';
}

// "Dine-in · Table 5, Buzz 76", "Room service · Room 104", "Takeaway"
function order_heading($order)
{
    $type = ORDER_TYPES[$order['order_type']][0];
    return $order['order_type'] === 'takeaway' ? $type : $type . ' · ' . order_place($order);
}

// "3 × Chicken Karahi, 1 × Naan"
function order_summary($order)
{
    return implode(', ', array_map(function ($item) {
        return $item['quantity'] . ' × ' . $item['item_name'];
    }, $order['items']));
}

// Next steps the kitchen can take (FR-ORD-03), with button text.
function next_order_statuses($order)
{
    $moves = [
        'pending'   => ['preparing' => 'Start preparing', 'cancelled' => 'Cancel order'],
        'preparing' => ['ready' => 'Mark ready', 'cancelled' => 'Cancel order'],
        'ready'     => ['completed' => 'Mark served / collected'],
    ];
    return $moves[$order['status']] ?? [];
}

// Changes an order's status if that step is allowed. Returns '' or a message.
function change_order_status($order, $status)
{
    if (!isset(next_order_statuses($order)[$status])) {
        return 'That change isn\'t possible for order ' . order_number($order) . ' any more. The page has been refreshed.';
    }
    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ? AND status = ?');
    $stmt->execute([$status, $order['id'], $order['status']]);
    return $stmt->rowCount() ? '' : 'Someone else changed order ' . order_number($order) . ' just now. The page has been refreshed.';
}

/* ---------- Table QR codes (FR-SIG-QR) ---------- */

const QR_TABLE_HOURS = 4; // how long a scanned table is remembered on that phone

// The address printed in a table's QR code, built from SITE_URL so reprinted codes follow a new address.
function table_qr_link($tableId)
{
    return SITE_URL . '/order/table.php?t=' . (int) $tableId;
}

function find_table($tableId)
{
    $stmt = db()->prepare('SELECT t.*, r.name AS restaurant FROM dining_tables t JOIN restaurants r ON r.id = t.restaurant_id WHERE t.id = ? AND t.is_active = 1');
    $stmt->execute([(int) $tableId]);
    return $stmt->fetch() ?: null;
}

// The table this phone scanned in the last few hours, or null.
function scanned_table()
{
    $scan = $_SESSION['qr_table'] ?? null;
    if (!$scan || $scan['at'] < time() - QR_TABLE_HOURS * 3600) {
        unset($_SESSION['qr_table']);
        return null;
    }
    return find_table($scan['id']);
}

// May the person looking at this page see the order? Its customer, an admin, or the walk-in guest who placed it.
function can_see_order($order)
{
    $user = current_user();
    return ($user && ((int) $order['user_id'] === (int) $user['id'] || $user['role'] === 'admin'))
        || in_array((int) $order['id'], $_SESSION['guest_orders'] ?? [], true);
}

// A guest may cancel their own order until the kitchen starts on it.
function guest_can_cancel_order($order)
{
    return $order['status'] === 'pending';
}

/* ---------- Email (FR-MAIL-05) ---------- */

function send_order_confirmation($order)
{
    if (empty($order['guest_email'])) {
        return false; // walk-in QR orders have no email
    }
    $first = explode(' ', trim($order['guest_name']))[0];
    $rows = '';
    foreach ($order['items'] as $item) {
        $rows .= '<tr><td style="padding:7px 0;border-bottom:1px solid #EEEEEE;">' . (int) $item['quantity'] . ' × ' . e($item['item_name']) . '</td>'
            . '<td style="padding:7px 0;border-bottom:1px solid #EEEEEE;text-align:right;">PKR ' . money($item['line_total']) . '</td></tr>';
    }
    $rows .= '<tr><td style="padding:10px 0;"><strong>Total</strong></td><td style="padding:10px 0;text-align:right;"><strong>PKR ' . money($order['total']) . '</strong></td></tr>';
    $link = SITE_URL . '/order/view.php?id=' . $order['id'];
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">We\'ve got your order, ' . e($first) . '</h1>'
        . '<p>Order <strong>' . e(order_number($order)) . '</strong> · ' . e(ORDER_TYPES[$order['order_type']][0]) . ' · ' . e(order_place($order)) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;">' . $rows . '</table>'
        . ($order['notes'] !== '' ? '<p><strong>Your note:</strong> ' . e($order['notes']) . '</p>' : '')
        . '<p>' . e(ORDER_TYPES[$order['order_type']][2]) . '</p>'
        . email_button($link, 'Follow my order');
    $text = "We've got your order, $first\n\nOrder " . order_number($order) . ' (' . ORDER_TYPES[$order['order_type']][0] . ', ' . order_place($order) . ")\n"
        . order_summary($order) . "\nTotal: PKR " . money($order['total']) . "\n\nFollow your order: $link";
    return send_email($order['guest_email'], $order['guest_name'], 'Order ' . order_number($order) . ' received', $html, $text);
}
