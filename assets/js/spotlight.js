/*
 * Spotlight hover effect: a soft glow that follows the mouse inside a box.
 *
 * Usage: add data-spotlight to any box, e.g.
 *   <div class="room-card" data-spotlight>...</div>
 * Optional: data-spotlight-size="250" (glow size in px)
 *           data-spotlight-color="rgba(210, 18, 66, 0.15)" (glow colour)
 *
 * Load after GSAP (optional, used for smooth movement):
 *   <script src="assets/vendor/gsap-3.15.0/gsap.min.js"></script>
 *   <script src="assets/js/spotlight.js"></script>
 *
 * Only runs on devices with a mouse, and not when the visitor has
 * "reduce motion" turned on. The page looks the same without it.
 */
(function () {
    'use strict';

    var hasMouse = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!hasMouse || reduceMotion) {
        return;
    }

    function addSpotlight(box) {
        var size = parseInt(box.getAttribute('data-spotlight-size'), 10) || 200;
        var color = box.getAttribute('data-spotlight-color') || 'rgba(255, 255, 255, 0.6)';

        // isolation + z-index -1 keeps the glow above the box background
        // but below its text and photos.
        if (getComputedStyle(box).position === 'static') {
            box.style.position = 'relative';
        }
        box.style.overflow = 'hidden';
        box.style.isolation = 'isolate';

        var glow = document.createElement('div');
        glow.setAttribute('aria-hidden', 'true');
        glow.style.cssText =
            'position:absolute; left:0; top:0; z-index:-1; pointer-events:none;' +
            'width:' + size + 'px; height:' + size + 'px; border-radius:50%;' +
            'background:radial-gradient(circle at center, ' + color + ', transparent 70%);' +
            'filter:blur(16px); opacity:0; transition:opacity 0.2s;';
        box.insertBefore(glow, box.firstChild);

        // With GSAP the glow eases after the mouse; without it, it follows directly.
        var moveX, moveY;
        if (window.gsap) {
            moveX = gsap.quickTo(glow, 'x', { duration: 0.3, ease: 'power3' });
            moveY = gsap.quickTo(glow, 'y', { duration: 0.3, ease: 'power3' });
        } else {
            var x = 0, y = 0;
            moveX = function (value) { x = value; glow.style.transform = 'translate(' + x + 'px,' + y + 'px)'; };
            moveY = function (value) { y = value; glow.style.transform = 'translate(' + x + 'px,' + y + 'px)'; };
        }

        function follow(event) {
            var rect = box.getBoundingClientRect();
            moveX(event.clientX - rect.left - size / 2);
            moveY(event.clientY - rect.top - size / 2);
        }

        box.addEventListener('mousemove', follow);
        box.addEventListener('mouseenter', function (event) {
            // Start at the mouse instead of sliding in from the corner.
            if (window.gsap) {
                var rect = box.getBoundingClientRect();
                gsap.set(glow, { x: event.clientX - rect.left - size / 2, y: event.clientY - rect.top - size / 2 });
            } else {
                follow(event);
            }
            glow.style.opacity = '1';
        });
        box.addEventListener('mouseleave', function () { glow.style.opacity = '0'; });
    }

    document.querySelectorAll('[data-spotlight]').forEach(addSpotlight);
    // Cards that arrive with a live update (live.js) get the glow too
    document.addEventListener('live:updated', function (event) {
        event.detail.region.querySelectorAll('[data-spotlight]').forEach(addSpotlight);
    });
})();
