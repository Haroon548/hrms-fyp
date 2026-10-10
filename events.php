<?php
/*
 * Meeting & Events (FR-WEB-07, FR-WEB-24; design.md: Meeting & Events, screenshot 07).
 * Ramada's page is one photo, one paragraph and a table of hall sizes. Ours, in the Home style (D20):
 *   1. a photo banner of the banquet hall with "Plan your event"
 *   2. what the hotel hosts (photo cards) and numbers worked out from the halls
 *   3. each hall as a card with a floor plan drawn to scale, so sizes can be compared at a glance,
 *      plus the same figures in a table (Ramada's table, kept for people who prefer it)
 *   4. a swipeable row of the hotel's banquet photos (from the Gallery)
 *   5. an event enquiry form: type, date, guests and hall, saved for the admin like a
 *      Contact message (topic "events") and emailed to the hotel (D13)
 * Halls are edited in the admin panel (admin/halls.php) and update here live.
 * Event booking itself is not in scope: the events team replies to each enquiry.
 */
require_once 'includes/init.php';
require_once 'includes/messages.php';
require_once 'includes/auth-layout.php'; // text_input()

$halls = db()->query('SELECT * FROM halls ORDER BY sort_order, id')->fetchAll();
$hallNames = array_column($halls, 'name', 'id');

$values = sender_defaults() + ['event_type' => '', 'event_date' => '', 'guests' => '', 'hall_id' => (int) ($_GET['hall'] ?? 0), 'message' => ''];
$errors = [];
$blocked = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $values = [
        'name'       => trim((string) ($_POST['name'] ?? '')),
        'email'      => trim((string) ($_POST['email'] ?? '')),
        'phone'      => trim((string) ($_POST['phone'] ?? '')),
        'event_type' => (string) ($_POST['event_type'] ?? ''),
        'event_date' => (string) ($_POST['event_date'] ?? ''),
        'guests'     => trim((string) ($_POST['guests'] ?? '')),
        'hall_id'    => (int) ($_POST['hall_id'] ?? 0),
        'message'    => trim((string) ($_POST['message'] ?? '')),
    ];
    $errors = check_sender($values, true);
    if (!in_array($values['event_type'], EVENT_TYPES, true)) {
        $errors['event_type'] = 'Choose the kind of event.';
    }
    $date = DateTime::createFromFormat('!Y-m-d', $values['event_date']);
    if ($values['event_date'] !== '' && (!$date || $values['event_date'] < date('Y-m-d') || $values['event_date'] > date('Y-m-d', strtotime('+2 years')))) {
        $errors['event_date'] = 'Choose a date from today up to two years ahead, or leave it empty.';
    }
    if ($values['guests'] !== '' && (!ctype_digit($values['guests']) || $values['guests'] < 1 || $values['guests'] > 5000)) {
        $errors['guests'] = 'Enter roughly how many guests, e.g. 250, or leave it empty.';
    }
    if ($values['hall_id'] && !isset($hallNames[$values['hall_id']])) {
        $values['hall_id'] = 0; // the hall was removed meanwhile: "Not sure yet"
    }
    if (mb_strlen($values['message']) > 2000) {
        $errors['message'] = 'Keep your message under 2,000 characters.';
    }
    $blocked = $errors ? '' : message_blocked();
    if (!$errors && $blocked === '') {
        // One readable subject line for the admin's inbox, e.g. "Wedding or walima · Sat 12 Dec 2026 · 300 guests · Zainab Hall"
        $parts = array_filter([
            $values['event_type'],
            $values['event_date'] !== '' ? date('D j M Y', strtotime($values['event_date'])) : 'date not fixed',
            $values['guests'] !== '' ? $values['guests'] . ' guests' : '',
            $values['hall_id'] ? $hallNames[$values['hall_id']] : '',
        ]);
        save_message([
            'name' => $values['name'], 'email' => $values['email'], 'phone' => $values['phone'], 'topic' => 'events',
            'subject' => implode(' · ', $parts),
            'message' => $values['message'] !== '' ? $values['message'] : '(No extra details given.)',
        ]);
        flash('success', 'Thank you, ' . explode(' ', $values['name'])[0] . '. Our events team has your enquiry and will call or email you soon, usually within a day.');
        redirect('events.php#enquiry');
    }
}

$hero = site_photo('events_hero', 'Banquet hall');
$types = [
    ['events_wedding', 'fa-solid fa-ring', 'Weddings & walimas', 'Stages, flowers and dinner for hundreds, in Zainab Hall or by the pool.'],
    ['events_meeting', 'fa-solid fa-briefcase', 'Meetings & conferences', 'Board meetings, trainings and seminars with tea breaks and lunch.'],
    ['events_poolside', 'fa-solid fa-water-ladder', 'Poolside evenings', 'Open-air dinners and mehndis around the pool, lit up at night.'],
    ['events_family', 'fa-solid fa-cake-candles', 'Family celebrations', 'Birthdays, engagements and get-togethers, catered by our kitchen.'],
];
$banquet = db()->query("SELECT file, caption FROM gallery_images WHERE category = 'Banquet' ORDER BY sort_order, id LIMIT 12")->fetchAll();
$largest = $halls ? max(array_column($halls, 'area')) : 0;
$maxLength = $halls ? max(array_merge(array_column($halls, 'length'), array_column($halls, 'width'))) : 1;

// "542.7" → "542.7", "33.0" → "33"
function metres($value)
{
    return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
}

// Gallery thumbnail (same path under gallery/thumbs/), falling back to the full photo
function banquet_thumb($file)
{
    $thumb = 'gallery/thumbs/' . substr($file, strlen('gallery/'));
    return is_file(__DIR__ . '/assets/images/' . $thumb) ? $thumb : $file;
}

$page_title = 'Meeting & Events | ' . HOTEL_NAME;
$page_description = 'Weddings, conferences and family events at Ramada by Wyndham Multan: four halls from a 33 m² board room to the 543 m² Zainab Hall. Send an enquiry online.';
$share_image = $hero['file'] ?: 'slides/slide-1.jpg';
$active_page = 'events';
$flash_inline = true;
$page_scripts = ['assets/vendor/gsap-3.15.0/gsap.min.js', 'assets/vendor/gsap-3.15.0/ScrollTrigger.min.js', 'assets/js/spotlight.js', 'assets/js/home.js', 'assets/js/checkout.js', 'assets/js/contact.js'];
live_watch('halls'); // hall sizes change by themselves when the admin edits them
include 'includes/header.php';
?>

<section class="about-hero events-hero">
    <div class="about-hero-photo" data-parallax="12"><?php photo($hero['file'], $hero['alt_text']); ?></div>
    <div class="container">
        <div class="about-hero-card" data-hero-in>
            <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> Meeting &amp; Events</nav>
            <p class="eyebrow">Meeting &amp; Events</p>
            <h1>Your celebration or conference, in the heart of Multan</h1>
            <p><?= count($halls) ?> halls and meeting rooms for weddings, conferences and family events, with our own kitchen.</p>
            <div class="hero-actions">
                <a href="#enquiry" class="btn-primary-red">Plan your event</a>
                <a href="#halls" class="btn-glass">See the halls</a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php section_heading('Meeting and Events', 'Whatever the occasion, we set the stage'); ?>
        <p class="events-intro">Ramada by Wyndham Multan is a natural pick for your next meeting, conference or celebration in Multan. Tell us the date and how many guests, and our events team will help you choose the hall, the layout and the menu.</p>
        <ul class="events-stats" data-live-region="stats">
            <li><strong><?= count($halls) ?></strong><span>Halls &amp; rooms</span></li>
            <li><strong><?= metres($largest) ?> m²</strong><span>Our largest hall</span></li>
            <li><strong>3</strong><span>Restaurants to cater</span></li>
            <li><strong>24/7</strong><span>Rooms for your guests</span></li>
        </ul>
        <div class="event-types">
            <?php foreach ($types as [$location, $icon, $title, $text]): $p = site_photo($location, $title); ?>
                <article class="event-type" data-spotlight data-spotlight-color="rgba(255, 255, 255, 0.18)" data-spotlight-size="260">
                    <?php photo($p['file'], $p['alt_text'], 'reveal'); ?>
                    <div class="event-type-text">
                        <i class="<?= $icon ?>" aria-hidden="true"></i>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($text) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt" id="halls" aria-labelledby="hallsHeading">
    <div class="container" data-live-region="halls">
        <div class="section-heading">
            <h2 id="hallsHeading">Our halls</h2>
            <p class="subtitle">Floor plans drawn to the same scale</p>
            <span class="divider"></span>
        </div>
        <?php if (!$halls): ?>
            <p class="text-center">Please <a href="<?= url('contact.php?topic=events') ?>">contact us</a> for our event spaces.</p>
        <?php else: ?>
            <div class="halls">
                <?php foreach ($halls as $i => $h): ?>
                    <article class="hall-card">
                        <div class="hall-plan" aria-hidden="true">
                            <span class="hall-shape" style="width: <?= round($h['length'] / $maxLength * 100, 1) ?>%; height: <?= round($h['width'] / $maxLength * 100, 1) ?>%;">
                                <span class="hall-dim hall-dim-l"><?= metres($h['length']) ?> m</span>
                                <span class="hall-dim hall-dim-w"><?= metres($h['width']) ?> m</span>
                            </span>
                        </div>
                        <div class="hall-text">
                            <span class="hall-number">0<?= $i + 1 ?></span>
                            <h3><?= e($h['name']) ?></h3>
                            <?php if ($h['description'] !== ''): ?><p><?= e($h['description']) ?></p><?php endif; ?>
                            <ul class="hall-facts">
                                <li><i class="fa-solid fa-expand" aria-hidden="true"></i> <strong><?= metres($h['area']) ?> m²</strong></li>
                                <li><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> <?= metres($h['length']) ?> × <?= metres($h['width']) ?> m</li>
                                <li><i class="fa-solid fa-up-down" aria-hidden="true"></i> Ceiling <?= metres($h['height']) ?> m</li>
                            </ul>
                            <a href="<?= url('events.php?hall=' . $h['id'] . '#enquiry') ?>" class="text-link" data-pick-hall="<?= $h['id'] ?>">Enquire about this hall</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="halls-scale"><i class="fa-solid fa-ruler" aria-hidden="true"></i> Each plan's square is <?= metres($maxLength) ?> m across, so the shapes show the real difference in size.</p>

            <div class="table-scroll">
                <table class="halls-table">
                    <caption>All halls at a glance</caption>
                    <thead><tr><th scope="col">Hall</th><th scope="col">Area (m²)</th><th scope="col">Height (m)</th><th scope="col">Length (m)</th><th scope="col">Width (m)</th></tr></thead>
                    <tbody>
                        <?php foreach ($halls as $h): ?>
                            <tr><th scope="row"><?= e($h['name']) ?></th><td><?= metres($h['area']) ?></td><td><?= metres($h['height']) ?></td><td><?= metres($h['length']) ?></td><td><?= metres($h['width']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($banquet): ?>
<section class="section events-photos" aria-labelledby="momentsHeading">
    <div class="container">
        <div class="events-photos-head">
            <div>
                <p class="eyebrow">Events at Ramada Multan</p>
                <h2 class="display-heading" id="momentsHeading">Set up by our team</h2>
            </div>
            <a href="<?= url('gallery.php?tab=banquet') ?>" class="text-link">All event photos</a>
        </div>
    </div>
    <ul class="events-strip">
        <?php foreach ($banquet as $photo): ?>
            <li><a href="<?= url('gallery.php?tab=banquet') ?>"><?php photo(banquet_thumb($photo['file']), $photo['caption'] ?: 'Event at the hotel'); ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="section enquiry-band" id="enquiry" aria-labelledby="enquiryHeading">
    <div class="container">
        <div class="row g-4 gx-lg-5 align-items-start">
            <div class="col-lg-5">
                <p class="eyebrow">Plan your event</p>
                <h2 class="display-heading" id="enquiryHeading">Tell us about your event</h2>
                <p>Send the details and our events team will call or email you, usually within a day, with available dates, layouts and menus. Nothing is booked or paid until you agree.</p>
                <ul class="enquiry-points">
                    <li><i class="fa-solid fa-utensils" aria-hidden="true"></i> Menus from our own kitchen, local and continental</li>
                    <li><i class="fa-solid fa-bed" aria-hidden="true"></i> Rooms for out-of-town guests</li>
                    <li><i class="fa-solid fa-square-parking" aria-hidden="true"></i> Free parking at the hotel</li>
                </ul>
                <p class="enquiry-call">Prefer to talk? <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>"><?= e(HOTEL_PHONE) ?></a> · <a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>" target="_blank" rel="noopener">WhatsApp</a></p>
            </div>
            <div class="col-lg-7">
                <form method="post" action="<?= url('events.php') ?>#enquiry" class="contact-card" novalidate data-checkout-form>
                    <?php show_flash(); ?>
                    <?php if ($errors || $blocked): ?>
                        <div class="notice notice-error error-summary" role="alert" tabindex="-1" data-error-summary>
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <div>
                                <strong><?= $blocked ? e($blocked) : 'Please check ' . (count($errors) === 1 ? 'this field' : 'these ' . count($errors) . ' fields') . ':' ?></strong>
                                <?php if ($errors): ?>
                                    <ul><?php foreach ($errors as $field => $message): ?><li><a href="#f_<?= $field ?>"><?= e($message) ?></a></li><?php endforeach; ?></ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?= csrf_field() ?>
                    <div class="visually-hidden" aria-hidden="true"><label for="f_website">Website</label><input type="text" id="f_website" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-field<?= isset($errors['event_type']) ? ' has-error' : '' ?>">
                                <label for="f_event_type">Kind of event</label>
                                <select id="f_event_type" name="event_type" required<?= isset($errors['event_type']) ? ' aria-invalid="true" aria-describedby="f_event_type_error"' : '' ?>>
                                    <option value="">Choose…</option>
                                    <?php foreach (EVENT_TYPES as $type): ?><option<?= $values['event_type'] === $type ? ' selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['event_type'])): ?><p class="field-error" id="f_event_type_error"><?= e($errors['event_type']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-field">
                                <label for="f_hall">Hall</label>
                                <select id="f_hall" name="hall_id">
                                    <option value="0">Not sure yet: please advise</option>
                                    <?php foreach ($halls as $h): ?><option value="<?= $h['id'] ?>"<?= (int) $values['hall_id'] === (int) $h['id'] ? ' selected' : '' ?>><?= e($h['name']) ?> (<?= metres($h['area']) ?> m²)</option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-field<?= isset($errors['event_date']) ? ' has-error' : '' ?>">
                                <label for="f_event_date">Date <span class="label-optional">(optional)</span></label>
                                <input type="date" id="f_event_date" name="event_date" value="<?= e($values['event_date']) ?>" min="<?= date('Y-m-d') ?>"<?= isset($errors['event_date']) ? ' aria-invalid="true" aria-describedby="f_event_date_error"' : '' ?>>
                                <?php if (isset($errors['event_date'])): ?><p class="field-error" id="f_event_date_error"><?= e($errors['event_date']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-field<?= isset($errors['guests']) ? ' has-error' : '' ?>">
                                <label for="f_guests">Guests, roughly <span class="label-optional">(optional)</span></label>
                                <input type="number" id="f_guests" name="guests" value="<?= e($values['guests']) ?>" min="1" max="5000" inputmode="numeric" placeholder="e.g. 250"<?= isset($errors['guests']) ? ' aria-invalid="true" aria-describedby="f_guests_error"' : '' ?>>
                                <?php if (isset($errors['guests'])): ?><p class="field-error" id="f_guests_error"><?= e($errors['guests']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6"><?php text_input('name', 'Your name', 'text', 'name', e($values['name']), $errors['name'] ?? '', 'maxlength="100"'); ?></div>
                        <div class="col-md-6"><?php text_input('phone', 'Phone', 'tel', 'tel', e($values['phone']), $errors['phone'] ?? '', 'maxlength="20"'); ?></div>
                        <div class="col-12"><?php text_input('email', 'Email', 'email', 'email', e($values['email']), $errors['email'] ?? '', 'maxlength="191"'); ?></div>
                        <div class="col-12">
                            <div class="form-field<?= isset($errors['message']) ? ' has-error' : '' ?>">
                                <label for="f_message">Anything else? <span class="label-optional">(optional)</span></label>
                                <textarea id="f_message" name="message" rows="3" maxlength="2000" placeholder="e.g. stage and decor, set menu, projector for a presentation" data-count="2000" aria-describedby="f_message_count"><?= e($values['message']) ?></textarea>
                                <p class="field-help field-count" id="f_message_count" aria-live="polite"><?= mb_strlen($values['message']) ?> / 2,000</p>
                                <?php if (isset($errors['message'])): ?><p class="field-error"><?= e($errors['message']) ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-red btn-complete" data-busy="SENDING…"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send enquiry</button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php include 'includes/footer.php';
