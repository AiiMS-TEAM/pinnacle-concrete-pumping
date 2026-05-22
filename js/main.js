// ============================================
// Pinnacle Concrete Pumping Group - Landing page scripts
// ============================================

(function () {
    'use strict';

    // ----- reCAPTCHA v3 site key (public — secret lives in cPanel .env) -----
    var RECAPTCHA_SITE_KEY = '6LfKPfcsAAAAAC2ddUWKwNb5GXaeEZOsuUCRB_ro';

    // Footer year
    var yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // Sticky header shadow on scroll
    var header = document.getElementById('siteHeader');
    var onScroll = function () {
        if (!header) return;
        if (window.scrollY > 12) {
            header.classList.add('scrolled');
            document.body.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
            document.body.classList.remove('scrolled');
        }
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

    /* ---------- Form submission ----------
       Submits JSON to the form's action URL (cPanel-hosted PHP on
       api.pinnacleconcretepumping.com.au). Server-side performs reCAPTCHA +
       field validation and returns { ok, message, errors }. Site key is
       public — secret + mail config live on the cPanel host in .env. */
    function bindForm(form, action) {
        if (!form) return;
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        function markInvalid(el, bad) {
            if (!el) return;
            el.classList.toggle('is-invalid', !!bad);
        }
        function clearInvalid() {
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
        }

        // Clear invalid state as user fixes fields
        form.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('is-invalid')) {
                markInvalid(e.target, false);
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearInvalid();
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Sending…';
            }

            var post = function (token) {
                var hidden = form.querySelector('input[name="recaptcha_token"]');
                if (hidden) hidden.value = token || '';

                var payload = {};
                new FormData(form).forEach(function (v, k) { payload[k] = v; });

                fetch(form.action || action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                })
                    .then(function (r) {
                        return r.json().catch(function () { return null; });
                    })
                    .then(function (data) {
                        if (!data) {
                            alert('Unexpected server response. Please try again.');
                            return;
                        }
                        if (data.ok) {
                            // Redirect to thank-you page so Google tracking /
                            // conversion scripts there fire exactly once per lead.
                            window.location.href = 'thank-you.html';
                            return;
                        }
                        (data.errors || []).forEach(function (name) {
                            markInvalid(form.elements[name], true);
                        });
                        alert(data.message || 'Please check the form and try again.');
                        var firstBad = form.querySelector('.is-invalid');
                        if (firstBad && firstBad.focus) firstBad.focus();
                    })
                    .catch(function () {
                        alert('Network error. Please try again or call 1300 688 390.');
                    })
                    .then(function () {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnHtml;
                        }
                    });
            };

            var hasKey = RECAPTCHA_SITE_KEY && RECAPTCHA_SITE_KEY.indexOf('YOUR_') !== 0;
            if (hasKey && window.grecaptcha && window.grecaptcha.ready) {
                window.grecaptcha.ready(function () {
                    window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: form.id || 'form' })
                        .then(post)
                        .catch(function () { post(''); });
                });
            } else {
                post('');
            }
        });
    }

    bindForm(
        document.getElementById('quoteForm'),
        'https://api.pinnacleconcretepumping.com.au/form-quote.php'
    );
    bindForm(
        document.getElementById('miniForm'),
        'https://api.pinnacleconcretepumping.com.au/form.php'
    );

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
