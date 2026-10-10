/*
 * Kitchen screen (FR-SIG-QR-04, 05).
 * When live.js says orders have changed (FR-LIVE-01), the columns are loaded again from
 * kitchen.php?board=1. When a new order has arrived since the last look, a short two-note
 * chime plays (made with the Web Audio API, so no sound file is needed) and the page title
 * shows how many orders are waiting. The "5 min" on each card counts up on the screen itself.
 */
(function () {
    'use strict';

    var main = document.getElementById('board');
    var url = main.getAttribute('data-board-url');
    var soundButton = document.querySelector('[data-sound]');
    var live = document.querySelector('[data-live]');
    var clock = document.querySelector('[data-clock]');
    var baseTitle = document.title;
    var audio = null;
    var soundOn = false;

    function board() { return main.querySelector('.kitchen-board'); }
    var latest = parseInt(board().getAttribute('data-latest'), 10) || 0;

    function showWaiting() {
        var waiting = parseInt(board().getAttribute('data-waiting'), 10) || 0;
        document.title = (waiting ? '(' + waiting + ') ' : '') + baseTitle;
    }

    // Browsers only allow sound after a tap, so the kitchen turns it on once
    soundButton.addEventListener('click', function () {
        soundOn = !soundOn;
        if (soundOn && !audio) {
            var Context = window.AudioContext || window.webkitAudioContext;
            audio = Context ? new Context() : null;
        }
        if (audio && audio.state === 'suspended') audio.resume();
        soundButton.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
        soundButton.innerHTML = soundOn
            ? '<i class="fa-solid fa-volume-high" aria-hidden="true"></i> Sound on'
            : '<i class="fa-solid fa-volume-xmark" aria-hidden="true"></i> Sound off';
        if (soundOn) chime();
    });

    function chime() {
        if (!soundOn || !audio) return;
        [880, 1320].forEach(function (frequency, i) {
            var start = audio.currentTime + i * 0.18;
            var tone = audio.createOscillator();
            var volume = audio.createGain();
            tone.type = 'sine';
            tone.frequency.value = frequency;
            volume.gain.setValueAtTime(0.0001, start);
            volume.gain.exponentialRampToValueAtTime(0.35, start + 0.02);
            volume.gain.exponentialRampToValueAtTime(0.0001, start + 0.35);
            tone.connect(volume).connect(audio.destination);
            tone.start(start);
            tone.stop(start + 0.4);
        });
    }

    function refresh() {
        fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok || response.redirected) throw new Error('Not loaded');
                return response.text();
            })
            .then(function (html) {
                main.innerHTML = html;
                var newest = parseInt(board().getAttribute('data-latest'), 10) || 0;
                if (newest > latest) {
                    chime();
                    main.querySelectorAll('.is-pending .kitchen-card').forEach(function (card) { card.classList.add('is-new'); });
                }
                latest = newest;
                showWaiting();
                tick();
            })
            .catch(function () {
                live.classList.add('is-offline'); // e.g. the Wi-Fi dropped; it loads again on the next change
            });
    }

    // Minutes waited, counted on the screen (server time + seconds since the board was loaded)
    function tick() {
        var b = board();
        var loadedAt = b.loadedAt || (b.loadedAt = Date.now());
        var now = (parseInt(b.getAttribute('data-now'), 10) || 0) + (Date.now() - loadedAt) / 1000;
        b.querySelectorAll('[data-placed]').forEach(function (card) {
            var mins = Math.max(0, Math.floor((now - parseInt(card.getAttribute('data-placed'), 10)) / 60));
            card.querySelector('.kitchen-time').textContent = mins < 1 ? 'now' : mins + ' min';
            card.classList.toggle('is-late', mins >= 10 && !!card.closest('.is-pending'));
        });
        clock.textContent = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    }

    document.addEventListener('live:change', refresh);
    document.addEventListener('live:status', function (event) {
        live.classList.toggle('is-offline', !event.detail.online);
    });
    setInterval(tick, 15000);
    showWaiting();
    tick();
})();
