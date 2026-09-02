# Loadvexa WordPress.org Approved Launch

Plugin: **Loadvexa - Autoload & Cache Diagnostics**

Permanent slug: `loadvexa-autoload-diagnostics`

Approved release: `1.4.1`

SVN URL: https://plugins.svn.wordpress.org/loadvexa-autoload-diagnostics/

Public URL: https://wordpress.org/plugins/loadvexa-autoload-diagnostics/

## Launch status

Initial WordPress.org deployment completed successfully on 2026-09-02 through GitHub Actions.

WordPress.org SVN commit: **r3677160**

The deploy job completed with `Plugin deployed!` and generated the distribution ZIP successfully.

## SVN layout deployed

- `/trunk/` — current 1.4.1 release source
- `/tags/1.4.1/` — release tag
- `/assets/` — WordPress.org directory assets

The deployed trunk and tag use Stable tag / Version `1.4.1`.

## WordPress.org assets deployed

The launch workflow generated and committed these directory assets:

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

The readme screenshot captions are normalized during deployment to match these five images.

## GitHub Actions deployment

`.github/workflows/deploy-wordpress-org.yml` downloads the exact approved WordPress.org 1.4.1 submission, generates the directory artwork using `.github/scripts/build-wporg-assets.sh`, validates release metadata/assets, and deploys to the permanent WordPress.org SVN slug.

SVN username is fixed to `wpzenora`. The SVN-specific password is read only from the GitHub Actions secret `SVN_PASSWORD`; the secret value is never committed to the repository or printed in logs.

The workflow can be run manually with `workflow_dispatch`. The `.wordpress-org-deploy-trigger` file is also available for an intentional push-triggered deployment.

## Credential safety

Never put the WordPress.org SVN password into a commit, file, issue, pull request, or chat. Keep it only in GitHub Actions secrets or a trusted local SVN credential store.

## After launch

WordPress.org may take time to process the SVN commit and propagate the public page, search results, assets, and profile association. Future releases should use a new version/tag and be tested before SVN deployment. Avoid empty releases, artificial review/install manipulation, and unverified compatibility claims.
