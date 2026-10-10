/*
 * Home page animations (design.md section 3 "Motion", D19).
 * Every effect is extra: the page is complete and readable without this file.
 * Text is never hidden waiting for a scroll; only photos are revealed on scroll.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // --- Header: see-through over the slideshow, white once the visitor scrolls ---
    var header = document.querySelector('.site-header');
    function updateHeader() {
        header.classList.toggle('is-solid', window.scrollY > 40);
    }
    if (header) {
        updateHeader();
        window.addEventListener('scroll', updateHeader, { passive: true });
    }

    // --- Slideshow: pause/play button; no automatic rotation for "reduce motion" ---
    var carouselEl = document.getElementById('heroCarousel');
    var pauseButton = document.getElementById('heroPause');
    if (carouselEl && window.bootstrap) {
        var carousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
        var paused = false;

        function setPaused(value) {
            paused = value;
            if (paused) {
                carousel.pause();
            } else {
                carousel.cycle();
            }
            if (pauseButton) {
                pauseButton.innerHTML = paused ? '<i class="fa-solid fa-play"></i>' : '<i class="fa-solid fa-pause"></i>';
                pauseButton.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
            }
        }

        if (pauseButton) {
            pauseButton.addEventListener('click', function () {
                setPaused(!paused);
            });
        }
        if (reduceMotion) {
            setPaused(true);
        }

        // Keep the dot buttons in step with the visible photo
        var dots = document.querySelectorAll('.hero-dots button');
        carouselEl.addEventListener('slide.bs.carousel', function (event) {
            dots.forEach(function (dot, i) {
                var active = i === event.to;
                dot.classList.toggle('active', active);
                if (active) {
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });
        });
    }

    // Everything below needs GSAP and is skipped for "reduce motion".
    if (!window.gsap || reduceMotion) {
        return;
    }
    if (window.ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);
    }

    // --- Hero: headline, text and buttons rise in once when the page opens
    // (after the opening intro, if it's playing) ---
    function heroIn() {
        gsap.from('[data-hero-in]', {
            y: 28,
            opacity: 0,
            duration: 0.9,
            ease: 'power3.out',
            stagger: 0.12,
            delay: 0.15,
            clearProps: 'transform,opacity'
        });
        gsap.from('.search-bar', { y: 40, opacity: 0, duration: 0.9, ease: 'power3.out', delay: 0.55, clearProps: 'transform,opacity' });
    }
    if (document.documentElement.classList.contains('intro-active')) {
        document.addEventListener('intro:done', heroIn, { once: true });
    } else {
        heroIn();
    }

    if (!window.ScrollTrigger) {
        return;
    }

    // --- Photos (never text) are uncovered from the bottom the first time they scroll into view ---
    gsap.utils.toArray('img.reveal').forEach(function (photo) {
        gsap.fromTo(photo,
            { clipPath: 'inset(100% 0 0 0)', scale: 1.12 },
            {
                clipPath: 'inset(0% 0 0 0)',
                scale: 1,
                duration: 1.1,
                ease: 'power3.out',
                clearProps: 'clipPath,transform',
                scrollTrigger: { trigger: photo, start: 'top 88%', once: true }
            }
        );
    });

    // --- Gentle depth: photo layers move a little slower or faster than the page ---
    gsap.utils.toArray('[data-parallax]').forEach(function (layer) {
        gsap.to(layer, {
            yPercent: parseFloat(layer.getAttribute('data-parallax')),
            ease: 'none',
            scrollTrigger: { trigger: layer.parentElement, start: 'top bottom', end: 'bottom top', scrub: 0.6 }
        });
    });

    // --- Numbers count up from 0 when they come into view (the real number is in the HTML) ---
    gsap.utils.toArray('[data-count]').forEach(function (element) {
        var target = parseInt(element.getAttribute('data-count'), 10);
        var counter = { value: 0 };
        ScrollTrigger.create({
            trigger: element,
            start: 'top 90%',
            once: true,
            onEnter: function () {
                gsap.to(counter, {
                    value: target,
                    duration: 1.4,
                    ease: 'power2.out',
                    onUpdate: function () {
                        element.textContent = Math.round(counter.value);
                    }
                });
            }
        });
    });
})();
