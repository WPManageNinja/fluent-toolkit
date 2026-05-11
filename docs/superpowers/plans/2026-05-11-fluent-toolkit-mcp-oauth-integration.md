# Fluent Toolkit MCP OAuth Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Fluent Toolkit provide the WordPress MCP Adapter and FluentCRM MCP OAuth Bridge functionality from inside the toolkit plugin while preserving current endpoint behavior and improving packaging, dependency handling, and admin UX.

**Architecture:** Fluent Toolkit becomes the provider/orchestrator. The Fluent-maintained OAuth bridge is absorbed as first-party Toolkit code under the `FluentToolkit\Mcp\OAuth` namespace. The WordPress-maintained MCP Adapter stays behind a replaceable adapter-provider boundary: Toolkit may load the official `wordpress/mcp-adapter` package when no external adapter plugin is active, but must not patch, fork, or couple to adapter internals. FluentCRM still owns the actual CRM tool definitions and `/wp-json/fluent-crm/mcp` server; Toolkit supplies adapter availability and OAuth protection around that route.

**Tech Stack:** WordPress plugin PHP 7.4-compatible code, Composer package `wordpress/mcp-adapter`, plain Composer autoloading, Vue 3 + Element Plus admin UI, WordPress REST API, OAuth 2.1 authorization-code flow with PKCE.

---

## Findings From Analysis

- `mcp-adapter` exists as the official Composer package `wordpress/mcp-adapter`. Packagist currently shows v0.5.0, requiring PHP `^7.4 || ^8.0`, `ext-json`, and `wordpress/php-mcp-schema`.
- The local standalone adapter plugin exposes `WP\MCP\Core\McpAdapter`, creates the default server at `/wp-json/mcp/mcp-adapter-default-server`, supports custom MCP servers, HTTP transport, WP-CLI STDIO transport, and uses `wp_register_ability()` / `wp_get_abilities()`.
- Fluent does not maintain the MCP Adapter. Treat it as an upstream dependency, not product code. It must be removable by deleting the adapter-provider module and Composer requirement, and updateable by changing only the Composer constraint/lock or by using the external WordPress plugin.
- The local FluentCRM code already registers a dedicated server at `/wp-json/fluent-crm/mcp` through `MCPInit::registerCustomServer()`. The OAuth bridge is hard-coded to protect `/fluent-crm/mcp`, which matches the current FluentCRM MCP route.
- `fluentcrm-mcp-oauth-bridge` is not a package. It is a small plugin with isolated classes under `FluentCrmMcpOAuthBridge`. It provides `.well-known` metadata, dynamic client registration, authorize/token endpoints, PKCE verification, bearer-token validation, token/client storage, and an admin settings page.
- `fluent-toolkit` currently has no Composer setup. Its build script only packages `fluent-toolkit.php`, `readme.txt`, `index.php`, `Classes/`, and compiled `dist/`, so any new `vendor/` or module directories must be explicitly included.

## Ownership Rule

- **Auth bridge:** Fluent-maintained. It can be moved into Toolkit as first-party code, renamed, enhanced, migrated, tested, and shipped as part of Toolkit.
- **MCP Adapter:** WordPress-maintained. Toolkit must integrate it as a dependency/provider, not as copied application code. Do not edit upstream adapter files. Do not depend on adapter internals beyond documented public classes, hooks, and WordPress ability APIs.
- **Preferred runtime order:** external active `mcp-adapter` plugin wins; Toolkit uses it. If absent, Toolkit may provide the Composer package fallback. If WordPress ships a better/core path later, Toolkit removes the fallback and keeps only detection/status/OAuth behavior.

## File Structure

Create:
- `composer.json` — declares backend package dependencies.
- `composer.lock` — locks adapter package versions for reproducible builds.
- `includes/index.php` — directory access guard.
- `includes/Mcp/index.php` — directory access guard.
- `includes/Mcp/AdapterBootstrap.php` — boots Composer autoloading and MCP Adapter safely.
- `includes/Mcp/OAuth/AdminPage.php` — Toolkit-owned replacement for bridge admin screen.
- `includes/Mcp/OAuth/AuthorizationServer.php` — bridge authorize/register/token REST endpoints.
- `includes/Mcp/OAuth/ClientStore.php` — dynamic client registration persistence.
- `includes/Mcp/OAuth/Metadata.php` — authorization server and protected resource metadata.
- `includes/Mcp/OAuth/Plugin.php` — OAuth module hooks and rewrite rules.
- `includes/Mcp/OAuth/ResourceServer.php` — bearer-token protection for `/fluent-crm/mcp`.
- `includes/Mcp/OAuth/Settings.php` — settings with migration from old bridge option names.
- `includes/Mcp/OAuth/TokenStore.php` — authorization code and access token persistence.
- `includes/Mcp/OAuth/index.php` — directory access guard.
- `Classes/McpStatus.php` — backend status helper for admin UI and health checks.

Modify:
- `fluent-toolkit.php` — add autoloading, boot adapter provider, boot OAuth module, activation/deactivation hooks, AJAX routes.
- `build.sh` — package `vendor/`, `includes/`, and any new module files.
- `src/components/Dashboard.vue` — add MCP panel/status/actions without changing existing beta installer behavior.
- `src/app.js` — no required structural change unless new API helpers are needed.
- `README.md` and `readme.txt` — document MCP/OAuth support.

Do not modify:
- `fluent-crm/app/Modules/MCP/*` for this integration unless implementation testing proves a compatibility defect. FluentCRM should remain the owner of CRM tools.
- The external `mcp-adapter/` or `fluentCRM-MCP-OAuth-Bridge/` plugin directories. They are source references only.

---

### Task 1: Add Replaceable MCP Adapter Provider

**Files:**
- Create: `composer.json`
- Create: `includes/index.php`
- Create: `includes/Mcp/index.php`
- Create: `includes/Mcp/AdapterBootstrap.php`
- Modify: `fluent-toolkit.php`
- Modify: `build.sh`

- [ ] **Step 1: Add package dependencies without owning upstream code**

The adapter dependency must be removable/updateable without touching OAuth bridge code. Keep all direct references to the adapter inside `includes/Mcp/AdapterBootstrap.php` and status helpers only.

Create `composer.json`:

```json
{
  "name": "wpmanageninja/fluent-toolkit",
  "description": "Toolkit for Fluent beta builds, add-ons, and MCP support.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=7.4",
    "wordpress/mcp-adapter": "^0.5"
  },
  "autoload": {
    "psr-4": {
      "FluentToolkit\\": "Classes/",
      "FluentToolkit\\Mcp\\": "includes/Mcp/"
    }
  },
  "config": {
    "optimize-autoloader": true,
    "sort-packages": true,
    "allow-plugins": {
      "composer/installers": true
    }
  },
  "scripts": {
    "build:autoload": "composer dump-autoload -o"
  }
}
```

- [ ] **Step 2: Install and lock dependencies**

Run:

```bash
composer install --no-dev --optimize-autoloader
```

Expected:

```text
vendor/autoload.php exists
composer.lock exists
vendor/wordpress/mcp-adapter exists
```

- [ ] **Step 3: Add directory guards**

Create each `index.php` guard with:

```php
<?php
// Silence is golden.
```

- [ ] **Step 4: Implement adapter bootstrap**

Create `includes/Mcp/AdapterBootstrap.php`:

```php
<?php

namespace FluentToolkit\Mcp;

defined('ABSPATH') || exit;

use WP\MCP\Core\McpAdapter;

class AdapterBootstrap
{
    public static function boot()
    {
        if (defined('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER') && FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER) {
            return class_exists(McpAdapter::class) && function_exists('wp_register_ability');
        }

        if (class_exists(McpAdapter::class)) {
            if (function_exists('wp_register_ability')) {
                McpAdapter::instance();
                return true;
            }

            return false;
        }

        self::loadAutoloader();

        if (!function_exists('wp_register_ability')) {
            return false;
        }

        if (!class_exists(McpAdapter::class)) {
            return false;
        }

        McpAdapter::instance();

        return true;
    }

    public static function available()
    {
        return function_exists('wp_register_ability') && class_exists(McpAdapter::class);
    }

    private static function loadAutoloader()
    {
        $composerAutoloader = FLUENT_BETA_TESTING_PLUGIN_PATH . 'vendor/autoload.php';
        if (is_readable($composerAutoloader)) {
            require_once $composerAutoloader;
        }
    }
}
```

Notes:
- `FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER` is the emergency removal switch.
- No code outside this bootstrap should `require` adapter files directly.
- Do not edit files under `vendor/wordpress/mcp-adapter`.

- [ ] **Step 5: Boot adapter from Toolkit**

In `fluent-toolkit.php`, after constants and before `new FluentToolkitBootstrap();`, load Composer and call the adapter on `plugins_loaded`:

```php
$fluentToolkitAutoload = FLUENT_BETA_TESTING_PLUGIN_PATH . 'vendor/autoload.php';
if (is_readable($fluentToolkitAutoload)) {
    require_once $fluentToolkitAutoload;
}

add_action('plugins_loaded', function () {
    if (class_exists('\FluentToolkit\Mcp\AdapterBootstrap')) {
        \FluentToolkit\Mcp\AdapterBootstrap::boot();
    }
}, 1);
```

- [ ] **Step 6: Package backend dependencies**

Update `build.sh` copy section:

```bash
cp -r includes "${BUILD_DIR}/includes"
cp composer.json composer.lock "${BUILD_DIR}/"
cp -r vendor "${BUILD_DIR}/vendor"
```

- [ ] **Step 7: Verify adapter availability**

Run:

```bash
php -l fluent-toolkit.php
php -l includes/Mcp/AdapterBootstrap.php
```

Expected:

```text
No syntax errors detected
```

Manual WP check:

```bash
wp eval "do_action('plugins_loaded'); var_export(class_exists('WP\\MCP\\Core\\McpAdapter'));"
```

Expected:

```text
true
```

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock fluent-toolkit.php build.sh includes vendor
git commit -m "feat: provide MCP adapter from toolkit"
```

### Task 2: Port OAuth Bridge Under Fluent Toolkit Namespace

**Files:**
- Create: `includes/Mcp/OAuth/AdminPage.php`
- Create: `includes/Mcp/OAuth/AuthorizationServer.php`
- Create: `includes/Mcp/OAuth/ClientStore.php`
- Create: `includes/Mcp/OAuth/Metadata.php`
- Create: `includes/Mcp/OAuth/Plugin.php`
- Create: `includes/Mcp/OAuth/ResourceServer.php`
- Create: `includes/Mcp/OAuth/Settings.php`
- Create: `includes/Mcp/OAuth/TokenStore.php`
- Create: `includes/Mcp/OAuth/index.php`
- Modify: `fluent-toolkit.php`

- [ ] **Step 1: Copy bridge code with namespace rename**

Copy the runtime classes from `/Users/masiur/Sites/authlab/wp-content/plugins/fluentCRM-MCP-OAuth-Bridge/includes/` into `includes/Mcp/OAuth/`.

Change:

```php
namespace FluentCrmMcpOAuthBridge;
```

to:

```php
namespace FluentToolkit\Mcp\OAuth;
```

- [ ] **Step 2: Rename text domain and option keys**

Use these constants in `Settings.php`:

```php
const OPTION_KEY = 'fluent_toolkit_mcp_oauth_settings';
const LEGACY_OPTION_KEY = 'fcrm_mcp_oauth_bridge_settings';
const MCP_ROUTE = '/fluent-crm/mcp';
const DEFAULT_SCOPE = 'fluentcrm.read fluentcrm.write';
```

Use these constants in `ClientStore.php`:

```php
const OPTION_KEY = 'fluent_toolkit_mcp_oauth_clients';
const LEGACY_OPTION_KEY = 'fcrm_mcp_oauth_bridge_clients';
```

Use these constants in `TokenStore.php`:

```php
const TOKENS_OPTION = 'fluent_toolkit_mcp_oauth_access_tokens';
const LEGACY_TOKENS_OPTION = 'fcrm_mcp_oauth_bridge_access_tokens';
const AUTH_CODE_PREFIX = 'fluent_toolkit_mcp_oauth_code_';
```

- [ ] **Step 3: Add legacy migration reads**

In each store class, read the Toolkit option first and fall back to the legacy option:

```php
$settings = get_option(self::OPTION_KEY, null);
if ($settings === null || $settings === false) {
    $settings = get_option(self::LEGACY_OPTION_KEY, []);
}
```

When saving, always save to the new Toolkit option.

- [ ] **Step 4: Keep route and OAuth behavior unchanged**

Do not change:

```php
register_rest_route('fluentcrm-mcp-oauth/v1', '/authorize', ...)
register_rest_route('fluentcrm-mcp-oauth/v1', '/register', ...)
register_rest_route('fluentcrm-mcp-oauth/v1', '/token', ...)
/.well-known/oauth-authorization-server
/.well-known/oauth-protected-resource
/wp-json/fluent-crm/mcp
```

- [ ] **Step 5: Boot OAuth module from Toolkit**

Add to `fluent-toolkit.php`:

```php
register_activation_hook(__FILE__, function () {
    if (class_exists('\FluentToolkit\Mcp\OAuth\Settings')) {
        \FluentToolkit\Mcp\OAuth\Settings::installDefaults();
        \FluentToolkit\Mcp\OAuth\Plugin::addRewriteRules();
        flush_rewrite_rules();
    }
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
    if (class_exists('\FluentToolkit\Mcp\OAuth\Plugin')) {
        \FluentToolkit\Mcp\OAuth\Plugin::boot();
    }
}, 5);
```

- [ ] **Step 6: Preserve validation helper compatibility**

Add this global helper in `fluent-toolkit.php` only if the old bridge is not active:

```php
if (!function_exists('fluentcrm_mcp_oauth_bridge_validate_token')) {
    function fluentcrm_mcp_oauth_bridge_validate_token($token, $resource = '')
    {
        return \FluentToolkit\Mcp\OAuth\TokenStore::validateAccessToken(
            $token,
            $resource ?: \FluentToolkit\Mcp\OAuth\Settings::resourceUrl()
        );
    }
}
```

- [ ] **Step 7: Verify syntax**

Run:

```bash
find includes/Mcp/OAuth -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected:

```text
No syntax errors detected
```

- [ ] **Step 8: Commit**

```bash
git add fluent-toolkit.php includes/Mcp/OAuth
git commit -m "feat: embed FluentCRM MCP OAuth bridge"
```

### Task 3: Add Conflict Detection, Compatibility Guards, and Exit Strategy

**Files:**
- Create: `Classes/McpStatus.php`
- Modify: `fluent-toolkit.php`
- Modify: `includes/Mcp/AdapterBootstrap.php`
- Modify: `includes/Mcp/OAuth/Plugin.php`

- [ ] **Step 1: Add status helper**

Create `Classes/McpStatus.php`:

```php
<?php

namespace FluentToolkit\Classes;

defined('ABSPATH') || exit;

class McpStatus
{
    public static function adapterActiveAsPlugin()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('mcp-adapter/mcp-adapter.php');
    }

    public static function oauthBridgeActiveAsPlugin()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('fluentCRM-MCP-OAuth-Bridge/fluentcrm-mcp-oauth-bridge.php')
            || is_plugin_active('fluentcrm-mcp-oauth-bridge/fluentcrm-mcp-oauth-bridge.php');
    }

    public static function fluentCrmMcpAvailable()
    {
        return class_exists('\FluentCrm\App\Modules\MCP\MCPInit') || has_action('mcp_adapter_init');
    }
}
```

- [ ] **Step 2: Avoid double-booting external adapter plugin**

In `AdapterBootstrap::boot()`, if `WP\MCP\Core\McpAdapter` already exists, instantiate it and return without requiring Toolkit to own the class source. This makes the external WordPress-maintained adapter plugin take precedence:

```php
if (class_exists(McpAdapter::class)) {
    if (function_exists('wp_register_ability')) {
        McpAdapter::instance();
        return true;
    }
    return false;
}
```

- [ ] **Step 3: Avoid double OAuth protection**

In `OAuth\Plugin::boot()`, return early if the old bridge plugin is active:

```php
if (class_exists('\FluentCrmMcpOAuthBridge\Plugin')) {
    return;
}
```

- [ ] **Step 4: Add admin notice for active duplicate bridge**

Hook an admin notice that tells the admin Toolkit now owns the bridge and the old standalone bridge can be deactivated after migration:

```php
add_action('admin_notices', function () {
    if (!current_user_can('manage_options') || !class_exists('\FluentCrmMcpOAuthBridge\Plugin')) {
        return;
    }

    echo '<div class="notice notice-warning"><p>' . esc_html__('Fluent Toolkit includes the FluentCRM MCP OAuth bridge. Deactivate the standalone bridge plugin after confirming your MCP connection still works.', 'fluent-toolkit') . '</p></div>';
});
```

- [ ] **Step 5: Verify both combinations**

Manual checks:

```bash
wp plugin activate fluent-toolkit
wp plugin activate mcp-adapter
wp plugin activate fluentCRM-MCP-OAuth-Bridge || true
wp eval "var_export(class_exists('WP\\MCP\\Core\\McpAdapter'));"
```

Expected:

```text
true
```

No fatal errors, no duplicate class declarations.

- [ ] **Step 6: Verify adapter fallback can be removed**

Temporarily define this before plugin load, usually in `wp-config.php`:

```php
define('FLUENT_TOOLKIT_DISABLE_BUNDLED_MCP_ADAPTER', true);
```

Then run:

```bash
wp plugin deactivate mcp-adapter || true
wp plugin deactivate fluent-toolkit
wp plugin activate fluent-toolkit
```

Expected:

```text
Fluent Toolkit activates without fatal errors.
MCP Adapter status reports unavailable.
OAuth module still loads, but the protected MCP route only becomes useful once an adapter is available.
```

- [ ] **Step 7: Commit**

```bash
git add Classes/McpStatus.php fluent-toolkit.php includes/Mcp/AdapterBootstrap.php includes/Mcp/OAuth/Plugin.php
git commit -m "fix: isolate MCP adapter provider"
```

### Task 4: Add Toolkit Admin MCP Panel

**Files:**
- Modify: `fluent-toolkit.php`
- Modify: `src/components/Dashboard.vue`
- Modify: `src/style.scss`

- [ ] **Step 1: Add AJAX status route**

In `FluentToolkitBootstrap::__construct()`:

```php
add_action('wp_ajax_fluent_toolkit_mcp_status', array($this, 'getMcpStatus'));
add_action('wp_ajax_fluent_toolkit_mcp_oauth_toggle', array($this, 'toggleMcpOAuth'));
```

Add methods:

```php
public function getMcpStatus()
{
    $this->verifyAjaxRequest();

    wp_send_json([
        'adapter_available' => class_exists('\WP\MCP\Core\McpAdapter'),
        'abilities_available' => function_exists('wp_register_ability'),
        'oauth_enabled' => class_exists('\FluentToolkit\Mcp\OAuth\Settings') ? \FluentToolkit\Mcp\OAuth\Settings::enabled() : false,
        'mcp_url' => class_exists('\FluentToolkit\Mcp\OAuth\Settings') ? \FluentToolkit\Mcp\OAuth\Settings::resourceUrl() : rest_url('fluent-crm/mcp'),
        'authorization_metadata_url' => home_url('/.well-known/oauth-authorization-server'),
        'protected_resource_metadata_url' => home_url('/.well-known/oauth-protected-resource'),
        'authorization_endpoint' => rest_url('fluentcrm-mcp-oauth/v1/authorize'),
        'token_endpoint' => rest_url('fluentcrm-mcp-oauth/v1/token'),
        'registration_endpoint' => rest_url('fluentcrm-mcp-oauth/v1/register'),
    ]);
}

public function toggleMcpOAuth()
{
    $this->verifyAjaxRequest();

    if (!class_exists('\FluentToolkit\Mcp\OAuth\Settings')) {
        wp_send_json(['message' => __('OAuth module is not available.', 'fluent-toolkit')], 500);
    }

    \FluentToolkit\Mcp\OAuth\Settings::update([
        'enabled' => !empty($_POST['enabled']) && $_POST['enabled'] === 'yes',
    ]);

    wp_send_json(['message' => __('MCP OAuth setting updated.', 'fluent-toolkit')]);
}
```

- [ ] **Step 2: Add Vue status state**

In `Dashboard.vue` data:

```js
mcpStatus: null,
mcpLoading: false,
oauthSaving: false,
```

Add mounted call:

```js
this.getMcpStatus();
```

Add methods:

```js
getMcpStatus() {
    this.mcpLoading = true;
    this.$get('fluent_toolkit_mcp_status')
        .then(response => {
            this.mcpStatus = response;
        })
        .catch(error => {
            this.$handleError(error);
        })
        .finally(() => {
            this.mcpLoading = false;
        });
},
toggleMcpOAuth(enabled) {
    this.oauthSaving = true;
    this.$post('fluent_toolkit_mcp_oauth_toggle', {
        enabled: enabled ? 'yes' : 'no'
    })
        .then(response => {
            this.$notify.success(response.message);
            this.getMcpStatus();
        })
        .catch(error => {
            this.$handleError(error);
        })
        .finally(() => {
            this.oauthSaving = false;
        });
}
```

- [ ] **Step 3: Add panel markup**

Place below the hero and above the plugin toolbar:

```vue
<section class="ft-mcp-panel" v-loading="mcpLoading">
    <div class="ft-mcp-header">
        <div>
            <div class="ft-hero-eyebrow">MCP Connection</div>
            <h2>FluentCRM MCP OAuth</h2>
        </div>
        <el-switch
            v-if="mcpStatus"
            :model-value="mcpStatus.oauth_enabled"
            :loading="oauthSaving"
            @change="toggleMcpOAuth"
        />
    </div>
    <div v-if="mcpStatus" class="ft-mcp-grid">
        <div>
            <span>Adapter</span>
            <strong>{{ mcpStatus.adapter_available ? 'Available' : 'Unavailable' }}</strong>
        </div>
        <div>
            <span>Abilities API</span>
            <strong>{{ mcpStatus.abilities_available ? 'Loaded' : 'Missing' }}</strong>
        </div>
        <div>
            <span>MCP URL</span>
            <code>{{ mcpStatus.mcp_url }}</code>
        </div>
        <div>
            <span>Registration</span>
            <code>{{ mcpStatus.registration_endpoint }}</code>
        </div>
    </div>
</section>
```

- [ ] **Step 4: Add restrained styles**

Add to `src/style.scss`:

```scss
.ft-mcp-panel {
    margin: 18px 0 22px;
    padding: 18px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
}

.ft-mcp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.ft-mcp-header h2 {
    margin: 4px 0 0;
    font-size: 18px;
    line-height: 1.3;
}

.ft-mcp-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.ft-mcp-grid > div {
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--border-soft);
    border-radius: 8px;
}

.ft-mcp-grid span {
    display: block;
    margin-bottom: 6px;
    color: var(--text-soft);
    font-size: 12px;
}

.ft-mcp-grid code {
    display: block;
    overflow-wrap: anywhere;
    font-size: 12px;
}

@media (max-width: 782px) {
    .ft-mcp-grid {
        grid-template-columns: 1fr;
    }
}
```

- [ ] **Step 5: Build assets**

Run:

```bash
npx mix --production
```

Expected:

```text
dist/app.js updated
dist/app.css updated if emitted
```

- [ ] **Step 6: Commit**

```bash
git add fluent-toolkit.php src/components/Dashboard.vue src/style.scss dist
git commit -m "feat: show MCP OAuth status in toolkit"
```

### Task 5: End-to-End Verification

**Files:**
- Modify only if tests expose defects.

- [ ] **Step 1: Syntax check all Toolkit PHP**

Run:

```bash
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected:

```text
No syntax errors detected
```

- [ ] **Step 2: Activate with no standalone adapter or bridge**

Run:

```bash
wp plugin deactivate mcp-adapter fluentCRM-MCP-OAuth-Bridge fluentcrm-mcp-oauth-bridge || true
wp plugin activate fluent-toolkit
```

Expected:

```text
Plugin 'fluent-toolkit' activated.
```

- [ ] **Step 3: Confirm MCP Adapter class and FluentCRM endpoint**

Run:

```bash
wp eval "var_export([class_exists('WP\\MCP\\Core\\McpAdapter'), function_exists('wp_register_ability')]);"
wp rest route list | grep 'fluent-crm/mcp'
```

Expected:

```text
array (0 => true, 1 => true)
/fluent-crm/mcp route is present when FluentCRM MCP module is active
```

- [ ] **Step 4: Confirm well-known metadata**

Run:

```bash
curl -s "$(wp option get siteurl)/.well-known/oauth-authorization-server" | jq .
curl -s "$(wp option get siteurl)/.well-known/oauth-protected-resource" | jq .
```

Expected:

```text
authorization_endpoint, token_endpoint, registration_endpoint exist
resource equals the /wp-json/fluent-crm/mcp URL
```

- [ ] **Step 5: Confirm MCP route requires bearer auth**

Run:

```bash
curl -i -s -X POST "$(wp option get siteurl)/wp-json/fluent-crm/mcp" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

Expected:

```text
HTTP/1.1 401
WWW-Authenticate: Bearer resource_metadata="..."
```

- [ ] **Step 6: Confirm old plugin migration**

Before deactivating the old bridge, create a registered client and token. Then activate Toolkit, deactivate the old bridge, and run:

```bash
wp option get fluent_toolkit_mcp_oauth_clients --format=json
wp option get fluent_toolkit_mcp_oauth_access_tokens --format=json
```

Expected:

```text
Existing clients and tokens are readable or migrated into Toolkit option keys.
```

- [ ] **Step 7: Build package**

Run:

```bash
bash build.sh
unzip -l builds/fluent-toolkit-*.zip | grep -E 'vendor|includes/Mcp|fluent-toolkit.php'
```

Expected:

```text
vendor, includes/Mcp, includes/Mcp/OAuth, and main plugin file are included.
```

- [ ] **Step 8: Commit verification fixes**

```bash
git add .
git commit -m "test: verify toolkit MCP OAuth integration"
```

### Task 6: Documentation and PR Description Prep

**Files:**
- Modify: `README.md`
- Modify: `readme.txt`

- [ ] **Step 1: Update README summary**

Add:

```markdown
### MCP OAuth Support

Fluent Toolkit can provide the WordPress MCP Adapter package and the FluentCRM MCP OAuth bridge from one plugin. The MCP endpoint remains `/wp-json/fluent-crm/mcp`; Toolkit adds OAuth metadata, dynamic client registration, PKCE authorization, token issuance, and bearer-token validation for that route.
```

- [ ] **Step 2: Update changelog**

Add:

```markdown
#### 1.2.0
- Added built-in WordPress MCP Adapter provider using the official `wordpress/mcp-adapter` package.
- Added built-in FluentCRM MCP OAuth bridge while keeping the existing `/wp-json/fluent-crm/mcp` route and OAuth endpoints.
- Added Toolkit dashboard status for MCP Adapter, Abilities API, OAuth status, and connector URLs.
```

- [ ] **Step 3: Prepare PR description using AGENTS.md instruction**

Use this shape:

```markdown
## Summary
Integrated the WordPress MCP Adapter and FluentCRM MCP OAuth Bridge into Fluent Toolkit so admins can enable the same MCP/OAuth functionality from one Toolkit install.

## Details
- Added Composer-based loading for `wordpress/mcp-adapter`.
- Ported OAuth bridge runtime classes under the Toolkit namespace.
- Preserved existing routes: `/wp-json/fluent-crm/mcp`, `/.well-known/oauth-authorization-server`, `/.well-known/oauth-protected-resource`, and `fluentcrm-mcp-oauth/v1/*`.
- Added guards for sites that still have the standalone adapter or bridge plugins active.
- Added Toolkit dashboard MCP status and OAuth toggle.

## Why It Was Done
The previous setup required separate MCP Adapter and OAuth Bridge plugins. The goal is to provide the same connection flow from Fluent Toolkit, reducing installation steps and making the feature easier to ship, test, and support.

## What Was Before
- MCP Adapter had to be installed/activated separately.
- FluentCRM MCP OAuth Bridge had to be installed/activated separately.
- Toolkit only listed beta builds and companion add-ons; it did not own MCP/OAuth status or configuration.

## Verification
- `php -l` on Toolkit PHP files.
- `npx mix --production`.
- `bash build.sh`.
- Manual WordPress activation with and without standalone MCP plugins.
- Metadata and protected MCP route curl checks.
```

- [ ] **Step 4: Commit docs**

```bash
git add README.md readme.txt
git commit -m "docs: document toolkit MCP OAuth support"
```

---

## Recommendation

Move the OAuth bridge into Toolkit as first-party Fluent code. Do not copy the MCP Adapter plugin code into Toolkit. Either prefer the external official adapter plugin, or use the official Composer package as a replaceable fallback provider isolated to `AdapterBootstrap`. The adapter must remain updateable by upstream WordPress releases and removable without touching the OAuth bridge.

## Main Risks

- WordPress Abilities API availability: adapter needs `wp_register_ability()`. Toolkit must skip MCP boot cleanly when it is missing.
- Duplicate plugin state: many test sites may still have standalone `mcp-adapter` or `fluentCRM-MCP-OAuth-Bridge` active. Guards must prevent duplicate classes, duplicate route registration, and confusing auth behavior.
- Upstream ownership: WordPress maintains MCP Adapter. Fluent should not patch vendored adapter code or depend on undocumented internals. Keep the dependency easy to update or remove.
- Build artifact omissions: current `build.sh` excludes `vendor/` and `includes/`; this must be fixed or the released plugin will fatal.
- OAuth token migration: existing bridge users should not lose registered clients or active tokens when moving to Toolkit.
- Route parity: the endpoint must remain `/wp-json/fluent-crm/mcp`; changing that breaks existing MCP client configs.
