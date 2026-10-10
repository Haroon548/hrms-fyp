<?php
/*
 * Shared page footer (design.md section 3, screenshot 03).
 * A page can set $page_scripts to a list of extra JS files to load.
 */
$page_scripts = $page_scripts ?? [];
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row gy-4 align-items-start">
            <div class="col-md-6 col-lg-3 text-center">
                <?php if (file_exists(__DIR__ . '/../assets/images/logo-white.png')): ?>
                    <img src="<?= url('assets/images/logo-white.png') ?>" alt="<?= e(HOTEL_NAME) ?>" class="footer-logo">
                <?php else: ?>
                    <span class="logo-text logo-text-white">RAMADA<small>BY WYNDHAM</small><strong>MULTAN</strong></span>
                <?php endif; ?>
                <p class="footer-label">LET'S GET SOCIAL</p>
                <div class="footer-social">
                    <a href="https://www.instagram.com/ramadamultan" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://www.facebook.com/RamadaMultan" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://x.com/ramadamultan" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 footer-contact">
                <p><?= e(HOTEL_ADDRESS) ?><br>
                    <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(urlencode(HOTEL_NAME . ', ' . HOTEL_ADDRESS)) ?>" target="_blank" rel="noopener" class="footer-directions"><i class="fa-solid fa-location-dot"></i> Get directions</a></p>
                <p>Phone: <a href="tel:<?= e(str_replace(' ', '', HOTEL_PHONE)) ?>"><?= e(HOTEL_PHONE) ?></a><br><?= e(HOTEL_PHONE_2) ?></p>
                <p><i class="fa-brands fa-whatsapp"></i> <a href="https://wa.me/<?= e(HOTEL_WHATSAPP) ?>" target="_blank" rel="noopener">WhatsApp us</a></p>
                <p><a href="mailto:<?= e(HOTEL_EMAIL) ?>"><?= e(HOTEL_EMAIL) ?></a></p>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="footer-links">
                    <a href="<?= url('about.php') ?>">About Us</a>
                    <a href="<?= url('contact.php') ?>">Contact Us</a>
                    <a href="<?= url('gallery.php') ?>">Gallery</a>
                    <a href="<?= url('events.php') ?>">Events</a>
                    <a href="<?= url('rooms.php') ?>">Rooms</a>
                    <a href="<?= url('dining.php') ?>">Dining</a>
                </div>
                <p class="footer-slogan">SAY HELLO TO RED</p>
            </div>

            <div class="col-md-6 col-lg-3 text-center">
                <?php if (file_exists(__DIR__ . '/../assets/images/wyndham-rewards.png')): ?>
                    <img src="<?= url('assets/images/wyndham-rewards.png') ?>" alt="Wyndham Rewards" class="footer-rewards">
                <?php else: ?>
                    <p class="rewards-text">WYNDHAM<br>REWARDS</p>
                <?php endif; ?>
                <a href="https://www.wyndhamhotels.com/wyndham-rewards/join" target="_blank" rel="noopener" class="btn-outline-pill">Join Now</a>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(HOTEL_NAME) ?>. All rights reserved.</p>
            <p>Developed as a final year project, University of Southern Punjab, Multan.</p>
        </div>
    </div>
</footer>

<button type="button" class="back-to-top" aria-label="Back to top"><i class="fa-solid fa-chevron-up"></i></button>

<script src="<?= url('assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/main.js') ?>"></script>
<?php foreach ($page_scripts as $script): ?>
<script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
<script src="<?= url('assets/js/live.js') ?>"></script>
</body>
</html>
