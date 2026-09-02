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

The stable tag in trunk/tag must remain `1.4.1`, matching the plugin header version.

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

Screenshot order:

1. Autoload Health Overview
2. Monitor & Tools
3. Cache & Optimization Advisor
4. Site Problem Scanner
5. Optimization Profiles

## GitHub Actions first deployment

The repository contains `.github/workflows/deploy-wordpress-org.yml`. It deliberately deploys the exact approved WordPress.org submission ZIP for version 1.4.1 and validates the version/stable tag before committing to SVN.

Before running it:

1. Upload the prepared file `wordpress-org-assets.zip` to the repository root on `main`.
2. In GitHub open **Settings > Secrets and variables > Actions**.
3. Create repository secret `SVN_USERNAME` with value `wpzenora`.
4. Create repository secret `SVN_PASSWORD` with the SVN-specific password generated in the WordPress.org Account & Security screen.
5. Open **Actions > Deploy Loadvexa to WordPress.org > Run workflow**.
6. The workflow will unpack the approved plugin, unpack the assets into the WordPress.org assets directory, validate required files, and deploy version 1.4.1 to the permanent slug.

## Credential safety

Never put the WordPress.org SVN password into a commit, file, issue, pull request, or chat. Store it only as a GitHub Actions secret or in a trusted local SVN credential store. The SVN username is `wpzenora`.

## First release validation

Before deployment:

1. Confirm main plugin Version is `1.4.1`.
2. Confirm readme Stable tag is `1.4.1`.
3. Confirm the asset bundle contains all required icon/banner/screenshot names.
4. Confirm assets belong in top-level SVN `/assets/`, not inside `/trunk/assets/`.
5. Deploy only after the GitHub secrets are configured.
6. Check the public directory page after WordPress.org processes the SVN commit.

## After launch

Search/profile propagation can take time. Avoid empty releases or artificial review/install manipulation. Keep support responses prompt and release only tested, meaningful updates.
