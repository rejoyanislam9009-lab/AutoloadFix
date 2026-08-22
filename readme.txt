=== AutoloadFix ===
Contributors: rejoyanislam9009-lab
Tags: autoload, database, performance, site health, optimization
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit WordPress autoloaded options, track growth, understand likely ownership, make cautious changes, and restore from snapshots.

== Description ==

AutoloadFix is a focused WordPress performance utility for reviewing autoloaded options safely.

Autoloaded options can be loaded on many WordPress requests. AutoloadFix helps administrators understand how much data is being autoloaded, which entries are largest, which plugin or theme may own an option, and whether autoload growth is changing over time.

AutoloadFix is conservative by design. It does not delete option values, does not automatically change unknown options, and protects WordPress-critical options from mutation.

= Highlights =

* Autoload health score, total size, option count, and large-entry metrics.
* Largest 250 autoloaded option review workspace.
* Search and assessment filters plus a watched-only view.
* Likely plugin/theme ownership with confidence scoring.
* Conservative assessment levels: Protected, Review, Candidate, and Ignored.
* Per-option impact estimate as a percentage of total autoload data.
* Watch list for options you want to monitor.
* Ignore list for entries you do not want recommended.
* Custom protected option names.
* WordPress-critical role/capability options are automatically protected, including prefixed user-roles options.
* Snapshot-before-change workflow.
* History and one-click restore.
* Read-only safe mode that blocks AutoloadFix autoload mutations.
* Manual, daily, or weekly audit points.
* Autoload growth alerts in the dashboard.
* Lightweight CSS trend visualization with no external chart library.
* JSON and CSV diagnostic exports without option values.
* WordPress Site Health test and Site Health Info fields.
* Safe diagnostics for WordPress, PHP, database, object cache, and raw autoload-state distribution.
* Multisite-aware active plugin detection.
* WP-CLI tools: `wp autoloadfix status`, `wp autoloadfix top`, and `wp autoloadfix audit`.
* No external API, account, ads, telemetry, or frontend assets.

= Safety model =

AutoloadFix never displays or exports option values. It only exposes metadata such as option name, byte size, autoload state, likely owner, confidence, and classification.

When an administrator disables autoload for an option, AutoloadFix first stores the option's previous raw autoload state in a snapshot. It then uses WordPress's autoload API and verifies the resulting database state. If the change cannot be verified, the temporary snapshot is removed and the action is reported as failed.

Ownership detection is heuristic. An option can still be required by custom code, a theme, an integration, a scheduled task, or an active workflow. Test important site paths after every change.

== Installation ==

1. Upload the `autoloadfix` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New Plugin > Upload Plugin.
2. Activate AutoloadFix.
3. Open AutoloadFix in the WordPress admin menu.
4. Open AutoloadFix > Monitor & Tools and run a manual audit to establish a trend baseline.
5. Review entries before making any autoload change.

== Frequently Asked Questions ==

= Does AutoloadFix delete database options? =

No. AutoloadFix changes only autoload behavior for administrator-selected options. It does not delete the option value.

= Does AutoloadFix automatically disable options? =

No. Unknown or third-party entries are never changed automatically.

= What does Read-only safe mode do? =

It prevents AutoloadFix from disabling or restoring autoload states while keeping scanning, diagnostics, watch/ignore lists, exports, and audit history available.

= Are option values included in reports? =

No. JSON and CSV exports contain metadata only.

= Does it use an external service? =

No. AutoloadFix runs locally in WordPress and does not require an account or API key.

= Does it work with multisite? =

AutoloadFix recognizes network-active plugins when estimating ownership. Audits and settings are maintained per site.

== Screenshots ==

1. Autoload health overview and metrics.
2. Searchable option review workspace.
3. Audit and trend history.
4. Snapshot history and restore tools.
5. Diagnostics and autoload-state breakdown.
6. Monitoring and safety settings.

== Changelog ==

= 1.1.0 =
* Added Monitor & Tools dashboard with audit trends.
* Added manual, daily, and weekly audit points.
* Added scheduled autoload growth alerts.
* Added search, assessment filters, and watched-only review mode.
* Added watch and ignore lists.
* Added owner confidence and per-option impact metrics.
* Added read-only safe mode.
* Added custom protected option names.
* Automatically protected prefixed WordPress user-role options.
* Added CSV export alongside JSON export.
* Added Diagnostics section and Site Health Info fields.
* Added WP-CLI status, top, and audit commands.
* Added safer upgrade, cron, and uninstall handling.
* Expanded responsive monitoring UI.

= 1.0.0 =
* Initial release with health score, scanner, ownership heuristics, protected options, snapshots, restore history, Site Health integration, and JSON export.

== Upgrade Notice ==

= 1.1.0 =
Adds professional monitoring, filtering, safety, diagnostics, and WP-CLI tools while preserving the conservative snapshot-first model.
