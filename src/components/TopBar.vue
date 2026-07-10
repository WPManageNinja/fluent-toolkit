<template>
    <header class="ft-topbar">
        <div class="ft-brand">
            <img class="ft-brand-logo" :src="brandLogoUrl" alt="FluentHub logo" />
            <span class="ft-brand-version">v{{ appVars.version }}</span>
        </div>
        <nav class="ft-view-tabs" role="tablist" aria-label="FluentHub sections">
            <button
                v-for="tab in tabs"
                :key="tab.view"
                class="ft-view-tab"
                role="tab"
                :aria-selected="activeView === tab.view ? 'true' : 'false'"
                @click="$emit('navigate', tab.view)"
            >{{ tab.label }}</button>
        </nav>
        <div class="ft-topbar-actions">
            <slot name="actions"></slot>
            <div class="ft-theme-wrap" ref="themeWrap">
                <button class="ft-iconbtn" @click="toggleThemeDropdown" :title="'Theme: ' + currentTheme" aria-label="Change color theme" :aria-expanded="themeDropdownOpen">
                    <!-- light -->
                    <svg v-if="resolvedTheme === 'light'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <!-- dark -->
                    <svg v-else-if="resolvedTheme === 'dark'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <!-- system -->
                    <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                    </svg>
                </button>
                <ul v-if="themeDropdownOpen" class="ft-theme-dropdown" role="menu">
                    <li v-for="opt in themeOptions" :key="opt.value" role="none">
                        <button class="ft-theme-dropdown-item" :class="{ 'is-active': currentTheme === opt.value }" role="menuitem" @click="setTheme(opt.value)">
                            <svg v-if="opt.value === 'light'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                            <svg v-else-if="opt.value === 'dark'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/>
                            </svg>
                            {{ opt.label }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </header>
</template>

<script>
export default {
    name: 'TopBar',
    props: {
        activeView: {
            type: String,
            default: 'dashboard',
        },
    },
    emits: ['navigate'],
    data() {
        return {
            currentTheme: 'system',
            themeDropdownOpen: false,
            _themeChannel: null,
            _mediaQueryHandler: null,
            _clickOutsideHandler: null,
            tabs: [
                { view: 'dashboard', label: 'Dashboard' },
                { view: 'mcp', label: 'MCP' },
            ],
            themeOptions: [
                { value: 'light', label: 'Light' },
                { value: 'dark',  label: 'Dark' },
                { value: 'system', label: 'System' },
            ],
        };
    },
    computed: {
        resolvedTheme() {
            if (this.currentTheme === 'system') {
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            return this.currentTheme;
        },
        brandLogoUrl() {
            const pluginUrl = (this.appVars && this.appVars.plugin_url) ? this.appVars.plugin_url : '';
            const file = this.resolvedTheme === 'dark' ? 'logo-fluent-hub-dark.svg' : 'logo.svg';
            return `${pluginUrl}dist/images/${file}`;
        },
    },
    methods: {
        initDarkMode() {
            const saved = localStorage.getItem('fluent_theme_mode');
            const allowed = ['light', 'dark', 'system'];
            const base = saved ? saved.split(':')[0] : null;
            this.currentTheme = allowed.includes(base) ? base : 'system';
            this.applyDarkTheme(this.resolvedTheme === 'dark');

            if (window.matchMedia) {
                const mq = window.matchMedia('(prefers-color-scheme: dark)');
                this._mediaQueryHandler = () => {
                    if (this.currentTheme === 'system') {
                        this.applyDarkTheme(mq.matches);
                    }
                };
                mq.addEventListener('change', this._mediaQueryHandler);
            }

            if (typeof BroadcastChannel !== 'undefined') {
                this._themeChannel = new BroadcastChannel('fluent_theme_changed:' + window.location.origin);
                this._themeChannel.onmessage = (event) => {
                    if (event.data && event.data.mode) {
                        const mode = event.data.mode;
                        const base = mode.split(':')[0];
                        const allowed = ['light', 'dark', 'system'];
                        this.currentTheme = allowed.includes(base) ? base : 'system';
                        this.applyDarkTheme(this.resolvedTheme === 'dark');
                    }
                };
            }
        },
        toggleThemeDropdown() {
            this.themeDropdownOpen = !this.themeDropdownOpen;
            if (this.themeDropdownOpen) {
                this._clickOutsideHandler = (e) => {
                    if (this.$refs.themeWrap && !this.$refs.themeWrap.contains(e.target)) {
                        this.themeDropdownOpen = false;
                        document.removeEventListener('click', this._clickOutsideHandler);
                        this._clickOutsideHandler = null;
                    }
                };
                setTimeout(() => document.addEventListener('click', this._clickOutsideHandler), 0);
            } else if (this._clickOutsideHandler) {
                document.removeEventListener('click', this._clickOutsideHandler);
                this._clickOutsideHandler = null;
            }
        },
        setTheme(mode) {
            this.currentTheme = mode;
            this.themeDropdownOpen = false;
            if (this._clickOutsideHandler) {
                document.removeEventListener('click', this._clickOutsideHandler);
                this._clickOutsideHandler = null;
            }
            const isDark = this.resolvedTheme === 'dark';
            const storageValue = mode === 'system' ? `system:${isDark ? 'dark' : 'light'}` : mode;
            localStorage.setItem('fluent_theme_mode', storageValue);
            this.applyDarkTheme(isDark);
            if (this._themeChannel) {
                this._themeChannel.postMessage({ mode: storageValue });
            }
        },
        applyDarkTheme(isDark) {
            const DARK_CLASS = 'fluent_theme_dark';
            const elements = [
                document.querySelector('#wpbody-content'),
                document.querySelector('.wp-toolbar'),
                document.body,
                document.querySelector('#wpfooter'),
            ].filter(Boolean);
            elements.forEach(el => {
                el.classList.toggle(DARK_CLASS, isDark);
            });
            document.documentElement.classList.remove(DARK_CLASS);
        },
    },
    mounted() {
        this.initDarkMode();
    },
    beforeUnmount() {
        if (this._themeChannel) {
            this._themeChannel.close();
            this._themeChannel = null;
        }
        if (this._mediaQueryHandler && window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').removeEventListener('change', this._mediaQueryHandler);
        }
        if (this._clickOutsideHandler) {
            document.removeEventListener('click', this._clickOutsideHandler);
            this._clickOutsideHandler = null;
        }
    },
};
</script>
