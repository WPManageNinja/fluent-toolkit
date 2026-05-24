<template>
    <div class="ft-app">

        <!-- Topbar -->
        <header class="ft-topbar">
            <div class="ft-brand">
                <img class="ft-brand-logo" :src="brandLogoUrl" alt="FluentHub logo" />
                <span class="ft-brand-version">v{{ appVars.version }}</span>
            </div>
            <div class="ft-topbar-actions">
                <el-button
                    v-if="appVars.require_update"
                    :loading="installing"
                    :disabled="installing"
                    @click="updateToolkit()"
                    type="danger"
                    size="small"
                >Update FluentHub</el-button>
                <button class="ft-iconbtn" @click="getBetaPlugins(true)" title="Refresh registry" aria-label="Refresh">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
                </button>
            </div>
        </header>

        <section class="ft-unified-panel" v-loading="unifiedUiSaving" element-loading-text="Saving Unified UI status...">
            <div class="ft-unified-copy">
                <span class="ft-unified-eyebrow">Unified Fluent workspace</span>
                <h1>Fluent Unified UI</h1>
                <p>Turn on a unified Fluent workspace that brings your Fluent tools into one cleaner, focused and easier-to-manage experience.</p>
            </div>
            <div class="ft-unified-control">
                <div>
                    <strong>Unified UI</strong>
                    <span>{{ unifiedUiStatusLabel }}</span>
                </div>
                <label class="ft-switch ft-switch-large" title="Enable Fluent Unified UI">
                    <input
                        type="checkbox"
                        :checked="unifiedUiEnabled"
                        :disabled="unifiedUiSaving"
                        @change="toggleUnifiedUi($event.target.checked)"
                    />
                    <span></span>
                </label>
            </div>
        </section>

        <section class="ft-hero ft-mcp-hero ft-mcp-dashboard-banner">
            <div class="ft-hero-inner">
                <div class="ft-mcp-banner-copy">
                    <div class="ft-hero-eyebrow">MCP for agents</div>
                    <h1>Connect AI agents to Fluent plugins.</h1>
                    <p>Enable MCP servers, review the available Fluent plugin surface, and manage connection details for Codex, Claude, Cursor, and other HTTP MCP clients.</p>
                </div>
                <div class="ft-mcp-banner-actions">
                    <button class="ft-btn ft-btn-primary" @click="$emit('navigate', 'mcp')">Manage MCP</button>
                </div>
            </div>
        </section>

        <div class="ft-section-head ft-beta-head">
            <div>
                <span class="ft-section-kicker">Fluent ecosystem</span>
                <h2>Plugins &amp; Add-ons</h2>
            </div>
            <div class="ft-beta-stats">
                <span>{{ betaPlugins.length }} available</span>
                <span>{{ installedCount }} installed</span>
                <span :class="{ 'ft-warn-text': updatesCount > 0 }">{{ updatesCount }} updates</span>
            </div>
        </div>

        <!-- List -->
        <div class="ft-list">
            <el-skeleton v-if="loading" :animated="true" :rows="3" style="padding: 24px;" />
            <div v-else v-loading="installing" element-loading-text="Installing… Please wait.">
                <div class="ft-list-head">
                    <div>Plugin</div>
                    <div>Status</div>
                    <div style="text-align: right;">Action</div>
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
                                <span v-if="plugin.has_beta_update || plugin.beta_version" class="ft-tag ft-tag-rc">RC</span>
                                <span v-if="plugin.is_beta" class="ft-tag ft-tag-beta">Beta</span>
                            </div>
                            <div class="ft-plugin-desc">{{ plugin.sub_title }}</div>
                            <div class="ft-meta-row">
                                <template v-if="plugin.installed_version">
                                    <span>Installed</span>
                                    <span class="ft-dot-sep"></span>
                                    <span class="ft-version-installed ft-mono">{{ plugin.installed_version }}</span>
                                    <template v-if="plugin.is_pro">
                                        <span class="ft-dot-sep"></span>
                                        <span>License: {{ plugin.license_key ? 'Active' : 'n/a' }}</span>
                                    </template>
                                </template>
                                <span v-else>Not installed</span>
                                <template v-if="plugin.changelog_url">
                                    <span class="ft-dot-sep"></span>
                                    <a :href="plugin.changelog_url" target="_blank" rel="noopener" class="ft-meta-link">Changelog</a>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <span v-if="!plugin.installed_version" class="ft-status ft-status-notinstalled">Not installed</span>
                        <span v-else-if="plugin.is_active === false" class="ft-status ft-status-notinstalled">
                            <span class="ft-dot"></span>Inactive
                        </span>
                        <span v-else-if="plugin.has_update || plugin.has_beta_update" class="ft-status ft-status-update">
                            <span class="ft-dot ft-dot-warn"></span>Update available
                        </span>
                        <span v-else class="ft-status ft-status-ok">
                            <span class="ft-dot ft-dot-ok"></span>Up to date
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="ft-actions">
                        <button v-if="!plugin.installed_version && caps.install_plugins" @click="installPlugin(plugin)" class="ft-btn ft-btn-primary">Install</button>
                        <button v-else-if="plugin.is_active === false && caps.activate_plugins" @click="activatePlugin(plugin)" class="ft-btn ft-btn-primary">Activate</button>
                        <button v-else-if="plugin.has_beta_update && caps.update_plugins" @click="installPlugin(plugin, 'yes')" class="ft-btn ft-btn-accent">Update to {{ plugin.beta_version }}</button>
                        <button v-else-if="plugin.has_update && caps.update_plugins" @click="installPlugin(plugin)" class="ft-btn ft-btn-accent">Update to {{ plugin.stable_version }}</button>
                        <span v-else>
                            --
                        </span>
                    </div>
                </div>

                <div v-if="filteredPlugins.length === 0" class="ft-empty">
                    No plugins available right now.
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="ft-footer">
            <div>Registry synced · <span @click="getBetaPlugins(true)" style="cursor: pointer; color: var(--accent);">Refresh now</span></div>
            <!-- <div>Toggle <a href="#">beta channel</a> in settings to opt in to release candidates</div> -->
        </div>

    </div>
</template>

<script type="text/babel">
export default {
    name: 'Dashboard',
    emits: ['navigate'],
    data() {
        return {
            betaPlugins: [],
            installing: false,
            loading: false,
            unifiedUiSaving: false,
            unifiedUiEnabled: (window.fluentToolkitVars.settings || {}).uinified_ui === 'yes',
            activeChannel: 'all',
            searchQuery: '',
            caps: Object.assign(
                { install_plugins: false, update_plugins: false, activate_plugins: false },
                window.fluentToolkitVars.caps || {}
            ),
        };
    },
    computed: {
        brandLogoUrl() {
            const pluginUrl = (this.appVars && this.appVars.plugin_url) ? this.appVars.plugin_url : '';
            return `${pluginUrl}dist/images/logo.svg`;
        },
        installedCount() {
            return this.betaPlugins.filter(p => p.installed_version).length;
        },
        updatesCount() {
            return this.betaPlugins.filter(p => p.has_update || p.has_beta_update).length;
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
            return this.unifiedUiEnabled ? 'Enabled' : 'Disabled';
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
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        toggleUnifiedUi(enabled) {
            this.unifiedUiSaving = true;
            this.$post('fluent_toolkit_unified_ui_toggle', {
                enabled: enabled ? 'yes' : 'no',
            })
                .then(() => {
                    window.location.reload();
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
                licenseKey = prompt('Please enter your license key for ' + plugin.name + ' to install.');
                if (!licenseKey) {
                    this.$notify.error('Please enter a valid license key.');
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
