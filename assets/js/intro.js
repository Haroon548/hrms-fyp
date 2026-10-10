/*
 * Opening intro on the Home page (FR-UX-08, design.md Home), about 3.5 seconds: arriving at
 * the hotel. The building with its RAMADA sign (HD) fades up and pulls back slowly → the building darkens in the hero's colours as the white logo and tagline rise in →
 * everything dissolves into the Home page.
 * The inline script in index.php decides whether it shows (first visit per tab or ?intro,
 * not for "reduce motion"). At the end it removes itself and fires "intro:done", which
 * home.js waits for before animating the hero.
 */
(function () {
    'use strict';

    var intro = document.getElementById('intro');
    if (!intro || intro.hidden) {
        return;
    }

    var root = document.documentElement;
    var finished = false;
    var timeline = null;

    function finish() {
        if (finished) return;
        finished = true;
        clearTimeout(safety);
        if (timeline) timeline.kill();
        root.classList.remove('intro-active');
        intro.remove();
        document.dispatchEvent(new Event('intro:done'));
    }

    // Never trap the visitor: whatever happens, the intro is gone after 9 s.
    var safety = setTimeout(finish, 9000);

    function skip() {
        if (finished) return;
        if (timeline) timeline.kill();
        if (window.gsap) {
            gsap.to(intro, { opacity: 0, duration: 0.3, onComplete: finish });
        } else {
            finish();
        }
    }
    document.getElementById('introSkip').addEventListener('click', skip);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') skip();
    });

    if (!window.gsap) {
        finish();
        return;
    }

    // Wait (at most 1.5 s) for the HD photo, so it doesn't start half-loaded
    var photo = intro.querySelector('.intro-shot');
    var loaded = photo.decode ? photo.decode().catch(function () {}) : Promise.resolve();
    Promise.race([loaded, new Promise(function (resolve) { setTimeout(resolve, 1500); })]).then(play);

    function play() {
        if (finished) return;
        var tl = gsap.timeline({ onComplete: finish });
        timeline = tl;
        tl
            // The building fades up from dark, starting close on the RAMADA sign, and the camera pulls back
            .fromTo(photo, { opacity: 0, scale: 1.3 }, { opacity: 1, duration: 0.8, ease: 'power2.out' }, 0)
            .to(photo, { scale: 1.04, duration: 2.6, ease: 'sine.out' }, 0)
            // The building darkens into the hero's colours and the logo rises in
            .to('.intro-tint', { opacity: 1, duration: 0.8, ease: 'power1.inOut' }, 1.0)
            .from('.intro-logo', { opacity: 0, y: 26, filter: 'blur(8px)', duration: 0.8, ease: 'power3.out' }, 1.1)
            .from('.intro-sub', { opacity: 0, letterSpacing: '1em', duration: 0.8, ease: 'power3.out' }, 1.3)
            // Everything dissolves into the Home page
            .to(intro, { opacity: 0, duration: 0.9, ease: 'power2.inOut' }, 2.6);
    }
})();
