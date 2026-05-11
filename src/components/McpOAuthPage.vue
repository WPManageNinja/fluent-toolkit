<template>
    <div class="ft-app ft-oauth-app" v-loading="loading">
        <header class="ft-topbar">
            <div class="ft-brand">
                <div class="ft-brand-mark"></div>
                <div class="ft-brand-name">
                    Fluent Toolkit
                    <span class="ft-brand-version">v{{ appVars.version }}</span>
                </div>
            </div>
            <div class="ft-topbar-actions">
                <a class="ft-btn ft-btn-ghost ft-btn-sm" :href="dashboardUrl" title="Back to Beta & Add-ons">
                    <IconArrowLeft />
                    Beta & Add-ons
                </a>
            </div>
        </header>

        <nav class="ft-crumbs" aria-label="Breadcrumb">
            <a :href="dashboardUrl">Toolkit</a>
            <span class="sep">/</span>
            <span class="current">MCP Auth Bridge</span>
        </nav>

        <section class="ft-oauth-pagehead">
            <div class="ft-pagehead-text">
                <div class="ft-hero-eyebrow">Add-on - Beta</div>
                <h1>MCP Auth Bridge</h1>
                <p>Adds an OAuth 2.1 connect flow in front of registered WordPress MCP adapter endpoints so AI clients can authorize and call tools securely.</p>
            </div>
            <div class="ft-bridge-status">
                <span class="ft-pill" :class="{ 'is-off': !status.enabled }">
                    <span class="dot"></span>{{ status.label }}
                </span>
                <span class="ft-endpoint-mini ft-mono">{{ status.route_label }}</span>
            </div>
        </section>

        <section class="ft-mcp-lite-panel">
            <div class="ft-mcp-lite-head">
                <div>
                    <div class="ft-hero-eyebrow">MCP Connection</div>
                    <h2>FluentCRM MCP endpoint</h2>
                    <p>Adapter and discovery status for the OAuth protected FluentCRM MCP route.</p>
                </div>
                <span class="ft-pill" :class="{ 'is-off': !connection.oauth_enabled }">
                    <span class="dot"></span>{{ connection.oauth_enabled ? 'OAuth enabled' : 'OAuth disabled' }}
                </span>
            </div>

            <div v-if="connection.standalone_oauth_bridge_active" class="ft-mcp-warning">
                Deactivate and remove the standalone FluentCRM MCP OAuth Bridge plugin before enabling Toolkit MCP OAuth.
            </div>
            <div v-else-if="connection.route_notice" class="ft-mcp-warning">
                {{ connection.route_notice }}
            </div>

            <div class="ft-mcp-grid">
                <div>
                    <span>Adapter</span>
                    <strong>{{ connection.adapter_available ? connection.adapter_provider_label : 'Unavailable' }}</strong>
                </div>
                <div>
                    <span>Abilities API</span>
                    <strong>{{ connection.abilities_available ? 'Loaded' : 'Missing' }}</strong>
                </div>
                <div>
                    <span>MCP URL</span>
                    <code>{{ connection.mcp_url || 'Not exposed' }}</code>
                </div>
                <div>
                    <span>Dynamic registration</span>
                    <code>{{ connection.registration_endpoint }}</code>
                </div>
            </div>
        </section>

        <form @submit.prevent="saveSettings">
            <section class="ft-card">
                <div class="ft-card-head">
                    <div>
                        <h2>Bridge Settings</h2>
                        <p>Configure how OAuth tokens authenticate calls to your MCP endpoints.</p>
                    </div>
                </div>

                <div class="ft-card-body">
                    <div class="ft-field">
                        <div class="ft-field-label">
                            <span class="lbl">Enable OAuth bridge</span>
                            <span class="hint">Master switch for token validation.</span>
                        </div>
                        <div class="ft-field-control">
                            <label class="ft-toggle">
                                <input type="checkbox" v-model="form.enabled" />
                                <span class="ft-toggle-track"></span>
                                <span class="ft-toggle-text">Authenticate bearer tokens for configured MCP routes</span>
                            </label>
                        </div>
                    </div>

                    <div class="ft-field">
                        <div class="ft-field-label">
                            <span class="lbl">Protected MCP routes</span>
                            <span class="hint">Each registered Fluent MCP adapter.</span>
                        </div>
                        <div class="ft-field-control">
                            <div class="ft-routes">
                                <div
                                    v-for="route in routes"
                                    :key="route.key"
                                    class="ft-route-row"
                                    :data-on="routeIsSelected(route.key) && route.available ? 'true' : 'false'"
                                >
                                    <div class="ft-route-icon" :class="route.class">{{ route.icon }}</div>
                                    <div class="ft-route-info">
                                        <div class="ft-route-name">
                                            {{ route.name }}
                                            <span v-if="route.version" class="ft-route-version ft-mono">v{{ route.version }}</span>
                                            <span v-if="!route.available" class="ft-route-version ft-mono">{{ route.status_label || 'Unavailable' }}</span>
                                        </div>
                                        <div v-if="route.available" class="ft-route-path ft-mono">{{ route.route }}</div>
                                        <div v-else class="ft-route-path is-muted">{{ route.status_label || 'Unavailable' }}</div>
                                    </div>
                                    <label class="ft-toggle" :aria-label="`Protect ${route.name} with OAuth`">
                                        <input
                                            type="checkbox"
                                            :checked="routeIsSelected(route.key)"
                                            :disabled="!route.available"
                                            @change="toggleRoute(route.key, $event.target.checked)"
                                        />
                                        <span class="ft-toggle-track"></span>
                                    </label>
                                </div>

                                <!-- Future Fluent MCP providers can register routes through fluent_toolkit/mcp_oauth_routes after their endpoints ship. -->
                            </div>
                            <div class="ft-field-help">OAuth only guards routes registered here. Other WordPress REST endpoints are not exposed by this bridge.</div>
                        </div>
                    </div>

                    <div class="ft-field">
                        <div class="ft-field-label">
                            <label class="lbl" for="required_capability">Required capability</label>
                            <span class="hint">WordPress capability gate.</span>
                        </div>
                        <div class="ft-field-control">
                            <input id="required_capability" v-model="form.required_capability" class="ft-input ft-mono" type="text" />
                            <div class="ft-field-help">Only users with this capability can authorize a client and use OAuth tokens.</div>
                        </div>
                    </div>

                    <div class="ft-field">
                        <div class="ft-field-label">
                            <label class="lbl" for="access_token_lifetime_value">Access token lifetime</label>
                            <span class="hint">Tokens auto-expire after this window.</span>
                        </div>
                        <div class="ft-field-control">
                            <div class="ft-lifetime">
                                <input
                                    id="access_token_lifetime_value"
                                    v-model.number="form.access_token_lifetime_value"
                                    class="ft-input ft-mono"
                                    type="number"
                                    min="1"
                                    max="90"
                                />
                                <select v-model="form.access_token_lifetime_unit" class="ft-select" aria-label="Lifetime unit">
                                    <option value="minutes">minutes</option>
                                    <option value="hours">hours</option>
                                    <option value="days">days</option>
                                </select>
                            </div>
                            <div class="ft-field-help">
                                Maximum lifetime is <strong>90 days</strong>. Current effective lifetime:
                                <strong>{{ settings.access_token_ttl_label }}</strong>.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ft-save-bar">
                    <div class="ft-save-meta">
                        <IconInfo />
                        <span>Changes apply to new tokens. Active tokens keep their current expiry and resource.</span>
                    </div>
                    <div class="ft-save-actions">
                        <button type="button" class="ft-btn ft-btn-ghost" @click="resetForm">Discard</button>
                        <button type="submit" class="ft-btn ft-btn-accent" :disabled="saving">
                            <IconCheck />
                            {{ saving ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </div>
            </section>
        </form>

        <section class="ft-card">
            <div class="ft-card-head">
                <div>
                    <h2>Connector URLs</h2>
                    <p>Paste these into your MCP client. The well-known endpoints handle discovery automatically.</p>
                </div>
                <button v-if="endpoints.length" type="button" class="ft-btn ft-btn-ghost ft-btn-sm" @click="copyAllEndpoints">
                    <IconCopy />
                    Copy all
                </button>
            </div>
            <div class="ft-card-body has-top-padding">
                <div class="ft-endpoint-list">
                    <div v-if="mcpEndpoints.length === 0" class="ft-endpoint-empty">
                        No MCP route is currently available to protect. Enable MCP for AI Agents in FluentCRM before connecting an MCP client.
                    </div>

                    <div
                        v-for="endpoint in endpoints"
                        :key="endpoint.label + endpoint.url"
                        class="ft-endpoint"
                    >
                        <div class="ft-endpoint-label">
                            <component :is="endpointIcon(endpoint.icon)" />
                            {{ endpoint.label }}
                        </div>
                        <div class="ft-endpoint-url ft-mono">{{ endpoint.url }}</div>
                        <button type="button" class="ft-copy-btn" title="Copy" aria-label="Copy" @click="copyText(endpoint.url)">
                            <IconCopy />
                        </button>
                    </div>

                    <button type="button" class="ft-disclosure" :aria-expanded="showDiscovery ? 'true' : 'false'" @click="showDiscovery = !showDiscovery">
                        <span>{{ showDiscovery ? 'Hide discovery URLs' : 'Show discovery URLs' }}</span>
                        <IconChevron />
                    </button>

                    <div class="ft-endpoint-extra" :class="{ open: showDiscovery }">
                        <div
                            v-for="endpoint in discoveryEndpoints"
                            :key="endpoint.label + endpoint.url"
                            class="ft-endpoint"
                        >
                            <div class="ft-endpoint-label">
                                <component :is="endpointIcon(endpoint.icon)" />
                                {{ endpoint.label }}
                            </div>
                            <div class="ft-endpoint-url ft-mono">{{ endpoint.url }}</div>
                            <button type="button" class="ft-copy-btn" title="Copy" aria-label="Copy" @click="copyText(endpoint.url)">
                                <IconCopy />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ft-card">
            <div class="ft-card-head">
                <div>
                    <h2>Registered Clients</h2>
                    <p>Apps that completed dynamic registration. Revoking a client also revokes its active approvals.</p>
                </div>
                <span class="ft-id-chip ft-mono">{{ clients.length }} {{ clients.length === 1 ? 'client' : 'clients' }}</span>
            </div>
            <div class="ft-table-wrap">
                <table class="ft-table">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th>Client ID</th>
                        <th>Redirect URI</th>
                        <th>Registered</th>
                        <th class="right">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="clients.length === 0">
                        <td colspan="5" class="ft-empty-cell">
                            <div class="ft-empty-icon"><IconUserPlus /></div>
                            <strong>No registered clients yet.</strong>
                            Clients appear here after dynamic registration.
                        </td>
                    </tr>
                    <tr v-for="client in clients" :key="client.client_id">
                        <td>
                            <div class="ft-client-cell">
                                <div class="ft-client-avatar">{{ client.client_initials }}</div>
                                <div>
                                    <div class="ft-client-name">{{ client.client_name }}</div>
                                    <div class="ft-client-meta">{{ client.client_meta }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="ft-id-chip ft-mono">{{ client.client_id_short }}</span></td>
                        <td class="muted ft-mono">
                            {{ client.redirect_uri || 'No redirect URI' }}
                            <div v-if="client.redirect_count > 1" class="ft-client-meta">+{{ client.redirect_count - 1 }} more</div>
                        </td>
                        <td class="muted">{{ client.created_at }}</td>
                        <td class="right">
                            <button type="button" class="ft-btn ft-btn-ghost ft-btn-sm" @click="revokeClient(client)">Revoke</button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ft-card">
            <div class="ft-card-head">
                <div>
                    <h2>Approved Access</h2>
                    <p>Active OAuth access tokens issued after a WordPress user approved a client.</p>
                </div>
                <span class="ft-id-chip ft-mono">{{ tokens.length }} active {{ tokens.length === 1 ? 'token' : 'tokens' }}</span>
            </div>
            <div class="ft-table-wrap">
                <table class="ft-table">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th>User</th>
                        <th>Scope</th>
                        <th>Issued</th>
                        <th>Expires</th>
                        <th class="right">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="tokens.length === 0">
                        <td colspan="6" class="ft-empty-cell">
                            <div class="ft-empty-icon"><IconLock /></div>
                            <strong>No active approvals yet.</strong>
                            Approved access tokens appear here after a client connects.
                        </td>
                    </tr>
                    <tr v-for="token in tokens" :key="token.token_hash">
                        <td>
                            <div class="ft-client-cell">
                                <div class="ft-client-avatar">{{ token.client_initials }}</div>
                                <div>
                                    <div class="ft-client-name">{{ token.client_name }}</div>
                                    <div class="ft-client-meta ft-mono">{{ token.client_id_short }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="ft-user-cell">
                                <div class="ft-user-avatar">{{ token.user_initials }}</div>
                                <div>
                                    <div>{{ token.user_name }}</div>
                                    <div v-if="token.user_email" class="ft-client-meta ft-mono">{{ token.user_email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span v-for="scope in token.scopes" :key="scope" class="ft-scope-chip ft-mono">{{ scope }}</span>
                        </td>
                        <td class="muted">{{ token.created_at }}</td>
                        <td class="muted">{{ token.expires_at }}</td>
                        <td class="right">
                            <button type="button" class="ft-btn ft-btn-ghost ft-btn-sm" @click="revokeToken(token)">Revoke</button>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ft-danger-zone">
            <div>
                <strong>Reset OAuth state</strong>
                <span>Use these when rotating secrets or migrating environments. Connected clients will need to re-authorize.</span>
            </div>
            <div class="ft-danger-actions">
                <button type="button" class="ft-btn ft-btn-danger ft-btn-sm" @click="clearClients">Clear registered clients</button>
                <button type="button" class="ft-btn ft-btn-danger ft-btn-sm" @click="clearTokens">Revoke all tokens</button>
            </div>
        </section>
    </div>
</template>

<script>
import {h} from 'vue';

const makeIcon = (name, paths, size = 14) => ({
    name,
    render() {
        return h('svg', {
            width: size,
            height: size,
            viewBox: '0 0 24 24',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': 2,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
            'aria-hidden': 'true',
            innerHTML: paths,
        });
    },
});

const IconCheck = makeIcon('IconCheck', '<polyline points="20 6 9 17 4 12" />', 13);
const IconChevron = makeIcon('IconChevron', '<polyline points="6 9 12 15 18 9" />', 12);
const IconCopy = makeIcon('IconCopy', '<rect x="9" y="9" width="13" height="13" rx="2" ry="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />', 13);
const IconGlobe = makeIcon('IconGlobe', '<circle cx="12" cy="12" r="10" /><line x1="2" y1="12" x2="22" y2="12" /><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />');
const IconInfo = makeIcon('IconInfo', '<circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />', 13);
const IconLink = makeIcon('IconLink', '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />');
const IconLock = makeIcon('IconLock', '<rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />');
const IconArrowLeft = makeIcon('IconArrowLeft', '<path d="m12 19-7-7 7-7" /><path d="M19 12H5" />', 13);
const IconShield = makeIcon('IconShield', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />');
const IconUserPlus = makeIcon('IconUserPlus', '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="8.5" cy="7" r="4" /><line x1="20" y1="8" x2="20" y2="14" /><line x1="23" y1="11" x2="17" y2="11" />');

const iconMap = {
    globe: IconGlobe,
    link: IconLink,
    lock: IconLock,
    shield: IconShield,
    'user-plus': IconUserPlus,
};

export default {
    name: 'McpOAuthPage',
    components: {
        IconCheck,
        IconChevron,
        IconCopy,
        IconInfo,
        IconLock,
        IconArrowLeft,
        IconUserPlus,
    },
    data() {
        return {
            loading: false,
            saving: false,
            showDiscovery: false,
            dashboardUrl: '',
            settings: {},
            status: {},
            connection: {},
            routes: [],
            endpoints: [],
            discoveryEndpoints: [],
            clients: [],
            tokens: [],
            form: {
                enabled: false,
                protected_routes: [],
                required_capability: 'manage_options',
                access_token_lifetime_value: 30,
                access_token_lifetime_unit: 'days',
            },
        };
    },
    computed: {
        mcpEndpoints() {
            return this.endpoints.filter(endpoint => endpoint.label.indexOf('MCP URL') !== -1);
        },
    },
    methods: {
        hydrate(payload) {
            if (!payload) {
                return;
            }

            this.dashboardUrl = payload.dashboard_url || this.appVars.dashboard_url || '';
            this.settings = payload.settings || {};
            this.status = payload.status || {};
            this.connection = payload.connection || {};
            this.routes = payload.routes || [];
            this.endpoints = payload.endpoints || [];
            this.discoveryEndpoints = payload.discovery_endpoints || [];
            this.clients = payload.clients || [];
            this.tokens = payload.tokens || [];
            this.resetForm();
        },
        resetForm() {
            this.form = {
                enabled: !!this.settings.enabled,
                protected_routes: [...(this.settings.protected_routes || [])],
                required_capability: this.settings.required_capability || 'manage_options',
                access_token_lifetime_value: this.settings.access_token_lifetime_value || 30,
                access_token_lifetime_unit: this.settings.access_token_lifetime_unit || 'days',
            };
        },
        fetchData() {
            this.loading = true;
            this.$get('fluent_toolkit_mcp_oauth_get')
                .then(response => {
                    this.hydrate(response);
                })
                .catch(error => this.$handleError(error))
                .finally(() => {
                    this.loading = false;
                });
        },
        routeIsSelected(key) {
            return this.form.protected_routes.includes(key);
        },
        endpointIcon(icon) {
            return iconMap[icon] || IconLink;
        },
        toggleRoute(key, checked) {
            if (checked && !this.form.protected_routes.includes(key)) {
                this.form.protected_routes.push(key);
            } else if (!checked) {
                this.form.protected_routes = this.form.protected_routes.filter(routeKey => routeKey !== key);
            }
        },
        saveSettings() {
            this.saving = true;
            this.$post('fluent_toolkit_mcp_oauth_save', {
                enabled: this.form.enabled ? 'yes' : 'no',
                protected_routes: this.form.protected_routes,
                required_capability: this.form.required_capability,
                access_token_lifetime_value: this.form.access_token_lifetime_value,
                access_token_lifetime_unit: this.form.access_token_lifetime_unit,
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.hydrate(response.data);
                })
                .catch(error => this.$handleError(error))
                .finally(() => {
                    this.saving = false;
                });
        },
        revokeClient(client) {
            this.$post('fluent_toolkit_mcp_oauth_revoke_client', {
                client_id: client.client_id,
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.hydrate(response.data);
                })
                .catch(error => this.$handleError(error));
        },
        revokeToken(token) {
            this.$post('fluent_toolkit_mcp_oauth_revoke_token', {
                token_hash: token.token_hash,
            })
                .then(response => {
                    this.$notify.success(response.message);
                    this.hydrate(response.data);
                })
                .catch(error => this.$handleError(error));
        },
        clearClients() {
            this.$post('fluent_toolkit_mcp_oauth_clear_clients')
                .then(response => {
                    this.$notify.success(response.message);
                    this.hydrate(response.data);
                })
                .catch(error => this.$handleError(error));
        },
        clearTokens() {
            this.$post('fluent_toolkit_mcp_oauth_clear_tokens')
                .then(response => {
                    this.$notify.success(response.message);
                    this.hydrate(response.data);
                })
                .catch(error => this.$handleError(error));
        },
        copyText(text) {
            if (!navigator.clipboard || !text) {
                return;
            }

            navigator.clipboard.writeText(text).then(() => {
                this.$notify.success('Copied');
            });
        },
        copyAllEndpoints() {
            const urls = [...this.endpoints, ...this.discoveryEndpoints].map(endpoint => endpoint.url);
            this.copyText(urls.join('\n'));
        },
    },
    mounted() {
        this.hydrate(this.appVars.oauth);
        this.fetchData();
    },
    created() {
        jQuery('.update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error').remove();
    },
};
</script>
