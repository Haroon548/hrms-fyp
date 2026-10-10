<?php
/*
 * Changes the cart (FR-ORD-01). Every "Add" button on the menu is a normal form that
 * posts here, so ordering works without JavaScript. With JavaScript (assets/js/dining.js)
 * the same request is sent in the background and the answer is JSON, so the page doesn't reload.
 *
 * Actions: add (one more of a dish), set (a quantity), clear.
 */
require_once '../includes/init.php';
require_once '../includes/orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dining.php#menu');
}
check_csrf();

$itemId = (int) ($_POST['item_id'] ?? 0);
switch ($_POST['action'] ?? '') {
    case 'add':
        cart_set($itemId, (cart()[$itemId] ?? 0) + 1);
        break;
    case 'set':
        cart_set($itemId, (int) ($_POST['quantity'] ?? 0));
        break;
    case 'clear':
        cart_clear();
        break;
}

$details = cart_details();

// Background request from the page: answer with the cart
if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
    header('Content-Type: application/json');
    echo json_encode([
        'count' => $details['count'],
        'total' => $details['total'],
        'total_text' => 'PKR ' . money($details['total']),
        'lines' => array_map(function ($line) {
            return $line + ['price_text' => 'PKR ' . money($line['price']), 'line_text' => 'PKR ' . money($line['line_total'])];
        }, $details['lines']),
    ]);
    exit;
}

// Normal form: back to where the guest was
if (($_POST['action'] ?? '') === 'add') {
    flash('success', 'Added to your order. ' . $details['count'] . ($details['count'] === 1 ? ' item' : ' items') . ', PKR ' . money($details['total']) . '.');
}
redirect(safe_next($_POST['return'] ?? '', 'dining.php') . '#menu');
