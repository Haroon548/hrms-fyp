/*
 * Dining page and the table QR menu (order/table.php).
 * 1. Cart: the "+" buttons and the − / + in the cart panel send their form in the background
 *    (order/cart.php answers with JSON), so the page doesn't jump; the cart button pops when
 *    something is added (FR-UX-04). Without JavaScript the same forms simply reload the page.
 * 2. Motion (D19, GSAP): the menu cards straighten and the photos drift as they scroll past,
 *    and the "Moments" row slides sideways. Nothing is hidden, and nothing moves for
 *    "reduce motion". On phones the row of photos is simply swiped by hand.
 * 3. The menu section tabs show which section is on screen.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var configEl = document.getElementById('cartConfig');
    var config = configEl ? JSON.parse(configEl.textContent) : null;

    /* ---------- 1. Cart ---------- */

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function send(form, submitter) {
        var data = new FormData(form);
        if (submitter && submitter.name) {
            data.set(submitter.name, submitter.value);
        }
        // getAttribute, not form.action: the form has a field named "action", which would shadow it
        return fetch(form.getAttribute('action'), { method: 'POST', body: data, headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('Cart not updated');
                return response.json();
            });
    }

    // Draw the cart lines and totals from the server's answer
    function render(cart) {
        document.querySelectorAll('[data-cart-count]').forEach(function (el) { el.textContent = cart.count; });
        document.querySelectorAll('[data-cart-total]').forEach(function (el) { el.textContent = cart.total_text; });
        document.querySelectorAll('[data-item-count]').forEach(function (el) { el.textContent = ''; });
        cart.lines.forEach(function (line) {
            var badge = document.querySelector('[data-item-count="' + line.id + '"]');
            if (badge) badge.textContent = line.quantity;
        });
        var list = document.querySelector('[data-cart-lines]');
        list.innerHTML = cart.lines.map(function (line) {
            var name = escapeHtml(line.name);
            return '<li data-line="' + line.id + '"><div><strong>' + name + '</strong><small>' + line.price_text + ' each</small></div>' +
                '<form action="' + config.url + '" method="post" class="qty" data-qty-form>' +
                '<input type="hidden" name="csrf" value="' + config.csrf + '"><input type="hidden" name="action" value="set">' +
                '<input type="hidden" name="item_id" value="' + line.id + '"><input type="hidden" name="return" value="' + escapeHtml(config.return) + '">' +
                '<button type="submit" name="quantity" value="' + (line.quantity - 1) + '" aria-label="One less ' + name + '"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>' +
                '<span aria-live="polite">' + line.quantity + '</span>' +
                '<button type="submit" name="quantity" value="' + (line.quantity + 1) + '" aria-label="One more ' + name + '"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>' +
                '</form><span class="cart-line-total">' + line.line_text + '</span></li>';
        }).join('');
        document.querySelector('[data-cart-empty]').hidden = cart.lines.length > 0;
        document.querySelector('[data-cart-checkout]').classList.toggle('disabled', cart.lines.length === 0);
        document.querySelector('[data-cart-fab]').classList.toggle('is-visible', cart.count > 0);
    }

    function pop(el) {
        if (!el || reduceMotion) return;
        el.classList.remove('is-popping');
        void el.offsetWidth; // restart the animation
        el.classList.add('is-popping');
    }

    if (config) {
        // "+" next to a dish, and − / + inside the cart panel. Listens on the whole page,
        // because the dishes and the cart can be redrawn (by render() or a live update).
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-add-form], [data-qty-form]');
            if (!form) return;
            event.preventDefault();
            if (form.hasAttribute('data-qty-form')) {
                send(form, event.submitter).then(render).catch(function () { form.submit(); });
                return;
            }
            var button = form.querySelector('button');
            button.disabled = true;
            send(form).then(function (cart) {
                render(cart);
                pop(button);
                pop(document.querySelector('[data-cart-fab]'));
            }).catch(function () {
                form.submit(); // fall back to the normal form
            }).finally(function () {
                button.disabled = false;
            });
        });
    }

    /* ---------- 3. Menu tabs follow the section on screen ---------- */

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                document.querySelectorAll('.menu-tabs a').forEach(function (tab) {
                    var active = tab.getAttribute('href') === '#' + entry.target.id;
                    tab.classList.toggle('active', active);
                    if (active) tab.setAttribute('aria-current', 'true'); else tab.removeAttribute('aria-current');
                });
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        var watchSections = function () {
            observer.disconnect();
            document.querySelectorAll('.menu-section').forEach(function (section) { observer.observe(section); });
        };
        watchSections();
        document.addEventListener('live:updated', watchSections); // the menu was redrawn
    }

    /* ---------- 2. Motion ---------- */

    if (reduceMotion || !window.gsap || !window.ScrollTrigger) {
        document.documentElement.classList.add('dine-static');
        return;
    }
    gsap.registerPlugin(ScrollTrigger);

    // Tilted menu cards straighten a little as they reach the middle of the screen
    document.querySelectorAll('[data-tilt-card]').forEach(function (card) {
        var flipped = card.closest('.is-flipped');
        gsap.fromTo(card, { rotate: flipped ? 5 : -5, y: 40 }, {
            rotate: flipped ? 1.5 : -1.5, y: 0, ease: 'none',
            scrollTrigger: { trigger: card, start: 'top bottom', end: 'center center', scrub: 0.6 }
        });
    });

    // Photos drift slightly slower than the page (depth)
    document.querySelectorAll('[data-parallax-photo] img').forEach(function (img) {
        gsap.fromTo(img, { yPercent: -6, scale: 1.12 }, {
            yPercent: 6, scale: 1.12, ease: 'none',
            scrollTrigger: { trigger: img.closest('[data-parallax-photo]'), start: 'top bottom', end: 'bottom top', scrub: true }
        });
    });

    // "Moments": the row slides sideways while the section passes (computers only; never pinned)
    var strip = document.querySelector('[data-strip]');
    ScrollTrigger.matchMedia({
        '(min-width: 768px)': function () {
            if (!strip) return;
            // While the row is moved by scrolling, it no longer needs its own scrollbar
            document.documentElement.classList.add('strip-moves');
            var distance = function () { return Math.max(0, strip.scrollWidth - window.innerWidth + 80); };
            gsap.fromTo(strip, { x: 40 }, {
                x: function () { return -distance(); }, ease: 'none',
                scrollTrigger: { trigger: strip, start: 'top bottom', end: 'bottom top', scrub: 0.8, invalidateOnRefresh: true }
            });
            return function () { document.documentElement.classList.remove('strip-moves'); };
        }
    });
})();
