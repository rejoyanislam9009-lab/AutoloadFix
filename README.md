# AutoloadFix

AutoloadFix is a WordPress performance utility for auditing autoloaded options, tracking growth, understanding cache layers, diagnosing page-specific problems, generating conservative optimization profiles for supported cache plugins, verifying fixes, and restoring autoload changes from snapshots.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Version 1.4.0 highlights

- Everything from the 1.3.0 Site Problem Scanner
- New Optimization Profiles dashboard
- Requires the current bounded Site Problem Scanner run to reach 100% before profile generation
- WP Rocket JSON import-profile adapter
- LiteSpeed Cache native `.data` import-profile adapter
- Profiles are issue-driven: only high-confidence scanner findings that map directly to a known setting can modify the generated file
- Dynamic commerce shared-cache HITs can generate exact-path cache exclusions
- Aggressive CSS/JS, delay, combine, image, object-cache, or crawler settings are not enabled merely because they exist
- Current/recommended/reason diff before download
- Exact third-party import path plus purge and re-check instructions
- Slow responses, 4xx/5xx, redirects, unknown cache status, and other non-deterministic findings remain manual instead of being falsely “fixed” by a settings file
- Dynamic WooCommerce pages with UNKNOWN/PRESENT cache status are now treated as unverified rather than automatically healthy
- No automatic third-party import, no external configuration service, and no profile upload to AutoloadFix

## Safety

AutoloadFix never deletes option values and never automatically mutates unknown options. Administrator-triggered autoload changes require a successful snapshot first and are verified after WordPress applies the change.

The cache advisor and site scanner do not install third-party cache plugins. Optimization Profiles are administrator-requested downloads only: AutoloadFix builds a profile for an explicitly supported native format, shows what would change, and leaves the actual import to the administrator. After import, Site Problem Scanner re-tests the affected pages instead of assuming the configuration change worked.

Site scanning is deliberately batched to reduce server load. Requests are anonymous and restricted to the same WordPress site. The scanner stores response metadata needed for diagnosis, not page content.

Cache and performance findings are diagnostic signals, not guarantees. Hosting-level caches, CDNs, reverse proxies, logged-in behavior, commerce sessions, security rules, and custom code can affect results.

## License

GPL-2.0-or-later.
