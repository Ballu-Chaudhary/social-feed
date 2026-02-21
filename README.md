# Social Feed

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/yourusername/social-feed/pulls)

Display beautiful social media feeds from Instagram, YouTube, and Facebook on your WordPress website. No coding required.

## Description

Social Feed is a powerful WordPress plugin that allows you to seamlessly integrate social media content into your website. Display your Instagram photos, YouTube videos, and Facebook posts in stunning, customizable layouts that match your brand.

### Key Features

- **Multiple Platforms** - Connect Instagram, YouTube, and Facebook accounts
- **Beautiful Layouts** - Grid, List, Masonry, and Carousel layouts
- **Fully Customizable** - Colors, spacing, hover effects, and more
- **Mobile Responsive** - Looks great on all devices
- **Lightbox Popup** - View media in an elegant popup
- **Caching System** - Fast loading with smart caching
- **Gutenberg Block** - Native block editor support
- **Shortcode Support** - Use anywhere with simple shortcodes

## Features Comparison

| Feature | Free | Pro |
|---------|:----:|:---:|
| Instagram User Feeds | ✅ | ✅ |
| Grid Layout | ✅ | ✅ |
| Shortcode Support | ✅ | ✅ |
| Gutenberg Block | ✅ | ✅ |
| Responsive Design | ✅ | ✅ |
| Instagram Hashtag Feeds | ❌ | ✅ |
| YouTube Integration | ❌ | ✅ |
| Facebook Integration | ❌ | ✅ |
| Masonry Layout | ❌ | ✅ |
| Carousel Layout | ❌ | ✅ |
| Lightbox Popup | ❌ | ✅ |
| Content Moderation | ❌ | ✅ |
| Feed Analytics | ❌ | ✅ |
| White Label | ❌ | ✅ (Agency) |
| Priority Support | ❌ | ✅ |

## Installation

### Via WordPress Admin

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Social Feed"
3. Click **Install Now** and then **Activate**
4. Go to **Social Feed > Settings** to configure the plugin

### Manual Installation

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Activate the plugin

### Via Composer

```bash
composer require yourname/social-feed
```

## Configuration

### Instagram API Setup

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Create a new app and select "Consumer" type
3. Add the "Instagram Basic Display" product
4. Configure OAuth redirect URI: `https://yoursite.com/wp-admin/admin-ajax.php?action=sf_instagram_callback`
5. Copy your App ID and App Secret
6. Go to **Social Feed > Settings > API Credentials**
7. Enter your Instagram App ID and App Secret
8. Click "Save Settings"

### YouTube API Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable the "YouTube Data API v3"
4. Go to **Credentials** and create an API Key
5. Restrict the key to YouTube Data API v3
6. Go to **Social Feed > Settings > API Credentials**
7. Enter your YouTube API Key
8. Click "Save Settings"

### Facebook API Setup

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Create a new app and select "Business" type
3. Add the "Facebook Login" product
4. Configure OAuth redirect URI: `https://yoursite.com/wp-admin/admin-ajax.php?action=sf_facebook_callback`
5. Request permissions: `pages_read_engagement`, `pages_read_user_content`
6. Copy your App ID and App Secret
7. Go to **Social Feed > Settings > API Credentials**
8. Enter your Facebook App ID and App Secret
9. Click "Save Settings"

## Usage

### Shortcode

```
[social_feed id="1"]
```

**Available Attributes:**

| Attribute | Description | Default |
|-----------|-------------|---------|
| `id` | Feed ID (required) | - |
| `title` | Override feed title | - |
| `columns` | Number of columns | 3 |
| `limit` | Number of posts | 12 |

**Example:**
```
[social_feed id="1" columns="4" limit="8"]
```

### Gutenberg Block

1. Edit a page or post with the block editor
2. Click the "+" button to add a block
3. Search for "Social Feed"
4. Select the Social Feed block
5. Choose your feed from the dropdown
6. Publish or update your page

### PHP Template

```php
<?php
if ( function_exists( 'SF_Renderer' ) ) {
    echo SF_Renderer::render_feed( 1 );
}
?>
```

## Screenshots

1. **Dashboard** - Overview of all your feeds and accounts
2. **Feed Customizer** - Visual editor with live preview
3. **Connected Accounts** - Manage your social media connections
4. **Instagram Grid** - Beautiful grid layout for Instagram
5. **YouTube Feed** - Showcase your YouTube videos
6. **Facebook Feed** - Display Facebook page posts
7. **Lightbox Popup** - Elegant media popup
8. **Settings Page** - Configure plugin options
9. **Gutenberg Block** - Native block editor integration
10. **Mobile View** - Fully responsive on all devices

## Frequently Asked Questions

### How do I get my Instagram Access Token?

Go to **Social Feed > Connected Accounts** and click "Connect New Account". Select Instagram and follow the OAuth authorization flow. The plugin will automatically obtain and store your access token.

### Why are my feeds not updating?

Feeds are cached to improve performance. You can clear the cache by going to **Social Feed > Settings > Cache** and clicking "Clear All Cache". You can also adjust the cache duration.

### Can I display feeds from multiple accounts?

Yes! You can connect multiple accounts from each platform and create separate feeds for each. You can also combine accounts in a single feed (Pro feature).

### Is the plugin GDPR compliant?

Yes. The plugin includes GDPR mode which shows a consent notice before loading any external content. You can customize the consent message in **Settings > Privacy/GDPR**.

### How do I get support?

- **Free users**: Post in the [WordPress.org support forum](https://wordpress.org/support/plugin/social-feed/)
- **Pro users**: Submit a ticket at [our support portal](https://yourpluginsite.com/support/)

## Changelog

### 1.0.0 (2024-01-15)

**Initial Release**

- Instagram Basic Display API integration
- YouTube Data API v3 integration
- Facebook Graph API integration
- Grid, List, Masonry, and Carousel layouts
- Feed Customizer with live preview
- Gutenberg block support
- Shortcode support
- Caching system with background refresh
- GDPR compliance mode
- License management system
- Automatic updates for Pro

See [CHANGELOG.md](CHANGELOG.md) for full changelog.

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Run linting: `composer run lint`
5. Commit your changes: `git commit -m 'Add amazing feature'`
6. Push to the branch: `git push origin feature/amazing-feature`
7. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/yourusername/social-feed.git

# Install dependencies
composer install
npm install

# Run linting
composer run lint

# Build assets
npm run build
```

### Coding Standards

This plugin follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/). Please ensure your code passes PHPCS before submitting a PR.

## License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Social Feed - Display social media feeds on WordPress
Copyright (C) 2024 Your Name

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

- Built with [WordPress](https://wordpress.org/)
- Icons by [Dashicons](https://developer.wordpress.org/resource/dashicons/)

## Support

- [Documentation](https://yourpluginsite.com/docs/)
- [Support Forum](https://wordpress.org/support/plugin/social-feed/)
- [Pro Support](https://yourpluginsite.com/support/)

---

Made with ❤️ by [Your Name](https://yourwebsite.com)
