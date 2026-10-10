/*
 * Small scripts used on every page.
 */
(function () {
    'use strict';

    // Back-to-top button: appears after scrolling down, and scrolls to the top when clicked.
    var backToTop = document.querySelector('.back-to-top');
    if (backToTop) {
        window.addEventListener('scroll', function () {
            backToTop.classList.toggle('show', window.scrollY > 400);
        });
        backToTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Pop-up notes ("You have logged out"): fade away after 6 seconds, or when clicked
    document.querySelectorAll('.toast-area .notice').forEach(function (note) {
        function hide() {
            note.classList.add('is-hiding');
            setTimeout(function () { note.remove(); }, 400);
        }
        note.addEventListener('click', hide);
        setTimeout(hide, 6000);
    });

    // "Are you sure?" before an action that can't be undone: <form data-confirm="Cancel this booking?">
    // (listens on the whole page, so forms that arrive with a live update are covered too)
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });

    // Search bar: check-out must be after check-in, and check-in can't be in the past (FR-BOOK-02).
    // The server checks this again when the search runs.
    var checkIn = document.getElementById('check_in');
    var checkOut = document.getElementById('check_out');
    // Date as "YYYY-MM-DD" in the visitor's own time zone.
    function toDateValue(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    }

    if (checkIn && checkOut) {
        var today = toDateValue(new Date());
        checkIn.min = today;
        checkOut.min = today;
        checkIn.addEventListener('change', function () {
            if (!checkIn.value) {
                return;
            }
            var nextDay = new Date(checkIn.value + 'T00:00');
            nextDay.setDate(nextDay.getDate() + 1);
            checkOut.min = toDateValue(nextDay);
            if (checkOut.value && checkOut.value <= checkIn.value) {
                checkOut.value = checkOut.min;
            }
            showNights();
        });
        checkOut.addEventListener('change', showNights);
    }

    // Shows "3 nights · Fri 16 Oct → Mon 19 Oct" under the search bar once both dates are picked.
    function showNights() {
        var note = document.getElementById('nightsNote');
        if (!note) {
            return;
        }
        if (!checkIn.value || !checkOut.value || checkOut.value <= checkIn.value) {
            note.textContent = '';
            return;
        }
        var start = new Date(checkIn.value + 'T00:00');
        var end = new Date(checkOut.value + 'T00:00');
        var nights = Math.round((end - start) / 86400000);
        var format = { weekday: 'short', day: 'numeric', month: 'short' };
        note.textContent = nights + (nights === 1 ? ' night' : ' nights') + ' · ' +
            start.toLocaleDateString('en-GB', format) + ' → ' + end.toLocaleDateString('en-GB', format);
    }
})();
