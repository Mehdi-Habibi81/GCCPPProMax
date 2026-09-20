/* ============================================================
 * Jalali date picker — self-contained vanilla JS.
 *
 * Turns every <input class="jalali-input"> into a field that opens
 * a Persian (Jalali) calendar popup. On day selection it writes the
 * value as "YYYY/MM/DD" with Latin digits into the input, which the
 * server converts via lab_jalali_to_gregorian().
 *
 * Calendar math is the canonical jalaali-js algorithm (MIT).
 * ============================================================ */
(function () {
    'use strict';

    /* ---------------- Jalaali calendar math ---------------- */
    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    function div(a, b) { return ~~(a / b); }
    function mod(a, b) { return a - ~~(a / b) * b; }

    function jalCal(jy) {
        var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0], jm, jump, leap, leapG, march, n, i;
        if (jy < jp || jy >= breaks[bl - 1]) throw new Error('Invalid Jalaali year ' + jy);
        for (i = 1; i < bl; i += 1) {
            jm = breaks[i];
            jump = jm - jp;
            if (jy < jm) break;
            leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
            jp = jm;
        }
        n = jy - jp;
        leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
        if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
        leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
        march = 20 + leapJ - leapG;
        if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
        leap = mod(mod(n + 1, 33) - 1, 4);
        if (leap === -1) leap = 4;
        return { leap: leap, gy: gy, march: march };
    }

    function g2d(gy, gm, gd) {
        var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
        d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
        return d;
    }

    function d2g(jdn) {
        var j = 4 * jdn + 139361631, j2, i, g, d, gm, gd, gy;
        j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        i = div(mod(j, 1461), 4) * 5 + 308;
        g = div(mod(i, 153), 5) + 1;
        d = mod(i, 153) + 1;
        gm = mod(div(i, 61), 12) + 1;
        gy = div(j, 1461) - 100100 + div(8 - gm, 6);
        return { gy: gy, gm: gm, gd: d };
    }

    function jal2jdn(jy, jm, jd) {
        var r = jalCal(jy);
        return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    }

    function j2g(jy, jm, jd) { return d2g(jal2jdn(jy, jm, jd)); }

    function g2j(gY, gM, gD) {
        var jdn = g2d(gY, gM, gD);
        var gy = d2g(jdn).gy, jy = gy - 621, r = jalCal(jy), jdn1f = g2d(gy, 3, r.march), k, jm, jd;
        k = jdn - jdn1f;
        if (k >= 0) {
            if (k <= 185) { jm = 1 + div(k, 31); jd = mod(k, 31) + 1; return { jy: jy, jm: jm, jd: jd }; }
            k -= 186;
        } else {
            jy -= 1; k += 179;
            if (r.leap === 1) k += 1;
        }
        jm = 7 + div(k, 30); jd = mod(k, 30) + 1;
        return { jy: jy, jm: jm, jd: jd };
    }

    function jalMonthLength(jy, jm) {
        if (jm <= 6) return 31;
        if (jm <= 11) return 30;
        return jalCal(jy).leap === 0 ? 30 : 29;
    }

    /* ---------------- Helpers ---------------- */
    var monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    var faDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    function toFa(n) { return String(n).replace(/\d/g, function (d) { return faDigits[+d]; }); }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function enDigit(c) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c); }

    function parseJalali(str) {
        if (!str) return null;
        var s = String(str).replace(/[۰-۹]/g, function (c) { return enDigit(c); });
        var m = s.match(/^\s*(\d{1,4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})\s*$/);
        if (!m) return null;
        var y = +m[1], mo = +m[2], d = +m[3];
        if (mo < 1 || mo > 12 || d < 1 || d > jalMonthLength(y, mo)) return null;
        return { jy: y, jm: mo, jd: d };
    }

    function today() {
        var now = new Date();
        return g2j(now.getFullYear(), now.getMonth() + 1, now.getDate());
    }

    /* ---------------- Widget ---------------- */
    function openPicker(input, wrap) {
        var pop = document.createElement('div');
        pop.className = 'jp-pop';

        var sel = parseJalali(input.value) || today();

        function render() {
            var g = j2g(sel.jy, sel.jm, 1);
            var firstWeekday = new Date(g.gy, g.gm - 1, g.gd).getDay();
            var offset = (firstWeekday + 1) % 7; // Saturday-first grid
            var daysInMonth = jalMonthLength(sel.jy, sel.jm);
            var todayJ = today();
            var selected = parseJalali(input.value);

            pop.innerHTML = '';

            var head = document.createElement('div');
            head.className = 'jp-head';
            var prev = document.createElement('button');
            prev.type = 'button'; prev.className = 'jp-nav'; prev.textContent = '»';
            prev.addEventListener('click', function () {
                sel.jm -= 1;
                if (sel.jm < 1) { sel.jm = 12; sel.jy -= 1; }
                render();
            });
            var title = document.createElement('span');
            title.className = 'jp-title';
            title.textContent = monthNames[sel.jm - 1] + ' ' + toFa(sel.jy);
            var next = document.createElement('button');
            next.type = 'button'; next.className = 'jp-nav'; next.textContent = '«';
            next.addEventListener('click', function () {
                sel.jm += 1;
                if (sel.jm > 12) { sel.jm = 1; sel.jy += 1; }
                render();
            });
            head.appendChild(prev); head.appendChild(title); head.appendChild(next);
            pop.appendChild(head);

            var week = document.createElement('div');
            week.className = 'jp-week';
            ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'].forEach(function (w) {
                var s = document.createElement('span'); s.textContent = w; week.appendChild(s);
            });
            pop.appendChild(week);

            var grid = document.createElement('div');
            grid.className = 'jp-days';

            for (var i = 0; i < offset; i++) {
                var blank = document.createElement('span'); grid.appendChild(blank);
            }
            for (var day = 1; day <= daysInMonth; day++) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'jp-day';
                if (day === todayJ.jd && sel.jy === todayJ.jy && sel.jm === todayJ.jm) btn.className += ' today';
                if (selected && selected.jy === sel.jy && selected.jm === sel.jm && selected.jd === day) btn.className += ' selected';
                btn.textContent = toFa(day);
                btn.addEventListener('click', function (d) {
                    input.value = sel.jy + '/' + pad(sel.jm) + '/' + pad(d);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    pop.remove();
                }.bind(null, day));
                grid.appendChild(btn);
            }
            pop.appendChild(grid);
        }

        render();
        return pop;
    }

    function init() {
        document.querySelectorAll('input.jalali-input').forEach(function (input) {
            if (input.dataset.jalaliInit) return;
            input.dataset.jalaliInit = '1';

            var wrap = document.createElement('span');
            wrap.className = 'jalali-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var pop = null;
            var close = function () { if (pop) { pop.remove(); pop = null; } };

            var open = function () {
                close();
                pop = openPicker(input, wrap);
                wrap.appendChild(pop);
            };

            input.addEventListener('focus', open);
            input.addEventListener('click', open);
            document.addEventListener('click', function (e) {
                // Ignore clicks on elements that were removed from the DOM
                // during a re-render (e.g. the prev/next month arrows, which
                // rebuild the popup body and detach the clicked button, so
                // wrap.contains(e.target) is false). Without this guard the
                // calendar would close on every navigation click.
                if (e.target && e.target.isConnected && !wrap.contains(e.target)) close();
            });

            var clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'jalali-clear';
            clear.title = 'پاک کردن تاریخ';
            clear.textContent = '✕';
            clear.addEventListener('click', function () {
                input.value = '';
                close();
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            wrap.appendChild(clear);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();