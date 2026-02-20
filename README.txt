=== Social Feed ===

Contributors: yourname
Tags: social feed, instagram, youtube, facebook, feed
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display social media feeds from Instagram, YouTube, and Facebook on your WordPress site.

== Description ==

Social Feed allows you to display posts from Instagram, YouTube, and Facebook in customizable layouts (grid, list, carousel). Configure your API credentials in the admin panel and use the shortcode to embed feeds anywhere.

== Installation ==

1. Upload the `social-feed` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Social Feed in the admin menu to configure your API keys
4. Use the shortcode: [social_feed platform="instagram" layout="grid" limit="9" columns="3"]

== Shortcode ==

[social_feed platform="instagram" layout="grid" limit="9" columns="3"]

Parameters:
* platform - instagram, youtube, or facebook (default: instagram)
* layout - grid, list, or carousel (instagram only); grid for youtube (default: grid)
* limit - number of posts (1-50, default: 9)
* columns - grid columns 1-6 (default: 3)

== Frequently Asked Questions ==

= How do I get API credentials? =

Each platform requires different setup:
* Instagram: Facebook Developer App, Instagram Basic Display or Graph API
* YouTube: Google Cloud Console, YouTube Data API v3
* Facebook: Facebook Developer App, Page Access Token

== Changelog ==

= 1.0.0 =
* Initial release
* Instagram, YouTube, Facebook support
* Grid, list, carousel layouts
