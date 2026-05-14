=== FluentKit ===
Contributors: techjewel, wpmanageninja
Tags: fluent plugins, toolkit
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Install early access addons, release candidates, and companion add-ons for the Fluent ecosystem — all from one place.

== Description ==

Fluent Toolkit gives you early access to beta builds and release candidates across the Fluent plugin ecosystem. Install companion add-ons, track update availability, and stay ahead of what's shipping — without leaving your WordPress dashboard.

Fluent Toolkit can load the official WordPress MCP Adapter as an isolated, replaceable dependency fallback. Authentication and authorization for MCP routes should be handled outside this plugin.

== Installation ==

Download the latest plugin zip from the GitHub Latest Release page:
https://github.com/WPManageNinja/fluent-toolkit/releases/latest

Upload the zip from your WordPress dashboard, activate Fluent Toolkit, then open Dashboard > Fluent Toolkit.

== Changelog ==

= 2.0.0 =
* Added replaceable WordPress MCP Adapter provider using the official `wordpress/mcp-adapter` package

= 1.1.0 =
* Redesigned dashboard UI — modern layout with topbar, hero stats, channel filter tabs, and plugin grid
* Added search to filter plugins by name or description
* Added channel tabs: All / Beta / Installed / Updates
* Live stats in hero: available, installed, and pending updates
