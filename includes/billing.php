<?php
/*
 * Billing (FR-BILL-01 to 06, FR-SIG-ROOM-02, 03; D5, D7).
 *   - A stay's bill = the booked room nights + every room service order charged to it
 *     (cancelled orders left out). While the guest is staying they see it as a running bill.
 *   - At check-out one invoice is created for the stay with all those lines (FR-SIG-ROOM-03).
 *   - A completed dine-in or takeaway order gets its own invoice (FR-BILL-01).
 *   - The admin marks an invoice paid, by cash or card; the payment date is saved (FR-BILL-03).
 * Lines are copied into invoice_items, so an invoice never changes after it is made.
 */
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/orders.php';

const PAYMENT_METHODS = ['cash' => 'Cash', 'card' => 'Card at the desk'];

/* ---------- The bill for a stay ---------- */

// Room service orders charged to a booking (not cancelled), oldest first, with their dishes.
function room_service_orders($bookingId)
{
    $stmt = db()->prepare("SELECT id FROM orders WHERE booking_id = ? AND order_type = 'room_service' AND status <> 'cancelled' ORDER BY created_at, id");
    $stmt->execute([$bookingId]);
    return array_map('find_order', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/*
 * The lines of a stay's bill: room nights first, then one line per room service order.
 * Each line: [description, date, quantity, unit price, amount, order id].
 */
function stay_bill_lines($booking)
{
    $nights = booking_nights($booking);
    $lines = [[
        'Room nights: ' . $booking['type_name'] . ', room ' . $booking['room_number'] . ' (' . short_date($booking['check_in']) . ' to ' . short_date($booking['check_out']) . ')',
        $booking['check_in'], $nights, (float) $booking['price_per_night'], (float) $booking['total'], null,
    ]];
    foreach (room_service_orders($booking['id']) as $order) {
        $lines[] = ['Room service order ' . order_number($order) . ': ' . order_summary($order),
            substr($order['created_at'], 0, 10), 1, (float) $order['total'], (float) $order['total'], (int) $order['id']];
    }
    return $lines;
}

// The running bill while a guest stays (FR-SIG-ROOM-02): nights so far plus room service.
function running_bill($booking)
{
    $nightsSoFar = max(1, min(booking_nights($booking), (int) (new DateTime($booking['check_in']))->diff(new DateTime('today'))->days + 1));
    $orders = room_service_orders($booking['id']);
    $roomSoFar = $nightsSoFar * (float) $booking['price_per_night'];
    $serviceTotal = array_sum(array_map(function ($o) { return (float) $o['total']; }, $orders));
    return [
        'nights_so_far' => $nightsSoFar,
        'room_so_far'   => $roomSoFar,
        'orders'        => $orders,
        'service_total' => $serviceTotal,
        'total_so_far'  => $roomSoFar + $serviceTotal,
        'full_stay'     => (float) $booking['total'] + $serviceTotal,
    ];
}

/* ---------- Making invoices ---------- */

// INV-2026-0007: numbered per year, never reused (FR-BILL-01)
function next_invoice_number()
{
    $prefix = 'INV-' . date('Y') . '-';
    $stmt = db()->prepare('SELECT MAX(CAST(SUBSTRING(invoice_number, ?) AS UNSIGNED)) FROM invoices WHERE invoice_number LIKE ?');
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string) ((int) $stmt->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}

// Saves an invoice with its lines. Returns the invoice id.
// The invoice number is unique in the database; if two invoices are made at the same moment
// and pick the same number, the second simply tries the next one.
function save_invoice($userId, $bookingId, $orderId, array $lines)
{
    $pdo = db();
    $total = array_sum(array_column($lines, 4));
    for ($try = 1; ; $try++) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO invoices (invoice_number, user_id, booking_id, order_id, total) VALUES (?, ?, ?, ?, ?)')
                ->execute([next_invoice_number(), $userId, $bookingId, $orderId, $total]);
            $invoiceId = (int) $pdo->lastInsertId();
            $line = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, item_date, quantity, unit_price, amount, order_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($lines as [$description, $date, $quantity, $unitPrice, $amount, $lineOrderId]) {
                $line->execute([$invoiceId, mb_substr($description, 0, 255), $date, $quantity, $unitPrice, $amount, $lineOrderId]);
            }
            $pdo->commit();
            return $invoiceId;
        } catch (PDOException $error) {
            $pdo->rollBack();
            if ($error->getCode() !== '23000' || $try >= 20) { // 23000 = the number was just taken
                throw $error;
            }
            usleep(random_int(5000, 50000)); // a short random pause, so busy moments sort themselves out
        }
    }
}

// The one invoice for a stay, made at check-out (FR-SIG-ROOM-03). Returns the existing one if already made.
function invoice_for_stay($bookingId)
{
    $stmt = db()->prepare('SELECT id FROM invoices WHERE booking_id = ?');
    $stmt->execute([$bookingId]);
    if ($id = $stmt->fetchColumn()) {
        return (int) $id;
    }
    $booking = find_booking((int) $bookingId);
    $id = save_invoice($booking['user_id'], $booking['id'], null, stay_bill_lines($booking));
    send_invoice_email(find_invoice($id));
    return $id;
}

// An invoice for a finished dine-in or takeaway order (room service goes on the stay's invoice).
function invoice_for_order($orderId)
{
    $order = find_order($orderId);
    if (!$order || $order['order_type'] === 'room_service' || $order['status'] !== 'completed') {
        return null;
    }
    $stmt = db()->prepare('SELECT id FROM invoices WHERE order_id = ?');
    $stmt->execute([$order['id']]);
    if ($id = $stmt->fetchColumn()) {
        return (int) $id;
    }
    $lines = [];
    foreach ($order['items'] as $item) {
        $lines[] = [$item['item_name'], substr($order['created_at'], 0, 10), (int) $item['quantity'], (float) $item['unit_price'], (float) $item['line_total'], null];
    }
    $id = save_invoice($order['user_id'], null, $order['id'], $lines);
    send_invoice_email(find_invoice($id));
    return $id;
}

/* ---------- Reading and paying ---------- */

function find_invoice($id)
{
    $stmt = db()->prepare(
        'SELECT i.*, u.name AS guest_name, u.email AS guest_email, u.phone AS guest_phone,
                b.reference AS booking_reference, o.guest_name AS order_guest_name, o.order_type
         FROM invoices i
         LEFT JOIN users u ON u.id = i.user_id
         LEFT JOIN bookings b ON b.id = i.booking_id
         LEFT JOIN orders o ON o.id = i.order_id
         WHERE i.id = ?'
    );
    $stmt->execute([(int) $id]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_date, id');
    $stmt->execute([$invoice['id']]);
    $invoice['items'] = $stmt->fetchAll();
    $invoice['customer'] = $invoice['guest_name'] ?: $invoice['order_guest_name'] ?: 'Walk-in guest';
    return $invoice;
}

// "Stay RM-7K3QX9" / "Order #45"
function invoice_subject($invoice)
{
    return $invoice['booking_id'] ? 'Stay ' . $invoice['booking_reference'] : 'Order #' . $invoice['order_id'];
}

// Marks an invoice paid (FR-BILL-03). Returns '' or a message.
function mark_invoice_paid($invoice, $method)
{
    if (!isset(PAYMENT_METHODS[$method])) {
        return 'Choose how it was paid.';
    }
    $stmt = db()->prepare("UPDATE invoices SET status = 'paid', payment_method = ?, paid_at = NOW() WHERE id = ? AND status = 'unpaid'");
    $stmt->execute([$method, $invoice['id']]);
    return $stmt->rowCount() ? '' : 'This invoice is already paid.';
}

// Who may open an invoice: its guest or an admin (FR-BILL-05).
function can_see_invoice($invoice)
{
    $user = current_user();
    return $user && ($user['role'] === 'admin' || (int) $invoice['user_id'] === (int) $user['id']);
}

/* ---------- Email (FR-MAIL-09) ---------- */

function send_invoice_email($invoice)
{
    if (empty($invoice['guest_email'])) {
        return false;
    }
    $first = explode(' ', trim($invoice['customer']))[0];
    $link = SITE_URL . '/invoice/view.php?id=' . $invoice['id'];
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">Your invoice ' . e($invoice['invoice_number']) . '</h1>'
        . '<p>Hello ' . e($first) . ', thank you for choosing ' . e(HOTEL_NAME) . '. Your invoice for ' . e(invoice_subject($invoice)) . ' comes to <strong>PKR ' . money($invoice['total']) . '</strong>.</p>'
        . email_button($link, 'View and print the invoice');
    $text = "Your invoice {$invoice['invoice_number']}\n\n" . invoice_subject($invoice) . ': PKR ' . money($invoice['total']) . "\n\nView and print it: $link";
    return send_email($invoice['guest_email'], $invoice['customer'], 'Invoice ' . $invoice['invoice_number'] . ' from ' . HOTEL_NAME, $html, $text);
}
