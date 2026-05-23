<template>
    <div class="ft-app">

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
                <ViewTabs :active-view="activeView" @navigate="$emit('navigate', $event)" />
                <button class="ft-iconbtn" @click="getOverview()" title="Refresh MCP status" aria-label="Refresh MCP status">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
                </button>
            </div>
        </header>

        <div class="ft-mcp-empty" v-if="showEmptyBanner">
            <div>
                <strong>No Fluent plugins are exposing MCP yet.</strong>
                <p>Install or update a Fluent plugin with MCP support. Once a plugin registers its MCP endpoint, it will appear here with tools, status, and connection snippets.</p>
            </div>
            <button class="ft-btn ft-btn-ghost" @click="getOverview()">Refresh</button>
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

        <div class="ft-mcp-layout" v-loading="loading" element-loading-text="Loading MCP status…">
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
                        <span>Endpoint</span>
                        <strong class="ft-mono">{{ activeProduct.endpoint_url }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>Tools available</span>
                        <strong>{{ activeProduct.tools_count }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>MCP status</span>
                        <strong>{{ statusLabel(activeProduct.status) }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>MCP adapter</span>
                        <strong>{{ adapterLabel }}</strong>
                    </div>
                </div>

                <div class="ft-mcp-note" v-if="!canToggle && activeProduct.slug !== 'fluent-crm'">
                    {{ activeProduct.name }} status is provided by hook. Enable and disable is handled only by Toolkit-supported manual handlers.
                </div>
                <div class="ft-mcp-note" v-else-if="!overview.adapter.available">
                    The WordPress MCP adapter is not available. Activate Fluent Toolkit's bundled adapter or the standalone adapter plugin.
                </div>
            </section>

            <section class="ft-panel ft-mcp-connect" v-if="activeProduct">
                <div class="ft-panel-head">
                    <div>
                        <h2>Connect client</h2>
                        <p>Use a WordPress Application Password for the selected user. Toolkit fills the endpoint and header format.</p>
                    </div>
                    <a class="ft-btn ft-btn-ghost" :href="activeProduct.app_passwords_url" target="_blank" rel="noopener">Create Application Password</a>
                </div>

                <div class="ft-credential-row">
                    <label>
                        <span>Username</span>
                        <input type="text" v-model="username" placeholder="wp-user" />
                    </label>
                    <label>
                        <span>Application password</span>
                        <input type="password" v-model="appPassword" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" />
                    </label>
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

                <div class="ft-snippet-box">
                    <div class="ft-snippet-head">
                        <div>{{ activeClientInfo.note }}</div>
                        <button class="ft-btn ft-btn-primary" @click="copySnippet()">Copy</button>
                    </div>
                    <pre class="ft-snippet"><code>{{ activeSnippet }}</code></pre>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import ViewTabs from './ViewTabs.vue';

export default {
    name: 'McpPage',
    components: {
        ViewTabs,
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
            },
            activeProductSlug: 'fluent-crm',
            activeClient: 'codex',
            loading: false,
            saving: false,
            username: window.fluentToolkitVars.current_user_login || '',
            appPassword: '',
            clients: [
                { key: 'codex', label: 'Codex', note: 'Paste these fields into Codex custom MCP settings.' },
                { key: 'copilot', label: 'GitHub Copilot', note: 'Paste this JSON into VS Code Copilot MCP configuration.' },
                { key: 'claude-desktop', label: 'Claude Desktop', note: 'Paste this JSON into claude_desktop_config.json and restart Claude Desktop.' },
                { key: 'cursor', label: 'Cursor', note: 'Paste this JSON into Cursor MCP settings and restart Cursor.' },
                { key: 'generic', label: 'Generic', note: 'Use this URL and Basic Auth header with any HTTP MCP client.' },
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
        adapterLabel() {
            if (!this.overview.adapter.available) {
                return 'Missing';
            }

            if (this.overview.adapter.provider === 'plugin') {
                return 'Standalone plugin';
            }

            if (this.overview.adapter.provider === 'toolkit') {
                return 'Toolkit bundled';
            }

            return 'Available';
        },
        activeClientInfo() {
            return this.clients.find(client => client.key === this.activeClient) || this.clients[0];
        },
        serverName() {
            return this.activeProduct ? this.activeProduct.slug : 'fluent-mcp';
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
            if (!this.activeProduct) {
                return '';
            }

            const endpoint = this.activeProduct.endpoint_url;
            const user = this.username || '<your-username>';
            const password = this.appPassword || '<your-application-password>';

            if (this.activeClient === 'codex') {
                return [
                    'Codex app custom MCP',
                    `Name: ${this.serverName}`,
                    'Transport: Streamable HTTP',
                    `URL: ${endpoint}`,
                    'Header key: Authorization',
                    `Header value: Basic ${this.authHeader}`,
                ].join('\n');
            }

            if (this.activeClient === 'copilot') {
                return JSON.stringify({
                    servers: {
                        [this.serverName]: {
                            type: 'http',
                            url: endpoint,
                            headers: {
                                Authorization: `Basic ${this.authHeader}`,
                            },
                        },
                    },
                }, null, 2);
            }

            if (this.activeClient === 'claude-desktop') {
                return JSON.stringify({
                    mcpServers: {
                        [this.serverName]: {
                            command: 'npx',
                            args: ['-y', '@automattic/mcp-wordpress-remote@latest'],
                            env: {
                                WP_API_URL: endpoint,
                                WP_API_USERNAME: user,
                                WP_API_PASSWORD: password,
                                OAUTH_ENABLED: 'false',
                                NODE_TLS_REJECT_UNAUTHORIZED: '0',
                            },
                        },
                    },
                }, null, 2);
            }

            if (this.activeClient === 'cursor') {
                return JSON.stringify({
                    mcpServers: {
                        [this.serverName]: {
                            url: endpoint,
                            type: 'http',
                            headers: {
                                Authorization: `Basic ${this.authHeader}`,
                            },
                        },
                    },
                }, null, 2);
            }

            if (this.activeClient === 'generic') {
                return [
                    `URL: ${endpoint}`,
                    `Authorization: Basic ${this.authHeader}`,
                    '',
                    `curl -s -u '${user}:${password}' \\`,
                    `  -X POST ${endpoint} \\`,
                    "  -H 'Content-Type: application/json' \\",
                    "  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}'",
                ].join('\n');
            }

            return [
                `URL: ${endpoint}`,
                `Authorization: Basic ${this.authHeader}`,
            ].join('\n');
        },
    },
    methods: {
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
        copySnippet() {
            if (!window.navigator || !window.navigator.clipboard) {
                this.$notify.warning('Clipboard is not available in this browser.');
                return;
            }

            window.navigator.clipboard.writeText(this.activeSnippet)
                .then(() => {
                    this.$notify.success('Copied.');
                })
                .catch(() => {
                    this.$notify.error('Could not copy the connection snippet.');
                });
        },
        statusLabel(status) {
            const labels = {
                ready: 'Ready',
                disabled: 'Disabled',
                adapter_required: 'Adapter required',
                crm_required: 'Product required',
                plugin_required: 'Product required',
            };

            return labels[status] || 'Unknown';
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
