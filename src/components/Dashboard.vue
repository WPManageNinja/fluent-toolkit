<template>
    <TopBar :active-view="activeView" @navigate="$emit('navigate', $event)">
        <template #actions>
            <button class="ft-iconbtn" @click="getBetaPlugins(true)" :title="$t('Refresh plugin list')" :aria-label="$t('Refresh plugin list')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
            </button>
        </template>
    </TopBar>

    <div class="ft-app">

        <section class="ft-update-banner" v-if="requireUpdate">
            <span class="ft-setting-icon ft-setting-icon--amber" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
                    <path d="M21 3v6h-6"/>
                </svg>
            </span>
            <div class="ft-update-banner-text">
                <strong>{{ updateBannerTitle }}</strong>
                <!-- translators: %s is the installed FluentHub version, for example v2.1.3 -->
                <span>{{ $t("You're running %s. Update to get the latest features and fixes.", 'v' + appVars.version) }}</span>
            </div>
            <button class="ft-btn ft-btn-accent" :disabled="installing" @click="updateToolkit()">
                {{ installing ? $t('Updating…') : $t('Update now') }}
            </button>
        </section>

        <section class="ft-card ft-workspace-card" v-loading="unifiedUiSaving" :element-loading-text="$t('Saving…')">
            <header class="ft-card-head">
                <span class="ft-card-eyebrow">{{ $t('Unified Fluent Workspace') }}</span>
                <h2 class="ft-card-title">{{ $t('Fluent Unified UI') }}</h2>
                <p class="ft-card-sub">{{ $t('Open all your Fluent plugins in one workspace, with a shared sidebar to move between them.') }}</p>
            </header>

            <ul class="ft-setting-list">
                <li class="ft-setting-item">
                    <span class="ft-setting-icon ft-setting-icon--indigo" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                            <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                            <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                            <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                        </svg>
                    </span>
                    <div class="ft-setting-text">
                        <strong>{{ $t('Enable Unified UI') }}</strong>
                        <span>{{ unifiedUiStatusLabel }} · {{ $t('Show Fluent plugin screens inside the unified workspace.') }}</span>
                    </div>
                    <label class="ft-switch" :title="$t('Enable Fluent Unified UI')">
                        <input
                            type="checkbox"
                            :checked="unifiedUiEnabled"
                            :disabled="unifiedUiSaving"
                            @change="toggleUnifiedUi($event.target.checked)"
                        />
                        <span></span>
                    </label>
                </li>

                <li class="ft-setting-item" :class="{ 'is-disabled': !unifiedUiEnabled }">
                    <span class="ft-setting-icon ft-setting-icon--violet" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <line x1="9" y1="4" x2="9" y2="20"/>
                            <line x1="5.5" y1="8.5" x2="6.5" y2="8.5"/>
                            <line x1="5.5" y1="12" x2="6.5" y2="12"/>
                            <line x1="5.5" y1="15.5" x2="6.5" y2="15.5"/>
                        </svg>
                    </span>
                    <div class="ft-setting-text">
                        <strong>{{ $t('Merge admin menus') }}</strong>
                        <span>{{ $t("Hide each Fluent plugin's top-level WordPress menu so everything opens from the unified workspace.") }}</span>
                    </div>
                    <label class="ft-switch" :title="unifiedUiEnabled ? $t('Merge top-level Fluent menus') : $t('Enable Unified UI first')">
                        <input
                            type="checkbox"
                            :checked="mergeAdminMenus"
                            :disabled="unifiedUiSaving || !unifiedUiEnabled"
                            @change="toggleMergeAdminMenus($event.target.checked)"
                        />
                        <span></span>
                    </label>
                </li>

                <li class="ft-setting-item" :class="{ 'is-disabled': !unifiedUiEnabled }">
                    <span class="ft-setting-icon ft-setting-icon--amber" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <line x1="3" y1="9" x2="21" y2="9"/>
                            <line x1="6.5" y1="6.5" x2="7.5" y2="6.5"/>
                            <line x1="9.5" y1="6.5" x2="10.5" y2="6.5"/>
                        </svg>
                    </span>
                    <div class="ft-setting-text">
                        <strong>{{ $t('Hide app headers') }}</strong>
                        <span>{{ $t('Hide the built-in headers of FluentCRM, FluentCart, Fluent Support and other Fluent apps for a cleaner workspace.') }}</span>
                    </div>
                    <label class="ft-switch" :title="unifiedUiEnabled ? $t('Hide built-in app headers') : $t('Enable Unified UI first')">
                        <input
                            type="checkbox"
                            :checked="hideAppHeaders"
                            :disabled="unifiedUiSaving || !unifiedUiEnabled"
                            @change="toggleHideAppHeaders($event.target.checked)"
                        />
                        <span></span>
                    </label>
                </li>
            </ul>
        </section>

        <section class="ft-hero ft-mcp-hero ft-mcp-dashboard-banner">
            <div class="ft-hero-inner">
                <div class="ft-mcp-banner-copy">
                    <div class="ft-hero-eyebrow">{{ $t('MCP for agents') }}</div>
                    <h1>{{ $t('Connect AI agents to Fluent plugins') }}</h1>
                    <p>{{ $t('MCP_Banner_Desc') }}</p>
                </div>
                <div class="ft-mcp-banner-actions">
                    <button class="ft-btn ft-btn-primary" @click="$emit('navigate', 'mcp')">{{ $t('Manage MCP') }}</button>
                </div>
            </div>
        </section>

        <div class="ft-section-head ft-beta-head">
            <div>
                <span class="ft-section-kicker">{{ $t('Fluent ecosystem') }}</span>
                <h2>{{ $t('Plugins & Add-ons') }}</h2>
            </div>
            <div class="ft-beta-stats">
                <button class="ft-iconbtn" @click="getBetaPlugins(true)" :title="$t('Refresh plugin list')" :aria-label="$t('Refresh plugin list')">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
                </button>
                <!-- translators: %s is the number of plugins in the list -->
                <span>{{ $t('Available: %s', betaPlugins.length) }}</span>
                <!-- translators: %s is the number of plugins installed on this site -->
                <span>{{ $t('Installed: %s', installedCount) }}</span>
                <!-- translators: %s is the number of plugins with an update available -->
                <span :class="{ 'ft-warn-text': updatesCount > 0 }">{{ $t('Updates: %s', updatesCount) }}</span>
                <!-- translators: %s is the number of active plugins that will be updated -->
                <button v-if="caps.update_plugins && bulkUpdatePlugins.length > 1" class="ft-btn ft-btn-accent" :disabled="installing" @click="updateAllPlugins()">{{ $t('Update all (%s)', bulkUpdatePlugins.length) }}</button>
            </div>
        </div>

        <!-- List -->
        <div class="ft-list">
            <div v-if="loading" class="ft-plugin-skeleton">
                <div class="ft-list-head">
                    <div>{{ $t('Plugin') }}</div>
                    <div>{{ $t('Status') }}</div>
                    <div style="text-align:right">{{ $t('Action') }}</div>
                </div>
                <div class="ft-row" v-for="i in 6" :key="i">
                    <div class="ft-plugin">
                        <div class="ft-skel ft-skel-icon"></div>
                        <div class="ft-plugin-info">
                            <div class="ft-skel ft-skel-name"></div>
                            <div class="ft-skel ft-skel-desc"></div>
                            <div class="ft-skel ft-skel-meta"></div>
                        </div>
                    </div>
                    <div class="ft-skel ft-skel-badge"></div>
                    <div style="display:flex;justify-content:flex-end">
                        <div class="ft-skel ft-skel-btn"></div>
                    </div>
                </div>
            </div>
            <div v-else v-loading="installing" :element-loading-text="installingText">
                <div class="ft-list-head">
                    <div>{{ $t('Plugin') }}</div>
                    <div>{{ $t('Status') }}</div>
                    <div style="text-align: right;">{{ $t('Action') }}</div>
                </div>
                <div class="ft-row" v-for="(plugin, index) in filteredPlugins" :key="plugin.slug">
                    <!-- Plugin info -->
                    <div class="ft-plugin">
                        <div class="ft-plugin-icon" :class="plugin.logo ? '' : `ft-ic-${index % 6}`">
                            <img style="max-width: 44px;" :src="plugin.logo" v-if="plugin.logo" />
                            <span v-else>{{ pluginInitials(plugin.name) }}</span>
                        </div>
                        <div class="ft-plugin-info">
                            <div class="ft-plugin-name">
                                {{ plugin.name }}
                                <span v-if="plugin.has_beta_update || plugin.beta_version" class="ft-tag ft-tag-rc">{{ $_x('RC', 'Release candidate badge') }}</span>
                                <span v-if="plugin.is_beta" class="ft-tag ft-tag-beta">{{ $t('Beta') }}</span>
                            </div>
                            <div class="ft-plugin-desc">{{ plugin.sub_title }}</div>
                            <div class="ft-meta-row">
                                <template v-if="plugin.installed_version">
                                    <span>{{ $t('Installed') }}</span>
                                    <span class="ft-dot-sep"></span>
                                    <span class="ft-version-installed ft-mono">{{ plugin.installed_version }}</span>
                                    <template v-if="plugin.is_pro">
                                        <span class="ft-dot-sep"></span>
                                        <span>{{ plugin.license_key ? $t('License key added') : $t('No license key') }}</span>
                                    </template>
                                </template>
                                <span v-else>{{ $t('Not installed') }}</span>
                                <template v-if="plugin.changelog_url">
                                    <span class="ft-dot-sep"></span>
                                    <a :href="plugin.changelog_url" target="_blank" rel="noopener" class="ft-meta-link">{{ $t('Changelog') }}</a>
                                </template>
                                <template v-if="plugin.dashboard_url">
                                    <span class="ft-dot-sep"></span>
                                    <a :href="plugin.dashboard_url" class="ft-meta-link">{{ $t('Settings') }}</a>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <span v-if="!plugin.installed_version" class="ft-status ft-status-notinstalled">{{ $t('Not installed') }}</span>
                        <span v-else-if="plugin.is_active === false" class="ft-status ft-status-notinstalled">
                            <span class="ft-dot"></span>{{ $t('Inactive') }}
                        </span>
                        <span v-else-if="plugin.has_update || plugin.has_beta_update" class="ft-status ft-status-update">
                            <span class="ft-dot ft-dot-warn"></span>{{ $t('Update available') }}
                        </span>
                        <span v-else class="ft-status ft-status-ok">
                            <span class="ft-dot ft-dot-ok"></span>{{ $t('Up to date') }}
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="ft-actions">
                        <button v-if="!plugin.installed_version && caps.install_plugins" @click="installPlugin(plugin)" class="ft-btn ft-btn-primary">{{ $t('Install') }}</button>
                        <button v-else-if="plugin.is_active === false && caps.activate_plugins" @click="activatePlugin(plugin)" class="ft-btn ft-btn-primary">{{ $t('Activate') }}</button>
                        <!-- translators: %s is the version number the plugin will be updated to -->
                        <button v-else-if="plugin.has_beta_update && caps.update_plugins" @click="installPlugin(plugin, 'yes')" class="ft-btn ft-btn-accent">{{ $t('Update to %s', plugin.beta_version) }}</button>
                        <!-- translators: %s is the version number the plugin will be updated to -->
                        <button v-else-if="plugin.has_update && caps.update_plugins" @click="installPlugin(plugin)" class="ft-btn ft-btn-accent">{{ $t('Update to %s', plugin.stable_version) }}</button>
                        <span v-else>
                            --
                        </span>
                    </div>
                </div>

                <div v-if="filteredPlugins.length === 0" class="ft-empty">
                    {{ $t('No plugins available right now.') }}
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="ft-footer">
            <div>{{ $t('Registry synced') }} · <span @click="getBetaPlugins(true)" style="cursor: pointer; color: var(--accent);">{{ $t('Refresh now') }}</span></div>
            <!-- <div>Toggle <a href="#">beta channel</a> in settings to opt in to release candidates</div> -->
        </div>

    </div>
</template>

<script type="text/babel">
import { h } from 'vue';
import TopBar from './TopBar.vue';

export default {
    name: 'Dashboard',
    components: {
        TopBar,
    },
    props: {
        activeView: {
            type: String,
            default: 'dashboard',
        },
    },
    emits: ['navigate'],
    data() {
        return {
            betaPlugins: [],
            requireUpdate: !!window.fluentToolkitVars.require_update,
            sourceVersion: window.fluentToolkitVars.source_version || '',
            installing: false,
            bulkProgress: null,
            loading: false,
            unifiedUiSaving: false,
            unifiedUiEnabled: (window.fluentToolkitVars.settings || {}).uinified_ui === 'yes',
            mergeAdminMenus: (window.fluentToolkitVars.settings || {}).merge_admin_menus === 'yes',
            hideAppHeaders: (window.fluentToolkitVars.settings || {}).hide_app_headers === 'yes',
            activeChannel: 'all',
            searchQuery: '',
            caps: Object.assign(
                { install_plugins: false, update_plugins: false, activate_plugins: false },
                window.fluentToolkitVars.caps || {}
            ),
        };
    },
    computed: {
        installedCount() {
            return this.betaPlugins.filter(p => p.installed_version).length;
        },
        updatesCount() {
            return this.betaPlugins.filter(p => p.has_update || p.has_beta_update).length;
        },
        // Active plugins with a stable update only: the installer activates
        // whatever it installs, so inactive plugins stay on their row button.
        // Free plugins go before Pro add-ons so a Pro update never lands on an
        // older free core, FluentHub itself last. Pro plugins without a saved
        // license key are left for the row button too.
        bulkUpdatePlugins() {
            const rank = p => (p.slug === 'fluent-toolkit' ? 2 : (p.is_pro ? 1 : 0));
            return this.betaPlugins
                .filter(p => p.installed_version && p.is_active && p.has_update)
                .filter(p => p.require_license != 'yes' || p.license_key)
                .sort((a, b) => rank(a) - rank(b));
        },
        installingText() {
            if (!this.bulkProgress) {
                return this.$t('Installing… Please wait.');
            }

            // translators: %1$s is the plugin being updated, %2$s its position in the queue, %3$s the number of plugins being updated
            return this.$t('Updating %1$s (%2$s of %3$s)…', this.bulkProgress.name, this.bulkProgress.current, this.bulkProgress.total);
        },
        betaCount() {
            return this.betaPlugins.filter(p => p.has_beta_update || p.beta_version || p.is_beta).length;
        },
        filteredPlugins() {
            let plugins = this.betaPlugins;

            if (this.activeChannel === 'beta') {
                plugins = plugins.filter(p => p.has_beta_update || p.beta_version || p.is_beta);
            } else if (this.activeChannel === 'installed') {
                plugins = plugins.filter(p => p.installed_version);
            } else if (this.activeChannel === 'updates') {
                plugins = plugins.filter(p => p.has_update || p.has_beta_update);
            }

            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                plugins = plugins.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.sub_title && p.sub_title.toLowerCase().includes(q))
                );
            }

            return plugins;
        },
        unifiedUiStatusLabel() {
            return this.unifiedUiEnabled ? this.$t('Enabled') : this.$t('Disabled');
        },
        updateBannerTitle() {
            if (!this.sourceVersion) {
                return this.$t('A FluentHub update is available');
            }

            // translators: %s is the new FluentHub version, for example v2.1.4
            return this.$t('FluentHub %s is available', 'v' + this.sourceVersion);
        },
    },
    methods: {
        pluginInitials(name) {
            return (name || '')
                .split(/\s+/)
                .map(w => w[0] || '')
                .join('')
                .slice(0, 2)
                .toUpperCase();
        },
        getBetaPlugins(refresh = false) {
            this.loading = true;
            this.$get('fluent_beta_get_beta_versions', refresh ? { refresh: 1 } : {})
                .then(response => {
                    this.betaPlugins = response.beta_versions;
                    if (response.toolkit) {
                        this.requireUpdate = !!response.toolkit.require_update;
                        this.sourceVersion = response.toolkit.source_version || this.sourceVersion;
                    }
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        toggleUnifiedUi(enabled) {
            this.saveDashboardSettings({ uinified_ui: enabled ? 'yes' : 'no' }, true);
        },
        toggleMergeAdminMenus(enabled) {
            this.saveDashboardSettings({ merge_admin_menus: enabled ? 'yes' : 'no' }, false);
        },
        toggleHideAppHeaders(enabled) {
            this.saveDashboardSettings({ hide_app_headers: enabled ? 'yes' : 'no' }, false);
        },
        saveDashboardSettings(settings, reload) {
            this.unifiedUiSaving = true;
            this.$post('fluent_toolkit_save_dashboard_settings', { settings })
                .then(response => {
                    if (response && response.settings) {
                        this.unifiedUiEnabled = response.settings.uinified_ui === 'yes';
                        this.mergeAdminMenus = response.settings.merge_admin_menus === 'yes';
                        this.hideAppHeaders = response.settings.hide_app_headers === 'yes';
                    }
                    if (reload) {
                        window.location.reload();
                        return;
                    }
                    if (response && response.message) {
                        this.$notify.success(response.message);
                    }
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.unifiedUiSaving = false;
                });
        },
        installPlugin(plugin, beta = '') {
            let licenseKey = plugin.license_key;
            if (plugin.require_license == 'yes' && !plugin.license_key) {
                // translators: %s is the plugin name
                licenseKey = prompt(this.$t('Enter your license key to install %s.', plugin.name));
                if (!licenseKey) {
                    this.$notify.error(this.$t('Please enter a valid license key.'));
                    return;
                }
            }

            this.installing = true;
            this.$post('fluent-beta-install', {
                slug: plugin.slug,
                license_key: licenseKey,
                beta: beta
            })
                .then(response => {
                    this.$notify.success(response.message);
                    if (response.message) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.installing = false;
                    this.getBetaPlugins();
                });
        },
        async updateAllPlugins() {
            const plugins = this.bulkUpdatePlugins.slice();
            // translators: %s is the number of active plugins that will be updated
            if (!plugins.length || !window.confirm(this.$t('Update %s active plugins to their latest stable versions?', plugins.length))) {
                return;
            }

            const failed = [];
            let updatedToolkit = false;
            this.installing = true;

            for (let i = 0; i < plugins.length; i++) {
                const plugin = plugins[i];
                this.bulkProgress = { name: plugin.name, current: i + 1, total: plugins.length };
                try {
                    await this.$post('fluent-beta-install', {
                        slug: plugin.slug,
                        license_key: plugin.license_key
                    });
                    if (plugin.slug === 'fluent-toolkit') {
                        updatedToolkit = true;
                    }
                } catch (error) {
                    failed.push(plugin.name + ': ' + ((error && error.message) || this.$t('Error')));
                }
            }

            this.bulkProgress = null;
            this.installing = false;

            if (failed.length) {
                this.$notify({
                    type: 'error',
                    // translators: %1$s is the number of plugins updated, %2$s the number of plugins attempted
                    title: this.$t('Updated %1$s of %2$s plugins', plugins.length - failed.length, plugins.length),
                    message: h('div', failed.map(line => h('div', line))),
                    duration: 0
                });
            } else {
                this.$notify.success(this.$t('All plugins are up to date.'));
            }

            // A new FluentHub build ships new dashboard assets, so reload to pick them up.
            if (updatedToolkit) {
                window.location.reload();
                return;
            }

            this.getBetaPlugins();
        },
        updateToolkit() {
            this.installing = true;
            this.installPlugin({slug: 'fluent-toolkit'});
        },
        activatePlugin(plugin) {
            this.installing = true;
            this.$post('fluent_toolkit_activate_plugin', { slug: plugin.slug })
                .then(response => {
                    this.$notify.success(response.message);
                    this.getBetaPlugins();
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.installing = false;
                });
        },
        hideLicenseKey(licenseKey) {
            if (!licenseKey) return '';
            return licenseKey.replace(/.(?=.{6})/g, '*').slice(-10);
        },
    },
    mounted() {
        this.getBetaPlugins();
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    }
};
</script>
