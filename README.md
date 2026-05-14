# Fluent Toolkit v2.0.2

Unified UI. MCP connector. Beta builds. Add-ons. One place.

Connect your Fluent plugins under one roof. Fluent Toolkit gives you a unified admin workspace, an MCP connector for AI agents, early access to release candidates, companion add-on installs, and update visibility across the Fluent ecosystem from your WordPress dashboard.

---

### Download

Primary download: [fluent-toolkit.zip](https://static.wpmanageninja.com/fluent-toolkit.zip)

You can also find packaged builds in the [Latest Release](https://github.com/WPManageNinja/fluent-toolkit/releases/latest) section of this repository.

### Features

- Unified UI for a cleaner shared Fluent workspace across supported Fluent products
- MCP connector page for enabling product MCP tools and copying client-ready connection snippets
- Client snippets for Codex, GitHub Copilot, Claude Desktop, Cursor, and generic HTTP MCP clients
- Browse and install beta builds & release candidates for Fluent plugins
- Install companion add-ons alongside core plugins
- Load the official WordPress MCP Adapter as a bundled, replaceable dependency fallback
- Live stats — available, installed, and pending updates at a glance
- Channel filter tabs: All / Beta / Installed / Updates
- Real-time search across plugins
- Self-update — toolkit updates itself when a new version is available

### How to Use

1. Download the latest plugin zip from [static.wpmanageninja.com/fluent-toolkit.zip](https://static.wpmanageninja.com/fluent-toolkit.zip)
2. Install on your WordPress site (staging preferred)
3. Activate the plugin
4. Go to **Dashboard → Fluent Toolkit** in the WordPress admin
5. Turn on **Fluent Unified UI** if you want the shared Fluent admin workspace
6. Open **MCP** to review available Fluent MCP tools and copy a client connection snippet
7. Install beta builds, RCs, or companion add-ons
8. Provide feedback at: https://community.wpmanageninja.com/portal

---

### Unified UI

Fluent Toolkit can turn supported Fluent product screens into one shared admin workspace. The unified UI adds a consistent sidebar, product switching, workspace controls, and shared styling for active Fluent products such as CRM, Forms, Commerce, Support Tickets, Appointments, and Projects.

Unified UI is controlled from the Toolkit dashboard and can be enabled or disabled without changing the underlying product data.

### MCP Connector

The MCP connector helps site admins connect AI clients to Fluent products that expose Model Context Protocol tools. From the MCP page, you can:

- See each product endpoint, tool count, status, and adapter availability
- Enable or disable Toolkit-supported MCP access, starting with FluentCRM
- Create WordPress Application Passwords for client authentication
- Copy ready-to-use connection snippets for Codex, GitHub Copilot, Claude Desktop, Cursor, or any HTTP MCP client

MCP routes still rely on WordPress and product-level authentication. Toolkit provides the management UI, connector snippets, and adapter fallback.

### Development

```bash
composer install --no-dev --optimize-autoloader
npm install
npx mix watch          # development
npx mix --production   # production build
bash build.sh          # create release zip → builds/fluent-toolkit-{version}.zip
```

### MCP Adapter Support

Fluent Toolkit can provide the official WordPress MCP Adapter package as a bundled fallback dependency. The adapter stays isolated behind Toolkit's adapter provider so it can be updated or removed independently.

Toolkit only bundles the adapter fallback. Authentication and authorization for MCP routes should be handled outside this plugin.

---

### Changelog

#### 2.0.2
- Bug fixes and release packaging updates

#### 2.0.0
- `McpManager` — per-product MCP enable/disable, status API, AJAX handlers wired into the toolkit settings
- `UnifiedUiHandler` — cross-plugin admin shell with shared assets (`dist/unified-ui.css`)
- `Updater` — self-hosted update channel against `https://kit.wpmanageninja.com/kit-version`, including `plugins_api` integration for the "View details" modal
- Bundled `wordpress/mcp-adapter` only when no other plugin defines `WP_MCP_VERSION`

#### 1.2.0
- Added replaceable WordPress MCP Adapter provider using the official `wordpress/mcp-adapter` package

#### 1.1.0
- Redesigned dashboard — topbar, hero stats, channel tabs, plugin grid
- Added search, channel filters (All / Beta / Installed / Updates)
- Updated copy: accurate tagline and description
- `build.sh` — automated release zip builder

#### 1.0.2
- UI improvements

#### 1.0.1
- Initial release
