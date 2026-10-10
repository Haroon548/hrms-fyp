/*
 * Room detail page.
 * 1. The photo viewer (GLightbox, stored in assets/vendor/): clicking any room photo opens it
 *    large, with arrows, thumbnails and a close button.
 * 2. The availability calendar (FR-SIG-CAL-01): the ‹ › arrows load another month without
 *    reloading the page, and clicking a check-in day and then a check-out day fills in the
 *    booking box. Without JavaScript the arrows still work as normal links.
 *    The server checks the dates again when the guest books (FR-SIG-CAL-03).
 * 3. Live updates (live.js): when bookings or prices change, the month on screen is loaded
 *    again, and new photos open in the viewer too.
 */
(function () {
    'use strict';

    var lightbox = window.GLightbox ? GLightbox({ selector: '.glightbox', loop: true, touchNavigation: true }) : null;
    document.addEventListener('live:updated', function () {
        if (lightbox) lightbox.reload();
    });

    var section = document.querySelector('[data-calendar-url]');
    // The booking box can be replaced by a live update, so it is looked up each time
    function bookingForm() { return document.querySelector('[data-booking-form]'); }
    if (!section || !bookingForm()) {
        return;
    }
    var holder = section.querySelector('[data-calendar-holder]');
    var pick = section.querySelector('[data-cal-pick]');
    var pickText = section.querySelector('[data-cal-pick-text]');
    var price = parseFloat(section.getAttribute('data-price')) || 0;
    var free = {};        // nights seen so far: '2026-11-04' → rooms free
    var checkIn = null;   // the chosen dates, as 'YYYY-MM-DD'
    var checkOut = null;

    function remember() {
        holder.querySelectorAll('.cal-day[data-date]').forEach(function (day) {
            free[day.getAttribute('data-date')] = parseInt(day.getAttribute('data-free'), 10);
        });
    }

    function addDays(date, days) {
        var d = new Date(date + 'T00:00');
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function nice(date) {
        return new Date(date + 'T00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    }

    // True if every night from check-in up to the day before check-out has a free room
    function nightsFree(from, to) {
        for (var night = from; night < to; night = addDays(night, 1)) {
            if (!(free[night] > 0)) return false;
        }
        return true;
    }

    // Colour the chosen stay and say what was picked
    function paint() {
        holder.querySelectorAll('.cal-day[data-date]').forEach(function (day) {
            var date = day.getAttribute('data-date');
            day.classList.toggle('is-start', date === checkIn);
            day.classList.toggle('is-end', date === checkOut);
            day.classList.toggle('is-between', !!(checkIn && checkOut && date > checkIn && date < checkOut));
            day.setAttribute('aria-pressed', date === checkIn || date === checkOut ? 'true' : 'false');
        });
        if (!checkIn) {
            pick.hidden = true;
            return;
        }
        pick.hidden = false;
        pick.classList.toggle('is-ready', !!checkOut);
        if (!checkOut) {
            pickText.textContent = 'Check-in ' + nice(checkIn) + '. Now choose your check-out day.';
            return;
        }
        var nights = Math.round((new Date(checkOut + 'T00:00') - new Date(checkIn + 'T00:00')) / 86400000);
        pickText.textContent = nice(checkIn) + ' → ' + nice(checkOut) + ' · ' + nights + (nights === 1 ? ' night' : ' nights') +
            (price ? ' · PKR ' + Math.round(price * nights).toLocaleString('en-US') : '');
    }

    function note(message) {
        pick.hidden = false;
        pick.classList.remove('is-ready');
        pickText.textContent = message;
    }

    holder.addEventListener('click', function (event) {
        var nav = event.target.closest('a.cal-nav');
        if (nav) {
            event.preventDefault();
            load(nav.getAttribute('data-month'));
            return;
        }
        var day = event.target.closest('.cal-day[data-date]');
        if (!day) return;
        var date = day.getAttribute('data-date');

        // Second click after check-in: a later day that keeps every night free becomes check-out
        if (checkIn && !checkOut && date > checkIn) {
            if (!nightsFree(checkIn, date)) {
                note('A night between those days is fully booked. Choose an earlier check-out day, or another check-in day.');
                return;
            }
            checkOut = date;
            var form = bookingForm();
            form.elements.check_in.value = checkIn;
            form.elements.check_out.value = checkOut;
            form.elements.check_out.min = addDays(checkIn, 1);
            form.elements.check_in.dispatchEvent(new Event('change'));
            form.elements.check_out.value = checkOut;
            form.elements.check_out.dispatchEvent(new Event('change'));
            paint();
            return;
        }
        // Otherwise this day starts a new stay (it needs a free night)
        if (!(free[date] > 0)) {
            note(nice(date) + ' is fully booked. Choose another check-in day.');
            return;
        }
        checkIn = date;
        checkOut = null;
        paint();
    });

    // "Check these dates" runs the normal availability check in the booking box
    section.querySelector('[data-cal-pick-go]').addEventListener('click', function (event) {
        if (checkIn && checkOut) {
            event.preventDefault();
            bookingForm().submit();
        }
    });

    function load(month) {
        holder.classList.add('is-loading');
        fetch(section.getAttribute('data-calendar-url') + '&month=' + encodeURIComponent(month), { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (response) {
                if (!response.ok) throw new Error('Calendar not loaded');
                return response.text();
            })
            .then(function (html) {
                holder.innerHTML = html;
                remember();
                paint();
            })
            .catch(function () {
                // If loading fails, go to the month the normal way
                window.location.href = holder.querySelector('a.cal-nav[data-month="' + month + '"]').href;
            })
            .finally(function () {
                holder.classList.remove('is-loading');
            });
    }

    // Someone booked, cancelled or changed the price: show this month again with the latest
    document.addEventListener('live:change', function () {
        var shown = holder.querySelector('[data-calendar]');
        free = {};
        load(shown ? shown.getAttribute('data-month') : '');
    });
    // A new price arrives with the booking box
    document.addEventListener('live:updated', function () {
        var box = document.querySelector('[data-room-price]');
        if (box) { price = parseFloat(box.getAttribute('data-room-price')) || price; paint(); }
    });

    remember();
})();
