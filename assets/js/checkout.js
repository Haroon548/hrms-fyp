/*
 * Booking checkout and food order pages: after the main button is pressed, it shows that
 * the request is being sent and can't be pressed again. (The server also ignores a second press.)
 * On the food order page, the table list only shows when "Dine-in" is chosen.
 * Listens on the whole page, because a live update can redraw the form.
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-checkout-form]');
        if (!form || event.defaultPrevented) return;
        var button = form.querySelector('.btn-complete');
        // Let the browser send the form first, then lock the button
        setTimeout(function () {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ' + (button.getAttribute('data-busy') || 'BOOKING YOUR ROOM…');
        }, 0);
    });

    function showTable() {
        var form = document.querySelector('[data-checkout-form]');
        var tableChoice = form && form.querySelector('[data-table-choice]');
        if (!tableChoice) return;
        var chosen = form.querySelector('[data-order-type]:checked');
        tableChoice.hidden = !chosen || chosen.value !== 'dine_in';
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-order-type]')) showTable();
    });
    document.addEventListener('live:updated', function () {
        showTable();
        // Booking page: the guest now sees the new price, so the form books at that price
        var quote = document.querySelector('[data-quote]');
        var field = document.querySelector('input[name="quoted_price"]');
        if (quote && field) field.value = quote.getAttribute('data-quote');
    });
    showTable();
})();
