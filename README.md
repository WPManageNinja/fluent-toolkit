# Fluent Toolkit v2.0.0

Beta builds. Add-ons. One place.

Get early access to release candidates, install companion add-ons, and track update availability across the Fluent ecosystem — all from your WordPress dashboard.

---

### Download

Download the latest plugin zip from the [Latest Release](https://github.com/WPManageNinja/fluent-toolkit/releases/latest) section of this repository.

### Features

- Browse and install beta builds & release candidates for Fluent plugins
- Install companion add-ons alongside core plugins
- Load the official WordPress MCP Adapter as a bundled, replaceable dependency fallback
- Live stats — available, installed, and pending updates at a glance
- Channel filter tabs: All / Beta / Installed / Updates
- Real-time search across plugins
- Self-update — toolkit updates itself when a new version is available

### How to Use

1. Open the [Latest Release](https://github.com/WPManageNinja/fluent-toolkit/releases/latest) and download the plugin zip from the release assets
2. Install on your WordPress site (staging preferred)
3. Activate the plugin
4. Go to **Dashboard → Fluent Toolkit** in the WordPress admin
5. Install beta builds, RCs, or companion add-ons
6. Provide feedback at: https://community.wpmanageninja.com/portal

---

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
