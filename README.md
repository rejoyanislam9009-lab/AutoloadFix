# AutoloadFix

AutoloadFix is a WordPress performance utility for auditing autoloaded options, tracking growth, reviewing likely ownership, understanding cache layers, cautiously changing autoload behavior, and restoring changes from snapshots.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Version 1.2.1 highlights

- Searchable/filterable largest-option review workspace
- Ownership confidence and per-option impact percentages
- Watch and ignore lists
- Read-only safe mode
- Custom protected options and automatic protection for prefixed WordPress user-role options
- Manual/daily/weekly audit history with growth alerts
- Snapshot-before-change and restore history
- Cache & Optimization Advisor
- Detection of page-cache-capable plugins, persistent object cache, cache drop-ins, and asset-optimization capability
- Warning for potentially overlapping page-cache plugins without assuming every feature is enabled
- Server-aware cache-plugin guidance without automatic installation
- Two-request public-home cache verification with HIT/MISS/BYPASS/STALE classification when supported headers are exposed
- Warm-cache HIT verification and a Next Best Action card
- Safe one-click purge for supported active integrations plus manual purge paths for others
- Separate confirmed persistent object-cache flush
- WooCommerce-aware cache guidance
- JSON and CSV metadata exports
- Site Health and diagnostics integration
- Multisite-aware plugin ownership/cache detection
- WP-CLI status/top/audit commands
- No external account, ads, telemetry, or frontend assets

## Safety

AutoloadFix never deletes option values and never automatically mutates unknown options. Option values are not displayed or exported. Administrator-triggered autoload changes require a successful snapshot first and are verified after WordPress applies the change.

The cache advisor does not install third-party cache plugins or edit their settings automatically. It uses supported purge APIs/hooks where available and otherwise shows a manual WordPress menu path. Persistent object-cache flushing is intentionally separate and requires explicit confirmation.

The public cache verifier makes two anonymous requests to the site's own home URL and stores only response metadata needed for cache diagnosis. It does not store page content.

Ownership and cache-fit recommendations are heuristic, so administrators should test important frontend, checkout, account, form, scheduled-task, and admin workflows after performance changes.

## License

GPL-2.0-or-later.
