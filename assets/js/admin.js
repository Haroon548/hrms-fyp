/*
 * Small helpers for the admin panel. Every page still works without this script;
 * it only adds previews and shortcuts. ("Are you sure?" dialogs come from main.js.)
 */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    // Runs on the page, and again on a part of it that a live update has just replaced
    function enhance(root) {

        // "Replace" photo: upload as soon as a file is chosen
        root.querySelectorAll('input[type="file"][data-auto-submit]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.files.length) {
                    input.closest('label').classList.add('is-busy');
                    input.form.submit();
                }
            });
        });

        // Upload box: list the chosen photos with small previews, and highlight it while dragging over it
        root.querySelectorAll('[data-drop-zone]').forEach(function (zone) {
            var input = zone.querySelector('input[type="file"]');
            var list = zone.querySelector('[data-file-list]');
            ['dragenter', 'dragover'].forEach(function (type) {
                zone.addEventListener(type, function () { zone.classList.add('is-over'); });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                zone.addEventListener(type, function () { zone.classList.remove('is-over'); });
            });
            input.addEventListener('change', function () {
                list.innerHTML = '';
                Array.prototype.forEach.call(input.files, function (file) {
                    var item = document.createElement('span');
                    item.className = 'drop-zone-file';
                    if (file.type.indexOf('image/') === 0) {
                        var img = document.createElement('img');
                        img.alt = '';
                        img.src = URL.createObjectURL(file);
                        img.onload = function () { URL.revokeObjectURL(img.src); };
                        item.appendChild(img);
                    }
                    item.appendChild(document.createTextNode(file.name));
                    list.appendChild(item);
                });
                zone.classList.toggle('has-files', input.files.length > 0);
            });
            // Show that something is happening while big photos upload
            input.form.addEventListener('submit', function () {
                if (!input.files.length) return;
                var button = input.form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Uploading…';
            });
        });

        // Rooms list: "tick all", and a count of ticked rooms in the bar above the table
        var checkAll = root.querySelector('[data-check-all]');
        var bar = root.querySelector('[data-bulk-bar]');
        if (checkAll && bar) {
            var boxes = root.querySelectorAll('input[name="ids[]"][type="checkbox"]');
            var count = bar.querySelector('[data-bulk-count]');
            var update = function () {
                var ticked = Array.prototype.filter.call(boxes, function (box) { return box.checked; }).length;
                count.textContent = ticked ? ticked + (ticked === 1 ? ' room ticked' : ' rooms ticked') : 'Tick rooms to change several at once';
                bar.classList.toggle('is-active', ticked > 0);
                checkAll.checked = ticked === boxes.length;
                checkAll.indeterminate = ticked > 0 && ticked < boxes.length;
            };
            checkAll.addEventListener('change', function () {
                boxes.forEach(function (box) { box.checked = checkAll.checked; });
                update();
            });
            boxes.forEach(function (box) { box.addEventListener('change', update); });
            update();
        }
    }

    enhance(document);
    document.addEventListener('live:updated', function (event) { enhance(event.detail.region); });
})();
