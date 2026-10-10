<?php
/*
 * Messages from the Contact page and event enquiries from Meeting & Events
 * (FR-WEB-04, FR-WEB-05, FR-WEB-24; D13). Each message is saved for the admin to read
 * (admin/messages.php), the hotel gets an email (NOTIFY_EMAIL in config.php), and the sender gets a short "we've got it"
 * email. A failed email never loses the message (D4).
 */
require_once __DIR__ . '/mail.php';

const MESSAGE_TOPICS = [
    'general'  => ['General question', 'fa-regular fa-comment'],
    'rooms'    => ['Rooms & bookings', 'fa-solid fa-bed'],
    'dining'   => ['Restaurants', 'fa-solid fa-utensils'],
    'events'   => ['Meetings & events', 'fa-solid fa-champagne-glasses'],
    'feedback' => ['Feedback', 'fa-regular fa-star'],
];
const EVENT_TYPES = ['Wedding or walima', 'Mehndi or engagement', 'Conference or seminar', 'Business meeting', 'Birthday or family event', 'Other'];
const MESSAGES_PER_HOUR = 5; // per visitor, to slow down spam

// Name, email and phone checks shared by both forms. Returns errors by field.
function check_sender(array $v, $phoneRequired)
{
    $errors = [];
    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 100) {
        $errors['name'] = 'Please tell us your name.';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 191) {
        $errors['email'] = 'Enter a valid email address, so we can reply.';
    }
    if (($phoneRequired || $v['phone'] !== '') && !preg_match('/^\+?[0-9 \-]{7,20}$/', $v['phone'])) {
        $errors['phone'] = $phoneRequired ? 'Enter a phone number, so our events team can call you, e.g. 0300 1234567.' : 'Enter a phone number like 0300 1234567, or leave it empty.';
    }
    return $errors;
}

// A filled-in hidden "website" box means a spam robot; too many messages from one visitor is refused.
// Returns a message for the visitor, or ''.
function message_blocked()
{
    if (trim($_POST['website'] ?? '') !== '') {
        return 'Your message could not be sent.';
    }
    $recent = array_filter($_SESSION['messages_sent'] ?? [], function ($at) { return $at > time() - 3600; });
    if (count($recent) >= MESSAGES_PER_HOUR) {
        return 'You have sent several messages in the last hour. Please call us on ' . HOTEL_PHONE . ' if it is urgent.';
    }
    return '';
}

/*
 * Saves a message and sends the emails. $m: name, email, phone, topic, subject, message.
 * Returns the new message's id.
 */
function save_message(array $m)
{
    db()->prepare('INSERT INTO contact_messages (name, email, phone, topic, subject, message) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$m['name'], $m['email'], $m['phone'], $m['topic'], mb_substr($m['subject'], 0, 150), $m['message']]);
    $id = (int) db()->lastInsertId();
    $_SESSION['messages_sent'][] = time();

    // To the hotel (D13)
    $topic = MESSAGE_TOPICS[$m['topic']][0] ?? 'Message';
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">New message: ' . e($topic) . '</h1>'
        . '<p><strong>' . e($m['subject']) . '</strong></p>'
        . '<p style="white-space:pre-line;">' . e($m['message']) . '</p>'
        . '<p>From <strong>' . e($m['name']) . '</strong><br>' . e($m['email']) . ($m['phone'] !== '' ? '<br>' . e($m['phone']) : '') . '</p>'
        . email_button(SITE_URL . '/admin/messages.php', 'Open messages');
    $text = "New message: $topic\n\n{$m['subject']}\n\n{$m['message']}\n\nFrom {$m['name']}, {$m['email']} {$m['phone']}";
    send_email(defined('NOTIFY_EMAIL') ? NOTIFY_EMAIL : HOTEL_EMAIL, HOTEL_NAME, $topic . ': ' . $m['subject'], $html, $text);

    // To the sender
    $first = explode(' ', trim($m['name']))[0];
    $html = '<h1 style="font-size:22px;color:#D21242;font-weight:normal;margin:0 0 16px;">We\'ve got your message, ' . e($first) . '</h1>'
        . '<p>Thank you for writing to ' . e(HOTEL_NAME) . '. Our team will reply to this email address' . ($m['phone'] !== '' ? ' or call you' : '') . ' soon.</p>'
        . '<p style="color:#707070;">Your message: <strong>' . e($m['subject']) . '</strong></p>'
        . '<p>If it is urgent, call us on ' . e(HOTEL_PHONE) . ' or message us on WhatsApp.</p>';
    $text = "We've got your message, $first\n\nThank you for writing to " . HOTEL_NAME . ". Our team will reply soon.\n\nIf it is urgent, call us on " . HOTEL_PHONE . '.';
    send_email($m['email'], $m['name'], 'We\'ve got your message', $html, $text);

    return $id;
}

// The values a form starts with: the logged-in guest's details, so they don't type them again.
function sender_defaults()
{
    $user = current_user();
    return ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? '', 'phone' => $user['phone'] ?? ''];
}

// "Open now · until 11:30 PM" for a restaurant (Contact page), from its reservation hours.
function open_now_text($restaurant)
{
    $now = date('H:i:s');
    if ($now >= $restaurant['opens_at'] && $now < $restaurant['closes_at']) {
        return [true, 'Open now · until ' . date('g:i A', strtotime($restaurant['closes_at']))];
    }
    return [false, 'Closed now · opens ' . date('g:i A', strtotime($restaurant['opens_at']))];
}
