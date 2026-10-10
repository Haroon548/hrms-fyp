<?php
/*
 * Admin: messages from the Contact page and event enquiries (FR-WEB-05, D13).
 * New / Event enquiries / Handled / All, a search box, and for each message: reply by email,
 * call or WhatsApp, and "Mark handled" (or back to new). New messages appear by themselves
 * (live updates), and the sidebar shows how many are unread.
 */
require_once '../includes/init.php';
require_once '../includes/messages.php';
require_once '../includes/admin-layout.php';
require_admin();

$views = [
    'new'     => ['New', 'm.is_handled = 0'],
    'events'  => ['Event enquiries', "m.topic = 'events' AND m.is_handled = 0"],
    'handled' => ['Handled', 'm.is_handled = 1'],
    'all'     => ['All', '1 = 1'],
];
$view = isset($views[$_GET['view'] ?? '']) ? $_GET['view'] : 'new';
$q = trim((string) ($_GET['q'] ?? ''));
$here = 'admin/messages.php?' . http_build_query(array_filter(['view' => $view, 'q' => $q]));

if (admin_action() === 'handled') {
    $handled = !empty($_POST['handled']) ? 1 : 0;
    $stmt = db()->prepare('UPDATE contact_messages SET is_handled = ?, handled_at = ' . ($handled ? 'NOW()' : 'NULL') . ' WHERE id = ?');
    $stmt->execute([$handled, (int) ($_POST['id'] ?? 0)]);
    flash('success', $handled ? 'Marked as handled.' : 'Moved back to new messages.');
    redirect($here);
}

$search = '';
$params = [];
if ($q !== '') {
    $search = ' AND (m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.subject LIKE ? OR m.message LIKE ?)';
    $params = array_fill(0, 5, '%' . $q . '%');
}
$counts = [];
foreach ($views as $key => [, $where]) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM contact_messages m WHERE $where$search");
    $stmt->execute($params);
    $counts[$key] = (int) $stmt->fetchColumn();
}
$stmt = db()->prepare("SELECT m.* FROM contact_messages m WHERE {$views[$view][1]}$search ORDER BY m.created_at DESC, m.id DESC LIMIT 200");
$stmt->execute($params);
$messages = $stmt->fetchAll();

// "5 min ago", "3 h ago", "Sat 10 Oct"
function received($datetime)
{
    $seconds = time() - strtotime($datetime);
    if ($seconds < 3600) return max(1, (int) floor($seconds / 60)) . ' min ago';
    if ($seconds < 86400) return (int) floor($seconds / 3600) . ' h ago';
    return date('D j M, g:i A', strtotime($datetime));
}

live_watch('contact_messages'); // new messages appear by themselves
admin_open('Messages', 'messages', 'Messages from the Contact page and event enquiries. Reply, then mark them handled.',
    '<a href="' . url('contact.php') . '" class="btn-small-outline" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Contact page</a>');
?>

<nav class="account-tabs admin-tabs" aria-label="Message views">
    <?php foreach ($views as $key => [$label]): ?>
        <a href="<?= url('admin/messages.php?' . http_build_query(array_filter(['view' => $key, 'q' => $q]))) ?>" class="<?= $key === $view ? 'active' : '' ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>>
            <?= e($label) ?><?php if ($counts[$key] && $key !== 'handled' && $key !== 'all'): ?> <span class="tab-count"><?= $counts[$key] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<form method="get" class="admin-search" role="search">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <label for="q" class="visually-hidden">Search messages</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone or words in the message">
    <button type="submit" class="btn-small-red"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
    <?php if ($q !== ''): ?><a href="<?= url('admin/messages.php?view=' . $view) ?>" class="admin-link">Clear</a><?php endif; ?>
</form>

<?php if (!$messages): ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-regular fa-envelope-open" aria-hidden="true"></i></span>
        <h2><?= $view === 'new' && $q === '' ? 'All caught up' : 'No messages here' ?></h2>
        <p>Messages sent from the Contact and Meeting &amp; Events pages appear here as soon as they arrive.</p>
    </div>
<?php else: ?>
    <div class="message-list">
        <?php foreach ($messages as $m): [$topicLabel, $topicIcon] = MESSAGE_TOPICS[$m['topic']] ?? MESSAGE_TOPICS['general'];
            $phoneDigits = preg_replace('/[^0-9]/', '', $m['phone']);
            $whatsapp = strpos($phoneDigits, '0') === 0 ? '92' . substr($phoneDigits, 1) : $phoneDigits; ?>
            <article class="message-card<?= $m['is_handled'] ? ' is-handled' : '' ?>" id="m<?= $m['id'] ?>">
                <header>
                    <span class="message-topic topic-<?= e($m['topic']) ?>"><i class="<?= $topicIcon ?>" aria-hidden="true"></i> <?= e($topicLabel) ?></span>
                    <span class="admin-muted"><?= e(received($m['created_at'])) ?></span>
                    <?= $m['is_handled'] ? status_badge('completed') : '<span class="status-badge badge-yellow">New</span>' ?>
                </header>
                <h2><?= e($m['subject']) ?></h2>
                <p class="message-text"><?= nl2br(e($m['message'])) ?></p>
                <p class="message-from"><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?php if ($m['phone'] !== ''): ?> · <a href="tel:<?= e($phoneDigits) ?>"><?= e($m['phone']) ?></a><?php endif; ?></p>
                <div class="message-actions">
                    <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>" class="btn-small-red"><i class="fa-solid fa-reply" aria-hidden="true"></i> Reply by email</a>
                    <?php if ($m['phone'] !== ''): ?>
                        <a href="tel:<?= e($phoneDigits) ?>" class="btn-small-outline"><i class="fa-solid fa-phone" aria-hidden="true"></i> Call</a>
                        <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="btn-small-outline"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                    <?php endif; ?>
                    <form method="post" class="ms-auto"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="handled"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <input type="hidden" name="handled" value="<?= $m['is_handled'] ? 0 : 1 ?>">
                        <button type="submit" class="btn-small-outline"><?= $m['is_handled'] ? '<i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Mark as new' : '<i class="fa-solid fa-check" aria-hidden="true"></i> Mark handled' ?></button>
                    </form>
                </div>
                <?php if ($m['is_handled'] && $m['handled_at']): ?><p class="admin-muted mb-0 mt-2">Handled <?= e(date('D j M, g:i A', strtotime($m['handled_at']))) ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php admin_close();
