/*
 * Booking confirmation page.
 * - Print button.
 * - The digital room key (FR-UX-07) tilts towards the mouse and a soft light follows it.
 *   Only on computers with a mouse, and not for "reduce motion" (D19).
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    var key = document.querySelector('[data-room-key]');
    if (!key || !window.matchMedia('(hover: hover) and (pointer: fine)').matches ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    var wrap = key.parentElement;
    wrap.addEventListener('mousemove', function (event) {
        var box = wrap.getBoundingClientRect();
        var x = (event.clientX - box.left) / box.width;   // 0 … 1
        var y = (event.clientY - box.top) / box.height;
        key.style.setProperty('--tilt-x', ((0.5 - y) * 16).toFixed(2) + 'deg');
        key.style.setProperty('--tilt-y', ((x - 0.5) * 20).toFixed(2) + 'deg');
        key.style.setProperty('--shine-x', (x * 100).toFixed(1) + '%');
        key.style.setProperty('--shine-y', (y * 100).toFixed(1) + '%');
    });
    wrap.addEventListener('mouseleave', function () {
        key.style.setProperty('--tilt-x', '0deg');
        key.style.setProperty('--tilt-y', '0deg');
    });
})();
