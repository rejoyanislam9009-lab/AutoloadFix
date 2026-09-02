# Loadvexa WordPress.org Approved Launch

Plugin: **Loadvexa - Autoload & Cache Diagnostics**

Permanent slug: `loadvexa-autoload-diagnostics`

Approved release: `1.4.1`

SVN URL: https://plugins.svn.wordpress.org/loadvexa-autoload-diagnostics/

Public URL: https://wordpress.org/plugins/loadvexa-autoload-diagnostics/

## Initial SVN layout

WordPress.org uses three top-level release locations:

- `/trunk/` — current release source
- `/tags/1.4.1/` — immutable release tag
- `/assets/` — directory-only marketing assets, not shipped inside the plugin ZIP

The stable tag in both trunk and the 1.4.1 tag must remain `1.4.1`, matching the plugin header version.

## WordPress.org assets

Use these exact lowercase filenames in SVN `/assets/`:

- `icon.svg`
- `icon-128x128.png`
- `icon-256x256.png`
- `banner-772x250.png`
- `banner-1544x500.png`
- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`
- `screenshot-5.png`

Screenshot captions in `readme.txt` must match the five uploaded screenshots.

## Screenshot order

1. Autoload Health Overview
2. Monitor & Tools
3. Cache & Optimization Advisor
4. Site Problem Scanner
5. Optimization Profiles

## Credential safety

Never put the WordPress.org SVN password into this repository, a commit, an issue, or chat. Store it only in a local SVN credential store or a GitHub Actions secret. The SVN username is `wpzenora`.

## First release validation

Before committing to SVN:

1. Confirm PHP syntax passes for every PHP file.
2. Confirm JavaScript syntax passes for shipped JavaScript files.
3. Confirm main plugin header Version is `1.4.1`.
4. Confirm both readme stable tags are `1.4.1`.
5. Confirm no development-only files are in trunk/tag.
6. Confirm assets are in top-level `/assets/`, not inside `/trunk/assets/`.
7. Commit trunk, tag and assets as a finished release.
8. Check the public directory page after WordPress.org processes the SVN commit.

## After launch

Search/profile propagation can take time. Avoid empty releases or artificial review/install manipulation. Keep support responses prompt and release only tested, meaningful updates.
