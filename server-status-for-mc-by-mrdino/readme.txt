=== Server Status for MC by MrDino ===
Contributors: mrdinocarlos
Donate link: https://buymeacoffee.com/mrdino
Tags: minecraft, server, status
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your Minecraft server status on your WordPress site. Basic mode works without any Minecraft plugin.

== Description ==

Display your Minecraft server status on your WordPress site. Basic mode works without any Minecraft plugin.
Server Status for MC by MrDino lets you show a clean, modern status card for your Minecraft server.

== Installation ==

1. Install through the WordPress plugins screen, search by "Server Status for MC by MrDino".
2. Activate the plugin.
3. Go to **Settings → Server Status for MC by MrDino** and enter your server details.
4. Use the shortcode `[mcsmd_status]` - shows the main Minecraft server status card (IP, version, MOTD, banner, players, ping, etc.).
4b. Use the shortcode `[mcsmd_players]` – shows only the online players list, with search box and sorting options.

== Quick start ==

Enter your Minecraft server address and port in the “Basic server settings” section above and save the changes.
Optionally adjust the display options (banner, MOTD, dark mode, player list, etc.).
Create or edit a page, paste the shortcode you want to use and publish the page.
Tip: the status and players cards auto-refresh in the background, so visitors will see updated information without reloading the whole page.

== External services ==

This plugin is developed and maintained by MrDino (https://mrdino.es).
All live status and avatar features are powered by the external services listed below.
Your WordPress site does not send any data to mrdino.es beyond the normal plugin links and metadata.

This plugin connects to two external services to display live Minecraft data and player avatars.

= mcsrvstat.us API =

This service is used to fetch live status information (online state, players, MOTD, version, ping, etc.) and an optional banner image for the Minecraft server you configure in the plugin settings.
* What data is sent: the server address (host and port) that you enter in the plugin settings.
* When data is sent: whenever the status or player list shortcodes are loaded on a page, or when they refresh via AJAX in the background.
* Service website and API documentation: https://mcsrvstat.us/ (API at https://api.mcsrvstat.us/)
* Legal / privacy: please refer to the legal information and privacy details linked from their website.

= MCHeads (mc-heads.net) =

This service is used to generate square avatar images ("heads") for Minecraft players based on their in-game name.
* What data is sent: the Minecraft player name(s) reported by your server (no WordPress user data is sent).
* When data is sent: when the player list is displayed and an avatar image needs to be shown for an online player.
* Service website: https://mc-heads.net/
* Terms of use: https://minecraft-heads.com/terms-of-use
* Privacy policy: https://minecraft-heads.com/privacy-policy

== Changelog ==

= 0.0.5 =

Added a new opt-in setting “Show credit link” allowing site owners to optionally display a small attribution link in the status cards.
Fixed all frontend credit/powered-by links that were previously displayed without user consent.
Fixed both status card and players list to display the credit only when the new option is explicitly enabled.
Fixed full compliance with WordPress.org guidelines regarding attribution and front-facing links.
Changed the settings page to include the new credit toggle under Display options (disabled by default).


