<?php
/*
 * Availability calendar for one room type (FR-SIG-CAL-01; design.md: Room detail).
 * One month at a time: free nights in green with the price, nights with only 1–2 rooms
 * left in amber, full nights in red and crossed out, past days in grey.
 *
 * Without JavaScript the ‹ › arrows are normal links that reload the room page.
 * With JavaScript (assets/js/room.js) they load the next month from booking/calendar.php
 * and clicking a check-in day and then a check-out day fills in the booking box.
 */
require_once __DIR__ . '/bookings.php';

const CALENDAR_MONTHS_AHEAD = 12;

// "2026-11" from the address, kept between this month and 12 months ahead.
function calendar_month($value)
{
    $first = date('Y-m-01');
    $last = date('Y-m-01', strtotime($first . ' +' . CALENDAR_MONTHS_AHEAD . ' months'));
    $month = preg_match('/^\d{4}-\d{2}$/', (string) $value) ? $value . '-01' : $first;
    return min(max($month, $first), $last);
}

function room_calendar($type, $monthStart)
{
    $firstMonth = date('Y-m-01');
    $lastMonth = date('Y-m-01', strtotime($firstMonth . ' +' . CALENDAR_MONTHS_AHEAD . ' months'));
    $next = date('Y-m-01', strtotime($monthStart . ' +1 month'));
    $prev = date('Y-m-01', strtotime($monthStart . ' -1 month'));
    $today = date('Y-m-d');
    $counts = nightly_free_counts($type['id'], $monthStart, $next);
    $price = $type['price_per_night'] !== null ? money($type['price_per_night']) : '';

    $link = function ($month) use ($type) {
        return url('room.php?type=' . urlencode($type['slug']) . '&month=' . substr($month, 0, 7) . '#availability');
    };
    ?>
    <div class="cal" data-calendar data-month="<?= substr($monthStart, 0, 7) ?>">
        <div class="cal-head">
            <?php if ($monthStart > $firstMonth): ?>
                <a href="<?= e($link($prev)) ?>" class="cal-nav" data-month="<?= substr($prev, 0, 7) ?>" aria-label="Previous month"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
            <?php else: ?>
                <span class="cal-nav is-off" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
            <?php endif; ?>
            <h3 aria-live="polite"><?= date('F Y', strtotime($monthStart)) ?></h3>
            <?php if ($monthStart < $lastMonth): ?>
                <a href="<?= e($link($next)) ?>" class="cal-nav" data-month="<?= substr($next, 0, 7) ?>" aria-label="Next month"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            <?php else: ?>
                <span class="cal-nav is-off" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <div class="cal-grid" role="group" aria-label="Nights in <?= date('F Y', strtotime($monthStart)) ?>">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day): ?>
                <span class="cal-weekday" aria-hidden="true"><?= $day ?></span>
            <?php endforeach; ?>
            <?php for ($i = 1; $i < (int) date('N', strtotime($monthStart)); $i++): ?>
                <span class="cal-blank" aria-hidden="true"></span>
            <?php endfor; ?>
            <?php foreach ($counts as $date => $free):
                $label = date('D j M', strtotime($date));
                if ($date < $today) {
                    echo '<span class="cal-day is-past" aria-hidden="true"><span class="cal-num">' . date('j', strtotime($date)) . '</span></span>';
                    continue;
                }
                $state = $free === 0 ? 'is-full' : ($free <= 2 ? 'is-few' : 'is-free');
                $say = $free === 0 ? 'fully booked' : ($free === 1 ? '1 room left' : $free . ' rooms free');
            ?>
                <button type="button" class="cal-day <?= $state ?><?= $date === $today ? ' is-today' : '' ?>" data-date="<?= $date ?>" data-free="<?= $free ?>"
                        aria-label="<?= e($label . ': ' . $say . ($free && $price ? ', PKR ' . $price : '')) ?>">
                    <span class="cal-num"><?= date('j', strtotime($date)) ?></span>
                    <?php if ($free && $price): ?><span class="cal-price"><?= $price ?></span><?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
