// ============================================
// Pinnacle Concrete Pumping Group - Landing page scripts
// ============================================

(function () {
    'use strict';

    // Footer year
    var yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // Sticky header shadow on scroll
    var header = document.getElementById('siteHeader');
    var onScroll = function () {
        if (!header) return;
        if (window.scrollY > 12) header.classList.add('scrolled');
        else header.classList.remove('scrolled');
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Mobile nav toggle
    var navToggle = document.getElementById('navToggle');
    var primaryNav = document.getElementById('primaryNav');
    var primaryNavClose = document.getElementById('primaryNavClose');
    var openNav = function () {
        primaryNav.classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    var closeNav = function () {
        primaryNav.classList.remove('open');
        document.body.style.overflow = '';
    };
    if (navToggle && primaryNav) {
        navToggle.addEventListener('click', function () {
            if (primaryNav.classList.contains('open')) closeNav(); else openNav();
        });
        if (primaryNavClose) primaryNavClose.addEventListener('click', closeNav);
        primaryNav.addEventListener('click', function (e) {
            if (e.target === primaryNav) closeNav();
        });
        primaryNav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeNav);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && primaryNav.classList.contains('open')) closeNav();
        });
    }

    // Quote form - basic client-side handling
    var quoteForm = document.getElementById('quoteForm');
    if (quoteForm) {
        quoteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var required = quoteForm.querySelectorAll('[required]');
            var valid = true;
            required.forEach(function (field) {
                if (!field.value.trim()) {
                    field.style.borderColor = '#E91E8C';
                    valid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            if (!valid) return;

            var btn = quoteForm.querySelector('button[type="submit"]');
            if (btn) {
                var original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="material-icons">check_circle</span>Thanks! We\'ll be in touch shortly.';
                quoteForm.reset();
                setTimeout(function () {
                    btn.disabled = false;
                    btn.innerHTML = original;
                }, 4500);
            }
        });
    }

    // Smooth scroll fix for sticky header offset
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var hash = link.getAttribute('href');
            if (hash.length < 2) return;
            var target = document.querySelector(hash);
            if (!target) return;
            e.preventDefault();
            var headerOffset = header ? header.offsetHeight : 0;
            var top = target.getBoundingClientRect().top + window.pageYOffset - headerOffset - 8;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });
})();
