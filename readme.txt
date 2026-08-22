=== AutoloadFix ===
Contributors: rejoyanislam9009-lab
Tags: autoload, database, performance, site health, optimization
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit WordPress autoloaded options, track growth, review cache layers, get optimization guidance, and restore autoload changes safely.

== Description ==

AutoloadFix is a focused WordPress performance utility for reviewing autoloaded options safely and understanding the site's cache/optimization stack.

Autoloaded options can be loaded on many WordPress requests. AutoloadFix helps administrators understand how much data is being autoloaded, which entries are largest, which plugin or theme may own an option, whether autoload growth is changing over time, and which cache layers are active.

AutoloadFix is conservative by design. It does not delete option values, does not automatically change unknown options, does not install third-party cache plugins, and protects WordPress-critical options from mutation.

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
* Cache & Optimization Advisor with server-aware recommendations.
* Detection of full-page cache, persistent object cache, cache drop-ins, and asset optimization layers.
* Warning when multiple recognized full-page cache plugins are active.
* Manual front-end cache probe that stores only status, timing, and cache-related response headers.
* Site-fit guidance that favors the current stable single-cache setup and does not force a plugin switch.
* One-click purge integrations for supported active cache tools.
* Exact WordPress menu paths for detected tools that require manual cache clearing.
* Separate, explicitly confirmed persistent object-cache flush.
* WooCommerce-aware cache guidance for Cart, Checkout, My Account, sessions, and personalized content.
* Lightweight CSS trend visualization with no external chart library.
* JSON and CSV diagnostic exports without option values.
* WordPress Site Health test and Site Health Info fields.
* Safe diagnostics for WordPress, PHP, database, object cache, and raw autoload-state distribution.
* Multisite-aware active plugin detection.
* WP-CLI tools: `wp autoloadfix status`, `wp autoloadfix top`, and `wp autoloadfix audit`.
* No external account, ads, telemetry, or frontend assets.

= Cache & Optimization Advisor =

The advisor distinguishes several cache layers instead of treating all caching as one switch:

* Full-page cache plugins.
* Persistent WordPress object cache.
* CSS/JS asset optimization.
* Front-end/CDN cache signals from public response headers.
* `advanced-cache.php` and `object-cache.php` drop-ins.

AutoloadFix can detect common cache/optimization plugins and show where their cache controls live. For integrations with a stable callable purge API, the administrator can purge from AutoloadFix and immediately run a new front-end probe. If no supported API is available, AutoloadFix shows the manual menu path instead of attempting an unsafe workaround.

AutoloadFix does not install cache plugins automatically. When no recognized full-page cache is active it provides neutral site-fit guidance. On LiteSpeed/OpenLiteSpeed servers, LiteSpeed Cache may be suggested as a natural server-integrated option. On other servers, AutoloadFix may mention simple or advanced WordPress.org cache choices while also reminding administrators that their host may already provide server-level caching.

If WP Rocket is already installed, AutoloadFix can detect it and use its available purge function. AutoloadFix does not require, bundle, sell, or install WP Rocket.

= Safety model =

AutoloadFix never displays or exports option values. It only exposes metadata such as option name, byte size, autoload state, likely owner, confidence, and classification.

When an administrator disables autoload for an option, AutoloadFix first stores the option's previous raw autoload state in a snapshot. It then uses WordPress's autoload API and verifies the resulting database state. If the change cannot be verified, the temporary snapshot is removed and the action is reported as failed.

Ownership detection is heuristic. An option can still be required by custom code, a theme, an integration, a scheduled task, or an active workflow. Test important site paths after every change.

Cache recommendations are also advisory. Hosting-level caches, CDNs, reverse proxies, logged-in behavior, custom application rules, and commerce sessions can change what the best setup is. AutoloadFix therefore avoids automatic plugin installation or destructive cache-file manipulation.

== Installation ==

1. Upload the `autoloadfix` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New Plugin > Upload Plugin.
2. Activate AutoloadFix.
3. Open AutoloadFix in the WordPress admin menu.
4. Open AutoloadFix > Monitor & Tools and run a manual audit to establish a trend baseline.
5. Open AutoloadFix > Optimization Advisor to inspect cache layers and run a front-end check.
6. Review entries before making any autoload change.

== Frequently Asked Questions ==

= Does AutoloadFix delete database options? =

No. AutoloadFix changes only autoload behavior for administrator-selected options. It does not delete the option value.

= Does AutoloadFix automatically disable options? =

No. Unknown or third-party entries are never changed automatically.

= Does AutoloadFix install or force a cache plugin? =

No. Recommendations are advisory. AutoloadFix never installs a third-party cache plugin automatically and never requires a paid cache product.

= Which cache tools can AutoloadFix recognize? =

The advisor recognizes several common WordPress cache/optimization plugins, including LiteSpeed Cache, WP Rocket, WP Super Cache, W3 Total Cache, WP Fastest Cache, Breeze, Speed Optimizer, WP-Optimize, and Autoptimize. Support differs by plugin: some expose a safe purge integration, while others are shown with their manual purge path.

= What does “Purge supported caches & re-check” do? =

It calls only supported purge APIs/hooks for active recognized integrations, stores which integrations were triggered, and then runs a new anonymous front-end probe. It does not delete plugin folders or edit third-party plugin settings.

= Why is object-cache flush a separate button? =

Persistent object cache is different from page cache. Flushing it can temporarily increase database/server work while objects warm again, so AutoloadFix requires a separate explicit confirmation.

= Does the front-end probe store my page content? =

No. It requests the public home URL and stores only the check time, HTTP status, response duration, and selected cache-related response headers. The page body is not stored.

= What does Read-only safe mode do? =

It prevents AutoloadFix from disabling or restoring autoload states while keeping scanning, diagnostics, watch/ignore lists, exports, and audit history available.

= Are option values included in reports? =

No. JSON and CSV exports contain metadata only.

= Does it use an external service? =

No external service or account is required. The optional cache probe requests only the site's own public home URL.

= Does it work with multisite? =

AutoloadFix recognizes network-active plugins when estimating ownership and detecting cache integrations. Audits and settings are maintained per site.

== Screenshots ==

1. Autoload health overview and metrics.
2. Searchable option review workspace.
3. Audit and trend history.
4. Snapshot history and restore tools.
5. Diagnostics and autoload-state breakdown.
6. Monitoring and safety settings.
7. Cache & Optimization Advisor with cache layers and site-fit recommendation.
8. Detected cache integrations, purge guidance, and front-end response-header check.

== Changelog ==

= 1.2.0 =
* Added Cache & Optimization Advisor.
* Added server/cache-stack detection for page cache, object cache, cache drop-ins, and asset optimization.
* Added site-fit cache recommendations without automatic plugin installation.
* Added warning for overlapping recognized full-page cache plugins.
* Added anonymous public-home cache probe with status, timing, and selected cache-related headers only.
* Added one-click purge support for active integrations that expose stable callable purge APIs/hooks.
* Added manual purge paths for recognized cache tools without a supported one-click integration.
* Added separate confirmed persistent object-cache flush.
* Added WooCommerce-aware cache guidance for dynamic commerce paths and sessions.
* Added purge-and-recheck workflow so administrators can verify changes from the same screen.

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

= 1.2.0 =
Adds cache-stack detection, site-fit guidance, safe purge integrations, manual purge paths, WooCommerce-aware recommendations, and a purge-and-recheck workflow.
