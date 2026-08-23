=== AutoloadFix ===
Contributors: rejoyanislam9009-lab
Tags: autoload, database, performance, site health, optimization
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit WordPress autoload data, diagnose cache layers and page-specific problems, get guided fixes, and verify the result.

== Description ==

AutoloadFix is a conservative WordPress performance utility for reviewing autoloaded options, tracking autoload growth, understanding cache layers, and diagnosing page-specific response/cache problems.

AutoloadFix does not delete option values, does not automatically change unknown options, does not install third-party cache plugins, and does not crawl external domains.

= Highlights =

* Autoload health score, total size, option count, and large-entry metrics.
* Largest 250 autoloaded option review workspace.
* Search, assessment filters, watch list, and ignore list.
* Likely plugin/theme ownership with confidence scoring.
* Protected, Review, Candidate, and Ignored assessments.
* Automatic protection for WordPress-critical role/capability options.
* Snapshot-before-change workflow with history and one-click restore.
* Read-only safe mode.
* Manual, daily, or weekly audit points and growth alerts.
* Cache & Optimization Advisor with server-aware guidance.
* Page-cache, persistent object-cache, cache-drop-in, asset-optimization capability, and cache/CDN signal detection.
* Two-request cache verification with HIT, MISS, BYPASS, and STALE classification when supported headers are exposed.
* Safe purge integration for supported active cache tools plus exact manual purge paths for others.
* Separate confirmed persistent object-cache flush.
* WooCommerce-aware cache guidance.
* Site Problem Scanner with page-by-page diagnostics and verification.
* Safe batch scanning of up to 300 public URLs per scan run.
* HTTP 4xx/5xx, redirect, response-time, cache-warming, cache-bypass, stale-cache, and restrictive Cache-Control checks.
* Dynamic WooCommerce page checks for Cart, Checkout, and My Account.
* Plugin-specific fix instructions for common cache plugins.
* Re-check one page or a safe batch of problem pages after making a change.
* Verified “Fixed after re-check” state.
* JSON and CSV autoload diagnostic exports without option values.
* WordPress Site Health integration and diagnostics.
* Multisite-aware active plugin detection.
* WP-CLI tools: `wp autoloadfix status`, `wp autoloadfix top`, and `wp autoloadfix audit`.
* No external account, ads, telemetry, or frontend assets.

= Site Problem Scanner =

The Site Problem Scanner turns cache and response diagnostics into a page-by-page workflow.

A scan can include the home page, configured front/posts pages, published public post types such as pages, posts and products, and key WooCommerce pages. AutoloadFix processes only a small batch at a time to reduce load and caps a scan at 300 public URLs.

For each scanned URL, AutoloadFix can report:

* HTTP response status.
* Server-side probe duration.
* Cache HIT, MISS, BYPASS, STALE, detected/unclassified, or unknown state where cache headers are available.
* Whether repeated anonymous requests warm a public page to a cache HIT.
* Unexpected cache bypass on ordinary public pages.
* Shared-cache HIT on dynamic WooCommerce pages.
* Restrictive `Cache-Control` on ordinary public pages.
* Page-specific fix steps and the exact cache-plugin menu path to review.

After following a fix, use “Re-check this page” or “Re-check problem pages”. AutoloadFix runs the same diagnostic again and can mark a previously detected issue as fixed when the problem is no longer present.

The scanner recognizes guidance paths for LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Breeze, Speed Optimizer, and WP-Optimize. If no recognized WordPress page-cache plugin is active, AutoloadFix points the administrator toward host/CDN cache controls instead of inventing a plugin-specific fix.

= Cache & Optimization Advisor =

The advisor separates page cache, persistent object cache, asset-optimization capability, cache drop-ins, and public cache/CDN response signals.

AutoloadFix can detect common cache/optimization plugins and show where their cache controls live. For integrations with a stable callable purge API, an administrator can purge from AutoloadFix and immediately run a two-request front-end verification. If no supported API is available, AutoloadFix shows the manual menu path instead of attempting an unsafe workaround.

AutoloadFix does not install cache plugins automatically. On LiteSpeed/OpenLiteSpeed servers, LiteSpeed Cache may be suggested as a natural server-integrated option. Existing stable cache setups are favored over unnecessary plugin switching.

= Safety model =

AutoloadFix never displays or exports option values. Autoload changes are administrator initiated, snapshot backed, applied through WordPress APIs where available, and verified afterward.

The page scanner is restricted to URLs on the same WordPress site. It uses anonymous requests and stores response metadata needed for diagnosis rather than page content. It does not follow links into external domains.

Cache recommendations are advisory. Hosting-level caches, CDNs, reverse proxies, logged-in behavior, custom rules, security products, and commerce sessions can change the best setup. Test important storefront, checkout, account, form, and admin workflows after performance changes.

== Installation ==

1. Upload the `autoloadfix` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New Plugin > Upload Plugin.
2. Activate AutoloadFix.
3. Open AutoloadFix in the WordPress admin menu.
4. Use Overview and Monitor & Tools for autoload diagnostics.
5. Use Optimization Advisor for cache-stack guidance.
6. Use Site Problem Scanner for page-by-page checks and guided verification.

== Frequently Asked Questions ==

= Does AutoloadFix delete database options? =

No. It does not delete option values.

= Does AutoloadFix automatically disable options? =

No. Unknown or third-party entries are never changed automatically.

= Does AutoloadFix install or force a cache plugin? =

No. Recommendations are advisory. AutoloadFix never automatically installs a third-party cache plugin and never requires a paid cache product.

= Which cache tools can AutoloadFix recognize? =

The optimization advisor recognizes several common WordPress cache/optimization plugins, including LiteSpeed Cache, WP Rocket, WP Super Cache, W3 Total Cache, WP Fastest Cache, Breeze, Speed Optimizer, WP-Optimize, and Autoptimize. Site Problem Scanner provides detailed cache-setting/exclusion paths for common page-cache tools.

= What does Site Problem Scanner change on my site? =

Nothing automatically. It sends anonymous same-site diagnostic requests, evaluates response/cache metadata, and shows fix instructions. You choose whether to change a cache-plugin or server setting.

= Can it scan every site regardless of size? =

A scan is deliberately capped at 300 public URLs and processed in small batches. This keeps the tool practical for typical sites without turning diagnostics into a high-load crawler. Very large sites should prioritize representative/key URLs or use dedicated performance monitoring tooling for exhaustive crawling.

= How does fix verification work? =

AutoloadFix stores the previous diagnostic result. After you change the cache/server configuration, re-check the page. If the previously actionable condition is no longer detected, AutoloadFix marks it as fixed after re-check.

= Does the scanner store my page content? =

No. It stores page identity plus response metadata required for the diagnostic result, not the page body.

= Why is object-cache flush separate? =

Persistent object cache differs from page cache. Flushing it can temporarily increase database/server work while objects warm again, so AutoloadFix keeps it behind a separate explicit confirmation.

= Does it use an external service? =

No external account or service is required. Optional page/cache probes request only URLs on the WordPress site itself.

= Does it work with multisite? =

AutoloadFix recognizes network-active plugins when estimating ownership and detecting cache integrations. Audits and settings are maintained per site.

== Screenshots ==

1. Autoload health overview and metrics.
2. Searchable option review workspace.
3. Audit and trend history.
4. Snapshot history and restore tools.
5. Diagnostics and monitoring settings.
6. Cache & Optimization Advisor.
7. Cache integrations, purge guidance, and warm-cache verification.
8. Site Problem Scanner summary and scan progress.
9. Page-by-page problem rows with plugin-specific fix steps.
10. Re-check workflow showing verified fixed results.

== Changelog ==

= 1.3.0 =
* Added Site Problem Scanner for page-by-page diagnostics.
* Added safe batch scanning with a 300-public-URL safety cap.
* Added scanning for home/front/posts pages, published public post types, and key WooCommerce pages.
* Added HTTP 4xx/5xx and redirect checks.
* Added page-level response-time checks.
* Added repeated-request cache warming checks and page-level HIT/MISS/BYPASS/STALE interpretation.
* Added unexpected public-page bypass and restrictive Cache-Control review.
* Added dynamic WooCommerce page protection checks for shared cache HITs.
* Added plugin-specific purge, cache-setting, and exclusion paths for common cache plugins.
* Added Re-check this page and Re-check problem pages workflows.
* Added verified Fixed after re-check state.
* Added same-site URL validation and no-content-storage scanning model.

= 1.2.1 =
* Added two-request warm-cache verification.
* Added HIT/MISS/BYPASS/STALE cache classification.
* Added Next Best Action guidance.
* Refined asset-optimization capability reporting and overlapping-cache wording.

= 1.2.0 =
* Added Cache & Optimization Advisor, cache-stack detection, site-fit recommendations, safe purge integrations, manual purge paths, object-cache flush, and WooCommerce-aware cache guidance.

= 1.1.0 =
* Added monitoring/audit trends, search/filtering, watch/ignore, read-only mode, custom protection, CSV export, diagnostics, Site Health Info, and WP-CLI tools.

= 1.0.0 =
* Initial release with health score, scanner, ownership heuristics, protected options, snapshots, restore history, Site Health integration, and JSON export.

== Upgrade Notice ==

= 1.3.0 =
Adds page-by-page website diagnostics, cache-plugin-specific fix instructions, and a re-check workflow that verifies whether detected problems were fixed.
