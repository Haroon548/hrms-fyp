<?php
/*
 * Contact Us (FR-WEB-04, 05, 20, 25; design.md: Contact, screenshots 11 and 12).
 * Ramada's page is a map, a list of numbers and a form whose messages only go to an inbox.
 * Ours, in the Home style (D20):
 *   1. a photo banner with one-tap Call, WhatsApp, Email and Directions
 *   2. the contact details, who is open right now, and the "Write us" form
 *      (saved for the admin, emailed to the hotel, a copy to the guest; D13)
 *   3. the Google map (the only outside service on the page, D15) with Get directions
 *   4. questions and answers the admin edits (FAQ), updated live
 */
require_once 'includes/init.php';
require_once 'includes/messages.php';
require_once 'includes/auth-layout.php'; // text_input()

$values = sender_defaults() + ['topic' => array_key_exists($_GET['topic'] ?? '', MESSAGE_TOPICS) ? $_GET['topic'] : 'general', 'subject' => '', 'message' => ''];
$errors = [];
$blocked = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $values = [
        'name'    => trim((string) ($_POST['name'] ?? '')),
        'email'   => trim((string) ($_POST['email'] ?? '')),
        'phone'   => trim((string) ($_POST['phone'] ?? '')),
        'topic'   => array_key_exists($_POST['topic'] ?? '', MESSAGE_TOPICS) ? $_POST['topic'] : 'general',
        'subject' => trim((string) ($_POST['subject'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];
    $errors = check_sender($values, false);
    if (mb_strlen($values['subject']) < 3 || mb_strlen($values['subject']) > 150) {
        $errors['subject'] = 'Give your message a short subject.';
    }
    if (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 2000) {
        $errors['message'] = 'Write your message (10 to 2,000 characters).';
    }
    $blocked = $errors ? '' : message_blocked();
    if (!$errors && $blocked === '') {
        save_message($values);
        flash('success', 'Thank you, ' . explode(' ', $values['name'])[0] . '. Your message has reached the hotel, and a copy is on its way to ' . $values['email'] . '. We usually reply within a day.');
        redirect('contact.php#write');
    }
}

$exterior = site_photo('exterior', 'Ramada by Wyndham Multan building');
$restaurants = db()->query('SELECT name, hours, opens_at, closes_at FROM restaurants ORDER BY sort_order')->fetchAll();
$faqs = db()->query('SELECT question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$phoneLink = 'tel:' . str_replace(' ', '', HOTEL_PHONE);
$mapsLink = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(HOTEL_NAME . ', ' . HOTEL_ADDRESS);
$labels = ['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'subject' => 'Subject', 'message' => 'Message'];

$page_title = 'Contact Us | ' . HOTEL_NAME;
$page_description = 'Call, WhatsApp or write to Ramada by Wyndham Multan, 76 Abdali Road. Front desk open 24 hours.';
$share_image = $exterior['file'] ?: 'slides/slide-1.jpg';
$active_page = 'contact';
$flash_inline = true;
$page_scripts = ['assets/js/checkout.js', 'assets/js/contact.js'];
live_watch('faqs', 'restaurants'); // answers and opening hours change by themselves
include 'includes/header.php';
?>

<section class="page-banner contact-banner">
    <?php photo($exterior['file'], $exterior['alt_text'], 'page-banner-photo'); ?>
    <div class="container page-banner-text">
        <nav class="crumbs" aria-label="You are here"><a href="<?= url('index.php') ?>">Home</a> <span aria-hidden="true">/</span> Contact Us</nav>
        <h1>Contact us</h1>
        <p>Our front desk is open 24 hours. Call, message or write: whatever is easiest for you.</p>
        <ul class="quick-contact">
            <li><a href="<?= e($phoneLink) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> Call</a></li>
            <li><a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a></li>
            <li><a href="mailto:<?= e(HOTEL_EMAIL) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</a></li>
            <li><a href="<?= e($mapsLink) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-arrow" aria-hidden="true"></i> Directions</a></li>
        </ul>
    </div>
</section>

<section class="section contact-section">
    <div class="container">
        <div class="row g-4 gx-lg-5">
            <div class="col-lg-5">
                <p class="eyebrow">Get in touch</p>
                <h2 class="display-heading">Contact information</h2>
                <ul class="contact-list">
                    <li><span class="contact-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                        <div><strong>Address</strong><a href="<?= e($mapsLink) ?>" target="_blank" rel="noopener"><?= e(HOTEL_ADDRESS) ?></a></div></li>
                    <li><span class="contact-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                        <div><strong>Phone</strong><span><a href="<?= e($phoneLink) ?>"><?= e(HOTEL_PHONE) ?></a> / <?= e(HOTEL_PHONE_2) ?></span></div></li>
                    <li><span class="contact-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
                        <div><strong>WhatsApp</strong><a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>" target="_blank" rel="noopener">+<?= e(substr(HOTEL_WHATSAPP, 0, 2) . ' ' . substr(HOTEL_WHATSAPP, 2, 3) . ' ' . substr(HOTEL_WHATSAPP, 5, 3) . ' ' . substr(HOTEL_WHATSAPP, 8)) ?></a></div></li>
                    <li><span class="contact-icon"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></span>
                        <div><strong>GSM</strong><a href="tel:<?= e(str_replace(' ', '', HOTEL_GSM)) ?>"><?= e(HOTEL_GSM) ?></a></div></li>
                    <li><span class="contact-icon"><i class="fa-solid fa-fax" aria-hidden="true"></i></span>
                        <div><strong>Fax</strong><span><?= e(HOTEL_FAX) ?></span></div></li>
                    <li><span class="contact-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></span>
                        <div><strong>Email</strong><a href="mailto:<?= e(HOTEL_EMAIL) ?>"><?= e(HOTEL_EMAIL) ?></a></div></li>
                </ul>

                <h3 class="contact-subheading">Open right now</h3>
                <ul class="open-now" data-live-region="open">
                    <li class="is-open"><i class="fa-solid fa-bell-concierge" aria-hidden="true"></i><div><strong>Front desk &amp; room service</strong><span>Open 24 hours</span></div></li>
                    <?php foreach ($restaurants as $r): [$open, $text] = open_now_text($r); ?>
                        <li class="<?= $open ? 'is-open' : 'is-closed' ?>"><i class="fa-solid fa-utensils" aria-hidden="true"></i><div><strong><?= e($r['name']) ?></strong><span><?= e($text) ?></span></div></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-lg-7">
                <form method="post" action="<?= url('contact.php') ?>#write" class="contact-card" id="write" novalidate data-checkout-form>
                    <h2 class="write-us"><span>Write</span> Us</h2>
                    <p class="contact-card-sub">We reply by email, usually within a day. For bookings today, please call.</p>
                    <?php show_flash(); ?>
                    <?php if ($errors || $blocked): ?>
                        <div class="notice notice-error error-summary" role="alert" tabindex="-1" data-error-summary>
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <div>
                                <strong><?= $blocked ? e($blocked) : 'Please check ' . (count($errors) === 1 ? 'this field' : 'these ' . count($errors) . ' fields') . ':' ?></strong>
                                <?php if ($errors): ?>
                                    <ul><?php foreach ($errors as $field => $message): ?><li><a href="#f_<?= $field ?>"><?= e($labels[$field]) ?>: <?= e($message) ?></a></li><?php endforeach; ?></ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?= csrf_field() ?>
                    <!-- Left empty by people; spam robots fill it in -->
                    <div class="visually-hidden" aria-hidden="true"><label for="f_website">Website</label><input type="text" id="f_website" name="website" tabindex="-1" autocomplete="off"></div>

                    <fieldset class="topic-chips">
                        <legend>What is it about?</legend>
                        <?php foreach (MESSAGE_TOPICS as $key => [$label, $icon]): ?>
                            <label><input type="radio" name="topic" value="<?= $key ?>"<?= $values['topic'] === $key ? ' checked' : '' ?>><span><i class="<?= $icon ?>" aria-hidden="true"></i> <?= e($label) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="topic-hint" data-topic-hint hidden>Planning an event? The <a href="<?= url('events.php#enquiry') ?>">event enquiry form</a> asks for the date and guests, so we can answer faster.</p>

                    <div class="row g-3">
                        <div class="col-md-6"><?php text_input('name', 'Your name', 'text', 'name', e($values['name']), $errors['name'] ?? '', 'maxlength="100"'); ?></div>
                        <div class="col-md-6"><?php text_input('email', 'Email', 'email', 'email', e($values['email']), $errors['email'] ?? '', 'maxlength="191"'); ?></div>
                        <div class="col-md-6">
                            <div class="form-field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                                <label for="f_phone">Phone <span class="label-optional">(optional)</span></label>
                                <input type="tel" id="f_phone" name="phone" value="<?= e($values['phone']) ?>" autocomplete="tel" maxlength="20"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="f_phone_error"' : '' ?>>
                                <?php if (isset($errors['phone'])): ?><p class="field-error" id="f_phone_error"><?= e($errors['phone']) ?></p><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6"><?php text_input('subject', 'Subject', 'text', 'off', e($values['subject']), $errors['subject'] ?? '', 'maxlength="150"'); ?></div>
                        <div class="col-12">
                            <div class="form-field<?= isset($errors['message']) ? ' has-error' : '' ?>">
                                <label for="f_message">Message</label>
                                <textarea id="f_message" name="message" rows="5" maxlength="2000" required data-count="2000" aria-describedby="f_message_count<?= isset($errors['message']) ? ' f_message_error' : '' ?>"><?= e($values['message']) ?></textarea>
                                <p class="field-help field-count" id="f_message_count" aria-live="polite"><?= mb_strlen($values['message']) ?> / 2,000</p>
                                <?php if (isset($errors['message'])): ?><p class="field-error" id="f_message_error"><?= e($errors['message']) ?></p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-red btn-complete" data-busy="SENDING…"><i class="fa-regular fa-paper-plane" aria-hidden="true"></i> Send</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="contact-map" aria-label="Map">
    <!-- Free Google Maps embed, no API key. Offline (e.g. at the viva) the card still gives the address. -->
    <iframe src="https://www.google.com/maps?q=<?= e(urlencode(HOTEL_NAME . ', ' . HOTEL_ADDRESS)) ?>&amp;output=embed" title="Map showing <?= e(HOTEL_NAME) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    <div class="container">
        <div class="map-card">
            <p class="eyebrow">Find us</p>
            <h2><?= e(HOTEL_NAME) ?></h2>
            <p><?= e(HOTEL_ADDRESS) ?> · near the shrine of Bahauddin Zakariya</p>
            <a href="<?= e($mapsLink) ?>" target="_blank" rel="noopener" class="btn-primary-red"><i class="fa-solid fa-location-arrow" aria-hidden="true"></i> Get directions</a>
        </div>
    </div>
</section>

<?php if ($faqs): ?>
<section class="section section-alt faq-section" aria-labelledby="faqHeading">
    <div class="container">
        <div class="section-heading">
            <h2 id="faqHeading">Questions guests ask</h2>
            <p class="subtitle">Quick answers before you call</p>
            <span class="divider"></span>
        </div>
        <div class="faq-list" data-live-region="faq">
            <?php foreach ($faqs as $i => $faq): ?>
                <details class="faq" name="faq"<?= $i === 0 ? ' open' : '' ?>>
                    <summary><?= e($faq['question']) ?><i class="fa-solid fa-plus" aria-hidden="true"></i></summary>
                    <p><?= nl2br(e($faq['answer'])) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
        <p class="text-center mt-4 mb-0">Still have a question? <a href="#write" class="text-link">Write to us</a></p>
    </div>
</section>
<?php endif; ?>
<?php include 'includes/footer.php';
