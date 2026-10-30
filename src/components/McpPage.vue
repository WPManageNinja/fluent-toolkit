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
                    v-if="hubProduct"
                    class="ft-channel"
                    :aria-selected="activeProductSlug === hubProduct.slug"
                    @click="activeProductSlug = hubProduct.slug"
                >
                    {{ hubProduct.name }}
                    <span class="ft-channel-count">{{ hubProduct.tools_count }}</span>
                </button>
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
            <section class="ft-panel ft-mcp-status" v-if="activeProduct && activeProduct.is_hub">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('FluentHub MCP server') }}</h2>
                        <p>{{ $t('MCP_Hub_Desc') }}</p>
                    </div>
                </div>

                <div class="ft-mcp-grid">
                    <div class="ft-mcp-metric">
                        <span>{{ $t('Endpoint') }}</span>
                        <strong class="ft-mono">{{ hub.endpoint_url }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>{{ $t('Actions available') }}</span>
                        <strong>{{ hub.actions_count }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>{{ $t('Plugins included') }}</span>
                        <strong>{{ hubServedProducts.length }}</strong>
                    </div>
                    <div class="ft-mcp-metric">
                        <span>{{ $t('MCP adapter') }}</span>
                        <strong>{{ adapterLabel }}</strong>
                    </div>
                </div>

                <div class="ft-mcp-hub-products">
                    <label class="ft-mcp-hub-product" v-for="product in hub.products" :key="product.slug">
                        <input
                            type="checkbox"
                            :checked="product.included"
                            :disabled="saving || !hub.available"
                            @change="toggleHubProduct(product.slug, $event.target.checked)"
                        />
                        <strong>{{ product.name }}</strong>
                        <!-- translators: %s is the number of MCP actions, for example 28 -->
                        <span v-if="product.mcp_enabled">{{ $_n('%s action', '%s actions', product.tools_count) }}</span>
                        <span v-else>{{ $t('MCP is off for this plugin. Turn it on in its tab to include it.') }}</span>
                    </label>
                </div>

                <div class="ft-mcp-oauth" v-if="hub.available">
                    <div class="ft-mcp-oauth-head">
                        <div>
                            <strong>{{ $t('Sign in with OAuth') }}</strong>
                            <p>{{ $t('MCP_OAuth_Desc') }}</p>
                        </div>
                        <label class="ft-switch">
                            <input
                                type="checkbox"
                                :checked="oauth.enabled"
                                :disabled="saving || !oauth.available"
                                @change="toggleOAuth($event.target.checked)"
                            />
                            <span></span>
                        </label>
                    </div>

                    <p class="ft-mcp-oauth-warn" v-if="oauth.reason === 'plain_permalinks'">{{ $t('MCP_OAuth_Plain_Permalinks') }}</p>
                    <p class="ft-mcp-oauth-warn" v-else-if="oauth.reason === 'app_passwords_off'">{{ $t('MCP_OAuth_App_Passwords_Off') }}</p>
                    <p class="ft-mcp-oauth-warn" v-else-if="oauthReady && !oauth.https">{{ $t('MCP_OAuth_No_Https') }}</p>

                    <template v-if="oauthReady">
                        <div class="ft-mcp-oauth-check">
                            <button class="ft-btn ft-btn-ghost" :disabled="oauthCheck.running" @click="runOAuthCheck()">
                                {{ oauthCheck.running ? $t('Checking…') : $t('Check sign-in setup') }}
                            </button>
                            <ul v-if="oauthCheck.results.length">
                                <li v-for="result in oauthCheck.results" :key="result.key" :class="'is-' + result.state">
                                    <span class="ft-dot" :class="result.state === 'ok' ? 'ft-dot-ok' : 'ft-dot-warn'"></span>
                                    {{ result.label }}
                                </li>
                            </ul>
                        </div>
                    </template>

                    <div class="ft-mcp-oauth-apps" v-if="oauthReady || oauth.apps.length">
                        <strong>{{ $t('Connected apps') }}</strong>
                        <p v-if="!oauth.apps.length">{{ $t('No apps are connected yet.') }}</p>
                        <table v-else>
                            <thead>
                                <tr>
                                    <th>{{ $t('App') }}</th>
                                    <th>{{ $t('User') }}</th>
                                    <th>{{ $t('Last used') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="app in oauth.apps" :key="app.id">
                                    <td>
                                        <strong>{{ app.name || $t('Unnamed app') }}</strong>
                                    </td>
                                    <td>
                                        {{ app.user }}
                                        <em v-if="app.user_login">{{ app.user_login }}</em>
                                    </td>
                                    <td>{{ app.last_used_at ? formatDate(app.last_used_at) : $t('Not used yet') }}</td>
                                    <td>
                                        <button class="ft-btn ft-btn-ghost" :disabled="saving" @click="revokeOAuthApp(app)">{{ $t('Disconnect') }}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ft-mcp-note" v-if="hub.reason === 'adapter_outdated'">
                    <!-- translators: 1: the MCP Adapter version that runs, 2: the minimum MCP Adapter version the FluentHub server needs -->
                    {{ $t('MCP_Hub_Adapter_Outdated', overview.adapter.version, hub.min_adapter) }}
                </div>
                <div class="ft-mcp-note" v-else-if="!overview.adapter.available">
                    {{ $t('MCP_Adapter_Missing') }}
                </div>
                <div class="ft-mcp-note" v-else-if="!hubServedProducts.length">
                    {{ $t('No plugins are in the FluentHub server. Turn MCP on for a plugin and tick it above.') }}
                </div>
            </section>

            <section class="ft-panel ft-mcp-status" v-else-if="activeProduct">
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
                <div class="ft-mcp-note" v-else-if="overview.adapter.outdated">
                    <!-- translators: 1: plugin name that loads the old MCP Adapter, 2: its MCP Adapter version, 3: the MCP Adapter version FluentHub bundles -->
                    {{ $t('MCP_Adapter_Outdated', overview.adapter.source, overview.adapter.version, overview.adapter.bundled_version) }}
                </div>
            </section>

            <section class="ft-panel ft-mcp-connect" v-if="activeProduct">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('Connect client') }}</h2>
                        <p>{{ useOAuth ? $t('MCP_OAuth_Connect_Desc') : (activeProduct.is_hub ? $t('MCP_Hub_Connect_Desc') : $t('MCP_Connect_Desc')) }}</p>
                    </div>
                    <div class="ft-connect-actions" v-if="!useOAuth">
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

                <div class="ft-channels ft-client-tabs" role="tablist" v-if="oauthReady">
                    <button class="ft-channel" :aria-selected="connectMode === 'oauth'" @click="connectMode = 'oauth'">
                        {{ $t('Sign in with OAuth') }}
                    </button>
                    <button class="ft-channel" :aria-selected="connectMode === 'password'" @click="connectMode = 'password'">
                        {{ $t('Application password') }}
                    </button>
                </div>

                <div class="ft-credential-row" v-if="!useOAuth">
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

                <div class="ft-mcp-note ft-mcp-note-ok" v-if="!useOAuth && generatedName">
                    <!-- translators: %s is the name of the new application password, for example FluentHub MCP -->
                    {{ $t('Created the application password "%s" and filled it in above.', generatedName) }}
                    {{ $t('App_Password_Once_Notice') }}
                </div>
                <div class="ft-mcp-note" v-else-if="!useOAuth && appPasswordNotice">
                    {{ appPasswordNotice }}
                </div>

                <div class="ft-channels ft-client-tabs" role="tablist">
                    <button
                        v-for="client in visibleClients"
                        :key="client.key"
                        class="ft-channel"
                        :aria-selected="activeClientInfo.key === client.key"
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
                    version: '',
                    bundled_version: '',
                    source: '',
                    outdated: false,
                },
                hub: {
                    available: false,
                    reason: '',
                    min_adapter: '',
                    endpoint_url: '',
                    products: [],
                    actions_count: 0,
                },
                oauth: {
                    enabled: false,
                    available: false,
                    reason: '',
                    https: true,
                    resource_metadata_url: '',
                    server_metadata_url: '',
                    apps: [],
                },
                products: [],
                app_password: {
                    available: false,
                    reason: '',
                    user_login: '',
                    manage_url: '',
                },
            },
            activeProductSlug: 'fluent-hub',
            activeClient: 'codex',
            connectMode: 'oauth',
            oauthCheck: {
                running: false,
                results: [],
            },
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
            oauthClients: [
                { key: 'claude', label: 'Claude', note: this.$t('In Claude, open Settings > Connectors, add a custom connector and paste this URL.') },
                { key: 'claude-code', label: 'Claude Code', note: this.$t('Run this, then run /mcp in Claude Code and pick fluent-hub to sign in.') },
                { key: 'codex', label: 'Codex', note: this.$t('Run these commands. The second one opens the sign-in page.') },
                { key: 'copilot', label: 'GitHub Copilot', note: this.$t('Paste this JSON into .vscode/mcp.json. VS Code asks you to sign in.') },
                { key: 'cursor', label: 'Cursor', note: this.$t('Paste this JSON into the Cursor MCP settings. Cursor asks you to sign in.') },
                { key: 'generic', label: this.$_x('Generic', 'MCP client type'), note: this.$t('Add this URL to any MCP client that supports OAuth, then sign in.') },
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
        hub() {
            return this.overview.hub || { products: [] };
        },
        // Products the FluentHub server serves right now: ticked here and with
        // their own MCP on. A plugin with MCP off registers no server to serve.
        hubServedProducts() {
            return (this.hub.products || []).filter(product => product.included && product.mcp_enabled);
        },
        // The FluentHub server, shaped like a product so the tab bar and the
        // connection snippets treat it the same way.
        hubProduct() {
            if (!this.hub.endpoint_url || !(this.hub.products || []).length) {
                return null;
            }

            return {
                slug: 'fluent-hub',
                name: this.$t('All Fluent plugins'),
                endpoint_url: this.hub.endpoint_url,
                tools_count: this.hub.actions_count,
                mcp_enabled: this.hub.available && this.hubServedProducts.length > 0,
                status: this.hub.available ? (this.hubServedProducts.length ? 'ready' : 'disabled') : 'adapter_required',
                toggleable: false,
                is_hub: true,
            };
        },
        oauth() {
            return this.overview.oauth || { apps: [] };
        },
        oauthReady() {
            return !!(this.hub.available && this.oauth.enabled && this.oauth.available);
        },
        // Sign-in works on the FluentHub endpoint only; plugin endpoints keep
        // using application passwords.
        useOAuth() {
            return !!(this.activeProduct && this.activeProduct.is_hub && this.oauthReady && this.connectMode === 'oauth');
        },
        visibleClients() {
            return this.useOAuth ? this.oauthClients : this.clients;
        },
        activeProduct() {
            if (this.hubProduct && this.activeProductSlug === this.hubProduct.slug) {
                return this.hubProduct;
            }

            return this.products.find(product => product.slug === this.activeProductSlug) || this.hubProduct || this.products[0] || null;
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

            if (this.overview.adapter.provider === 'other' && this.overview.adapter.source) {
                // translators: %s is the plugin name that bundles the MCP Adapter, for example Rank Math SEO
                return this.$t('Bundled with %s', this.overview.adapter.source);
            }

            return this.$_x('Available', 'MCP adapter status');
        },
        activeClientInfo() {
            return this.visibleClients.find(client => client.key === this.activeClient) || this.visibleClients[0];
        },
        // Only enabled products get an entry in the combined snippet — a disabled
        // server's route isn't registered, so pasting it would just 404.
        enabledProducts() {
            return this.products.filter(product => product.mcp_enabled && product.endpoint_url);
        },
        canCombine() {
            return this.activeProduct && !this.activeProduct.is_hub && this.enabledProducts.length > 1;
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

            const client = this.activeClientInfo ? this.activeClientInfo.key : '';

            if (this.useOAuth) {
                return this.oauthSnippet(entries[0].endpoint_url);
            }

            const user = this.username || '<your-username>';
            const password = this.appPassword || '<your-application-password>';

            if (client === 'codex') {
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

            if (client === 'copilot') {
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

            if (client === 'claude-desktop') {
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

            if (client === 'cursor') {
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

            if (client === 'generic') {
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
        oauthSnippet(url) {
            const client = this.activeClientInfo.key;

            if (client === 'claude-code') {
                return `claude mcp add --transport http fluent-hub ${url}`;
            }

            if (client === 'codex') {
                return [`codex mcp add fluent-hub --url ${url}`, 'codex mcp login fluent-hub'].join('\n');
            }

            if (client === 'copilot') {
                return JSON.stringify({ servers: { 'fluent-hub': { type: 'http', url } } }, null, 2);
            }

            if (client === 'cursor') {
                return JSON.stringify({ mcpServers: { 'fluent-hub': { url } } }, null, 2);
            }

            return url;
        },
        formatDate(timestamp) {
            return new Date(timestamp * 1000).toLocaleString();
        },
        toggleOAuth(enabled) {
            this.saving = true;

            this.$post('fluent_toolkit_mcp_oauth_toggle', {
                enabled: enabled ? 'yes' : 'no',
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.overview = response.status;
                    this.oauthCheck.results = [];
                })
                .catch(error => {
                    this.$handleError(error);
                    this.getOverview();
                })
                .finally(() => {
                    this.saving = false;
                });
        },
        revokeOAuthApp(app) {
            this.saving = true;

            this.$post('fluent_toolkit_mcp_oauth_revoke', {
                id: app.id,
                user_id: app.user_id,
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
        // Runs the first steps an MCP client takes, from this browser and
        // without the login cookie. It cannot tell whether the site is
        // reachable from the internet.
        async runOAuthCheck() {
            const results = [];
            const fetchJson = url => fetch(url, { credentials: 'omit' })
                .then(response => (response.ok ? response.json() : Promise.reject(response.status)));

            this.oauthCheck.running = true;

            try {
                const response = await fetch(this.hub.endpoint_url, {
                    method: 'POST',
                    credentials: 'omit',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' },
                    body: '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}',
                });
                const challenge = response.headers.get('WWW-Authenticate') || '';
                const ok = response.status === 401 && challenge.indexOf('resource_metadata=') !== -1;
                results.push({ key: 'challenge', state: ok ? 'ok' : 'fail', label: ok ? this.$t('The MCP endpoint asks clients to sign in.') : this.$t('MCP_OAuth_Check_Challenge_Fail') });
            } catch (e) {
                results.push({ key: 'challenge', state: 'fail', label: this.$t('MCP_OAuth_Check_Challenge_Fail') });
            }

            try {
                const metadata = await fetchJson(this.oauth.resource_metadata_url);
                const ok = Array.isArray(metadata.authorization_servers) && metadata.authorization_servers.length > 0;
                results.push({ key: 'resource', state: ok ? 'ok' : 'fail', label: ok ? this.$t('Resource metadata loads.') : this.$t('Resource metadata does not load.') });
            } catch (e) {
                results.push({ key: 'resource', state: 'fail', label: this.$t('Resource metadata does not load.') });
            }

            try {
                const metadata = await fetchJson(this.oauth.server_metadata_url);
                const ok = !!(metadata.token_endpoint && metadata.authorization_endpoint);
                results.push({ key: 'server', state: ok ? 'ok' : 'fail', label: ok ? this.$t('Sign-in server metadata loads.') : this.$t('Sign-in server metadata does not load.') });
            } catch (e) {
                results.push({ key: 'server', state: 'fail', label: this.$t('Sign-in server metadata does not load.') });
            }

            // Many clients look at the site root before the REST URL. WordPress
            // can redirect that path to a page, and some clients then fail.
            try {
                const issuer = new URL(this.oauth.server_metadata_url.replace(/\/\.well-known\/openid-configuration$/, ''));
                const response = await fetch(issuer.origin + '/.well-known/oauth-authorization-server' + issuer.pathname, { credentials: 'omit', redirect: 'manual' });
                if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
                    results.push({ key: 'root', state: 'warn', label: this.$t('MCP_OAuth_Check_Root_Redirect') });
                }
            } catch (e) {
                // A network error here says nothing about the REST URLs.
            }

            this.oauthCheck.results = results;
            this.oauthCheck.running = false;
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
        toggleHubProduct(slug, included) {
            const slugs = (this.hub.products || [])
                .filter(product => (product.slug === slug ? included : product.included))
                .map(product => product.slug);

            this.saving = true;

            this.$post('fluent_toolkit_mcp_hub_products', {
                included: slugs,
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
