# AutoloadFix

AutoloadFix is a WordPress performance utility for auditing autoloaded options, tracking growth, understanding cache layers, diagnosing page-specific problems, verifying fixes, and restoring autoload changes from snapshots.

## Requirements

- WordPress 6.6+
- PHP 7.2+

## Version 1.3.0 highlights

- Everything from the 1.2.1 Cache & Optimization Advisor
- New Site Problem Scanner for page-by-page diagnostics
- Safe batch scanning of up to 300 public WordPress URLs per run
- Home, front page, posts page, published public post types, and key WooCommerce pages
- HTTP 4xx/5xx and redirect detection
- Server-side response-time checks
- Cache HIT/MISS/BYPASS/STALE classification when supported headers are exposed
- Repeated anonymous requests to verify whether public pages warm to cache HIT
- WooCommerce dynamic-page safety checks so Cart, Checkout, and My Account are not treated like ordinary cacheable pages
- Restrictive Cache-Control review for public static pages
- Plugin-specific fix steps for LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, Breeze, Speed Optimizer, and WP-Optimize
- Exact purge, cache-setting, and exclusion menu paths
- Re-check one page after a fix
- Re-check problem pages in safe batches
- “Fixed after re-check” verification state
- No crawling of external domains and no page-content storage
- Existing autoload scanner, snapshots, monitoring, Site Health, exports, and WP-CLI tools remain available

## Safety

AutoloadFix never deletes option values and never automatically mutates unknown options. Option values are not displayed or exported. Administrator-triggered autoload changes require a successful snapshot first and are verified after WordPress applies the change.

The cache advisor and site scanner do not install third-party cache plugins or edit their settings automatically. They show the relevant cache-plugin menu paths, use supported purge APIs only where available, and let the administrator verify the result afterward.

Site scanning is deliberately batched to reduce server load. Requests are anonymous and restricted to the same WordPress site. The scanner stores response metadata needed for diagnosis, not page content.

Cache and performance findings are diagnostic signals, not guarantees. Hosting-level caches, CDNs, reverse proxies, logged-in behavior, commerce sessions, security rules, and custom code can affect results.

## License

GPL-2.0-or-later.
