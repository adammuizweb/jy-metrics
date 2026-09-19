# Jy Metrics

Jy Metrics adds analytics, search performance, realtime activity, location,
content, and page experience reports to the Jyavani CMS dashboard.

Reports are loaded only when their dashboard sections become visible. This
keeps the initial page responsive and avoids requesting every external report
at once.

## Features

- Summary metrics for visitors, impressions, clicks, and average position
- Realtime activity with country and city breakdowns
- Daily traffic and search-performance trends
- Channel, country, province, and city reports
- Top search queries and top-performing pages
- Mobile and desktop page experience reports
- Lazy-loaded dashboard sections with stale-request protection
- Cached API responses to reduce external requests
- Optional public analytics, tag management, and advertising snippets

## Requirements

- Jyavani CMS 2.3.74 or newer
- PHP 8.1 or newer
- PHP extensions: PDO, JSON, and cURL
- An OAuth web application from a supported analytics provider
- An optional API key for page experience reports

## Installation

1. Download the plugin from the Jyavani Plugin Store.
2. Open **Plugins** in the Jyavani dashboard.
3. Install and activate Jy Metrics.
4. Open **Tools > Jy Metrics**.
5. Complete the setup wizard.

The plugin can also be installed from a flat ZIP archive. `plugin.json` must be
located at the archive root.

## Configuration

Open **Jy Metrics > Settings** and enter the OAuth client credentials created
for your site. Register the callback URL displayed by the settings page in the
provider's developer console.

After connecting an account, use the setup wizard to select the site and
analytics property used by the dashboard. Optional integration identifiers can
be entered manually when automatic discovery is unavailable.

Page experience reports require a separate API key. Restrict that key to the
required service and to the server or site that uses it.

## Permissions

Jy Metrics declares two CMS permissions:

- `plugin.jy-metrics.reports.read` allows viewing reports.
- `plugin.jy-metrics.integrations.manage` allows managing credentials,
  connection state, and integrations.

Integration management is intentionally nondelegable because it provides
access to sensitive credentials and OAuth operations.

## Privacy And Security

- OAuth tokens and integration settings are stored by the Jyavani installation.
- Report endpoints require an authenticated dashboard session, permission
  checks, and a valid CSRF token.
- Credentials and tokens are never included in the plugin package.
- Public tracking snippets are optional and controlled by site administrators.
- External providers may process visitor or account data under their own terms.
  Site operators are responsible for consent, disclosure, retention, and other
  compliance requirements that apply to their deployment.

The **Privacy Consent** setting can leave consent handling disabled or show
global category choices to every visitor. Necessary site functions remain
active, while Analytics and Advertising start disabled. Google Analytics loads
only with Analytics consent. Tag Manager and AdSense load only with Advertising
consent because a Tag Manager container may execute marketing tags.

The first layer offers **Accept all**, **Manage choices**, and a close button.
Closing rejects all optional services. Visitors can later reopen their choices,
accept or reject each category, and withdraw consent. Choices are stored locally
for 180 days. Existing schema-1 Accept/Reject decisions are migrated to the new
category model without granting additional access.

When **Privacy Policy URL** points to a page on the same site, the floating
reopen button disappears from regular pages after a decision and remains
available on that policy page. An empty or external policy URL retains the
floating button everywhere as a safe fallback so visitors do not lose access to
withdrawal controls. Configured query strings and fragments participate in page
matching when present.

When the browser exposes Global Privacy Control, Advertising remains disabled
even if the visitor selects **Accept all**. Jy Metrics maps category choices to
Google Consent Mode v2 and reloads the page after withdrawing consent from a
service whose script was already loaded.

Jy Metrics controls only snippets it injects. Site owners must separately audit
scripts added by themes or other plugins and ensure Tag Manager tags honor the
consent signals. This feature assists consent handling but is not legal advice
or a substitute for a site-specific privacy review.

## Development

The plugin uses native PHP and browser JavaScript without a build step.

Run PHP syntax checks:

```bash
for file in plugin.php admin/*.php api/*.php; do php -l "$file" || exit 1; done
```

Validate the manifest:

```bash
php -r 'json_decode(file_get_contents("plugin.json"), true, 512, JSON_THROW_ON_ERROR);'
```

## Independence

Jy Metrics is an independent Jyavani CMS plugin. It is not endorsed by,
sponsored by, or affiliated with external analytics or platform providers.

## License

Jy Metrics is released under the MIT License. See [LICENSE](LICENSE).
