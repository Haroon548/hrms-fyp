/*
 * Contact and Meeting & Events forms (the forms work without this script; it only helps):
 * 1. After a failed send, the list of problems gets the focus, so keyboard and screen-reader
 *    users hear what to fix; each item links to its field.
 * 2. A live "120 / 2,000" count under the message box.
 * 3. Choosing "Meetings & events" on the Contact form points to the event enquiry form.
 * 4. "Enquire about this hall" on Meeting & Events picks that hall in the enquiry form.
 * 5. Only one FAQ answer open at a time (newer browsers do this by themselves).
 */
(function () {
    'use strict';

    // (after the browser has jumped to the form's #anchor, which would otherwise take the focus away)
    var summary = document.querySelector('[data-error-summary]');
    if (summary) {
        window.addEventListener('load', function () {
            setTimeout(function () { summary.focus({ preventScroll: true }); }, 0);
        });
    }

    document.querySelectorAll('[data-count]').forEach(function (box) {
        var counter = document.getElementById(box.id + '_count');
        var max = parseInt(box.getAttribute('data-count'), 10);
        box.addEventListener('input', function () {
            counter.textContent = box.value.length + ' / ' + max.toLocaleString('en-US');
        });
    });

    var hint = document.querySelector('[data-topic-hint]');
    function showHint() {
        var chosen = document.querySelector('input[name="topic"]:checked');
        if (hint) hint.hidden = !chosen || chosen.value !== 'events';
    }
    document.addEventListener('change', function (event) {
        if (event.target.name === 'topic') showHint();
    });
    showHint();

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-pick-hall]');
        var select = document.getElementById('f_hall');
        if (link && select) select.value = link.getAttribute('data-pick-hall');
    });

    document.addEventListener('toggle', function (event) {
        var opened = event.target;
        if (!opened.matches || !opened.matches('details.faq') || !opened.open) return;
        document.querySelectorAll('details.faq[open]').forEach(function (other) {
            if (other !== opened) other.open = false;
        });
    }, true);
})();
