/*
 * Filmstrip: a looping deck of framed photo cards on a curved 3D rail.
 *
 * Adapted from "Character Filmstrip" by ThreeUI (https://threeui.com),
 * a Community component under the MIT License:
 *   Copyright (c) ThreeUI. Permission is hereby granted, free of charge, to any person
 *   obtaining a copy of this software and associated documentation files, to deal in the
 *   Software without restriction, including without limitation the rights to use, copy,
 *   modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 *   subject to including this notice. THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY
 *   OF ANY KIND.
 *
 * Changes for this site: hotel photos come from the page (PHP) instead of embedded
 * portraits; the mouse wheel scrolls the page as normal (‹ › buttons instead); arrow
 * keys only work while the filmstrip has focus; phones swipe sideways without blocking
 * page scrolling; the animation pauses while off-screen; no idle drift for "reduce motion";
 * clicking the front card opens its link.
 *
 * Usage: <div class="filmstrip" data-filmstrip> with .filmstrip-deck holding
 * <button class="film-card" data-href="..."> cards, plus .film-prev / .film-next buttons.
 */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function setUp(stage) {
        var cards = Array.prototype.slice.call(stage.querySelectorAll('.film-card'));
        var count = cards.length;
        if (count === 0) {
            return;
        }

        var start = Math.floor(count / 2);
        var state = {
            phase: start,
            target: start,
            base: start,
            pointerX: 0,
            pointerY: 0,
            active: false,
            lastInput: performance.now()
        };
        var running = false;
        var previousTime = performance.now();

        stage.classList.add('is-ready');

        function wrappedDelta(index, phase) {
            var delta = index - phase;
            while (delta > count / 2) delta -= count;
            while (delta < -count / 2) delta += count;
            return delta;
        }

        function nearestIndex() {
            return ((Math.round(state.phase) % count) + count) % count;
        }

        function step(direction) {
            state.base += direction;
            state.target = state.base;
            state.active = false;
            state.lastInput = performance.now();
        }

        // The card the deck is heading to (nearestIndex is the one currently in front).
        function targetIndex() {
            return ((Math.round(state.base) % count) + count) % count;
        }

        function moveTo(index) {
            var delta = index - targetIndex();
            if (delta > count / 2) delta -= count;
            if (delta < -count / 2) delta += count;
            step(delta);
        }

        // Click a side card to bring it to the front; click the front card to open it.
        // (A click also focuses the card first; the focus moves the target, not the
        // card on screen, so nearestIndex() still tells us what was in front.)
        cards.forEach(function (card, index) {
            card.addEventListener('click', function () {
                if (index === nearestIndex() && card.getAttribute('data-href')) {
                    window.location.href = card.getAttribute('data-href');
                } else {
                    moveTo(index);
                }
            });
            card.addEventListener('focus', function () {
                moveTo(index);
            });
        });

        var prev = stage.querySelector('.film-prev');
        var next = stage.querySelector('.film-next');
        if (prev) prev.addEventListener('click', function () { step(-1); });
        if (next) next.addEventListener('click', function () { step(1); });

        // Mouse: the deck leans toward the pointer (computers only).
        stage.addEventListener('pointermove', function (event) {
            if (event.pointerType !== 'mouse') {
                return;
            }
            var rect = stage.getBoundingClientRect();
            var nx = Math.max(-1, Math.min(1, ((event.clientX - rect.left) / rect.width - 0.5) * 2));
            var ny = Math.max(-1, Math.min(1, ((event.clientY - rect.top) / rect.height - 0.5) * 2));
            state.pointerX = nx;
            state.pointerY = ny;
            state.active = true;
            state.target = state.base + nx * 1.6;
            state.lastInput = performance.now();
            stage.style.setProperty('--pointer-x', ((nx + 1) * 50) + '%');
        });
        stage.addEventListener('pointerleave', function () {
            state.active = false;
            state.pointerX = 0;
            state.pointerY = 0;
            state.target = state.base;
            stage.style.setProperty('--pointer-x', '50%');
        });

        // Touch: swipe left or right to move one card; vertical swipes scroll the page.
        var touchStartX = null;
        var touchStartY = null;
        stage.addEventListener('touchstart', function (event) {
            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
        }, { passive: true });
        stage.addEventListener('touchend', function (event) {
            if (touchStartX === null) return;
            var dx = event.changedTouches[0].clientX - touchStartX;
            var dy = event.changedTouches[0].clientY - touchStartY;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                step(dx < 0 ? 1 : -1);
            }
            touchStartX = null;
        }, { passive: true });

        // Keyboard: arrow keys only while focus is inside the filmstrip.
        stage.addEventListener('keydown', function (event) {
            var forward = event.key === 'ArrowRight';
            var backward = event.key === 'ArrowLeft';
            if (!forward && !backward) return;
            event.preventDefault();
            step(forward ? 1 : -1);
            cards[nearestIndex()].focus({ preventScroll: true });
        });

        function render(time) {
            if (!running) return;
            var deltaTime = Math.min(32, time - previousTime);
            previousTime = time;
            var ease = reducedMotion ? 1 : 1 - Math.pow(0.001, deltaTime / 1000);

            // After a few quiet seconds, drift gently back and forth.
            if (!reducedMotion && !state.active && time - state.lastInput > 3600) {
                var idle = time - state.lastInput - 3600;
                state.target = state.base + Math.sin(idle * 0.00042) * 2.45;
            }

            state.phase += (state.target - state.phase) * ease;
            var width = stage.clientWidth;
            var compact = width < 650;
            var spacing = compact ? Math.max(70, width * 0.2) : Math.min(168, Math.max(112, width * 0.116));
            var activeIndex = nearestIndex();

            cards.forEach(function (card, index) {
                var delta = wrappedDelta(index, state.phase);
                var distance = Math.abs(delta);
                var focus = Math.exp(-distance * distance * 1.28);
                var side = Math.max(0, 1 - distance / 5);
                var direction = Math.sign(delta);
                var x = delta * spacing;
                var y = distance * 8 + state.pointerY * focus * 10;
                var z = focus * 145 - distance * 148;
                var scale = 0.54 + side * 0.15 + focus * 0.54;
                var rotateX = -state.pointerY * focus * 3.5;
                var rotateY = -direction * (distance > 0.2 ? 14 + Math.min(distance, 3) * 5 : 0) + state.pointerX * focus * 3;
                var rotateZ = delta * 0.7;

                card.style.setProperty('--focus', focus.toFixed(4));
                card.style.zIndex = String(Math.round(1000 - distance * 100));
                card.style.opacity = String(Math.max(0.13, side * 0.76 + focus * 0.24));
                card.style.filter = 'blur(' + (Math.max(0, distance - 1.5) * 0.38).toFixed(2) + 'px)';
                card.style.transform = 'translate(-50%, -50%) translate3d(' + x.toFixed(2) + 'px, ' + y.toFixed(2) + 'px, ' + z.toFixed(2) + 'px) ' +
                    'rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) rotateZ(' + rotateZ.toFixed(2) + 'deg) ' +
                    'scale(' + scale.toFixed(4) + ')';
                card.setAttribute('aria-current', index === activeIndex ? 'true' : 'false');
                // Only nearby cards can be tabbed to, so keyboard users aren't sent through hidden ones
                card.tabIndex = distance < 2.5 ? 0 : -1;
            });

            requestAnimationFrame(render);
        }

        function play() {
            if (running) return;
            running = true;
            previousTime = performance.now();
            requestAnimationFrame(render);
        }

        // Only animate while the filmstrip is on screen (saves battery on phones).
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    play();
                } else {
                    running = false;
                }
            }).observe(stage);
        } else {
            play();
        }
    }

    document.querySelectorAll('[data-filmstrip]').forEach(setUp);
})();
