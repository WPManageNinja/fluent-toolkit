<template>
    <div class="fbeta_dashboard">
        <div class="fluent_header">
            <h1 style="margin-bottom: 10px;">
                Fluent Toolkit
                <el-tag type="success">{{ appVars.version }}</el-tag>
                <el-button
                    v-if="appVars.require_update"
                    :loading="installing"
                    :disabled="installing"
                    @click="updateToolkit()"
                    style="float: right;"
                    type="danger"
                >
                    Update Toolkit
                </el-button>
            </h1>
            <p>Install beta plugins, manage addon credentials, and enable shared tooling across Fluent plugins.</p>
        </div>

        <div class="fluent_content">
            <el-tabs v-model="activeTab">
                <el-tab-pane label="Beta Plugins" name="plugins">
                    <h3>Plugins</h3>
                    <el-skeleton v-if="loading" :animated="true" :rows="10"/>

                    <div v-loading="installing" element-loading-text="Installing... Please wait...." class="fluent_plugins">
                        <div v-for="plugin in betaPlugins" :key="plugin.slug" class="fluent_plugin">
                            <div class="fluent_plugin_name">
                                <img :src="plugin.logo" :alt="plugin.name"/>

                                <div class="fluent_plugin_description">
                                    <h3>
                                        {{ plugin.name }}
                                        <el-tag v-if="!plugin.installed_version" type="info">{{ plugin.stable_version }}</el-tag>
                                    </h3>
                                    <p>{{ plugin.sub_title }}</p>

                                    <div class="fluent_plugin_statuses">
                                        <el-tag v-if="plugin.installed_version">Installed: {{ plugin.installed_version }}</el-tag>

                                        <el-button
                                            v-if="plugin.has_beta_update"
                                            @click="installPlugin(plugin, 'yes')"
                                            type="danger"
                                            size="small"
                                            plain
                                        >
                                            Install Beta - {{ plugin.beta_version }}
                                        </el-button>

                                        <span v-if="plugin.is_pro">
                                            License: {{ hideLicenseKey(plugin.license_key) || 'n/a' }}
                                            <el-button
                                                v-if="plugin.has_beta_update"
                                                @click="setCustomLicenseKey(plugin)"
                                                size="small"
                                                text
                                            >
                                                <el-icon><EditPen/></el-icon>
                                            </el-button>
                                        </span>

                                        <a v-if="plugin.changelog_url" target="_blank" rel="noopener" :href="plugin.changelog_url">Change log</a>
                                    </div>
                                </div>
                            </div>

                            <div class="fluent_plugin_actions">
                                <el-button v-if="!plugin.installed_version" @click="installPlugin(plugin)" type="primary">
                                    Install
                                </el-button>

                                <el-button v-else-if="plugin.has_update" @click="installPlugin(plugin)" type="danger">
                                    Update to {{ plugin.stable_version }}
                                </el-button>

                                <el-tag v-else type="success">Up to date</el-tag>
                            </div>
                        </div>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="AI Addon" name="ai">
                    <div class="ai_settings_grid">
                        <div class="ai_settings_panel">
                            <div class="panel_header">
                                <h3>AI Addon Settings</h3>
                                <p>Enable Fluent AI and define the credentials used by supported plugin drivers like FluentCart.</p>
                            </div>

                            <el-alert
                                v-if="aiState.status?.blocked_by_standalone_plugin"
                                title="The standalone fluent-cart-ai plugin is active. Disable it before booting AI from Fluent Toolkit to avoid duplicate widgets and routes."
                                type="warning"
                                :closable="false"
                                show-icon
                            />

                            <el-alert
                                v-else-if="aiState.status?.boot_ready"
                                title="AI is ready to boot into active supported plugins."
                                type="success"
                                :closable="false"
                                show-icon
                            />

                            <el-alert
                                v-else-if="aiState.settings?.enabled && !aiState.status?.has_credentials"
                                title="AI is enabled, but credentials are still missing."
                                type="warning"
                                :closable="false"
                                show-icon
                            />

                            <el-alert
                                v-else-if="aiState.settings?.enabled && !hasAvailableDrivers"
                                title="AI is enabled, but no supported Fluent plugin is active on this site yet."
                                type="info"
                                :closable="false"
                                show-icon
                            />

                            <el-form label-position="top" class="ai_form">
                                <el-form-item label="Activate AI addon">
                                    <el-switch v-model="aiForm.enabled"/>
                                </el-form-item>

                                <el-form-item v-if="!isAiFieldLocked('openai_api_key')" label="OpenAI API Key">
                                    <el-input
                                        v-model="aiForm.openai_api_key"
                                        type="password"
                                        show-password
                                        placeholder="Leave blank to keep the current key"
                                    />
                                    <div class="field_hint">
                                        <span v-if="aiState.api_key?.configured">
                                            Current source: {{ aiState.api_key.source }}<span v-if="aiState.api_key.preview"> ({{ aiState.api_key.preview }})</span>
                                        </span>
                                        <span v-else>No API key configured yet.</span>
                                    </div>
                                    <div v-if="isAiFieldLocked('openai_api_key')" class="field_hint">
                                        This value is overridden by <code>FLUENT_TOOLKIT_AI_OPENAI_API_KEY</code>.
                                    </div>
                                    <el-checkbox
                                        v-if="canClearStoredApiKey"
                                        v-model="aiForm.clear_api_key"
                                    >
                                        Clear the saved database key
                                    </el-checkbox>
                                </el-form-item>

                                <el-form-item v-else label="OpenAI API Key">
                                    <div class="config_managed_box">
                                        <strong>Managed by wp-config.php</strong>
                                        <p>
                                            This site is using <code>FLUENT_TOOLKIT_AI_OPENAI_API_KEY</code>, so the API key is not editable from Fluent Toolkit.
                                        </p>
                                        <p v-if="aiState.api_key?.preview">
                                            Current value: {{ aiState.api_key.preview }}
                                        </p>
                                    </div>
                                </el-form-item>

                                <el-form-item label="OpenAI Model">
                                    <el-input
                                        v-model="aiForm.openai_model"
                                        :disabled="isAiFieldLocked('openai_model')"
                                        placeholder="gpt-4.1-mini"
                                    />
                                    <div v-if="isAiFieldLocked('openai_model')" class="field_hint">
                                        This value is overridden by <code>FLUENT_TOOLKIT_AI_OPENAI_MODEL</code>.
                                    </div>
                                </el-form-item>

                                <el-form-item label="Enable SQL fallback">
                                    <el-switch v-model="aiForm.sql_fallback" :disabled="isAiFieldLocked('sql_fallback')"/>
                                    <div class="field_hint">Use guarded read-only SQL only when normal AI tools are insufficient.</div>
                                </el-form-item>

                                <el-form-item label="Store provider responses">
                                    <el-switch v-model="aiForm.store_provider_responses" :disabled="isAiFieldLocked('store_provider_responses')"/>
                                    <div class="field_hint">Keep provider response references available for tool continuation and traceability.</div>
                                </el-form-item>

                                <div class="panel_actions">
                                    <el-button type="primary" :loading="aiSaving" @click="saveAiSettings">
                                        Save AI Settings
                                    </el-button>
                                    <el-button :disabled="aiSaving" @click="resetAiForm">Reset</el-button>
                                </div>
                            </el-form>
                        </div>

                        <div class="ai_status_panel">
                            <div class="panel_header">
                                <h3>Runtime Status</h3>
                                <p>The AI widget loads only when the addon is enabled, credentials exist, and a supported plugin driver is active.</p>
                            </div>

                            <div class="status_tags">
                                <el-tag :type="aiState.settings?.enabled ? 'success' : 'info'">
                                    {{ aiState.settings?.enabled ? 'Enabled' : 'Disabled' }}
                                </el-tag>
                                <el-tag :type="aiState.status?.has_credentials ? 'success' : 'warning'">
                                    {{ aiState.status?.has_credentials ? 'Credentials Ready' : 'Missing Credentials' }}
                                </el-tag>
                                <el-tag :type="aiState.status?.boot_ready ? 'success' : 'info'">
                                    {{ aiState.status?.boot_ready ? 'Boot Ready' : 'Waiting' }}
                                </el-tag>
                            </div>

                            <div class="driver_list">
                                <div v-for="driver in aiState.drivers" :key="driver.slug" class="driver_card">
                                    <div class="driver_card_header">
                                        <strong>{{ driver.label }}</strong>
                                        <el-tag :type="driver.available ? 'success' : 'info'">
                                            {{ driver.available ? 'Active' : 'Inactive' }}
                                        </el-tag>
                                    </div>
                                    <p>{{ driver.message }}</p>
                                </div>
                            </div>

                            <div class="override_list" v-if="hasAnyOverrides">
                                <h4>Constant Overrides</h4>
                                <ul>
                                    <li v-if="aiState.overrides?.openai_api_key"><code>FLUENT_TOOLKIT_AI_OPENAI_API_KEY</code></li>
                                    <li v-if="aiState.overrides?.openai_model"><code>FLUENT_TOOLKIT_AI_OPENAI_MODEL</code></li>
                                    <li v-if="aiState.overrides?.sql_fallback"><code>FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK</code></li>
                                    <li v-if="aiState.overrides?.store_provider_responses"><code>FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES</code></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </div>
    </div>
</template>

<script type="text/babel">
import { EditPen } from "@element-plus/icons-vue";

const defaultAiPayload = () => ({
    settings: {
        enabled: false,
        openai_api_key: '',
        openai_model: 'gpt-4.1-mini',
        sql_fallback: true,
        store_provider_responses: true,
        clear_api_key: false
    },
    status: {
        enabled: false,
        has_credentials: false,
        boot_ready: false
    },
    api_key: {
        configured: false,
        stored: false,
        source: 'none',
        preview: ''
    },
    overrides: {
        openai_api_key: false,
        openai_model: false,
        sql_fallback: false,
        store_provider_responses: false
    },
    drivers: []
});

export default {
    name: 'Dashboard',
    components: { EditPen },
    data() {
        return {
            activeTab: 'plugins',
            betaPlugins: [],
            installing: false,
            loading: false,
            aiSaving: false,
            aiState: defaultAiPayload(),
            aiForm: {
                enabled: false,
                openai_api_key: '',
                openai_model: 'gpt-4.1-mini',
                sql_fallback: true,
                store_provider_responses: true,
                clear_api_key: false
            }
        };
    },
    computed: {
        hasAvailableDrivers() {
            return (this.aiState.drivers || []).some(driver => driver.available);
        },
        hasAnyOverrides() {
            const overrides = this.aiState.overrides || {};
            return Object.values(overrides).some(Boolean);
        },
        canClearStoredApiKey() {
            return !this.isAiFieldLocked('openai_api_key') && this.aiState.api_key?.stored;
        }
    },
    methods: {
        syncAiState(payload) {
            this.aiState = Object.assign(defaultAiPayload(), payload || {});
            this.resetAiForm();
        },
        resetAiForm() {
            const settings = this.aiState.settings || {};
            this.aiForm = {
                enabled: !!settings.enabled,
                openai_api_key: '',
                openai_model: settings.openai_model || 'gpt-4.1-mini',
                sql_fallback: !!settings.sql_fallback,
                store_provider_responses: !!settings.store_provider_responses,
                clear_api_key: false
            };
        },
        isAiFieldLocked(field) {
            return !!(this.aiState.overrides || {})[field];
        },
        getBetaPlugins() {
            this.loading = true;

            this.$get('fluent_toolkit_get_beta_versions')
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

            this.$post('fluent_toolkit_install_beta', {
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
            this.installPlugin({ slug: 'fluent-toolkit' });
        },
        saveAiSettings() {
            this.aiSaving = true;

            this.$post('fluent_toolkit_save_ai_settings', {
                enabled: this.aiForm.enabled ? 'yes' : 'no',
                openai_api_key: this.aiForm.openai_api_key,
                openai_model: this.aiForm.openai_model,
                sql_fallback: this.aiForm.sql_fallback ? 'yes' : 'no',
                store_provider_responses: this.aiForm.store_provider_responses ? 'yes' : 'no',
                clear_api_key: this.aiForm.clear_api_key ? 'yes' : 'no'
            })
                .then(response => {
                    this.syncAiState(response.ai);
                    this.$notify.success(response.message);
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.aiSaving = false;
                });
        },
        hideLicenseKey(licenseKey) {
            if (!licenseKey) {
                return '';
            }

            return licenseKey.replace(/.(?=.{6})/g, '*').slice(-10);
        },
        setCustomLicenseKey(plugin) {
            const licenseKey = prompt('Please enter your license key for ' + plugin.name);

            if (!licenseKey) {
                this.$notify.error('Please enter a valid license key.');
                return;
            }

            plugin.license_key = licenseKey;
        }
    },
    mounted() {
        this.syncAiState(this.appVars.ai);
        this.getBetaPlugins();
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    }
}
</script>
