(function () {
    var selector = '.stories, .perks, .raffles, .rail, .people, .car-shots, .route-slider';

    function button(label, side, path) {
        var el = document.createElement('button');
        el.type = 'button';
        el.className = 'hscroll-btn ' + side;
        el.setAttribute('aria-label', label);
        el.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + path + '"/></svg>';
        return el;
    }

    document.querySelectorAll(selector).forEach(function (track) {
        if (track.closest('.hscroll')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'hscroll';
        track.parentNode.insertBefore(wrap, track);
        wrap.appendChild(track);
        var prev = button('Anterior', 'prev', 'M14.5 6.5 9 12l5.5 5.5');
        var next = button('Próximo', 'next', 'M9.5 6.5 15 12l-5.5 5.5');
        wrap.appendChild(prev);
        wrap.appendChild(next);

        function amount() {
            var card = track.firstElementChild;
            var styles = getComputedStyle(track);
            var gap = parseFloat(styles.columnGap || styles.gap) || 0;
            if (!card) {
                return Math.round(track.clientWidth * 0.8);
            }
            return Math.round(card.getBoundingClientRect().width + gap);
        }

        function update() {
            var max = track.scrollWidth - track.clientWidth;
            prev.hidden = max <= 2 || track.scrollLeft <= 2;
            next.hidden = max <= 2 || track.scrollLeft >= max - 2;
        }

        function glide(delta) {
            var max = Math.max(0, track.scrollWidth - track.clientWidth);
            var start = track.scrollLeft;
            var end = Math.max(0, Math.min(max, start + delta));
            var t0 = performance.now();
            if (track._glide) {
                cancelAnimationFrame(track._glide);
            }
            function tick(now) {
                var p = Math.min(1, (now - t0) / 180);
                var eased = 1 - Math.pow(1 - p, 3);
                track.scrollLeft = start + (end - start) * eased;
                if (p < 1) {
                    track._glide = requestAnimationFrame(tick);
                }
            }
            track._glide = requestAnimationFrame(tick);
        }

        prev.addEventListener('click', function () {
            glide(-amount());
        });
        next.addEventListener('click', function () {
            glide(amount());
        });
        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        if (window.ResizeObserver) {
            new ResizeObserver(update).observe(track);
        }
        update();
    });
})();
