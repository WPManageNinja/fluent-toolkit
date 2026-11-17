=== FluentHub ===
Contributors: techjewel, wpmanageninja
Tags: fluent plugins, toolkit
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

One place to install, update and connect your Fluent plugins.

== Description ==

FluentHub lists every Fluent plugin and add-on in one dashboard. Install them, update them all in one click, and get release candidates before they reach wordpress.org. The Unified UI opens your Fluent plugins in one workspace, with a shared sidebar to move between them.

FluentHub also runs one MCP server for all your Fluent plugins, so AI apps such as Claude, Codex, GitHub Copilot and Cursor can work with them through one URL. Apps sign in with your WordPress account (OAuth), and each connection has the permissions of the user who approved it. The MCP server is off until you turn it on. FluentHub loads the official WordPress MCP Adapter when no other plugin supplies it.

== Installation ==

Download the latest plugin zip:
https://static.wpmanageninja.com/fluent-toolkit.zip

Upload the zip from your WordPress dashboard, activate FluentHub, then open FluentHub in the admin menu.

== Changelog ==

= 2.2.0 (Date: 17 Nov, 2026) =
* New: FluentHub MCP server: one URL gives AI apps every Fluent plugin whose MCP is on, with one read tool and one write tool per plugin
* New: Sign in with WordPress (OAuth 2.1) for the FluentHub MCP server: Claude, Claude Code, Codex, GitHub Copilot, Cursor and other MCP apps connect with the URL alone, with no application password to copy
* New: Connected apps list on the MCP page; revoke a connection there or on the user's profile
* New: Fluent plugins can show a "connect with one URL" card on their own MCP screens and turn the FluentHub MCP server on from there
* New: FluentCommunity in the FluentHub MCP server
* Improved: The FluentHub dashboard and MCP page now look like the other Fluent apps, in light and dark mode
* Improved: Plainer wording across FluentHub, and one name (FluentHub) everywhere; error messages now say what to do next
* Improved: A simpler MCP page around one "FluentHub MCP server" switch, with a logo and a short description for each plugin
* Improved: Each tool result is sent once, so AI apps that read both copies use fewer tokens
* Fixed: Dashboard notifications had no background and sat on the top bar; they now show at the bottom right

= 2.1.4 (Date: 23 Oct, 2026) =
* New: Update all active Fluent plugins in one click: free plugins first, then Pro add-ons, then FluentHub
* New: Collapsible icon-rail sidebar on desktop in the Unified UI
* New: FluentHub is translatable (dashboard, MCP screens and sidebar) and ships a POT file
* New: FluentComments in the dashboard plugin list
* New: Dark mode for Fluent Support 2.4.7 and later in the Unified UI
* Improved: Updated the bundled MCP Adapter to 0.7.1, which includes a security fix for MCP session storage
* Improved: The MCP page shows which plugin supplies the MCP Adapter, and warns when another plugin bundles an older copy
* Fixed: FluentHub reported its own MCP Adapter version when another plugin, such as Rank Math, had already loaded an older copy
* Fixed: Forms and Ninja Tables were missing from the hub menu for non-admin users who have access
* Fixed: The Unified UI sidebar stayed light in FluentBoards dark mode

= 2.1.3 (Date: 09 Oct, 2026) =
* New: "Settings" link for active plugins in the FluentHub plugin list
* Fixed: FluentSupport single-ticket action bar overlapping the app navbar in the Unified UI
* Improved: Namespaced the AJAX nonce request key

= 2.1.2 (Date: 17 Sep, 2026) =
* Fixed: FluentAuth 3.0's Settings and Security screens rendered underneath the Unified UI sidebar — the settings pane and section bar are now laid out inside the content column
* Fixed: FluentSMTP 2.4's app header rendered across the Unified UI sidebar and over the page heading
* Fixed: Unified UI sidebar links for FluentAuth 3.0 now point at its current screens instead of bouncing through redirects

= 2.1.1 (Date: 31 Aug, 2026) =
* Styling Improvements for the plugin dashboard
* Updated MCP Adapter to the latest version - 0.6.1
* Added Dark mode support to more Fluent Plugins

= 2.1.0 =
* New: Addon updates now appear on the Plugins screen right after release — no more waiting out wp.org's update-check delay
* New: FluentPlayer added to the plugin catalog
* Improved: Local and remote plugin catalogs are merged by slug — no duplicate entries, remote data takes priority
* Improved: Fresh installs of wp.org plugins always fetch the latest stable release
* Improved: Refresh button added to the dashboard top bar

= 2.0.5 =
* Standalone MCP Adapter collision fix

= 2.0.2 =
* UI Refactored
* Mobile Responsive Added

= 2.0.2 =
* Bug Fixed

= 2.0.0 =
* New: AI / MCP support — turn on Model Context Protocol tools per Fluent product (FluentCRM, Fluent Forms, FluentCart, and more) directly from the Toolkit settings
* New: Unified UI — a single, consistent admin experience that ties your Fluent plugins together under one dashboard
* New: Built-in update channel — Toolkit now self-updates from the official Fluent release channel, with "Check Update" and version details available right from the Plugins screen
* Bundles the official WordPress MCP Adapter as a safe fallback when no other plugin provides it

= 1.1.0 =
* Redesigned dashboard UI — modern layout with topbar, hero stats, channel filter tabs, and plugin grid
* Added search to filter plugins by name or description
* Added channel tabs: All / Beta / Installed / Updates
* Live stats in hero: available, installed, and pending updates
