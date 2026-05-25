# FluentHub (fluent-toolkit)

WordPress plugin that unifies Fluent plugin admin UIs into one workspace, manages MCP for AI agents, and ships an updater + plugin installer dashboard.

## Build

- `npx mix` from plugin root rebuilds everything into `dist/`.
- No Composer autoload — `fluent-toolkit.php::loadClasses()` manually `require_once`s each class file. Add new classes there.

## Code map

- `fluent-toolkit.php` — bootstrap, AJAX handlers (`fluent_toolkit_save_dashboard_settings`, `fluent-beta-install`, `fluent_toolkit_activate_plugin`, MCP toggles), license/install logic.
- `Classes/AdminMenu.php` — registers the `FluentHub` top-level menu + submenu links, enqueues the Vue dashboard.
- `Classes/UnifiedUiHandler.php` — wraps Fluent plugin admin pages with the unified sidebar template. Reads `_fluent_kit_settings` to decide whether to hide native admin menus / app headers.
- `Classes/UnifiedUi/MenuProviders.php` — one static class with `getCrmMenu()`, `getCartMenu()`, etc. Each returns `[item_key => {title, url, icon_svg, sub_menu?}]` or `[]` when the plugin isn't active.
- `Classes/UnifiedUi/Icons.php` — `Icons::get($key)` returns an inline SVG string by name.
- `src/unified-ui.js` → `dist/unified-ui.js` — sidebar DOM interactions (mobile drawer, WP menu drawer, theme toggle, hash routing, workspace switcher). Reads per-page flags from `data-*` attributes on `.fluent_uui`.
- `src/unified-ui.scss` + `src/unified-ui/_utilities.scss` → `dist/unified-ui.css` — sidebar styles.
- `src/components/Dashboard.vue` + `src/style.scss` — FluentHub admin dashboard (Vue 3 + Element Plus).
- `includes/Mcp/` — MCP adapter bootstrap.

## Settings

- Stored in `_fluent_kit_settings` option (array).
- Whitelisted keys (set via `saveDashboardSettings()` AJAX): `uinified_ui` (typo intentional — don't fix, would break existing installs), `merge_admin_menus`, `hide_app_headers`.
- Defaults on first Unified UI activation: `merge_admin_menus = yes`. Existing users who already had it on don't get the key set, so they keep their menus visible until they opt in.
- `hide_app_headers` is opt-in for everyone (default off).

## Gotcha: other Fluent plugins strip foreign scripts

FluentCRM and friends call `wp_dequeue_script()` on non-Fluent scripts on their own admin pages. `wp_enqueue_script()` from this plugin gets removed silently. Print `<script src>` directly via `admin_print_footer_scripts` instead (see `UnifiedUiHandler::printUnifiedUiScript()`). CSS via `wp_enqueue_style()` is not stripped — that path is fine.

## Conventions

- PHP namespace: `FluentToolkit\Classes` (handler) / `FluentToolkit\Classes\UnifiedUi` (helpers).
- Vue dashboard talks to the backend via `this.$post(action, data)` / `this.$get(...)` (mixin defined in `src/app.js`). All actions require the `fluent_toolkit_nonce`.
- Sidebar root div carries class chains: `fluent_uui [fui-hide-app-headers|fui-has-app-headers] fui_app_{slug}` and `data-has-dark-mode="0|1"`. JS reads dark-mode flag from that attribute.
- The mobile breakpoint is `782px` (matches WP admin).
- Icon colors in the dashboard settings list: indigo / violet / amber — see `.ft-setting-icon--*` in `src/style.scss`.

## Dashboard plugin icon

`AdminMenu::pluginIcon()` returns a base64 SVG data URL. WP masks menu icons to a single color, so the icon must be a monochrome silhouette (rounded square with the "F" marks cut out via `fill-rule="evenodd"`). Don't use a multi-color logo here — it'll flatten.
