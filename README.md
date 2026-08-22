# AutoloadFix

AutoloadFix is a WordPress performance utility for auditing autoloaded options, reviewing oversized entries, changing autoload behavior cautiously, and restoring changes from snapshots.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Core features

- Autoload health score and total autoload size
- Largest autoloaded option scanner
- Conservative plugin/theme ownership heuristics
- Protected WordPress-critical options
- Review classification for large and inactive-plugin entries
- Snapshot-before-change workflow
- Restore history with before/after totals
- WordPress Site Health integration
- JSON diagnostic export
- No external API, account, telemetry, ads, or frontend assets

## Safety model

AutoloadFix does not delete option values and does not automatically change unknown options. Known WordPress-critical options are locked. Every administrator-triggered autoload change is preceded by a snapshot, and failed changes discard their temporary snapshot so history reflects completed operations.

Ownership detection is heuristic. An option can still be required by custom code, a theme, an integration, a scheduled task, or an active workflow, so important site paths should be tested after changes.

## Development source

This repository contains the human-readable source shipped in the plugin. There is no generated/minified-only application source and no external build step is required for version 1.0.0.

## License

GPL-2.0-or-later.
