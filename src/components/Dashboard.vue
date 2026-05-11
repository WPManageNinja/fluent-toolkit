<template>
    <div class="ft-app">

        <!-- Topbar -->
        <header class="ft-topbar">
            <div class="ft-brand">
                <div class="ft-brand-mark"></div>
                <div>
                    <div class="ft-brand-name">
                        Fluent Toolkit
                        <span class="ft-brand-version">v{{ appVars.version }}</span>
                    </div>
                </div>
            </div>
            <div class="ft-topbar-actions">
                <el-button
                    v-if="appVars.require_update"
                    :loading="installing"
                    :disabled="installing"
                    @click="updateToolkit()"
                    type="danger"
                    size="small"
                >Update Toolkit</el-button>
                <button class="ft-iconbtn" @click="getBetaPlugins()" title="Refresh registry" aria-label="Refresh">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
                </button>
                <button class="ft-iconbtn" title="Settings" aria-label="Settings">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </button>
            </div>
        </header>

        <!-- Hero -->
        <section class="ft-hero">
            <div class="ft-hero-inner">
                <div>
                    <div class="ft-hero-eyebrow">Beta Program</div>
                    <h1>Beta builds. Add-ons. One place.</h1>
                    <p>Get ahead of what's shipping. Install release candidates, pick up companion add-ons, and track every beta across the Fluent ecosystem — all from here.</p>
                </div>
                <div class="ft-hero-stats">
                    <div>
                        <div class="ft-stat-num">{{ betaPlugins.length }}</div>
                        <div class="ft-stat-label">Available</div>
                    </div>
                    <div>
                        <div class="ft-stat-num">{{ installedCount }}</div>
                        <div class="ft-stat-label">Installed</div>
                    </div>
                    <div>
                        <div class="ft-stat-num" :class="{ 'ft-warn-num': updatesCount > 0 }">{{ updatesCount }}</div>
                        <div class="ft-stat-label">Updates</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- MCP -->
        <section class="ft-mcp-panel" v-loading="mcpLoading">
            <div class="ft-mcp-header">
                <div>
                    <div class="ft-hero-eyebrow">MCP Connection</div>
                    <h2>FluentCRM MCP OAuth</h2>
                    <p>OAuth protected access for the FluentCRM MCP endpoint.</p>
                </div>
                <div class="ft-mcp-actions">
                    <a
                        v-if="mcpStatus && mcpStatus.oauth_settings_url && !mcpStatus.standalone_oauth_bridge_active"
                        :href="mcpStatus.oauth_settings_url"
                        class="ft-btn ft-btn-ghost"
                    >Manage</a>
                    <el-switch
                        v-if="mcpStatus"
                        :model-value="mcpStatus.oauth_enabled"
                        :disabled="mcpStatus.standalone_oauth_bridge_active"
                        :loading="mcpSaving"
                        @change="toggleMcpOAuth"
                    />
                </div>
            </div>

            <div v-if="mcpStatus && mcpStatus.standalone_oauth_bridge_active" class="ft-mcp-warning">
                Deactivate and remove the standalone FluentCRM MCP OAuth Bridge plugin before enabling Toolkit MCP OAuth.
            </div>

            <div v-if="mcpStatus" class="ft-mcp-grid">
                <div>
                    <span>Adapter</span>
                    <strong>{{ mcpStatus.adapter_available ? adapterProviderLabel : 'Unavailable' }}</strong>
                </div>
                <div>
                    <span>Abilities API</span>
                    <strong>{{ mcpStatus.abilities_available ? 'Loaded' : 'Missing' }}</strong>
                </div>
                <div>
                    <span>MCP URL</span>
                    <code>{{ mcpStatus.mcp_url }}</code>
                </div>
                <div>
                    <span>Dynamic registration</span>
                    <code>{{ mcpStatus.registration_endpoint }}</code>
                </div>
            </div>
        </section>

        <!-- Toolbar -->
        <div class="ft-toolbar">
            <div class="ft-channels" role="tablist">
                <button class="ft-channel" :aria-selected="activeChannel === 'all'" @click="activeChannel = 'all'">
                    All <span class="ft-channel-count">{{ betaPlugins.length }}</span>
                </button>
                <button class="ft-channel" :aria-selected="activeChannel === 'beta'" @click="activeChannel = 'beta'">
                    Beta <span class="ft-channel-count">{{ betaCount }}</span>
                </button>
                <button class="ft-channel" :aria-selected="activeChannel === 'installed'" @click="activeChannel = 'installed'">
                    Installed <span class="ft-channel-count">{{ installedCount }}</span>
                </button>
                <button class="ft-channel" :aria-selected="activeChannel === 'updates'" @click="activeChannel = 'updates'">
                    Updates <span class="ft-channel-count">{{ updatesCount }}</span>
                </button>
            </div>
            <div class="ft-toolbar-right">
                <div class="ft-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-soft); flex-shrink: 0;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" placeholder="Search plugins…" v-model="searchQuery" />
                    <!-- <span class="ft-kbd">⌘K</span> -->
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="ft-list">
            <el-skeleton v-if="loading" :animated="true" :rows="8" style="padding: 24px;" />
            <div v-else v-loading="installing" element-loading-text="Installing… Please wait.">
                <div class="ft-list-head">
                    <div>Plugin</div>
                    <div>Latest</div>
                    <div>Status</div>
                    <div style="text-align: right;">Action</div>
                </div>
                <div class="ft-row" v-for="(plugin, index) in filteredPlugins" :key="plugin.slug">
                    <!-- Plugin info -->
                    <div class="ft-plugin">
                        <div class="ft-plugin-icon" :class="`ft-ic-${index % 6}`">
                            {{ pluginInitials(plugin.name) }}
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
                            </div>
                        </div>
                    </div>

                    <!-- Latest version -->
                    <div class="ft-version ft-mono">
                        {{ plugin.has_beta_update ? plugin.beta_version : plugin.stable_version }}
                    </div>

                    <!-- Status -->
                    <div>
                        <span v-if="!plugin.installed_version" class="ft-status ft-status-notinstalled">Not installed</span>
                        <span v-else-if="plugin.has_update || plugin.has_beta_update" class="ft-status ft-status-update">
                            <span class="ft-dot ft-dot-warn"></span>Update available
                        </span>
                        <span v-else class="ft-status ft-status-ok">
                            <span class="ft-dot ft-dot-ok"></span>Up to date
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="ft-actions">
                        <a v-if="plugin.changelog_url" :href="plugin.changelog_url" target="_blank" rel="noopener" class="ft-btn ft-btn-ghost">Changelog</a>
                        <button v-if="!plugin.installed_version" @click="installPlugin(plugin)" class="ft-btn ft-btn-primary">Install</button>
                        <button v-else-if="plugin.has_beta_update" @click="installPlugin(plugin, 'yes')" class="ft-btn ft-btn-accent">Update</button>
                        <button v-else-if="plugin.has_update" @click="installPlugin(plugin)" class="ft-btn ft-btn-accent">Update</button>
                        <button v-else class="ft-btn ft-btn-ghost ft-btn-icon-only" title="More options">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                        </button>
                    </div>
                </div>

                <div v-if="filteredPlugins.length === 0" class="ft-empty">
                    No plugins found.
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="ft-footer">
            <div>Registry synced · <span @click="getBetaPlugins()" style="cursor: pointer; color: var(--accent);">Refresh now</span></div>
            <!-- <div>Toggle <a href="#">beta channel</a> in settings to opt in to release candidates</div> -->
        </div>

    </div>
</template>

<script type="text/babel">
import {EditPen} from "@element-plus/icons-vue";

export default {
    name: 'Dashboard',
    components: {EditPen},
    data() {
        return {
            betaPlugins: [],
            installing: false,
            loading: false,
            mcpLoading: false,
            mcpSaving: false,
            mcpStatus: null,
            activeChannel: 'all',
            searchQuery: '',
        };
    },
    computed: {
        installedCount() {
            return this.betaPlugins.filter(p => p.installed_version).length;
        },
        updatesCount() {
            return this.betaPlugins.filter(p => p.has_update || p.has_beta_update).length;
        },
        betaCount() {
            return this.betaPlugins.filter(p => p.has_beta_update || p.beta_version || p.is_beta).length;
        },
        adapterProviderLabel() {
            if (!this.mcpStatus) {
                return '';
            }

            if (this.mcpStatus.adapter_provider === 'plugin') {
                return 'External plugin';
            }

            if (this.mcpStatus.adapter_provider === 'toolkit') {
                return 'Toolkit fallback';
            }

            return 'Available';
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
        getBetaPlugins() {
            this.loading = true;
            this.$get('fluent_beta_get_beta_versions')
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
        getMcpStatus() {
            this.mcpLoading = true;
            this.$get('fluent_toolkit_mcp_status')
                .then(response => {
                    this.mcpStatus = response;
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.mcpLoading = false;
                });
        },
        toggleMcpOAuth(enabled) {
            this.mcpSaving = true;
            this.$post('fluent_toolkit_mcp_oauth_toggle', {
                enabled: enabled ? 'yes' : 'no'
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.getMcpStatus();
                })
                .catch(error => {
                    this.$handleError(error);
                    this.getMcpStatus();
                })
                .finally(() => {
                    this.mcpSaving = false;
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
        hideLicenseKey(licenseKey) {
            if (!licenseKey) return '';
            return licenseKey.replace(/.(?=.{6})/g, '*').slice(-10);
        },
    },
    mounted() {
        this.getBetaPlugins();
        this.getMcpStatus();
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    }
};
</script>
