# AutoloadFix development repository

This repository contains the maintained development history for the plugin currently being resubmitted to WordPress.org under the distinctive public identity **Aulonexa - Autoload & Cache Diagnostics**.

The pending WordPress.org permalink request is `aulonexa-autoload-diagnostics`.

## Source and build

All custom runtime PHP, JavaScript, and CSS is maintained in human-readable form. In particular, `assets/js/advanced.js` is committed as readable source and is not a generated/minified-only artifact.

The plugin does not require npm, webpack, transpilation, or another front-end compilation step for its runtime assets. The GitHub Actions workflow in `.github/workflows/quality.yml` documents the release packaging and runs PHP syntax checks plus WordPress Plugin Check.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Version 1.4.x functionality

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

The plugin never deletes option values and never automatically mutates unknown options. Administrator-triggered autoload changes require a successful snapshot first and are verified after WordPress applies the change.

The cache advisor and site scanner do not install third-party cache plugins. Optimization profiles are administrator-requested downloads only. Site scanner requests are anonymous and restricted to the same WordPress site, and the scanner stores diagnostic response metadata rather than page content.

## License

GPL-2.0-or-later.
