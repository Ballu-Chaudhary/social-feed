=== Social Feed - Instagram, YouTube & Facebook Feeds ===
Contributors: yourname
Donate link: https://yourpluginsite.com/donate/
Tags: instagram feed, youtube feed, facebook feed, social media, social feed
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display beautiful Instagram, YouTube, and Facebook feeds on your WordPress website. Customizable layouts, caching, and Gutenberg block included.

== Description ==

**Social Feed** is the easiest way to display your social media content on your WordPress website. Show your Instagram photos, YouTube videos, and Facebook posts in beautiful, customizable layouts that perfectly match your brand.

= Why Choose Social Feed? =

* **Easy Setup** - Connect your accounts in minutes with our guided setup wizard
* **Beautiful Designs** - Choose from Grid, List, Masonry, or Carousel layouts
* **Fully Customizable** - Adjust colors, spacing, fonts, and more without any coding
* **Lightning Fast** - Smart caching ensures your pages load quickly
* **Mobile Ready** - Looks perfect on phones, tablets, and desktops
* **SEO Friendly** - Server-side rendering for better search engine indexing

= Features =

**Instagram Feed**
Display your Instagram photos and videos in a stunning grid layout. Show likes, comments, and captions. Supports Instagram Basic Display API.

**YouTube Feed** (Pro)
Showcase your YouTube channel videos. Display video thumbnails, titles, view counts, and more. Supports playlists and search.

**Facebook Feed** (Pro)
Show your Facebook page posts including text, photos, videos, and events. Display likes, comments, and shares.

**Multiple Layouts**
* Grid - Classic grid layout with customizable columns
* List - Full-width posts with detailed information
* Masonry - Pinterest-style dynamic grid (Pro)
* Carousel - Swipeable slider with navigation (Pro)

**Customization Options**
* Custom colors and fonts
* Adjustable spacing and borders
* Hover effects (zoom, fade, overlay)
* Header with profile info and follow button
* Load more button or infinite scroll
* Lightbox popup for media viewing (Pro)

**Developer Friendly**
* Clean, well-documented code
* WordPress Coding Standards compliant
* Hooks and filters for customization
* Template override system

= Pro Features =

Upgrade to [Social Feed Pro](https://yourpluginsite.com/pricing/) for:

* Instagram Hashtag Feeds
* YouTube Integration
* Facebook Integration
* Masonry & Carousel Layouts
* Lightbox Popup
* Content Moderation
* Feed Analytics
* Priority Support
* Automatic Updates

= Documentation =

Visit our [documentation](https://yourpluginsite.com/docs/) for detailed guides and tutorials.

= Support =

Need help? Check out our [support forum](https://wordpress.org/support/plugin/social-feed/) or visit our [knowledge base](https://yourpluginsite.com/docs/).

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Social Feed"
3. Click **Install Now** and then **Activate**
4. Go to **Social Feed** in the admin menu to configure

= Manual Installation =

1. Download the plugin ZIP file from WordPress.org
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the downloaded ZIP file
4. Click **Install Now** and then **Activate**
5. Go to **Social Feed** in the admin menu to configure

= Configuration =

1. Go to **Social Feed > Settings > API Credentials**
2. Enter your API credentials for each platform you want to use
3. Go to **Social Feed > Connected Accounts**
4. Click "Connect New Account" and authorize your social media accounts
5. Go to **Social Feed > Create Feed** to create your first feed
6. Copy the shortcode and paste it into any page or post

== Frequently Asked Questions ==

= How do I connect my Instagram account? =

1. Go to Social Feed > Connected Accounts
2. Click "Connect New Account"
3. Select Instagram from the platform list
4. Click "Connect with Instagram"
5. Log in to your Instagram account and authorize the app
6. Your account will appear in the Connected Accounts list

= Why isn't my feed showing any posts? =

There are several reasons why your feed might not show posts:

1. **Cache**: Try clearing the cache in Settings > Cache
2. **API Credentials**: Verify your API credentials are correct in Settings
3. **Token Expired**: Check if your access token has expired and reconnect
4. **Account Privacy**: Instagram Business/Creator accounts work better than personal accounts

= How often does the feed update? =

By default, feeds are cached for 1 hour to improve performance and reduce API calls. You can change this in Settings > Cache. The plugin also runs hourly background refreshes for all active feeds.

= Is this plugin GDPR compliant? =

Yes! Social Feed includes a GDPR mode that shows a consent notice before loading any external content. Enable this in Settings > Privacy/GDPR. The plugin does not store any visitor personal data - it only stores the posts fetched from your connected social media accounts.

= Can I use this with page builders? =

Yes! Social Feed works with all major page builders including:

* Gutenberg (native block included)
* Elementor (use shortcode widget)
* Beaver Builder (use HTML module)
* Divi (use Code module)
* WPBakery (use Raw HTML element)

== Screenshots ==

1. Dashboard overview showing feeds, accounts, and quick stats
2. Feed Customizer with live preview and styling options
3. Connected Accounts page for managing social media connections
4. Instagram feed displayed in grid layout on the frontend
5. YouTube feed showing video thumbnails with play buttons
6. Facebook feed with posts, photos, and engagement stats
7. Lightbox popup for viewing media in full size
8. Settings page with General, Cache, Privacy, and Advanced tabs
9. Gutenberg block in the WordPress block editor
10. Mobile responsive view on a smartphone

== Changelog ==

= 1.0.0 - 2024-01-15 =

**Initial Release**

* New: Instagram Basic Display API integration
* New: YouTube Data API v3 integration (Pro)
* New: Facebook Graph API integration (Pro)
* New: Grid layout for all platforms
* New: List layout for detailed posts
* New: Masonry layout (Pro)
* New: Carousel layout (Pro)
* New: Feed Customizer with live preview
* New: Gutenberg block support
* New: Shortcode [social_feed] support
* New: Connected Accounts management
* New: OAuth 2.0 authentication flow
* New: Token auto-refresh for Instagram
* New: Caching system with background refresh
* New: GDPR compliance mode
* New: Lightbox popup for media (Pro)
* New: Content moderation tools (Pro)
* New: Feed analytics dashboard (Pro)
* New: License management for Pro
* New: Automatic updates for Pro
* New: Email notifications for token expiry
* New: Export/Import settings
* New: Debug mode for troubleshooting

== Upgrade Notice ==

= 1.0.0 =
Initial release of Social Feed. Install to start displaying your social media feeds on WordPress!

== Additional Info ==

= Minimum Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* HTTPS enabled (required for OAuth)

= API Requirements =

**Instagram:**
* Meta Developer Account
* Instagram Basic Display API app

**YouTube:**
* Google Cloud Console account
* YouTube Data API v3 enabled

**Facebook:**
* Meta Developer Account
* Facebook Login configured

= Privacy Policy =

Social Feed connects to third-party services (Instagram, YouTube, Facebook) to fetch your content. Please review each platform's privacy policy:

* [Instagram Privacy Policy](https://help.instagram.com/519522125107875)
* [YouTube Privacy Policy](https://policies.google.com/privacy)
* [Facebook Privacy Policy](https://www.facebook.com/privacy/policy/)

The plugin stores API responses in your WordPress database for caching purposes. No visitor personal data is collected or stored.

= Credits =

* Developed by [Your Name](https://yourwebsite.com)
* Icons from WordPress Dashicons
* Built following WordPress Coding Standards
