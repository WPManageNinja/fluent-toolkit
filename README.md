# Fluent Toolkit v1.2.0

Beta builds. Add-ons. One place.

Get early access to release candidates, install companion add-ons, and track update availability across the Fluent ecosystem — all from your WordPress dashboard.

---

### 👉 [Download Latest Release ⬇️](https://wpmanageninja.s3.amazonaws.com/fluent-toolkit.zip)

---

### Features

- Browse and install beta builds & release candidates for Fluent plugins
- Install companion add-ons alongside core plugins
- Provide FluentCRM MCP OAuth support from Toolkit
- Load the official WordPress MCP Adapter as a replaceable dependency fallback
- Live stats — available, installed, and pending updates at a glance
- Channel filter tabs: All / Beta / Installed / Updates
- Real-time search across plugins
- Self-update — toolkit updates itself when a new version is available

### How to Use

1. Download from Releases tab or [click here](https://wpmanageninja.s3.amazonaws.com/fluent-toolkit.zip)
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

### MCP OAuth Support

Fluent Toolkit can provide the WordPress MCP Adapter package and the FluentCRM MCP OAuth bridge from one plugin. The MCP endpoint remains `/wp-json/fluent-crm/mcp`; Toolkit adds OAuth metadata, dynamic client registration, PKCE authorization, token issuance, and bearer-token validation for that route.

The OAuth bridge is Fluent-maintained first-party Toolkit code. The MCP Adapter is WordPress-maintained and remains isolated behind Toolkit's adapter provider so it can be updated or removed independently.

---

### Changelog

#### 1.2.0
- Added built-in FluentCRM MCP OAuth bridge while keeping the existing `/wp-json/fluent-crm/mcp` route and OAuth endpoints
- Added replaceable WordPress MCP Adapter provider using the official `wordpress/mcp-adapter` package
- Added Toolkit dashboard status for MCP Adapter, Abilities API, OAuth status, and connector URLs

#### 1.1.0
- Redesigned dashboard — topbar, hero stats, channel tabs, plugin grid
- Added search, channel filters (All / Beta / Installed / Updates)
- Updated copy: accurate tagline and description
- `build.sh` — automated release zip builder

#### 1.0.2
- UI improvements

#### 1.0.1
- Initial release
