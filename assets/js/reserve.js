/*
 * Reserve a table: the Time list only offers the times the chosen restaurant takes
 * reservations (from opening until 90 minutes before closing), and today only later times.
 * The server checks the same rules again (includes/reservations.php).
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-reserve-form]');
    if (!form) return;
    var restaurant = form.elements.restaurant;
    var date = form.elements.date;
    var time = form.elements.time;
    var note = form.querySelector('[data-hours-note]');

    function pad(n) { return String(n).padStart(2, '0'); }

    function update() {
        var chosen = restaurant.options[restaurant.selectedIndex];
        var opens = chosen.getAttribute('data-opens');
        var last = chosen.getAttribute('data-last');
        var now = new Date();
        var today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        var soon = new Date(now.getTime() + 30 * 60000);
        var earliest = date.value === today ? pad(soon.getHours()) + ':' + pad(soon.getMinutes()) : '00:00';
        Array.prototype.forEach.call(time.options, function (option) {
            if (!option.value) return;
            var ok = option.value >= opens && option.value <= last && option.value >= earliest;
            option.hidden = !ok;
            option.disabled = !ok;
        });
        if (time.selectedOptions[0] && time.selectedOptions[0].disabled) {
            time.value = '';
        }
        note.textContent = chosen.text + ' takes reservations from ' + label(opens) + ' to ' + label(last) + '.';
    }

    function label(hhmm) {
        var parts = hhmm.split(':');
        var h = parseInt(parts[0], 10);
        return (h % 12 || 12) + ':' + parts[1] + (h < 12 ? ' AM' : ' PM');
    }

    restaurant.addEventListener('change', update);
    date.addEventListener('change', update);
    update();
})();
