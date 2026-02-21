# Changelog

All notable changes to the Social Feed plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- TikTok integration
- Twitter/X integration
- Feed combining (multiple sources in one feed)
- Shoppable Instagram feeds
- Story support for Instagram

---

## [1.0.0] - 2024-01-15

### Added

#### Core Features
- Initial release of Social Feed plugin
- WordPress 5.8+ compatibility
- PHP 7.4+ support
- Gutenberg block editor support
- Classic editor shortcode support
- Multisite network compatibility

#### Platform Integrations
- **Instagram Basic Display API**
  - User feed display
  - Profile information (avatar, username, bio, followers)
  - Media types: Image, Video, Carousel
  - Hashtag feeds (Pro)
  - Automatic token refresh
  
- **YouTube Data API v3** (Pro)
  - Channel videos display
  - Playlist support
  - Video search functionality
  - Video details (views, likes, duration)
  - Channel information display
  
- **Facebook Graph API** (Pro)
  - Page posts display
  - Photos and videos
  - Events display
  - Reviews/ratings display
  - Page information

#### Layouts
- **Grid Layout** - Responsive grid with customizable columns
- **List Layout** - Full-width posts with details
- **Masonry Layout** (Pro) - Pinterest-style dynamic grid
- **Carousel Layout** (Pro) - Touch-enabled slider with navigation

#### Feed Customizer
- Live preview while editing
- Device preview (Desktop, Tablet, Mobile)
- Header customization (avatar, username, bio, follow button)
- Color customization (background, text, links, overlays)
- Typography settings
- Spacing and border controls
- Hover effects (zoom, fade, slide)
- Custom CSS support

#### Caching System
- WordPress transients-based caching
- Configurable cache duration (1 hour to 1 week)
- Background cache refresh via WP-Cron
- Manual cache clear functionality
- Cache statistics dashboard

#### Account Management
- OAuth 2.0 authentication flow
- Multiple accounts per platform
- Token expiry monitoring
- Automatic token refresh (Instagram)
- Email notifications for expiring tokens
- Easy reconnection flow

#### Admin Interface
- Dashboard with quick stats
- All Feeds list view with bulk actions
- Connected Accounts management
- Settings page with 4 tabs (General, Cache, Privacy, Advanced)
- License activation page
- Help & Support page

#### Privacy & GDPR
- GDPR compliance mode
- Customizable consent notice
- Data retention settings
- Delete data on uninstall option
- No visitor tracking

#### Developer Features
- WordPress Coding Standards compliant
- Extensive hooks and filters
  - `sf_feed_items` - Modify feed items before display
  - `sf_before_render_feed` - Action before rendering
  - `sf_after_render_feed` - Action after rendering
- Template override system (theme templates)
- REST API ready
- Translation ready (i18n)

#### Pro Features
- License key activation
- Automatic updates
- Priority support access
- All platform integrations
- Advanced layouts
- Lightbox popup
- Content moderation
- Feed analytics
- White label option (Agency plan)

### Security
- Nonce verification on all forms
- Capability checks for admin actions
- Encrypted token storage
- Sanitized inputs and escaped outputs
- Direct file access prevention

### Performance
- Conditional asset loading
- Lazy loading for images
- Minified CSS and JavaScript
- Database query optimization
- Efficient API request handling

---

## Upgrade Guide

### Upgrading to 1.0.0
This is the initial release. No upgrade steps required.

---

## Version History

| Version | Release Date | WordPress | PHP |
|---------|--------------|-----------|-----|
| 1.0.0   | 2024-01-15   | 5.8+      | 7.4+|

---

## Support

- **Documentation**: [https://yourpluginsite.com/docs/](https://yourpluginsite.com/docs/)
- **Support Forum**: [https://wordpress.org/support/plugin/social-feed/](https://wordpress.org/support/plugin/social-feed/)
- **Pro Support**: [https://yourpluginsite.com/support/](https://yourpluginsite.com/support/)

---

## Links

- [Plugin Website](https://yourpluginsite.com/)
- [GitHub Repository](https://github.com/yourusername/social-feed)
- [WordPress.org](https://wordpress.org/plugins/social-feed/)

[Unreleased]: https://github.com/yourusername/social-feed/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourusername/social-feed/releases/tag/v1.0.0
