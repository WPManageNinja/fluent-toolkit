<template>
    <TopBar :active-view="activeView" @navigate="$emit('navigate', $event)">
        <template #actions>
            <button class="ft-iconbtn" @click="getOverview()" :title="$t('Refresh MCP status')" :aria-label="$t('Refresh MCP status')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
            </button>
        </template>
    </TopBar>

    <div class="ft-app">

        <div class="ft-mcp-empty" v-if="showEmptyBanner">
            <div>
                <strong>{{ $t('No Fluent plugins are exposing MCP yet.') }}</strong>
                <p>{{ $t('MCP_Empty_Desc') }}</p>
            </div>
            <button class="ft-btn ft-btn-ghost" @click="getOverview()">{{ $t('Refresh') }}</button>
        </div>

        <div class="ft-toolbar" v-if="products.length">
            <div class="ft-channels" role="tablist">
                <button
                    v-for="product in products"
                    :key="product.slug"
                    class="ft-channel"
                    :aria-selected="activeProductSlug === product.slug"
                    @click="activeProductSlug = product.slug"
                >
                    {{ product.name }}
                    <span class="ft-channel-count">{{ product.tools_count }}</span>
                </button>
            </div>
            <div class="ft-toolbar-right" v-if="activeProduct">
                <span class="ft-status" :class="statusClass(activeProduct.status)">
                    <span class="ft-dot" :class="activeProduct.status === 'ready' ? 'ft-dot-ok' : 'ft-dot-warn'"></span>
                    {{ statusLabel(activeProduct.status) }}
                </span>
            </div>
        </div>

        <div class="ft-mcp-layout" v-loading="loading" :element-loading-text="$t('Loading MCP status…')">
            <section class="ft-panel ft-mcp-status" v-if="activeProduct">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ activeProduct.name }}</h2>
                        <p>{{ activeProduct.endpoint_url }}</p>
                    </div>
                    <label class="ft-switch">
                        <input
                            type="checkbox"
                            :checked="activeProduct.mcp_enabled"
                            :disabled="saving || !canToggle"
                            @change="toggleProduct($event.target.checked)"
                        />
                        <span></span>
                    </label>
                </div>

                <div class="ft-mcp-grid">
                    <div class="ft-mcp-metric">
                        <span>{{ $t('Endpoint') }}</span>
                        <strong class="ft-mono">{{ activeProduct.endpoint_url }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>{{ $t('Tools available') }}</span>
                        <strong>{{ activeProduct.tools_count }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>{{ $t('MCP status') }}</span>
                        <strong>{{ statusLabel(activeProduct.status) }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>{{ $t('MCP adapter') }}</span>
                        <strong>{{ adapterLabel }}</strong>
                    </div>
                </div>

                <div class="ft-mcp-note" v-if="!canToggle && activeProduct.slug !== 'fluent-crm'">
                    <!-- translators: %s is the plugin name, for example Fluent Support -->
                    {{ $t("%s manages its own MCP setting, so it can't be turned on or off here.", activeProduct.name) }}
                </div>
                <div class="ft-mcp-note" v-else-if="!overview.adapter.available">
                    {{ $t('MCP_Adapter_Missing') }}
                </div>
            </section>

            <section class="ft-panel ft-mcp-connect" v-if="activeProduct">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('Connect client') }}</h2>
                        <p>{{ $t('MCP_Connect_Desc') }}</p>
                    </div>
                    <div class="ft-connect-actions">
                        <button
                            v-if="canCreateAppPassword"
                            class="ft-btn ft-btn-primary"
                            :disabled="creatingPassword"
                            @click="createAppPassword()"
                        >
                            {{ creatingPassword ? $t('Creating…') : $t('Create application password') }}
                        </button>
                        <a class="ft-btn ft-btn-ghost" :href="appPasswordsUrl" target="_blank" rel="noopener">
                            {{ canCreateAppPassword ? $t('Manage passwords') : $t('Create application password') }}
                        </a>
                    </div>
                </div>

                <div class="ft-credential-row">
                    <label>
                        <span>{{ $t('Username') }}</span>
                        <input type="text" v-model="username" placeholder="wp-user" />
                    </label>
                    <label>
                        <span>{{ $t('Application password') }}</span>
                        <div class="ft-input-affix">
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                v-model="appPassword"
                                placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                            />
                            <button
                                type="button"
                                class="ft-input-btn"
                                :title="showPassword ? $t('Hide password') : $t('Show password')"
                                :aria-label="showPassword ? $t('Hide password') : $t('Show password')"
                                @click="showPassword = !showPassword"
                            >
                                <svg v-if="showPassword" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19M6.6 6.6A18.5 18.5 0 0 0 2 12s3 8 10 8a9.7 9.7 0 0 0 5.4-1.6"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/><path d="m2 2 20 20"/></svg>
                                <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-8 10-8 10 8 10 8-3 8-10 8-10-8-10-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </label>
                </div>

                <div class="ft-mcp-note ft-mcp-note-ok" v-if="generatedName">
                    <!-- translators: %s is the name of the new application password, for example FluentHub MCP -->
                    {{ $t('Created the application password "%s" and filled it in above.', generatedName) }}
                    {{ $t('App_Password_Once_Notice') }}
                </div>
                <div class="ft-mcp-note" v-else-if="appPasswordNotice">
                    {{ appPasswordNotice }}
                </div>

                <div class="ft-channels ft-client-tabs" role="tablist">
                    <button
                        v-for="client in clients"
                        :key="client.key"
                        class="ft-channel"
                        :aria-selected="activeClient === client.key"
                        @click="activeClient = client.key"
                    >
                        {{ client.label }}
                    </button>
                </div>

                <div class="ft-channels ft-client-tabs" role="tablist" v-if="canCombine">
                    <button
                        class="ft-channel"
                        :aria-selected="snippetScope === 'single'"
                        @click="snippetScope = 'single'"
                    >
                        <!-- translators: %s is the plugin name, for example FluentCRM -->
                        {{ $t('%s only', activeProduct.name) }}
                    </button>
                    <button
                        class="ft-channel"
                        :aria-selected="snippetScope === 'all'"
                        @click="snippetScope = 'all'"
                    >
                        {{ $t('All enabled plugins') }}
                        <span class="ft-channel-count">{{ enabledProducts.length }}</span>
                    </button>
                </div>

                <div class="ft-snippet-box">
                    <div class="ft-snippet-head">
                        <div>{{ activeClientInfo.note }}</div>
                        <button class="ft-btn ft-btn-primary" @click="copySnippet()">{{ $t('Copy') }}</button>
                    </div>
                    <pre class="ft-snippet"><code>{{ activeSnippet }}</code></pre>
                </div>

                <div class="ft-mcp-note" v-if="snippetScope === 'all' && canCombine">
                    <!-- translators: %s is a comma-separated list of plugin names -->
                    {{ $t('Adds every enabled endpoint in one paste: %s.', enabledProducts.map(product => product.name).join(', ')) }}
                    {{ $t('Plugins with MCP turned off are left out. Turn MCP on for them and copy again.') }}
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import TopBar from './TopBar.vue';

export default {
    name: 'McpPage',
    components: {
        TopBar,
    },
    props: {
        activeView: {
            type: String,
            default: 'mcp',
        },
    },
    emits: ['navigate'],
    data() {
        return {
            overview: {
                adapter: {
                    available: false,
                    provider: 'missing',
                },
                products: [],
                app_password: {
                    available: false,
                    reason: '',
                    user_login: '',
                    manage_url: '',
                },
            },
            activeProductSlug: 'fluent-crm',
            activeClient: 'codex',
            snippetScope: 'single',
            loading: false,
            saving: false,
            creatingPassword: false,
            showPassword: false,
            generatedName: '',
            username: window.fluentToolkitVars.current_user_login || '',
            appPassword: '',
            clients: [
                { key: 'codex', label: 'Codex', note: this.$t('Paste these fields into the Codex custom MCP settings.') },
                { key: 'copilot', label: 'GitHub Copilot', note: this.$t('Paste this JSON into the GitHub Copilot MCP configuration in VS Code.') },
                { key: 'claude-desktop', label: 'Claude Desktop', note: this.$t('Paste this JSON into claude_desktop_config.json and restart Claude Desktop.') },
                { key: 'cursor', label: 'Cursor', note: this.$t('Paste this JSON into the Cursor MCP settings and restart Cursor.') },
                { key: 'generic', label: this.$_x('Generic', 'MCP client type'), note: this.$t('Use this URL and Basic Auth header with any HTTP MCP client.') },
            ],
        };
    },
    computed: {
        products() {
            return this.overview.products || [];
        },
        showEmptyBanner() {
            return !this.loading && !this.products.length;
        },
        activeProduct() {
            return this.products.find(product => product.slug === this.activeProductSlug) || this.products[0] || null;
        },
        appPasswordSupport() {
            return this.overview.app_password || {};
        },
        canCreateAppPassword() {
            return !!this.appPasswordSupport.available;
        },
        appPasswordsUrl() {
            return this.appPasswordSupport.manage_url
                || (this.activeProduct && this.activeProduct.app_passwords_url)
                || '';
        },
        // Only explain why the inline button is missing; when it works the button
        // speaks for itself.
        appPasswordNotice() {
            if (this.canCreateAppPassword) {
                return '';
            }

            const notices = {
                site_disabled: this.$t('App_Password_Site_Disabled'),
                user_disabled: this.$t('Application passwords are disabled for your user account.'),
                unsupported: this.$t('This WordPress version does not support application passwords.'),
            };

            return notices[this.appPasswordSupport.reason] || '';
        },
        adapterLabel() {
            if (!this.overview.adapter.available) {
                return this.$_x('Missing', 'MCP adapter status');
            }

            if (this.overview.adapter.provider === 'plugin') {
                return this.$t('Standalone plugin');
            }

            if (this.overview.adapter.provider === 'toolkit') {
                return this.$t('Bundled with FluentHub');
            }

            return this.$_x('Available', 'MCP adapter status');
        },
        activeClientInfo() {
            return this.clients.find(client => client.key === this.activeClient) || this.clients[0];
        },
        // Only enabled products get an entry in the combined snippet — a disabled
        // server's route isn't registered, so pasting it would just 404.
        enabledProducts() {
            return this.products.filter(product => product.mcp_enabled && product.endpoint_url);
        },
        canCombine() {
            return this.activeProduct && this.enabledProducts.length > 1;
        },
        snippetEntries() {
            if (this.snippetScope === 'all' && this.canCombine) {
                return this.enabledProducts;
            }

            return this.activeProduct ? [this.activeProduct] : [];
        },
        canToggle() {
            return this.activeProduct && this.activeProduct.toggleable;
        },
        authHeader() {
            const user = this.username || '<your-username>';
            const password = this.appPassword || '<your-application-password>';

            if (this.username && this.appPassword && window.btoa) {
                return window.btoa(`${user}:${password}`);
            }

            return `<base64(${user}:${password})>`;
        },
        activeSnippet() {
            const entries = this.snippetEntries;

            if (!entries.length) {
                return '';
            }

            const user = this.username || '<your-username>';
            const password = this.appPassword || '<your-application-password>';

            if (this.activeClient === 'codex') {
                const blocks = entries.map(product => [
                    `Name: ${product.slug}`,
                    'Transport: Streamable HTTP',
                    `URL: ${product.endpoint_url}`,
                    'Header key: Authorization',
                    `Header value: Basic ${this.authHeader}`,
                ].join('\n'));

                const heading = entries.length > 1
                    ? `Codex app custom MCP — add ${entries.length} servers`
                    : 'Codex app custom MCP';

                return [heading, ''].concat(blocks.join('\n\n')).join('\n');
            }

            if (this.activeClient === 'copilot') {
                return JSON.stringify({
                    servers: this.keyBySlug(entries, product => ({
                        type: 'http',
                        url: product.endpoint_url,
                        headers: {
                            Authorization: `Basic ${this.authHeader}`,
                        },
                    })),
                }, null, 2);
            }

            if (this.activeClient === 'claude-desktop') {
                return JSON.stringify({
                    mcpServers: this.keyBySlug(entries, product => ({
                        command: 'npx',
                        args: ['-y', '@automattic/mcp-wordpress-remote@latest'],
                        env: {
                            WP_API_URL: product.endpoint_url,
                            WP_API_USERNAME: user,
                            WP_API_PASSWORD: password,
                            OAUTH_ENABLED: 'false',
                            NODE_TLS_REJECT_UNAUTHORIZED: '0',
                        },
                    })),
                }, null, 2);
            }

            if (this.activeClient === 'cursor') {
                return JSON.stringify({
                    mcpServers: this.keyBySlug(entries, product => ({
                        url: product.endpoint_url,
                        type: 'http',
                        headers: {
                            Authorization: `Basic ${this.authHeader}`,
                        },
                    })),
                }, null, 2);
            }

            if (this.activeClient === 'generic') {
                return entries.map(product => [
                    `# ${product.name}`,
                    `URL: ${product.endpoint_url}`,
                    `Authorization: Basic ${this.authHeader}`,
                    '',
                    `curl -s -u '${user}:${password}' \\`,
                    `  -X POST ${product.endpoint_url} \\`,
                    "  -H 'Content-Type: application/json' \\",
                    "  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
                ].join('\n')).join('\n\n');
            }

            return entries.map(product => [
                `URL: ${product.endpoint_url}`,
                `Authorization: Basic ${this.authHeader}`,
            ].join('\n')).join('\n\n');
        },
    },
    methods: {
        // Client configs key servers by name; the product slug is already unique
        // per endpoint, so it doubles as the server key.
        keyBySlug(products, build) {
            return products.reduce((servers, product) => {
                servers[product.slug] = build(product);
                return servers;
            }, {});
        },
        getOverview() {
            this.loading = true;

            this.$get('fluent_toolkit_mcp_overview')
                .then(response => {
                    this.overview = response;
                    if (this.products.length && !this.activeProduct) {
                        this.activeProductSlug = this.products[0].slug;
                    }
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        toggleProduct(enabled) {
            this.saving = true;

            this.$post('fluent_toolkit_mcp_toggle', {
                slug: this.activeProduct.slug,
                enabled: enabled ? 'yes' : 'no',
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.overview = response.status;
                })
                .catch(error => {
                    this.$handleError(error);
                    this.getOverview();
                })
                .finally(() => {
                    this.saving = false;
                });
        },
        createAppPassword() {
            this.creatingPassword = true;

            this.$post('fluent_toolkit_mcp_create_app_password', {
                name: 'FluentHub MCP',
            })
                .then(response => {
                    const created = response.app_password || {};
                    this.username = created.username || this.username;
                    this.appPassword = created.password || '';
                    this.generatedName = created.name || '';
                    this.showPassword = true;
                    this.$notify.success(response.message);
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.creatingPassword = false;
                });
        },
        copySnippet() {
            if (!window.navigator || !window.navigator.clipboard) {
                this.$notify.warning(this.$t('Copying is not available on this page. Select the snippet and copy it manually.'));
                return;
            }

            window.navigator.clipboard.writeText(this.activeSnippet)
                .then(() => {
                    this.$notify.success(this.$t('Copied.'));
                })
                .catch(() => {
                    this.$notify.error(this.$t('Could not copy the connection snippet.'));
                });
        },
        statusLabel(status) {
            const labels = {
                ready: this.$_x('Ready', 'MCP status'),
                disabled: this.$_x('Disabled', 'MCP status'),
                adapter_required: this.$t('Adapter required'),
                crm_required: this.$t('Plugin not active'),
                plugin_required: this.$t('Plugin not active'),
            };

            return labels[status] || this.$_x('Unknown', 'MCP status');
        },
        statusClass(status) {
            if (status === 'ready') {
                return 'ft-status-ok';
            }

            if (status === 'disabled') {
                return 'ft-status-update';
            }

            return 'ft-status-notinstalled';
        },
    },
    mounted() {
        this.getOverview();
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    },
};
</script>
