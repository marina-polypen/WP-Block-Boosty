/**
 * WP Block Boosty – Frontend JS
 * Карусель, Latest Posts, Scroll Animations.
 */
(function () {
    'use strict';

    var s = window.wpbbSettings || {};
    var p = s.prefix || 'be';

    document.addEventListener('DOMContentLoaded', function () {
        /* ── #8 Gallery → Carousel ─────────────────── */
        if (parseInt(s.galleryEnabled) === 1) {
            initCarousels();
        }

        /* ── #9 Latest Posts ───────────────────────── */
        initLatestPosts();

        /* ── Micro-animations (Intersection Observer) ── */
        initScrollAnimations();
    });

    /* ═══════════════════════════════════════════════
       CAROUSEL
       ═══════════════════════════════════════════════ */
    function initCarousels() {
        var galleries = document.querySelectorAll('.wp-block-gallery, .gallery');
        if (!galleries.length) return;

        galleries.forEach(function (gallery) {
            if (gallery.classList.contains(p + '-carousel-init')) return;
            gallery.classList.add(p + '-carousel-init');

            var items = gallery.querySelectorAll('.wp-block-image, .gallery-item');
            if (!items.length) return;

            // Обёртка.
            var wrap = document.createElement('div');
            wrap.className = p + '-carousel-wrap';

            var track = document.createElement('div');
            track.className = p + '-carousel';

            items.forEach(function (item) {
                var slide = document.createElement('div');
                slide.className = p + '-carousel-item';

                // Переносим (не клонируем) узел, чтобы изображение не грузилось дважды.
                var img = item.querySelector('img');
                if (img) {
                    if (!img.getAttribute('loading')) {
                        img.setAttribute('loading', 'lazy');
                    }
                    img.setAttribute('decoding', 'async');
                    slide.appendChild(img);
                }

                // Если есть figcaption — переносим.
                var caption = item.querySelector('figcaption');
                if (caption) {
                    caption.style.padding = '10px 14px';
                    caption.style.fontSize = '0.85rem';
                    caption.style.color = '#6b7280';
                    caption.style.textAlign = 'center';
                    slide.appendChild(caption);
                }

                track.appendChild(slide);
            });

            wrap.appendChild(track);

            // Стрелки.
            if (parseInt(s.galleryArrows) === 1) {
                var prevBtn = document.createElement('button');
                prevBtn.className = p + '-carousel-arrow ' + p + '-carousel-prev';
                prevBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>';
                prevBtn.type = 'button';
                prevBtn.setAttribute('aria-label', 'Previous');

                var nextBtn = document.createElement('button');
                nextBtn.className = p + '-carousel-arrow ' + p + '-carousel-next';
                nextBtn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
                nextBtn.type = 'button';
                nextBtn.setAttribute('aria-label', 'Next');

                wrap.appendChild(prevBtn);
                wrap.appendChild(nextBtn);

                prevBtn.addEventListener('click', function () {
                    scrollCarousel(track, -1);
                });
                nextBtn.addEventListener('click', function () {
                    scrollCarousel(track, 1);
                });
            }

            // Буллиты.
            if (parseInt(s.galleryDots) === 1) {
                var dotsWrap = document.createElement('div');
                dotsWrap.className = p + '-carousel-dots';

                var slideCount = track.children.length;
                for (var i = 0; i < slideCount; i++) {
                    var dot = document.createElement('button');
                    dot.className = p + '-carousel-dot' + (i === 0 ? ' active' : '');
                    dot.type = 'button';
                    dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                    dot.dataset.index = i;
                    dot.addEventListener('click', (function (idx) {
                        return function () {
                            var slideEl = track.children[idx];
                            if (slideEl) {
                                track.scrollTo({
                                    left: slideEl.offsetLeft - track.offsetLeft,
                                    behavior: 'smooth'
                                });
                            }
                        };
                    })(i));
                    dotsWrap.appendChild(dot);
                }
                wrap.appendChild(dotsWrap);

                // Debounced scroll handler.
                var scrollTimer;
                track.addEventListener('scroll', function () {
                    clearTimeout(scrollTimer);
                    scrollTimer = setTimeout(function () {
                        updateDots(track, dotsWrap);
                    }, 60);
                });
            }

            // Заменяем оригинальную галерею (узлы уже перенесены — удаляем пустой оригинал).
            gallery.parentNode.insertBefore(wrap, gallery);
            gallery.parentNode.removeChild(gallery);
        });
    }

    function scrollCarousel(track, direction) {
        var slideWidth = track.children[0] ? track.children[0].offsetWidth + 16 : 300;
        track.scrollBy({
            left: slideWidth * direction,
            behavior: 'smooth'
        });
    }

    function updateDots(track, dotsWrap) {
        var dots = dotsWrap.querySelectorAll('.' + p + '-carousel-dot');
        var scrollLeft = track.scrollLeft;
        var slideWidth = track.children[0] ? track.children[0].offsetWidth + 16 : 300;
        var activeIdx = Math.round(scrollLeft / slideWidth);

        dots.forEach(function (dot, i) {
            dot.classList.toggle('active', i === activeIdx);
        });
    }

    /* ═══════════════════════════════════════════════
       LATEST POSTS
       ═══════════════════════════════════════════════ */
    function initLatestPosts() {
        var blocks = document.querySelectorAll('.wp-block-latest-posts');
        blocks.forEach(function (block) {
            if (!block.classList.contains(p + '-latest-posts')) {
                block.classList.add(p + '-latest-posts');
            }
        });
    }

    /* ═══════════════════════════════════════════════
       SCROLL ANIMATIONS (IntersectionObserver)
       ═══════════════════════════════════════════════ */
    function initScrollAnimations() {
        if (!('IntersectionObserver' in window)) return;

        // Анимируемые элементы — блоки с префиксом.
        var selectors = [
            '.' + p + '-ol li',
            '.' + p + '-ul li',
            '.' + p + '-table-wrap',
            '.' + p + '-bq-block',
            '.' + p + '-figure',
            '.' + p + '-carousel-wrap',
            '.' + p + '-latest-posts li'
        ];

        var elements = document.querySelectorAll(selectors.join(','));
        if (!elements.length) return;

        elements.forEach(function (el) {
            el.classList.add('wpbb-animate-in');
        });

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    // Stagger delay based on index among siblings.
                    var parent = entry.target.parentElement;
                    var siblings = parent ? Array.from(parent.children) : [];
                    var idx = siblings.indexOf(entry.target);
                    var delay = Math.min(idx * 60, 300);

                    setTimeout(function () {
                        entry.target.classList.add('wpbb-visible');
                    }, delay);

                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        elements.forEach(function (el) {
            observer.observe(el);
        });
    }
})();
