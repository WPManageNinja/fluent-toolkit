=== Fluent Toolkit ===
Contributors: techjewel, wpmanageninja
Tags: fluent plugins, toolkit
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Install beta builds, release candidates, and companion add-ons for the Fluent ecosystem — all from one place.

== Description ==

Fluent Toolkit gives you early access to beta builds and release candidates across the Fluent plugin ecosystem. Install companion add-ons, track update availability, and stay ahead of what's shipping — without leaving your WordPress dashboard.

Fluent Toolkit also provides FluentCRM MCP OAuth support. It keeps the FluentCRM MCP endpoint at `/wp-json/fluent-crm/mcp`, adds OAuth discovery and token flow endpoints, and can load the official WordPress MCP Adapter as an isolated, replaceable dependency fallback.

== Changelog ==

= 1.2.0 =
* Added built-in FluentCRM MCP OAuth bridge while keeping the existing `/wp-json/fluent-crm/mcp` route and OAuth endpoints
* Added replaceable WordPress MCP Adapter provider using the official `wordpress/mcp-adapter` package
* Added Toolkit dashboard status for MCP Adapter, Abilities API, OAuth status, and connector URLs

= 1.1.0 =
* Redesigned dashboard UI — modern layout with topbar, hero stats, channel filter tabs, and plugin grid
* Added search to filter plugins by name or description
* Added channel tabs: All / Beta / Installed / Updates
* Live stats in hero: available, installed, and pending updates
