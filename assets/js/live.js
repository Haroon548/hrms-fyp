/*
 * Live updates (FR-LIVE-01, D17): a page changes by itself when something it shows is saved
 * anywhere, e.g. the guest's order page when the kitchen marks the order ready, or the
 * admin's booking list when a guest books. No refresh button needed.
 *
 *  1. The page lists the tables it shows and the version of each it was made from
 *     (<body data-live-watch="orders,invoices" data-live-known='{"orders":12}'>).
 *  2. live.php holds the question "has anything changed?" open for up to 20 seconds and
 *     answers the moment something is saved, so changes show within about a second.
 *  3. When one of the page's tables has a new version, the page loads itself again in the
 *     background and swaps only its live parts ([data-live-region]). A part where the visitor
 *     is typing is left alone; a small bar offers "Update" instead.
 *  4. Only one visible tab per browser asks the server and shares the answer with the others
 *     (Web Locks + BroadcastChannel). Hidden tabs catch up when they are shown again.
 * Other scripts can listen for "live:change" (tables changed) and "live:updated" (a part was swapped).
 */
(function () {
    'use strict';

    var body = document.body;
    var endpoint = body.getAttribute('data-live-url');
    var watch = (body.getAttribute('data-live-watch') || '').split(',').filter(Boolean);
    if (!endpoint || !watch.length || !window.fetch || !window.DOMParser) return;

    var known = readKnown(body);
    var posted = body.hasAttribute('data-live-posted'); // this page is the answer to a form: never swap it
    var channel = 'BroadcastChannel' in window ? new BroadcastChannel('hrms-live') : null;
    var lastSum = '';
    var stale = false, loading = false, again = false, waitingForFocus = false;
    var bar = null, poller = null;
    var lastHtml = new WeakMap(); // region → the HTML it was last given

    function readKnown(element) {
        try { return JSON.parse(element.getAttribute('data-live-known') || '{}'); } catch (e) { return {}; }
    }

    /* ---------- 1. New version numbers arrive ---------- */

    function receive(data) {
        if (!data || !data.tables) return;
        lastSum = data.sum;
        var changed = watch.filter(function (table) { return (data.tables[table] || 0) !== (known[table] || 0); });
        if (!changed.length) return;
        changed.forEach(function (table) { known[table] = data.tables[table] || 0; });
        document.dispatchEvent(new CustomEvent('live:change', { detail: { tables: changed } }));
        if (document.querySelector('[data-live-region]')) update();
    }

    /* ---------- 2. Load the page again and swap its live parts ---------- */

    function update() {
        if (document.hidden) { stale = true; return; }
        if (loading) { again = true; return; }
        loading = true;
        stale = false;
        fetch(window.location.href, { headers: { 'X-Live': '1' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) {
                // Gone or moved (e.g. logged out elsewhere): leave the page as it is
                if (!response.ok || response.redirected) throw new Error('not loaded');
                return response.text();
            })
            .then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html');
                var blocked = swap(fresh);
                var freshKnown = readKnown(fresh.body);
                Object.keys(freshKnown).forEach(function (table) { known[table] = freshKnown[table]; });
                if (fresh.title) document.title = fresh.title;
                showBar(blocked);
            })
            .catch(function () {})
            .then(function () {
                loading = false;
                if (again) { again = false; update(); }
            });
    }

    function swap(fresh) {
        var blocked = false;
        document.querySelectorAll('[data-live-region]').forEach(function (region) {
            var name = region.getAttribute('data-live-region');
            var next = fresh.querySelector('[data-live-region="' + name + '"]');
            if (!next) { blocked = true; return; } // the page now looks different (e.g. the room was just taken)
            if (next.innerHTML === lastHtml.get(region)) return; // nothing new here
            if (looks(next) === looks(region)) { lastHtml.set(region, next.innerHTML); return; } // the same as on screen
            if (posted || isBusy(region)) { blocked = true; return; }
            var scrolls = rememberScroll(region);
            region.innerHTML = next.innerHTML;
            lastHtml.set(region, next.innerHTML);
            restoreScroll(region, scrolls);
            region.classList.remove('is-live-updated');
            void region.offsetWidth; // restart the short highlight
            region.classList.add('is-live-updated');
            document.dispatchEvent(new CustomEvent('live:updated', { detail: { region: region } }));
        });
        return blocked;
    }

    // What the server sent for this part: its text, photos, states and the starting value of each
    // form field (never what the visitor has typed), to tell a real change from the same page again
    function looks(element) {
        var parts = [element.textContent.replace(/\s+/g, ' ')];
        element.querySelectorAll('img, input, select option, [class*="badge"], [disabled], [aria-disabled], [hidden]').forEach(function (el) {
            parts.push(el.tagName + (el.getAttribute('src') || '') + (el.getAttribute('value') || '') + el.className
                + (el.hasAttribute('checked') ? 'c' : '') + (el.hasAttribute('selected') ? 's' : '')
                + (el.hasAttribute('disabled') ? 'd' : '') + (el.hasAttribute('hidden') ? 'h' : ''));
        });
        return parts.join('|');
    }

    // Is the visitor typing here, or has a menu or pop-up open?
    function isBusy(region) {
        var active = document.activeElement;
        if (active && region.contains(active) && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName) && !/^(submit|button|hidden)$/.test(active.type)) {
            waitingForFocus = true;
            return true;
        }
        if (region.querySelector('.dropdown-menu.show, .modal.show, .offcanvas.show')) return true;
        return Array.prototype.some.call(region.querySelectorAll('input, textarea, select'), isChanged);
    }

    function isChanged(field) {
        if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') return false;
        if (field.type === 'checkbox' || field.type === 'radio') return field.checked !== field.defaultChecked;
        if (field.tagName === 'SELECT') {
            var first = Array.prototype.findIndex.call(field.options, function (option) { return option.defaultSelected; });
            return field.selectedIndex !== (first < 0 ? 0 : first);
        }
        return field.value !== field.defaultValue;
    }

    // Boxes that scroll sideways (e.g. the occupancy chart) keep their place
    function rememberScroll(region) {
        var list = [];
        region.querySelectorAll('*').forEach(function (el) {
            if (el.scrollLeft || el.scrollTop) list.push([el.id ? '#' + el.id : el.className, el.scrollLeft, el.scrollTop]);
        });
        return list;
    }

    function restoreScroll(region, list) {
        list.forEach(function (item) {
            var el = item[0].charAt(0) === '#' ? region.querySelector(item[0])
                : Array.prototype.find.call(region.querySelectorAll('*'), function (e) { return e.className === item[0]; });
            if (el) { el.scrollLeft = item[1]; el.scrollTop = item[2]; }
        });
    }

    /* ---------- "New updates" bar, when a part couldn't be swapped ---------- */

    function showBar(show) {
        if (!show) { if (bar) bar.hidden = true; return; }
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'live-bar';
            bar.setAttribute('role', 'status');
            bar.innerHTML = '<i class="fa-solid fa-rotate" aria-hidden="true"></i><span>This page has new information.</span>'
                + '<button type="button" data-live-reload>Update</button>'
                + '<button type="button" class="live-bar-close" aria-label="Hide this message">&times;</button>';
            bar.querySelector('[data-live-reload]').addEventListener('click', function () {
                var dirty = Array.prototype.some.call(document.querySelectorAll('[data-live-region] input, [data-live-region] textarea, [data-live-region] select'), isChanged);
                if (dirty && !window.confirm('Update the page? What you have typed but not saved will be lost.')) return;
                if (posted) window.location.href = window.location.href.split('#')[0];
                else window.location.reload();
            });
            bar.querySelector('.live-bar-close').addEventListener('click', function () { bar.hidden = true; });
            document.body.appendChild(bar);
        }
        bar.hidden = false;
    }

    // Finished typing in a field that blocked an update: try again
    document.addEventListener('focusout', function () {
        if (!waitingForFocus) return;
        waitingForFocus = false;
        setTimeout(update, 250);
    });

    /* ---------- 3. Asking the server (one tab per browser) ---------- */

    function pause(ms, signal) {
        return new Promise(function (resolve) {
            var timer = setTimeout(resolve, ms);
            signal.addEventListener('abort', function () { clearTimeout(timer); resolve(); });
        });
    }

    function status(online) {
        document.dispatchEvent(new CustomEvent('live:status', { detail: { online: online } }));
    }

    async function ask(signal) {
        var failures = 0;
        while (!signal.aborted) {
            var delay;
            try {
                var response = await fetch(endpoint + '?since=' + encodeURIComponent(lastSum), { cache: 'no-store', credentials: 'same-origin', signal: signal });
                if (!response.ok) throw new Error('status ' + response.status);
                var data = await response.json();
                failures = 0;
                if (channel) channel.postMessage({ data: data, online: true });
                status(true);
                receive(data);
                delay = Math.max(1, data.next || 0);
            } catch (error) {
                if (signal.aborted) return;
                failures++;
                if (channel) channel.postMessage({ online: false });
                status(false);
                delay = Math.min(30, 3 * failures); // e.g. the Wi-Fi dropped: try again a little later
            }
            await pause(delay * 1000, signal);
        }
    }

    function start() {
        if (poller || document.hidden) return;
        poller = new AbortController();
        var signal = poller.signal;
        if (navigator.locks) {
            navigator.locks.request('hrms-live', { signal: signal }, function () { return ask(signal); }).catch(function () {});
        } else {
            ask(signal);
        }
    }

    function stop() {
        if (poller) { poller.abort(); poller = null; }
    }

    if (channel) {
        channel.onmessage = function (event) {
            if (event.data.data) receive(event.data.data);
            status(event.data.online);
        };
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { stop(); return; }
        start();
        if (stale) update();
    });
    // Coming back with the browser's Back button can show an old copy of the page
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { start(); update(); }
    });

    start();
})();
