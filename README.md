# Loadvexa - Autoload & Cache Diagnostics

Loadvexa is the approved WordPress.org identity for this project.

- WordPress.org slug: `loadvexa-autoload-diagnostics`
- Public directory URL: https://wordpress.org/plugins/loadvexa-autoload-diagnostics/
- WordPress.org SVN: https://plugins.svn.wordpress.org/loadvexa-autoload-diagnostics/
- Current approved release: `1.4.1`

This repository preserves the development history that began under the AutoloadFix working name. Internal class names, option keys, CSS selectors, and database identifiers may retain the `autoloadfix` prefix for backward compatibility; the public plugin identity is Loadvexa.

## Source and build

All custom runtime PHP, JavaScript, and CSS is maintained in human-readable form. `assets/js/advanced.js` is committed as readable source and is not a generated/minified-only artifact.

The plugin does not require npm, webpack, transpilation, or another front-end compilation step for its runtime assets. `.github/workflows/quality.yml` documents the quality checks and package build process.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Main functionality

- Autoload health metrics and option diagnostics
- Snapshot-backed autoload changes and restore
- Monitoring and audit history
- Cache & Optimization Advisor
- Page-by-page Site Problem Scanner
- WooCommerce-aware dynamic page checks
- Supported cache-plugin optimization profiles
- WP Rocket JSON profile support
- LiteSpeed Cache native `.data` profile support
- Re-check workflow that verifies results instead of assuming a setting change worked

## Safety

Loadvexa never deletes option values and never automatically mutates unknown options. Administrator-triggered autoload changes require a successful snapshot first and are verified after WordPress applies the change.

The cache advisor and site scanner do not install third-party cache plugins. Optimization profiles are administrator-requested downloads only. Site scanner requests are anonymous and restricted to the same WordPress site, and the scanner stores diagnostic response metadata rather than page content.

## WordPress.org release

The plugin was approved for the WordPress.org Plugin Directory under the permanent slug `loadvexa-autoload-diagnostics`. Finished releases belong in WordPress.org SVN; banners, icons, and screenshots belong in the top-level SVN `/assets/` directory.

## License

GPL-2.0-or-later.
