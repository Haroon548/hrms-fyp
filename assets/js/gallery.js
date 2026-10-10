/*
 * Gallery page: filter tabs (photos slide into place with GSAP Flip, FR-UX-03)
 * and the photo viewer (GLightbox), which only steps through the visible photos.
 * Without JavaScript the tabs are normal links (gallery.php?tab=...) that still work.
 */
(function () {
    'use strict';

    var grid = document.getElementById('galleryGrid');
    if (!grid) return;
    var items = Array.prototype.slice.call(grid.querySelectorAll('.gallery-item'));
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.gallery-tab'));
    var countEl = document.getElementById('galleryCount');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var lightbox = null;

    if (window.gsap && window.Flip) {
        gsap.registerPlugin(Flip);
    }

    // Photo viewer over the photos currently shown
    function setUpLightbox() {
        if (!window.GLightbox) return;
        if (lightbox) lightbox.destroy();
        lightbox = GLightbox({ selector: '.gallery-item:not(.is-hidden)', loop: true, touchNavigation: true });
    }

    function updateCount() {
        var shown = items.filter(function (item) { return !item.classList.contains('is-hidden'); }).length;
        countEl.textContent = 'Showing ' + shown + (shown === 1 ? ' photo' : ' photos');
    }

    function show(filter, animate) {
        var state = (animate && window.Flip && !reduceMotion) ? Flip.getState(items) : null;
        // What's on screen now, including photos still flying from a previous click
        var gridTop = grid.getBoundingClientRect().top;
        var oldHeight = items.reduce(function (lowest, item) {
            return item.offsetParent ? Math.max(lowest, item.getBoundingClientRect().bottom - gridTop) : lowest;
        }, grid.offsetHeight);
        // Stop the slide and height animation from a previous click (their "finished" steps
        // must not run), so the new height is measured properly
        if (state) Flip.killFlipsOf(items);
        if (window.gsap) gsap.killTweensOf(grid);
        grid.style.height = '';

        items.forEach(function (item) {
            item.classList.toggle('is-hidden', filter !== 'all' && item.getAttribute('data-category') !== filter);
        });
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-filter') === filter;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        if (state) {
            // While the photos slide, they're lifted out of the page layout and may fly across
            // the page. Keep the grid at its full height until they've all landed, then ease it
            // to the new height, so the red footer below never shows behind moving photos.
            var newHeight = grid.offsetHeight;
            grid.style.height = Math.max(oldHeight, newHeight) + 'px';
            Flip.from(state, {
                duration: 0.55,
                ease: 'power2.inOut',
                absolute: true,
                scale: true,
                onComplete: function () {
                    gsap.to(grid, {
                        height: newHeight,
                        duration: newHeight < oldHeight ? 0.35 : 0,
                        ease: 'power2.out',
                        onComplete: function () { grid.style.height = ''; }
                    });
                },
                onEnter: function (elements) {
                    return gsap.fromTo(elements, { opacity: 0, scale: 0.85 }, { opacity: 1, scale: 1, duration: 0.45 });
                },
                onLeave: function (elements) {
                    return gsap.to(elements, { opacity: 0, scale: 0.85, duration: 0.25 });
                }
            });
        }
        updateCount();
        setUpLightbox();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            var filter = tab.getAttribute('data-filter');
            show(filter, true);
            // Keep the address in step, so the tab can be shared or bookmarked
            if (window.history && history.replaceState) {
                history.replaceState(null, '', tab.getAttribute('href'));
            }
        });
    });

    var start = tabs.filter(function (tab) { return tab.classList.contains('active'); })[0];
    show(start ? start.getAttribute('data-filter') : 'all', false);

    /* ---------- Ring of photos around the heading (FR-WEB-22) ----------
       Turns slowly; each card is shaded by how far round the back it is, so the ring
       reads as one solid cylinder. Hover slows it; drag sideways to spin it. */
    var orbit = document.getElementById('orbit');
    if (orbit && !reduceMotion) {
        var ring = orbit.querySelector('.orbit-ring');
        var cards = Array.prototype.slice.call(orbit.querySelectorAll('.orbit-card'));
        var n = cards.length;
        var angle = 0;          // degrees the ring has turned
        var speed = -6;         // degrees per second (one turn a minute)
        var velocity = 0;       // extra spin after a drag
        var hovering = false;
        var dragging = false;
        var lastX = 0;
        var last = performance.now();
        orbit.classList.add('js-orbit');

        function frame(now) {
            var dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            if (!dragging) {
                angle += (speed * (hovering ? 0.25 : 1) + velocity) * dt;
                velocity *= Math.pow(0.04, dt); // drag spin slows down smoothly
            }
            ring.style.transform = 'translateY(-6px) rotateX(-7deg) rotateY(' + angle.toFixed(2) + 'deg)';
            cards.forEach(function (card, i) {
                // Facing the viewer = 1, at the back = 0
                var a = (i * 360 / n + angle) * Math.PI / 180;
                var facing = (Math.cos(a) + 1) / 2;
                // Front cards stop a little short of full brightness so the heading stands out
                card.style.filter = 'brightness(' + (0.3 + 0.55 * facing).toFixed(3) + ')';
            });
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);

        orbit.addEventListener('pointerenter', function () { hovering = true; });
        orbit.addEventListener('pointerleave', function () { hovering = false; dragging = false; orbit.classList.remove('dragging'); });
        orbit.addEventListener('pointerdown', function (event) {
            dragging = true;
            lastX = event.clientX;
            velocity = 0;
            orbit.classList.add('dragging');
        });
        orbit.addEventListener('pointermove', function (event) {
            if (!dragging) return;
            var dx = event.clientX - lastX;
            lastX = event.clientX;
            angle += dx * 0.25;
            velocity = dx * 6;
        });
        window.addEventListener('pointerup', function () {
            dragging = false;
            orbit.classList.remove('dragging');
        });
    }
})();
