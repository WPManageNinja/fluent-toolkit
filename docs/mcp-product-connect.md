# The FluentHub connection on a product's own MCP screen

Each Fluent product has its own MCP settings screen. That screen should lead with the FluentHub
connection when it is available: one URL, sign-in with WordPress, no password to copy, and the
agent reaches every Fluent plugin on the site. This page is the contract for that card, so the
six screens behave and read the same.

## Read the state, never the classes

```php
$state = apply_filters('fluent_kit/mcp_hub_state', null, 'fluent-community');
```

- `null`: FluentHub is not active. Show the product's own way to connect.
- An array: see below. Do not call `FluentToolkit\...` classes from a product; their names can
  change, and this filter is the stable surface. Check `$state['version']` is `1`.

| Key | Meaning |
|---|---|
| `hub.enabled` | The "FluentHub MCP server" switch is on. |
| `hub.supported` | The MCP adapter is present and new enough (0.7.0+). |
| `hub.reason` | `''`, `adapter_missing`, `adapter_outdated` or `off`. |
| `hub.endpoint_url` | The one URL. |
| `hub.server_name` | The name an AI app gives this site's connection, for example `fluent-community-lab`. It carries the site's address, because an app refuses a second connection with a name it already has. The snippets in `clients` already use it. |
| `sign_in.enabled` | Sign-in with WordPress is switched on. |
| `sign_in.available` | Sign-in works on this site now. |
| `sign_in.reason` | `''`, `off`, `hub_off`, `hub_unavailable`, `plain_permalinks` or `app_passwords_off`. |
| `sign_in.https` | The site uses HTTPS. Most AI apps refuse sign-in over plain HTTP. |
| `product.known` | FluentHub knows this product slug. |
| `product.included` | The admin has not left this product out of the hub. |
| `ready` | Sign-in works and this product is served. Lead with the one URL. |
| `user_can_connect` | The current user may use this product through the hub. |
| `products` | The active Fluent plugins with an MCP server: `slug`, `name`, `active`, `mcp_enabled` (its own switch), `included` (not left out of the hub), `served` (the hub is on, its MCP is on, and it is included), `tools_count`, `can_enable` (the current user can turn it on from here). Plugins that are not installed are left out. |
| `clients` | The per-app instructions: `key`, `label`, `note`, `snippet`. Print them as given. |
| `manage_url` | The FluentHub MCP page: the switches and the connected apps. |
| `profile_url` | The user's own application passwords, where they can revoke an app. |
| `can_manage` | The current user can change FluentHub's settings. |

## The four states of the card

1. **`ready` is true.** Title "Connect with one URL". One line: sign in with your WordPress
   account, no password to copy, and this URL also gives the agent your other Fluent plugins.
   Then the URL with a copy button, the app tabs from `clients`, and a link "Manage connections
   in FluentHub" to `manage_url`. Below, collapsed: the product's own endpoint with an
   application password.
2. **FluentHub is active and the hub is off** (`hub.reason` is `off`). When `can_manage` is
   true, a primary button "Turn on FluentHub sign-in" with one line that says what it does for
   the whole site: every Fluent plugin that has MCP switched on becomes reachable through one
   URL. A site administrator reaches all of them; anyone else reaches only the plugins that
   give them access. See "Turning the hub on" below. Then the collapsed
   application-password section.

   **The hub is on and sign-in is off** (`sign_in.reason` is `off`): an admin turned sign-in
   off under Advanced. Show a link to `manage_url`, not a button; that choice stays on the
   FluentHub page.
3. **FluentHub is active, sign-in cannot run** (any other reason, or `product.included` is
   false). Say why in one line, link to `manage_url`, and open the application-password section.
4. **`null`.** As the product does today.

When `user_can_connect` is false, do not show the one URL as usable. Say: "Your account has no
access to this plugin's AI tools." FluentHub has no setting for it, so do not send the user
there: access comes from the plugin's own permissions.

## Turning the hub on

```php
// The hub's own switch.
$result = apply_filters('fluent_kit/mcp_hub_enable', null, 'fluent-community', 'hub');
// Another Fluent plugin's MCP: its own switch on, and back into the hub if it was left out.
$result = apply_filters('fluent_kit/mcp_hub_enable', null, 'fluent-community', 'fluent-crm');
```

The second argument is the calling product, the third is the target. There is no way to pass
"off": the call only turns things on.

- Call it only from a POST route of your own that has checked a nonce. The hook checks the
  user's capability (`manage_options`), not the request.
- `null`: FluentHub is not active.
- `status`: `done` (it is on; also when it was on already), `forbidden` (`reason` is
  `capability`: the user cannot manage the site, or `product_refused`: the product's own switch
  handler refused, with its `message`) or `unavailable` (`reason` is `adapter_missing`,
  `adapter_outdated`, `unknown_product`, `not_active` or `no_handler`).
- A product target runs that product's own `fluent_kit/mcp_toggle_handlers` `set_enabled`, so
  the product's permission rule runs too. It does not touch the hub's own switch.
- `served` reads the settings. Each MCP request builds the servers again, so a plugin turned on
  now is in the next request an app sends. An app that keeps its tool list must reload it. Say:
  "On. Your AI app sees it the next time it loads its tools."
- Under a "Turn on" button, every product screen says the same sentence: "Connected AI apps
  can then read and change this plugin's data, for the users the plugin allows."
- Do not offer to install a plugin, and list a plugin only when it is in `products`.
- `state`: the fresh state, in the same shape as `fluent_kit/mcp_hub_state`, to redraw the card
  from one response.
- It does what the switch on the FluentHub page does: the hub goes on, and sign-in goes on with
  it the first time. It never turns anything off, and it does not turn sign-in back on after an
  admin turned it off.
- A product does not offer "turn off". The link to `manage_url` is for that.

## Letting a product's own managers connect

A site administrator reaches every plugin on the hub. FluentHub has no role setting: everyone
else connects through the plugin that admits them, and reaches that plugin only. A user with
access to two plugins reaches both. So every product wires this filter, with the same rule its
own endpoint uses, or its people who are not administrators cannot use the one URL:

```php
add_filter('fluent_kit/mcp_hub_product_user_can_connect', function ($allowed, $slug, $user) {
    return 'fluent-community' === $slug ? McpGate::allows('manager', $user) : $allowed;
}, 10, 3);
```

Such a user can sign in, and reaches that product only. The router tools of every other
product stay hidden and refuse a call, whatever those products' own gates say. The product's
own gate and each tool's permission check still apply.

## What sign-in needs

- HTTPS and pretty permalinks.
- Application passwords available on the site and for the user. The access token is an
  application password that FluentHub creates and limits to the hub endpoint. If a host or a
  security plugin switches application passwords off, sign-in cannot issue a token:
  `sign_in.reason` is `app_passwords_off`. Do not tell users it works without them.
- A site the AI service can reach. An app that runs in the cloud calls the site from its own
  servers, so a local or firewalled site works only with apps that run on the user's computer.
