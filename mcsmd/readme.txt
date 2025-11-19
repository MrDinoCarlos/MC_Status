=== MC Status by MrDino ===
Contributors: mrdinocarlos
Donate link: https://buymeacoffee.com/mrdino
Tags: minecraft, server, status
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your Minecraft server status on your WordPress site. Basic mode works without any Minecraft plugin.

== Description ==

Display your Minecraft server status on your WordPress site. Basic mode works without any Minecraft plugin.
MC Status by MrDino lets you show a clean, modern status card for your Minecraft server.

== Installation ==

1. Install through the WordPress plugins screen, search by "MC Status by MrDino".
2. Activate the plugin.
3. Go to **Settings → MC Status by MrDino** and enter your server details.
4. Use the shortcode `[mcsmd_status]` in any page or post.

== Changelog ==

= 0.0.1 =
* Initial release.
Introduced the first public version of MC Status by MrDino.
Added a modern, Minecraft-inspired server status card for WordPress.
Implemented Online/Offline server detection using the mcsrvstat.us API.
Added server version detection with fallback to last-known version when offline.
Player count display with support for max players.
Built-in TCP ping measurement for more accurate latency results.
Dynamic status indicator with soft green pulse animation when online.
Server IP, MOTD, Server banner, Player list, Minecraft avatars via mc-heads.net,
Ping tooltip, Player status dot.
Added card glow (green for online / red for offline).
Full dark mode support (admin toggle).
Refresh button to instantly update server state and bypass caching.
Customizable settings panel in WordPress Admin.
Added protection against page caching (DONOTCACHEPAGE).
WordPress-compatible asset loading system (CSS/JS).
Added credits footer with link to plugin author.
Security improvements: escaped output, sanitized inputs, Plugin Check compliance.
Added admin notice explaining the 1-minute delay when using only the API.
Added shortcode: [mcsmd_status]
Added fallback icon when no server favicon is available.
