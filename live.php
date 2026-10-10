<?php
/*
 * Live updates (FR-LIVE-01, D17). Open pages ask: "has anything changed since <sum>?"
 * The answer is the version number of every table (live_versions). If nothing has changed,
 * the question is held open for up to LIVE_WAIT_SECONDS and answered the moment something
 * is saved (long polling), so a change shows on other screens within about a second while
 * each browser only sends one request every ~20 seconds. assets/js/live.js asks the questions.
 *   live.php?since=57
 */
require_once 'includes/init.php';
session_write_close(); // don't keep the visitor's session locked while waiting

$wait = defined('LIVE_WAIT_SECONDS') ? max(0, min(25, (int) LIVE_WAIT_SECONDS)) : 20;
set_time_limit($wait + 15);
header('Content-Type: application/json');
header('Cache-Control: no-store');

$since = (string) ($_GET['since'] ?? '');
$until = time() + $wait;
do {
    $versions = live_versions() ?? [];
    $sum = (string) array_sum($versions);
    if ($sum !== $since || time() >= $until) {
        break;
    }
    sleep(1);
} while (!connection_aborted());

echo json_encode([
    'sum'    => $sum,
    'tables' => (object) $versions,
    'next'   => $wait > 0 ? 0 : 15, // seconds before asking again (no waiting on the server → ask every 15 s)
]);
