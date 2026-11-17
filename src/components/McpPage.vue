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

        <div class="ft-mcp-layout" v-loading="loading" :element-loading-text="$t('Loading MCP status…')">
            <section class="ft-panel ft-mcp-hub" v-if="hubProduct">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('FluentHub MCP server') }}</h2>
                        <p>{{ $t('MCP_Hub_Desc') }}</p>
                    </div>
                    <label class="ft-switch">
                        <input
                            type="checkbox"
                            :checked="hub.enabled"
                            :disabled="saving || (!hub.available && !hub.enabled)"
                            :aria-label="$t('FluentHub MCP server')"
                            @change="toggleHub($event.target.checked)"
                        />
                        <span></span>
                    </label>
                </div>

                <div class="ft-mcp-note" v-if="!overview.adapter.available">
                    {{ $t('MCP_Adapter_Missing') }}
                </div>
                <div class="ft-mcp-note" v-else-if="hub.reason === 'adapter_outdated'">
                    <!-- translators: 1: the MCP Adapter version that runs, 2: the minimum MCP Adapter version the FluentHub server needs -->
                    {{ $t('MCP_Hub_Adapter_Outdated', overview.adapter.version, hub.min_adapter) }}
                </div>
                <div class="ft-mcp-note" v-else-if="hub.enabled && !hubServedProducts.length">
                    {{ $t('No plugin is on yet. Turn on at least one plugin below.') }}
                </div>
                <div class="ft-mcp-note" v-else-if="signInBlockedNote">
                    {{ signInBlockedNote }}
                </div>
            </section>

            <section class="ft-panel ft-mcp-plugins" v-if="products.length">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('Plugins') }}</h2>
                        <p>{{ hub.enabled ? $t('The FluentHub MCP server reaches every plugin that is on here.') : $t('Turn MCP on for each plugin your AI apps may use.') }}</p>
                    </div>
                </div>

                <div class="ft-mcp-rows">
                    <div class="ft-mcp-row" v-for="product in products" :key="product.slug">
                        <img class="ft-mcp-row-logo" v-if="productLogo(product)" :src="productLogo(product)" alt="" />
                        <span class="ft-mcp-row-logo ft-mcp-row-logo-blank" v-else aria-hidden="true">{{ product.name.charAt(0) }}</span>
                        <div class="ft-mcp-row-body">
                        <div class="ft-mcp-row-text">
                            <strong>{{ product.name }}</strong>
                            <!-- translators: %s is the number of MCP actions, for example 28 -->
                            <span v-if="product.mcp_enabled">{{ $_n('%s action', '%s actions', product.tools_count) }}</span>
                            <span v-else-if="product.status === 'disabled'">{{ $t('MCP is off') }}</span>
                            <span v-else>{{ statusLabel(product.status) }}</span>
                            <span v-if="hub.enabled && product.mcp_enabled && leftOutOfHub(product)">{{ $t('Left out of the FluentHub server (see Advanced)') }}</span>
                            <span v-if="!product.toggleable && product.slug !== 'fluent-crm'">{{ $t('Turned on and off in the plugin settings') }}</span>
                        </div>
                        <p class="ft-mcp-row-desc" v-if="productDesc(product)">{{ productDesc(product) }}</p>
                        </div>
                        <label class="ft-switch">
                            <input
                                type="checkbox"
                                :checked="product.mcp_enabled"
                                :disabled="saving || !product.toggleable"
                                :aria-label="product.name"
                                @change="toggleProduct(product, $event.target.checked)"
                            />
                            <span></span>
                        </label>
                    </div>
                </div>

                <div class="ft-mcp-note" v-if="!hubProduct && !overview.adapter.available">
                    {{ $t('MCP_Adapter_Missing') }}
                </div>
                <div class="ft-mcp-note" v-else-if="overview.adapter.outdated">
                    <!-- translators: 1: plugin name that loads the old MCP Adapter, 2: its MCP Adapter version, 3: the MCP Adapter version FluentHub bundles -->
                    {{ $t('MCP_Adapter_Outdated', overview.adapter.source, overview.adapter.version, overview.adapter.bundled_version) }}
                </div>
            </section>

            <section class="ft-panel ft-mcp-connect" v-if="products.length">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('Connect an AI app') }}</h2>
                        <p>{{ mode === 'oauth' ? $t('Choose your app and copy the line into it. The app opens your WordPress login, and you approve it there.') : $t('Choose what to connect to, add an application password, choose your app, then copy the details.') }}</p>
                    </div>
                </div>

                <template v-if="mode === 'password' && targets.length">
                    <div class="ft-mcp-field">
                        <label class="ft-mcp-field-label" for="ft-mcp-target">{{ $t('Connect to') }}</label>
                        <div>
                            <select id="ft-mcp-target" class="ft-mcp-select" :value="target" @change="targetSlug = $event.target.value">
                                <option v-for="option in targets" :key="option.key" :value="option.key">{{ option.label }}</option>
                            </select>
                            <div class="ft-mcp-help"><p>{{ targetNote }}</p></div>
                        </div>
                    </div>

                    <div class="ft-mcp-field">
                        <span class="ft-mcp-field-label">{{ $t('Credentials') }}</span>
                        <div>
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
                            <div class="ft-mcp-help ft-mcp-help-ok" v-if="generatedName">
                                <p>
                                    <!-- translators: %s is the name of the new application password, for example FluentHub MCP -->
                                    {{ $t('Created the application password "%s" and filled it in above.', generatedName) }}
                                    {{ $t('App_Password_Once_Notice') }}
                                </p>
                            </div>
                            <div class="ft-mcp-help" v-else-if="appPasswordNotice">
                                <p class="ft-mcp-oauth-warn">{{ appPasswordNotice }}</p>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-if="canShowSnippet">
                    <div class="ft-mcp-field">
                        <span class="ft-mcp-field-label">{{ $t('AI app') }}</span>
                        <div class="ft-channels" role="tablist">
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
                    </div>

                    <div class="ft-snippet-box">
                        <div class="ft-snippet-head">
                            <div>{{ activeClientInfo.note }}</div>
                            <button class="ft-btn ft-btn-primary" @click="copySnippet()">{{ $t('Copy') }}</button>
                        </div>
                        <pre class="ft-snippet"><code>{{ activeSnippet }}</code></pre>
                    </div>
                </template>
                <div class="ft-mcp-note" v-else>
                    {{ $t('Turn MCP on for a plugin above to get the connection details.') }}
                </div>

                <div class="ft-mcp-switchmode" v-if="signInUsable">
                    <button type="button" class="ft-mcp-link" @click="connectMode = mode === 'oauth' ? 'password' : 'oauth'">
                        {{ mode === 'oauth' ? $t('Connect with an application password instead') : $t('Back to sign in with WordPress') }}
                    </button>
                </div>
            </section>

            <section class="ft-panel ft-mcp-apps" v-if="(hub.enabled && oauth.enabled) || oauth.apps.length">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('Connected apps') }}</h2>
                        <p>{{ $t('Apps that people approved on the WordPress login. Disconnect an app to end its access.') }}</p>
                    </div>
                </div>

                <p class="ft-mcp-blank" v-if="!oauth.apps.length">{{ $t('No apps are connected yet.') }}</p>
                <div class="ft-mcp-table" v-else>
                    <table>
                        <thead>
                            <tr>
                                <th>{{ $t('App') }}</th>
                                <th>{{ $t('User') }}</th>
                                <th>{{ $t('Last used') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="app in visibleApps" :key="app.id">
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
                    <p class="ft-mcp-apps-more" v-if="oauth.apps.length > appsPageSize">
                        <button class="ft-mcp-link" @click="showAllApps = !showAllApps">
                            <!-- translators: %s is the number of connected apps, for example 34 -->
                            {{ showAllApps ? $t('Show fewer') : $t('Show all %s apps', oauth.apps.length) }}
                        </button>
                    </p>
                    <p class="ft-mcp-apps-more" v-if="oauth.apps_truncated && showAllApps">
                        {{ $t('This list shows the newest 200 apps. Older ones are on each user profile.') }}
                    </p>
                </div>
            </section>

            <section class="ft-panel ft-mcp-access" v-if="hub.enabled && (hub.roles || []).length">
                <div class="ft-panel-head">
                    <div>
                        <h2>{{ $t('Who can connect') }}</h2>
                        <p>{{ $t('MCP_Roles_Desc') }}</p>
                    </div>
                </div>
                <div class="ft-mcp-roles">
                    <label
                        v-for="role in hub.roles"
                        :key="role.slug"
                        :class="{ 'is-on': role.allowed, 'is-locked': role.locked }"
                    >
                        <input
                            type="checkbox"
                            :checked="role.allowed"
                            :disabled="saving || role.locked"
                            @change="toggleRole(role.slug, $event.target.checked)"
                        />
                        {{ role.name }}
                        <em v-if="role.locked">{{ $t('Always') }}</em>
                    </label>
                </div>
            </section>

            <details class="ft-panel ft-mcp-advanced" v-if="products.length">
                <summary>{{ $t('Advanced') }}</summary>

                <div class="ft-mcp-advanced-block" v-if="hub.enabled">
                    <div class="ft-mcp-advanced-switch">
                        <div>
                            <strong>{{ $t('Sign in with WordPress') }}</strong>
                            <p>{{ $t('MCP_OAuth_Desc') }}</p>
                        </div>
                        <label class="ft-switch">
                            <input
                                type="checkbox"
                                :checked="oauth.enabled"
                                :disabled="saving || (!oauth.available && !oauth.enabled)"
                                :aria-label="$t('Sign in with WordPress')"
                                @change="toggleOAuth($event.target.checked)"
                            />
                            <span></span>
                        </label>
                    </div>
                </div>

                <div class="ft-mcp-advanced-block">
                    <strong>{{ $t('Endpoints') }}</strong>
                    <p>{{ hub.enabled ? $t('MCP_Endpoints_Desc') : $t('Each plugin has its own endpoint.') }}</p>
                    <div class="ft-mcp-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ $t('Server') }}</th>
                                    <th>{{ $t('Endpoint') }}</th>
                                    <th v-if="hub.enabled">{{ $t('In the FluentHub server') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="hubProduct && hub.enabled">
                                    <td><strong>{{ $t('FluentHub MCP server') }}</strong></td>
                                    <td class="ft-mono">{{ hub.endpoint_url }}</td>
                                    <td></td>
                                </tr>
                                <tr v-for="product in products" :key="product.slug">
                                    <td><strong>{{ product.name }}</strong></td>
                                    <td class="ft-mono">{{ product.endpoint_url }}</td>
                                    <td v-if="hub.enabled">
                                        <input
                                            v-if="hubEntry(product)"
                                            type="checkbox"
                                            :checked="hubEntry(product).included"
                                            :disabled="saving || !hub.available"
                                            :aria-label="product.name"
                                            @change="toggleHubProduct(product.slug, $event.target.checked)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ft-mcp-advanced-block">
                    <strong>{{ $t('MCP adapter') }}</strong>
                    <p>{{ adapterLabel }}<template v-if="overview.adapter.version"> · {{ overview.adapter.version }}</template></p>
                </div>

                <div class="ft-mcp-advanced-block" v-if="oauthReady">
                    <strong>{{ $t('Sign-in setup') }}</strong>
                    <p>{{ $t('Runs the first steps an AI app takes to sign in, from this browser.') }}</p>
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
                </div>
            </details>
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
            showAllApps: false,
            appsPageSize: 10,
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
                    enabled: false,
                    roles: [],
                },
                oauth: {
                    enabled: false,
                    available: false,
                    reason: '',
                    https: true,
                    resource_metadata_url: '',
                    server_metadata_url: '',
                    clients: [],
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
            // '' shows the first app of the list for the chosen sign-in method.
            activeClient: '',
            // 'password' once the admin asks for that way; see the `mode` computed.
            connectMode: '',
            targetSlug: '',
            oauthCheck: {
                running: false,
                results: [],
            },
            loading: false,
            saving: false,
            creatingPassword: false,
            showPassword: false,
            generatedName: '',
            username: window.fluentToolkitVars.current_user_login || '',
            appPassword: '',
            // Application password way. The order matches the sign-in list:
            // Claude first. Claude on the web has no tab here: its connectors
            // cannot send a password header, so it connects with sign-in only.
            clients: [
                { key: 'claude-code', label: 'Claude Code', note: this.$t('Run this in your terminal. It adds the server to Claude Code with your application password.') },
                { key: 'claude-desktop', label: 'Claude Desktop', note: this.$t('Paste this JSON into claude_desktop_config.json and restart Claude Desktop.') },
                { key: 'codex', label: 'Codex', note: this.$t('Paste these fields into the Codex custom MCP settings.') },
                { key: 'copilot', label: 'GitHub Copilot', note: this.$t('Paste this JSON into the GitHub Copilot MCP configuration in VS Code.') },
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
                // The name the AI app gives the connection. It carries the
                // site's address, so two sites do not collide in one app.
                client_name: this.hub.server_name || 'fluent-hub',
                name: this.$t('All Fluent plugins'),
                endpoint_url: this.hub.endpoint_url,
                tools_count: this.hub.actions_count,
                mcp_enabled: !!(this.hub.enabled && this.hub.available && this.hubServedProducts.length > 0),
                status: this.hub.available ? (this.hub.enabled && this.hubServedProducts.length ? 'ready' : 'disabled') : 'adapter_required',
                toggleable: false,
                is_hub: true,
            };
        },
        oauth() {
            return this.overview.oauth || { apps: [], clients: [] };
        },
        oauthReady() {
            return !!(this.hub.enabled && this.hub.available && this.oauth.enabled && this.oauth.available);
        },
        // An app can sign in with WordPress: the server is on, serves a plugin,
        // and sign-in can run.
        signInUsable() {
            return this.oauthReady && this.hubServes;
        },
        // Sign in with WordPress whenever it can be used, unless the admin
        // asked for the application password way.
        mode() {
            return this.signInUsable && this.connectMode !== 'password' ? 'oauth' : 'password';
        },
        useOAuth() {
            return this.mode === 'oauth';
        },
        // Why apps cannot sign in with WordPress although the server is on.
        signInBlockedNote() {
            if (!this.hub.enabled || !this.oauth.enabled) {
                return '';
            }

            if (this.oauth.reason === 'plain_permalinks') {
                return this.$t('MCP_OAuth_Plain_Permalinks');
            }

            if (this.oauth.reason === 'app_passwords_off') {
                return this.$t('MCP_OAuth_App_Passwords_Off');
            }

            return this.oauthReady && !this.oauth.https ? this.$t('MCP_OAuth_No_Https') : '';
        },
        visibleClients() {
            // The sign-in instructions come from PHP (OAuthServer::clients()),
            // so each product's own screen shows the same wording.
            return this.useOAuth ? (this.oauth.clients || []) : this.clients;
        },
        hubServes() {
            return !!(this.hubProduct && this.hubProduct.mcp_enabled);
        },
        // What an application password connection can point at: the FluentHub
        // server, one plugin, or every plugin as its own entry.
        targets() {
            const list = [];

            if (this.hubServes) {
                list.push({ key: 'fluent-hub', label: this.$t('All plugins through one endpoint (recommended)') });
            }

            this.enabledProducts.forEach(product => {
                // translators: %s is the plugin name, for example FluentCRM
                list.push({ key: product.slug, label: this.$t('%s only', product.name) });
            });

            if (this.enabledProducts.length > 1) {
                list.push({ key: '__each', label: this.$t('Every plugin as its own entry') });
            }

            return list;
        },
        target() {
            if (this.targets.some(option => option.key === this.targetSlug)) {
                return this.targetSlug;
            }

            return this.targets.length ? this.targets[0].key : '';
        },
        targetNote() {
            if (this.target === 'fluent-hub') {
                return this.$t('MCP_Hub_Connect_Desc');
            }

            if (this.target === '__each') {
                return this.$t('MCP_Connect_Desc');
            }

            return this.$t('Connects to this plugin only, on its own endpoint.');
        },
        canShowSnippet() {
            return this.mode === 'oauth' || this.targets.length > 0;
        },
        appPasswordSupport() {
            return this.overview.app_password || {};
        },
        canCreateAppPassword() {
            return !!this.appPasswordSupport.available;
        },
        appPasswordsUrl() {
            return this.appPasswordSupport.manage_url
                || (this.products[0] && this.products[0].app_passwords_url)
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
        visibleApps() {
            const apps = this.oauth.apps || [];

            return this.showAllApps ? apps : apps.slice(0, this.appsPageSize);
        },
        activeClientInfo() {
            return this.visibleClients.find(client => client.key === this.activeClient)
                || this.visibleClients[0]
                || { key: '', label: '', note: '', snippet: '' };
        },
        // Only enabled products get an entry in the combined snippet — a disabled
        // server's route isn't registered, so pasting it would just 404.
        enabledProducts() {
            return this.products.filter(product => product.mcp_enabled && product.endpoint_url);
        },
        snippetEntries() {
            if (this.useOAuth || this.target === 'fluent-hub') {
                return this.hubProduct ? [this.hubProduct] : [];
            }

            if (this.target === '__each') {
                return this.enabledProducts;
            }

            return this.enabledProducts.filter(product => product.slug === this.target);
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
                return this.activeClientInfo ? this.activeClientInfo.snippet : '';
            }

            const user = this.username || '<your-username>';
            const password = this.appPassword || '<your-application-password>';

            if (client === 'claude-code') {
                return entries.map(product => (
                    `claude mcp add --transport http ${product.client_name || product.slug} ${product.endpoint_url} --header "Authorization: Basic ${this.authHeader}"`
                )).join('\n');
            }

            if (client === 'codex') {
                const blocks = entries.map(product => [
                    `Name: ${product.client_name || product.slug}`,
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
                servers[product.client_name || product.slug] = build(product);
                return servers;
            }, {});
        },
        formatDate(timestamp) {
            return new Date(timestamp * 1000).toLocaleString();
        },
        toggleHub(enabled) {
            this.saving = true;

            this.$post('fluent_toolkit_mcp_hub_toggle', {
                enabled: enabled ? 'yes' : 'no',
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.overview = response.status;
                    this.connectMode = '';
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
        toggleRole(slug, allowed) {
            const roles = (this.hub.roles || [])
                .filter(role => (role.slug === slug ? allowed : role.allowed))
                .map(role => role.slug);

            this.saving = true;

            this.$post('fluent_toolkit_mcp_hub_roles', { roles })
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
                })
                .catch(error => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        // The FluentHub server's record of a plugin, or undefined for a plugin
        // it does not know.
        hubEntry(product) {
            return (this.hub.products || []).find(entry => entry.slug === product.slug);
        },
        productLogo(product) {
            const files = {
                'fluent-crm': 'fluentcrm_icon.svg',
                'fluent-boards': 'fluentboards_icon.svg',
                'fluent-booking': 'fluentbooking_icon.svg',
                'fluent-cart': 'fluentcart_icon.svg',
                'fluentform': 'fluentforms_icon.svg',
                'fluent-community': 'fluentcommunity_icon.svg',
                'fluent-support': 'fluentsupport_icon.svg',
            };
            const vars = window.fluentToolkitVars || {};
            const base = vars.plugin_url || vars.pluginUrl || '';

            return files[product.slug] && base ? `${base}dist/images/${files[product.slug]}` : '';
        },
        // What an AI app can work with in each plugin, for a person.
        productDesc(product) {
            const lines = {
                'fluent-crm': this.$t('Contacts, tags, lists, email campaigns and automations.'),
                'fluent-boards': this.$t('Project boards, tasks, subtasks and comments.'),
                'fluent-booking': this.$t('Calendars, bookings and open time slots.'),
                'fluent-cart': this.$t('Orders, products, customers, subscriptions and sales reports.'),
                'fluentform': this.$t('Forms, submissions, notifications and form reports.'),
                'fluent-community': this.$t('Spaces, posts, comments, members and courses.'),
                'fluent-support': this.$t('Tickets, replies, customers and support reports.'),
            };

            return lines[product.slug] || '';
        },
        leftOutOfHub(product) {
            const entry = this.hubEntry(product);

            return !!(this.hub.available && entry && !entry.included);
        },
        // One switch per plugin. Turning a plugin on also puts it back in the
        // FluentHub server, so "on" always means an AI app can reach it.
        toggleProduct(product, enabled) {
            const putBack = enabled && this.leftOutOfHub(product);

            this.saving = true;

            this.$post('fluent_toolkit_mcp_toggle', {
                slug: product.slug,
                enabled: enabled ? 'yes' : 'no',
            })
                .then(response => {
                    if (!putBack) {
                        return response;
                    }

                    return this.$post('fluent_toolkit_mcp_hub_products', { slug: product.slug, included: 'yes' })
                        .then(hubResponse => ({ message: response.message, status: hubResponse.status }));
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
            this.saving = true;

            this.$post('fluent_toolkit_mcp_hub_products', {
                slug,
                included: included ? 'yes' : 'no',
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
