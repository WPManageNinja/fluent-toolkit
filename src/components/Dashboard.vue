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
                    <div class="toolkit_settings">
                        <div class="toolkit_settings_header">
                            <div>
                                <h3>AI Assistant</h3>
                                <p>Configure the shared AI addon for supported Fluent plugins like FluentCart.</p>
                            </div>
                            <div class="panel_actions">
                                <el-button type="primary" :loading="aiSaving" @click="saveAiSettings">
                                    Save Settings
                                </el-button>
                                <el-button :disabled="aiSaving" @click="resetAiForm">Reset</el-button>
                            </div>
                        </div>

                        <div class="toolkit_settings_body">
                            <div class="toolkit_settings_section">
                                <div class="toolkit_settings_section_content toolkit_rounded_top">
                                    <div class="toolkit_setting_switch">
                                        <div class="toolkit_switch">
                                            <el-switch v-model="aiForm.enabled"/>
                                        </div>
                                        <div class="toolkit_switch_label">
                                            <p>Enable AI Addon</p>
                                            <span>When enabled, Fluent Toolkit will inject the AI assistant into supported plugins that have valid credentials.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <template v-if="aiForm.enabled">
                                <div class="toolkit_status_alerts">
                                    <el-alert
                                        v-if="aiState.status?.blocked_by_standalone_plugin"
                                        title="A standalone FluentCart AI plugin is active. Disable it before booting AI from Fluent Toolkit to avoid duplicate widgets and routes."
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
                                        v-else-if="!aiState.status?.has_credentials"
                                        title="AI is enabled, but credentials are still missing."
                                        type="warning"
                                        :closable="false"
                                        show-icon
                                    />

                                    <el-alert
                                        v-else-if="!hasAvailableDrivers"
                                        title="AI is enabled, but no supported Fluent plugin is active on this site yet."
                                        type="info"
                                        :closable="false"
                                        show-icon
                                    />
                                </div>

                                <div class="toolkit_settings_section">
                                    <div class="toolkit_settings_section_header">
                                        <h4>AI Provider</h4>
                                    </div>
                                    <div class="toolkit_settings_section_content">
                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Provider</p>
                                                <span>Fluent Toolkit currently boots the native AI addon with OpenAI.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <el-select v-model="aiForm.provider" size="large" class="toolkit_select" disabled>
                                                    <el-option v-for="provider in providerOptions" :key="provider.value" :label="provider.label" :value="provider.value"/>
                                                </el-select>
                                            </div>
                                        </div>

                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Model</p>
                                                <span>Choose the OpenAI model used for native AI requests.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <el-select
                                                    v-model="aiForm.openai_model"
                                                    size="large"
                                                    class="toolkit_select"
                                                    filterable
                                                    allow-create
                                                    default-first-option
                                                    :disabled="isAiFieldLocked('openai_model')"
                                                >
                                                    <el-option
                                                        v-for="model in openAiModels"
                                                        :key="model.value"
                                                        :label="model.label"
                                                        :value="model.value"
                                                    />
                                                </el-select>
                                                <div v-if="isAiFieldLocked('openai_model')" class="field_hint">
                                                    This value is overridden by <code>FLUENT_TOOLKIT_AI_OPENAI_MODEL</code>.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Store Provider Responses</p>
                                                <span>Keep provider response references for continuation, retries, and tool loops.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <el-switch v-model="aiForm.store_provider_responses" :disabled="isAiFieldLocked('store_provider_responses')"/>
                                            </div>
                                        </div>

                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Enable SQL Fallback</p>
                                                <span>Use guarded read-only SQL only when the normal AI tools and aggregates are insufficient.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <el-switch v-model="aiForm.sql_fallback" :disabled="isAiFieldLocked('sql_fallback')"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="toolkit_settings_section">
                                    <div class="toolkit_settings_section_header">
                                        <h4>API Key</h4>
                                    </div>
                                    <div class="toolkit_settings_section_content">
                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>OpenAI API Key</p>
                                                <span v-if="!isAiFieldLocked('openai_api_key')">Enter your OpenAI API key. Leave the field empty to keep the current saved key.</span>
                                                <span v-else>The API key is being managed from <code>wp-config.php</code>.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <template v-if="!isAiFieldLocked('openai_api_key')">
                                                    <el-input
                                                        v-model="aiForm.openai_api_key"
                                                        size="large"
                                                        type="password"
                                                        show-password
                                                        autocomplete="off"
                                                        data-1p-ignore
                                                        data-lpignore="true"
                                                        data-bwignore
                                                        placeholder="Enter OpenAI API Key"
                                                    />
                                                    <div class="field_hint">
                                                        <span v-if="aiState.api_key?.configured">
                                                            Current source: {{ aiState.api_key.source }}<span v-if="aiState.api_key.preview"> ({{ aiState.api_key.preview }})</span>
                                                        </span>
                                                        <span v-else>No API key configured yet.</span>
                                                    </div>
                                                    <el-checkbox v-if="canClearStoredApiKey" v-model="aiForm.clear_api_key">
                                                        Clear the saved database key
                                                    </el-checkbox>
                                                </template>
                                                <div v-else class="config_managed_box">
                                                    <strong>Managed by wp-config.php</strong>
                                                    <p>This site is using <code>FLUENT_TOOLKIT_AI_OPENAI_API_KEY</code>, so the API key is not editable from Fluent Toolkit.</p>
                                                    <p v-if="aiState.api_key?.preview">Current value: {{ aiState.api_key.preview }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="toolkit_settings_section">
                                    <div class="toolkit_settings_section_header">
                                        <h4>Custom Instructions</h4>
                                    </div>
                                    <div class="toolkit_settings_section_content">
                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Custom System Prompt</p>
                                                <span>These instructions will be included with every AI request. Use this to set brand voice, guardrails, or store-specific guidance.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <el-input
                                                    v-model="aiForm.system_prompt"
                                                    type="textarea"
                                                    :rows="5"
                                                    :disabled="isAiFieldLocked('system_prompt')"
                                                    placeholder="e.g. Keep answers concise. Always format grouped data in tables. Use the store’s brand tone."
                                                    style="width: 100%;"
                                                />
                                                <div v-if="isAiFieldLocked('system_prompt')" class="field_hint">
                                                    This value is overridden by <code>FLUENT_TOOLKIT_AI_SYSTEM_PROMPT</code>.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="toolkit_settings_section">
                                    <div class="toolkit_settings_section_header">
                                        <h4>Runtime Status</h4>
                                    </div>
                                    <div class="toolkit_settings_section_content">
                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Current Status</p>
                                                <span>The widget boots only when credentials exist, the addon is enabled, and a supported plugin driver is active.</span>
                                            </div>
                                            <div class="toolkit_select_option">
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
                                            </div>
                                        </div>

                                        <div class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Supported Integrations</p>
                                                <span>These drivers are available to host the AI widget.</span>
                                            </div>
                                            <div class="toolkit_select_option">
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
                                            </div>
                                        </div>

                                        <div v-if="hasAnyOverrides" class="toolkit_setting_option">
                                            <div class="toolkit_select_option_label">
                                                <p>Constant Overrides</p>
                                                <span>These values are being controlled from code and override the saved UI values.</span>
                                            </div>
                                            <div class="toolkit_select_option">
                                                <div class="override_list inline_override_list">
                                                    <ul>
                                                        <li v-if="aiState.overrides?.openai_api_key"><code>FLUENT_TOOLKIT_AI_OPENAI_API_KEY</code></li>
                                                        <li v-if="aiState.overrides?.openai_model"><code>FLUENT_TOOLKIT_AI_OPENAI_MODEL</code></li>
                                                        <li v-if="aiState.overrides?.system_prompt"><code>FLUENT_TOOLKIT_AI_SYSTEM_PROMPT</code></li>
                                                        <li v-if="aiState.overrides?.sql_fallback"><code>FLUENT_TOOLKIT_AI_ENABLE_SQL_FALLBACK</code></li>
                                                        <li v-if="aiState.overrides?.store_provider_responses"><code>FLUENT_TOOLKIT_AI_STORE_PROVIDER_RESPONSES</code></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
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
        system_prompt: '',
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
        system_prompt: false,
        sql_fallback: false,
        store_provider_responses: false
    },
    drivers: []
});

const OPENAI_MODELS = [
    { value: 'gpt-4.1-mini', label: 'GPT-4.1 Mini' },
    { value: 'gpt-4.1', label: 'GPT-4.1' },
    { value: 'gpt-4o-mini', label: 'GPT-4o Mini' },
    { value: 'gpt-4o', label: 'GPT-4o' },
    { value: 'gpt-5-mini', label: 'GPT-5 Mini' }
];

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
            providerOptions: [
                { value: 'openai', label: 'OpenAI' }
            ],
            openAiModels: OPENAI_MODELS,
            aiForm: {
                enabled: false,
                provider: 'openai',
                openai_api_key: '',
                openai_model: 'gpt-4.1-mini',
                system_prompt: '',
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
                provider: 'openai',
                openai_api_key: '',
                openai_model: settings.openai_model || 'gpt-4.1-mini',
                system_prompt: settings.system_prompt || '',
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
                system_prompt: this.aiForm.system_prompt,
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
