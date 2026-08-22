# AutoloadFix

AutoloadFix is a WordPress performance utility for auditing autoloaded options, tracking growth, reviewing likely ownership, cautiously changing autoload behavior, and restoring changes from snapshots.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Version 1.1.0 highlights

- Searchable/filterable largest-option review workspace
- Ownership confidence and per-option impact percentages
- Watch and ignore lists
- Read-only safe mode
- Custom protected options and automatic protection for prefixed WordPress user-role options
- Manual/daily/weekly audit history with growth alerts
- Snapshot-before-change and restore history
- JSON and CSV metadata exports
- Site Health and diagnostics integration
- Multisite-aware plugin ownership detection
- WP-CLI status/top/audit commands
- No external service, account, telemetry, or frontend assets

## Safety

AutoloadFix never deletes option values and never automatically mutates unknown options. Option values are not displayed or exported. Administrator-triggered autoload changes require a successful snapshot first and are verified after WordPress applies the change.

Ownership detection is heuristic, so administrators should test important frontend and admin workflows after any autoload change.

## License

GPL-2.0-or-later.
