/**
 * Fluent Unified UI — sidebar interactions.
 *
 * Drives every interactive piece of the unified workspace shell:
 *  • mobile sidebar drawer + WP-menu drawer
 *  • hash-based active state for sidebar + mobile nav
 *  • product section / sub-item expand-collapse via click delegation
 *  • theme (light/dark/system) toggle with localStorage persistence
 *  • workspace switcher dropdown
 *
 * Wires up against DOM nodes rendered by UnifiedUiHandler::pushUnifiedUiToTop().
 * Per-page flags come in via data attributes on `.fluent_uui` so this file
 * stays static and cacheable.
 */
(function () {
    function init() {
        var sidebar = document.querySelector('.fluent_ui_sidebar');
        if (!sidebar) return;

        var uuiRoot = document.querySelector('.fluent_uui');
        var mobileToggle = document.querySelector('.fui-mobile-toggle');
        var backdrop = document.querySelector('.fui-backdrop');
        var hasDarkMode = !!(uuiRoot && uuiRoot.dataset.hasDarkMode === '1');

        function setMobileOpen(open) {
            if (!uuiRoot) return;
            uuiRoot.classList.toggle('is-mobile-open', open);
            if (mobileToggle) mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (backdrop) backdrop.hidden = !open;
            document.body.style.overflow = open ? 'hidden' : '';
        }

        if (mobileToggle && uuiRoot) {
            mobileToggle.addEventListener('click', function () {
                setMobileOpen(!uuiRoot.classList.contains('is-mobile-open'));
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setMobileOpen(false);
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                setMobileOpen(false);
            }
        });

        function normalize(h) {
            if (!h) return '';
            return h.replace(/\/$/, '') || '#';
        }

        function applyActive() {
            var current = normalize(window.location.hash || '#/');
            var links = sidebar.querySelectorAll('[data-fui-hash]');
            var bestEl = null;
            var bestLen = -1;

            links.forEach(function (el) {
                el.classList.remove('active');
                var target = normalize(el.getAttribute('data-fui-hash'));
                if (!target) return;
                if (current === target || (target !== '#' && current.indexOf(target) === 0)) {
                    if (target.length >= bestLen) {
                        bestLen = target.length;
                        bestEl = el;
                    }
                }
            });

            if (bestEl) {
                bestEl.classList.add('active');
                var parentItem = bestEl.closest('.fui-item--has-sub');
                if (parentItem) {
                    var parentLink = parentItem.querySelector(':scope > .fui-apps-menu-item, :scope > .fui-apps-submenu-item');
                    if (parentLink && parentLink !== bestEl) {
                        parentLink.classList.add('is-parent-active');
                    }
                }
            }

            var mobileNav = document.querySelector('.fui-mobile-nav');
            if (mobileNav) {
                var mobileLinks = mobileNav.querySelectorAll('.fui-mobile-nav-item[data-fui-hash]');
                var mobileBestEl = null;
                var mobileBestLen = -1;
                mobileLinks.forEach(function (el) {
                    el.classList.remove('active');
                    var target = normalize(el.getAttribute('data-fui-hash'));
                    if (!target) return;
                    if (current === target || (target !== '#' && current.indexOf(target) === 0)) {
                        if (target.length >= mobileBestLen) {
                            mobileBestLen = target.length;
                            mobileBestEl = el;
                        }
                    }
                });
                if (mobileBestEl) mobileBestEl.classList.add('active');
            }
        }

        applyActive();
        window.addEventListener('hashchange', applyActive);

        // WP admin menu drawer — keeps the native #adminmenumain hidden by default
        // and slides it in as a second column when the user clicks the WP icon.
        var wpMenuTrigger = sidebar.querySelector('.fui-wordpress-menu-link');
        var adminMenuEl = document.getElementById('adminmenumain');

        function setWpMenuOpen(open) {
            document.body.classList.toggle('fui-wp-menu-open', open);
            if (wpMenuTrigger) wpMenuTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            // On mobile the unified sidebar is a full-overlay drawer; close it
            // when the WP menu opens so the WP drawer isn't hidden behind it.
            if (open && uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                setMobileOpen(false);
            }
            document.body.style.overflow = open
                ? 'hidden'
                : (uuiRoot && uuiRoot.classList.contains('is-mobile-open') ? 'hidden' : '');
        }

        if (wpMenuTrigger && adminMenuEl) {
            wpMenuTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                setWpMenuOpen(!document.body.classList.contains('fui-wp-menu-open'));
            });
            document.addEventListener('click', function (e) {
                if (!document.body.classList.contains('fui-wp-menu-open')) return;
                if (adminMenuEl.contains(e.target)) return;
                if (wpMenuTrigger.contains(e.target)) return;
                setWpMenuOpen(false);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.body.classList.contains('fui-wp-menu-open')) {
                    setWpMenuOpen(false);
                }
            });
        }

        // Sidebar click delegation: product header expand, item chevron toggle,
        // item link auto-open sub-menu, mobile nav-link auto-close drawer.
        sidebar.addEventListener('click', function (e) {
            var productHeader = e.target.closest('.fui-product-header');
            if (productHeader) {
                if (productHeader.classList.contains('fui-product-header--flat')) {
                    return;
                }
                e.preventDefault();
                var section = productHeader.closest('.fui-product-section');
                if (section) {
                    var isAlreadyOpen = section.classList.contains('is-open');
                    sidebar.querySelectorAll('.fui-product-section').forEach(function (s) {
                        s.classList.remove('is-open');
                        var h = s.querySelector('.fui-product-header');
                        if (h) h.setAttribute('aria-expanded', 'false');
                    });
                    if (!isAlreadyOpen) {
                        section.classList.add('is-open');
                        productHeader.setAttribute('aria-expanded', 'true');
                    }
                }
                return;
            }

            var itemChevron = e.target.closest('.fui-item-chevron');
            if (itemChevron) {
                e.preventDefault();
                e.stopPropagation();
                var item = itemChevron.closest('.fui-item--has-sub');
                if (item) {
                    var isAlreadyOpen = item.classList.contains('is-open');
                    sidebar.querySelectorAll('.fui-item--has-sub').forEach(function (i) {
                        i.classList.remove('is-open');
                    });
                    if (!isAlreadyOpen) {
                        item.classList.add('is-open');
                    }
                }
                return;
            }

            var itemLink = e.target.closest('.fui-apps-menu-item, .fui-apps-submenu-item');
            if (itemLink) {
                var parent = itemLink.closest('.fui-item--has-sub');
                if (parent && parent.querySelector(':scope > .fui-apps-menu-item, :scope > .fui-apps-submenu-item') === itemLink) {
                    parent.classList.add('is-open');
                }
            }

            if (uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                var navLink = e.target.closest('.fui-apps-menu-item, .fui-apps-submenu-item');
                if (navLink) setMobileOpen(false);
            }
        });

        // Theme toggle dropdown.
        var themeToggle = document.getElementById('fui-theme-toggle');
        var themeDropdown = document.getElementById('fui-theme-dropdown');

        function applyTheme(mode) {
            if (!hasDarkMode) {
                document.body.classList.remove('fluent_theme_dark');
                return;
            }
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var isDark = mode === 'dark' || (mode === 'system' && prefersDark);
            document.body.classList.remove('fluent_theme_dark');
            if (isDark) document.body.classList.add('fluent_theme_dark');
            if (mode === 'system') {
                localStorage.setItem('fluent_theme_mode', 'system:' + (prefersDark ? 'dark' : 'light'));
            } else {
                localStorage.setItem('fluent_theme_mode', mode);
            }
            if (themeToggle) themeToggle.classList.toggle('is-dark', isDark);
            if (themeDropdown) {
                themeDropdown.querySelectorAll('.fui-theme-option').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.dataset.theme === mode);
                });
            }
        }

        var savedMode = localStorage.getItem('fluent_theme_mode') || 'system';
        var baseMode = savedMode.indexOf('system') === 0 ? 'system' : savedMode;
        applyTheme(baseMode);

        if (themeToggle && themeDropdown) {
            themeToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = !themeDropdown.hidden;
                themeDropdown.hidden = isOpen;
                themeToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });

            themeDropdown.querySelectorAll('.fui-theme-option').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyTheme(btn.dataset.theme);
                    themeDropdown.hidden = true;
                    themeToggle.setAttribute('aria-expanded', 'false');
                });
            });

            document.addEventListener('click', function (e) {
                if (!themeToggle.contains(e.target) && !themeDropdown.contains(e.target)) {
                    themeDropdown.hidden = true;
                    themeToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            var stored = localStorage.getItem('fluent_theme_mode') || 'system';
            var base = stored.indexOf('system') === 0 ? 'system' : stored;
            if (base === 'system') applyTheme('system');
        });

        // Workspace switcher dropdown.
        var wsWrap = sidebar.querySelector('.fui-workspace-wrap');
        if (wsWrap) {
            var wsBtn = wsWrap.querySelector('.fui-workspace');
            var wsMenu = wsWrap.querySelector('.fui-workspace-menu');

            function closeWs() {
                wsWrap.classList.remove('is-open');
                wsBtn.setAttribute('aria-expanded', 'false');
                wsMenu.hidden = true;
            }

            function openWs() {
                wsWrap.classList.add('is-open');
                wsBtn.setAttribute('aria-expanded', 'true');
                wsMenu.hidden = false;
            }

            wsBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (wsWrap.classList.contains('is-open')) closeWs();
                else openWs();
            });

            document.addEventListener('click', function (e) {
                if (!wsWrap.contains(e.target)) closeWs();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && wsWrap.classList.contains('is-open')) {
                    closeWs();
                    wsBtn.focus();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
