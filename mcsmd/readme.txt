=== MC Status by MrDino ===
Contributors: mrdinocarlos
Donate link: https://buymeacoffee.com/mrdino
Tags: minecraft, server, status
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.0.2
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
4. Use the shortcode `[mcsmd_status]` - shows the main Minecraft server status card (IP, version, MOTD, banner, players, ping, etc.).
4b. Use the shortcode `[mcsmd_players]` – shows only the online players list, with search box and sorting options.

== Quick start ==
Enter your Minecraft server address and port in the “Basic server settings” section above and save the changes.
Optionally adjust the display options (banner, MOTD, dark mode, player list, etc.).
Create or edit a page, paste the shortcode you want to use and publish the page.
Tip: the status and players cards auto-refresh in the background, so visitors will see updated information without reloading the whole page.

== Changelog ==

= 0.0.2 =

Added automatic AJAX refresh for the main server status card every 15 seconds.
Added automatic AJAX refresh for the [mcsmd_players] shortcode, including offline → online transitions.
Added manual refresh button (AJAX-based) that updates both cards instantly without page reload.
Added persistent “last known” data system: icon, MOTD (HTML/clean), and version are retained while offline.
Added internal storage fields: last_icon, last_motd_html, last_motd_clean, and updated version persistence logic.
Added support for displaying server port even when it is the default 25565 (new show_port_in_ip option).
Added complete shortcode documentation inside the admin panel with usage examples for [mcsmd_status] & [mcsmd_players].
Added improved safeguarding against page caching plugins (LiteSpeed, WP Rocket, Cloudflare).
Added auto-recovery system when API cache returns incomplete icon/banner data.
Added global forced dark mode toggle.
Added translators comments (translators:) for all localization strings containing placeholders for full i18n compatibility.
Added PHPCS-compliant handling for socket-based ping measurement, with explicit ignore rules for fsockopen() and fclose() where allowed.
Added enhanced AJAX refresh structure for both the main status card and the players list shortcode.
Added improved internal cache-bypass logic for more reliable real-time updates and consistent transitions.
Fixed player list block not updating automatically on status change (offline ↔ online).
Fixed manual refresh not updating the player list block.
Fixed MOTD & banner disappearing during offline mode — now they remain until the next valid online response.
Fixed refresh logic to properly bypass transients when needed.
Fixed multiple WordPressCS errors, including: Non-prefixed global constant warnings for DONOTCACHEPAGE.
Fixed multiple WordPressCS errors, including: Missing translators comments for localized strings with placeholders.
Fixed multiple WordPressCS errors, including: Alternative PHP filesystem warnings for fsockopen() / fclose().
Fixed incorrect placement of PHPCS ignore tags, preventing false rule triggers.
Fixed hover “glare” animation that covered text elements.
Fixed dark mode inconsistencies and missing color override selectors.
Fixed fallback version display when a server goes offline.
Fixed alignment issues in player list rows and column-based layouts.
Fixed escaping inconsistencies in printf() and echo calls.
Fixed AJAX nonce validation order inside refresh endpoints.
Fixed minor CSS spacing, padding, and container layout issues across multiple screen sizes.
Fixed duplicate settings updates being triggered unnecessarily.
Fixed DOM handling issues in player filters/search after AJAX refresh.
Removed outdated GET-based manual refresh logic (kept only for backward compatibility fallback).
Removed redundant code paths in MOTD parsing & icon handling.
Removed multiple old PHPCS ignore comments no longer required.
Removed redundant inline comments and legacy AJAX formatting leftover from early versions.
Removed obsolete caching behavior that caused stale banner/icon data.