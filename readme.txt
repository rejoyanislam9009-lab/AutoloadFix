=== AutoloadFix ===
Contributors: rejoyanislam9009-lab
Tags: autoload, autoloaded options, database, performance, site health
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit WordPress autoloaded options, find large database entries, safely disable unnecessary autoloading, and restore changes from snapshots.

== Description ==

AutoloadFix helps administrators understand WordPress autoloaded options without turning database maintenance into guesswork.

The plugin scans the options table using the autoload values WordPress itself considers autoloaded. It highlights large entries, estimates likely plugin ownership, protects known WordPress-critical options, and lets administrators disable autoloading only after an explicit review.

Before AutoloadFix changes an autoload value, it stores a restore snapshot. The option value itself is not deleted.

Key features:

* Autoload health score and total autoload size.
* Largest autoloaded option report.
* Likely plugin ownership heuristics with confidence labels.
* Protected WordPress core/site-critical options.
* Large-option and inactive-plugin review candidates.
* Snapshot-before-change safety workflow.
* Restore history.
* WordPress Site Health integration.
* JSON diagnostic export.
* No external API, account, tracking, advertising, or frontend scripts.

Important: AutoloadFix deliberately does not claim that an unknown option is safe. An option may be required by a theme, plugin, integration, scheduled task, checkout flow, or custom code. Test important site workflows after any change.

== Installation ==

1. Upload the `autoloadfix` folder to `/wp-content/plugins/`, or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate AutoloadFix.
3. Open AutoloadFix in the WordPress admin menu.
4. Review the largest autoloaded options and their assessment.
5. If you choose to disable autoloading for an option, test the site afterward.
6. Use AutoloadFix > History & Restore if you need to restore the previous autoload behavior.

== Frequently Asked Questions ==

= Does AutoloadFix delete option values? =

No. The primary action changes only whether a selected option is autoloaded. The option value remains in the database.

= Does it automatically disable everything it marks as large? =

No. Size alone is not proof that disabling autoload is safe. AutoloadFix requires an administrator to review and explicitly apply a change.

= Can I restore a change? =

Yes. AutoloadFix stores the prior autoload behavior in a snapshot before a change and provides a restore action in History & Restore.

= Does it use an external service or collect data? =

No. Scanning and changes are performed locally on your WordPress site. AutoloadFix does not send telemetry.

= Why does it require WordPress 6.6 or later? =

WordPress 6.6 introduced public APIs and Site Health improvements for modern autoload behavior. AutoloadFix relies on those APIs rather than implementing its own incompatible autoload rules.

== Privacy ==

AutoloadFix runs locally in WordPress. It does not send site data, option names, diagnostics, or usage telemetry to an external service. Exported reports are generated only when an administrator explicitly requests them.

== Safety ==

AutoloadFix changes autoload behavior only after an administrator explicitly chooses an option. It does not delete the option value. Known WordPress-critical options are locked, and a restore snapshot is created before a successful change is attempted. Ownership detection is heuristic and should be treated as guidance, not proof.

== Screenshots ==

1. Autoload health score and metrics.
2. Largest autoloaded options with ownership and risk assessment.
3. Change history and restore snapshots.
4. Scanner thresholds and settings.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added autoload health dashboard.
* Added largest-option scanner.
* Added ownership heuristics and protected option rules.
* Added snapshot-before-change and restore history.
* Added Site Health integration and JSON export.
