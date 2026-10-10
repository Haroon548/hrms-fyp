/*
 * Login / register / password pages: show-hide password and the strength meter.
 * The server checks the password rules again; this only helps people as they type.
 */
(function () {
    'use strict';

    // Eye button: show or hide the password
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-toggle-password'));
        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
        });
    });

    // Strength meter: a bar that fills and changes colour, plus a short hint
    var levels = [
        { width: '15%', colour: '#dc3545', text: 'Too short: use at least 8 characters.' },
        { width: '35%', colour: '#dc3545', text: 'Add a letter and a number.' },
        { width: '60%', colour: '#f0ad4e', text: 'Okay. Longer, or a symbol, makes it stronger.' },
        { width: '85%', colour: '#28a745', text: 'Strong password.' },
        { width: '100%', colour: '#1e7e34', text: 'Very strong password.' }
    ];
    document.querySelectorAll('[data-strength]').forEach(function (input) {
        var field = input.closest('.form-field');
        var bar = field.querySelector('.strength span');
        var hint = field.querySelector('[data-strength-text]');
        var startHint = hint.textContent;
        input.addEventListener('input', function () {
            var value = input.value;
            if (value === '') {
                bar.style.width = '0';
                hint.textContent = startHint;
                return;
            }
            var level;
            if (value.length < 8) {
                level = 0;
            } else if (!/[A-Za-z]/.test(value) || !/[0-9]/.test(value)) {
                level = 1;
            } else {
                level = 2;
                if (value.length >= 12) level++;
                if (/[^A-Za-z0-9]/.test(value)) level++;
            }
            bar.style.width = levels[level].width;
            bar.style.background = levels[level].colour;
            hint.textContent = levels[level].text;
        });
    });

    // "Confirm password" must match
    var confirm = document.querySelector('[name="password_confirm"]');
    var first = document.querySelector('[name="password"]');
    if (confirm && first) {
        var check = function () {
            confirm.setCustomValidity(confirm.value && confirm.value !== first.value ? 'The passwords don\'t match.' : '');
        };
        confirm.addEventListener('input', check);
        first.addEventListener('input', check);
    }

    // 6-digit email code: digits only (pasting "482 913" works), sent as soon as all 6 are in
    document.querySelectorAll('[data-code-input]').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '').slice(0, 6);
            input.value = digits;
            var form = input.closest('[data-code-form]');
            if (digits.length === 6 && form && !form.dataset.sent) {
                form.dataset.sent = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });

    // "Send a new code (42 s)": counts down, then can be pressed
    document.querySelectorAll('[data-resend-wait]').forEach(function (button) {
        var wait = parseInt(button.getAttribute('data-resend-wait'), 10) || 0;
        if (!wait) return;
        var timer = setInterval(function () {
            wait--;
            button.textContent = wait > 0 ? 'Send a new code (' + wait + ' s)' : 'Send a new code';
            if (wait <= 0) {
                button.disabled = false;
                clearInterval(timer);
            }
        }, 1000);
    });
})();
