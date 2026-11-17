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
| `sign_in.enabled` | Sign-in with WordPress is switched on. |
| `sign_in.available` | Sign-in works on this site now. |
| `sign_in.reason` | `''`, `off`, `hub_off`, `hub_unavailable`, `plain_permalinks` or `app_passwords_off`. |
| `sign_in.https` | The site uses HTTPS. Most AI apps refuse sign-in over plain HTTP. |
| `product.known` | FluentHub knows this product slug. |
| `product.included` | The admin has not left this product out of the hub. |
| `ready` | Sign-in works and this product is served. Lead with the one URL. |
| `user_can_connect` | The current user may use this product through the hub. |
| `clients` | The per-app instructions: `key`, `label`, `note`, `snippet`. Print them as given. |
| `manage_url` | The FluentHub MCP page: the switches, who can connect, connected apps. |
| `profile_url` | The user's own application passwords, where they can revoke an app. |
| `can_manage` | The current user can change FluentHub's settings. |

## The four states of the card

1. **`ready` is true.** Title "Connect with one URL". One line: sign in with your WordPress
   account, no password to copy, and this URL also gives the agent your other Fluent plugins.
   Then the URL with a copy button, the app tabs from `clients`, and a link "Manage connections
   in FluentHub" to `manage_url`. Below, collapsed: the product's own endpoint with an
   application password.
2. **FluentHub is active, the hub or sign-in is off** (`hub.reason` is `off`, or
   `sign_in.reason` is `off`). A short explanation and one link to `manage_url`, shown only
   when `can_manage` is true. A product does not turn the hub on itself: that switch opens app
   registration and sign-in for the whole site, and the page that holds it also shows who can
   connect and which plugins are served. Then the collapsed application-password section.
3. **FluentHub is active, sign-in cannot run** (any other reason, or `product.included` is
   false). Say why in one line, link to `manage_url`, and open the application-password section.
4. **`null`.** As the product does today.

When `user_can_connect` is false, do not show the one URL as usable: say that the site admin
decides who can connect, with the link to `manage_url` for an admin.

## Letting a product's own managers connect

By default only the roles ticked under "Who can connect apps" (administrator always) may use
the hub. A product whose managers are not administrators, such as a community moderator or a
support agent who is a WordPress subscriber, admits them to its own tools:

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
