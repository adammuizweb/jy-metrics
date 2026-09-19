<?php
// /plugins/jy-metrics/admin/settings.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    echo '<p>Database not available.</p>';
    return;
}
adiwira_require_permission($pdo, 'plugin.jy-metrics.integrations.manage', false);

$csrf = function_exists('csrf_token') ? csrf_token() : '';
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('csrf_check') || !csrf_check($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $messageType = 'error';
    } else {
        $consentModeInput = trim((string)($_POST['consent_mode'] ?? 'off'));
        $privacyUrlInput = trim((string)($_POST['privacy_url'] ?? ''));
        if (!in_array($consentModeInput, ['off', 'choices'], true)
            || !gsk_valid_privacy_url($privacyUrlInput)) {
            $message = 'Invalid privacy consent settings.';
            $messageType = 'error';
        } else {
            gsk_save_setting($pdo, GSK_CLIENT_ID_KEY, trim((string)($_POST['client_id'] ?? '')));
            gsk_save_setting($pdo, GSK_CLIENT_SECRET_KEY, trim((string)($_POST['client_secret'] ?? '')));
            gsk_save_setting($pdo, GSK_GA4_PROPERTY_ID_KEY, trim((string)($_POST['ga4_property_id'] ?? '')));
            gsk_save_setting($pdo, GSK_MEASUREMENT_ID_KEY, trim((string)($_POST['measurement_id'] ?? '')));
            gsk_save_setting($pdo, GSK_GTM_ID_KEY, trim((string)($_POST['gtm_id'] ?? '')));
            gsk_save_setting($pdo, GSK_ADSENSE_CLIENT_KEY, trim((string)($_POST['adsense_client'] ?? '')));
            gsk_save_setting($pdo, GSK_SITE_VERIFICATION_KEY, trim((string)($_POST['site_verification'] ?? '')));
            gsk_save_setting($pdo, GSK_PAGESPEED_API_KEY, trim((string)($_POST['pagespeed_api_key'] ?? '')));
            gsk_save_setting($pdo, GSK_CONSENT_MODE_KEY, $consentModeInput);
            gsk_save_setting($pdo, GSK_PRIVACY_URL_KEY, $privacyUrlInput);
            $message = 'Settings saved.';
            $messageType = 'success';
        }
    }
}

$settings = gsk_all_settings($pdo);
$connected = gsk_is_connected($pdo);
$redirectUri = gsk_oauth_redirect_uri();
$basePage = gsk_page_url('');
$wizardPage = gsk_page_url('wizard');

$email = $settings[GSK_USER_EMAIL_KEY] ?? '';
$siteUrl = $settings[GSK_SITE_URL_KEY] ?? '';
$measurementId = $settings[GSK_MEASUREMENT_ID_KEY] ?? '';
$propertyId = $settings[GSK_GA4_PROPERTY_ID_KEY] ?? '';
$gtmId = $settings[GSK_GTM_ID_KEY] ?? '';
$adsense = $settings[GSK_ADSENSE_CLIENT_KEY] ?? '';
$verification = $settings[GSK_SITE_VERIFICATION_KEY] ?? '';
$consentMode = gsk_consent_mode($pdo);
$privacyUrl = $settings[GSK_PRIVACY_URL_KEY] ?? '';
$consentLabels = ['off' => 'Disabled', 'choices' => 'Global privacy choices'];
$hasClientId = ($settings[GSK_CLIENT_ID_KEY] ?? '') !== '';
$hasClientSecret = ($settings[GSK_CLIENT_SECRET_KEY] ?? '') !== '';
$hasOAuthCredentials = $hasClientId && $hasClientSecret;
$hasPageSpeedKey = ($settings[GSK_PAGESPEED_API_KEY] ?? '') !== '';
$googleProject = preg_match('/^(\d+)-/', (string)($settings[GSK_CLIENT_ID_KEY] ?? ''), $projectMatch) ? $projectMatch[1] : '';
$pageSpeedLibraryUrl = 'https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com' . ($googleProject !== '' ? '?project=' . rawurlencode($googleProject) : '');
$pageSpeedCredentialsUrl = 'https://console.cloud.google.com/apis/credentials' . ($googleProject !== '' ? '?project=' . rawurlencode($googleProject) : '');
$manualOverrideCount = count(array_filter([$measurementId, $gtmId, $adsense, $verification], static fn(string $value): bool => $value !== ''));
$connectedServiceCount = count(array_filter([$connected && $siteUrl !== '', $connected && $propertyId !== '']));

$scLink = $siteUrl !== '' ? 'https://search.google.com/search-console?resource_id=' . urlencode($siteUrl) : '';
$gaLink = 'https://analytics.google.com/analytics/web/';

function gskServiceRow(string $label, bool $active, string $value, string $link = '', string $linkText = 'See full details'): string {
    $dot = $active ? 'gsk-dot--on' : 'gsk-dot--off';
    $valueHtml = $active && $value !== '' ? gsk_e($value) : '<span class="gsk-meta">Not configured</span>';
    $linkHtml = $active && $link !== '' ? '<a href="' . gsk_e($link) . '" target="_blank" rel="noopener" class="gsk-service-link">' . gsk_e($linkText) . '</a>' : '';
    return '<div class="gsk-service-row"><div class="gsk-service-info"><span class="gsk-dot ' . $dot . '"></span><div><strong class="gsk-service-label">' . gsk_e($label) . '</strong><div>' . $valueHtml . '</div></div></div>' . $linkHtml . '</div>';
}
?>

<div class="gsk-admin">
  <div class="gsk-admin__head">
    <div>
      <h1 class="gsk-admin__title">Jy Metrics Settings</h1>
      <p class="gsk-admin__subtitle">Connect this Jyavani site to your own Google Cloud project.</p>
    </div>
    <a href="<?= gsk_e($basePage) ?>" class="adam-cancle">← Back to Dashboard</a>
  </div>

  <?php if ($message !== ''): ?>
    <div class="gsk-alert gsk-alert--<?= gsk_e($messageType) ?>"><?= gsk_e($message) ?></div>
  <?php endif; ?>

  <?php if ($connected && $email !== ''): ?>
    <div class="gsk-card gsk-card--info">
      <div class="gsk-card__body gsk-account">
        <div class="gsk-account__info">
          <span class="gsk-dot gsk-dot--on"></span>
          <span>Connected as <strong><?= gsk_e($email) ?></strong></span>
        </div>
        <button type="button" class="adam-cancle" onclick="gskDisconnect()">Disconnect</button>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$connected): ?>
  <div class="gsk-card gsk-setup gsk-card--collapsible" data-gsk-card>
    <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="true"><span><span class="gsk-card__title">Google setup checklist <span class="gsk-help" title="A guided client-side checklist. It keeps the setup order clear, but it does not change your Google Cloud account.">?</span></span></span><span class="gsk-card__summary">5 steps · <?= $hasOAuthCredentials ? 'credentials ready' : 'start here' ?></span></button>
    <div class="gsk-card__body" data-gsk-card-body>
      <p class="gsk-meta">Each Jyavani site should use credentials from its own Google Cloud project. Jy Metrics never requires access to another site owner's project.</p>
      <div class="gsk-flow" data-gsk-flow data-oauth-ready="<?= $hasOAuthCredentials ? '1' : '0' ?>">
        <article class="gsk-flow-step is-open" data-step="1">
          <button type="button" class="gsk-flow-step__toggle"><span class="gsk-flow-step__number">1</span><span><strong>Create a Google Cloud project</strong><small>One project for this Jyavani site</small></span><span class="gsk-flow-step__chevron">⌄</span></button>
          <div class="gsk-flow-step__body"><ol><li>Open the project creator and sign in with the Google account that owns this site.</li><li>Create a new project, for example <code>My Site Jy Metrics</code>.</li><li>Keep that project selected for the next steps.</li></ol><a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Create Google Cloud project</a><button type="button" class="gsk-flow-step__complete">I have created a project</button></div>
        </article>
        <article class="gsk-flow-step is-locked" data-step="2">
          <button type="button" class="gsk-flow-step__toggle" aria-disabled="true"><span class="gsk-flow-step__number">2</span><span><strong>Enable required APIs</strong><small>Search Console and GA4 data access</small></span><span class="gsk-flow-step__chevron">⌄</span></button>
          <div class="gsk-flow-step__body"><ol><li>Open API Library inside the project created in step 1.</li><li>Enable <strong>Google Search Console API</strong>.</li><li>Enable <strong>Google Analytics Admin API</strong> and <strong>Google Analytics Data API</strong>.</li></ol><a href="https://console.cloud.google.com/apis/library" target="_blank" rel="noopener">Open API Library</a><button type="button" class="gsk-flow-step__complete">Required APIs are enabled</button></div>
        </article>
        <article class="gsk-flow-step is-locked" data-step="3">
          <button type="button" class="gsk-flow-step__toggle" aria-disabled="true"><span class="gsk-flow-step__number">3</span><span><strong>Configure Google Auth Platform</strong><small>Branding, audience, and read-only scopes</small></span><span class="gsk-flow-step__chevron">⌄</span></button>
          <div class="gsk-flow-step__body"><ol><li>Create an <strong>External</strong> app under Google Auth Platform.</li><li>Add <code>webmasters.readonly</code> and <code>analytics.readonly</code> under Data Access.</li><li>Choose <strong>Web application</strong> when creating an OAuth client.</li></ol><a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener">Open Google Auth Platform</a><button type="button" class="gsk-flow-step__complete">OAuth client is ready to create</button></div>
        </article>
        <article class="gsk-flow-step is-locked" data-step="4">
          <button type="button" class="gsk-flow-step__toggle" aria-disabled="true"><span class="gsk-flow-step__number">4</span><span><strong>Save OAuth credentials</strong><small>Register the callback URI, then paste Client ID and Secret</small></span><span class="gsk-flow-step__chevron">⌄</span></button>
          <div class="gsk-flow-step__body"><p>Use the OAuth credentials form directly below. Copy its Redirect URI into Google Cloud's <strong>Authorized redirect URIs</strong>, then save the generated Client ID and Client Secret.</p><button type="button" class="gsk-flow-step__complete" data-gsk-scroll-credentials>Go to OAuth credentials</button></div>
        </article>
        <article class="gsk-flow-step is-locked" data-step="5">
          <button type="button" class="gsk-flow-step__toggle" aria-disabled="true"><span class="gsk-flow-step__number">5</span><span><strong>Connect and choose properties</strong><small>Authorize Google, then select Search Console and GA4</small></span><span class="gsk-flow-step__chevron">⌄</span></button>
          <div class="gsk-flow-step__body"><p>After credentials are saved, run the wizard to authorize your Google account and select the site and GA4 property to report on.</p><a href="<?= gsk_e($wizardPage) ?>" class="adam-button gsk-wizard-cta">Run Setup Wizard</a></div>
        </article>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= gsk_e(gsk_page_url('settings')) ?>" class="gsk-form">
    <input type="hidden" name="csrf_token" value="<?= gsk_e($csrf) ?>">

    <div class="gsk-card gsk-card--collapsible" id="gsk-oauth-credentials" data-gsk-card>
        <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="<?= $hasOAuthCredentials ? 'false' : 'true' ?>"><span class="gsk-card__title">OAuth credentials <span class="gsk-help" title="Private credentials from the Google Cloud project created for this site.">?</span></span><span class="gsk-card__summary"><?= $hasClientId ? 'Client ID ready' : 'Client ID missing' ?> · <?= $hasClientSecret ? 'secret ready' : 'secret missing' ?></span></button>
        <div class="gsk-card__body" data-gsk-card-body <?= $hasOAuthCredentials ? 'hidden' : '' ?>>
        <p class="gsk-meta">Create a <strong>Web application</strong> client in <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noopener">Google Auth Platform</a>. This server-side flow requires the Redirect URI below. <strong>Authorized JavaScript origins are not required</strong> unless you add your own browser-side Google API integration.</p>
        <div class="gsk-field-row">
          <div class="gsk-field">
            <label class="gsk-field__label" for="gsk-client-id">Client ID</label>
            <input type="text" id="gsk-client-id" name="client_id" class="inpud" value="<?= gsk_e($settings[GSK_CLIENT_ID_KEY]) ?>" placeholder="123456789012-abc123.apps.googleusercontent.com">
          </div>
          <div class="gsk-field">
            <label class="gsk-field__label" for="gsk-client-secret">Client Secret</label>
            <input type="password" id="gsk-client-secret" name="client_secret" class="inpud" value="<?= gsk_e($settings[GSK_CLIENT_SECRET_KEY]) ?>" placeholder="GOCSPX-...">
          </div>
        </div>

        <div class="gsk-field">
          <label class="gsk-field__label" for="gsk-redirect-uri">Redirect URI (copy into Google Cloud Console)</label>
          <div class="gsk-copy-row">
            <input type="text" id="gsk-redirect-uri" class="inpud" value="<?= gsk_e($redirectUri) ?>" readonly onclick="this.select()">
            <button type="button" class="adam-button adam-button--secondary" onclick="gskCopySetting('gsk-redirect-uri')">Copy</button>
          </div>
        </div>
      </div>
    </div>

    <div class="gsk-card gsk-card--collapsible" data-gsk-card>
      <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="<?= $consentMode === 'off' ? 'false' : 'true' ?>"><span class="gsk-card__title">Privacy Consent <span class="gsk-help" title="Controls whether optional Google scripts wait for visitor consent.">?</span></span><span class="gsk-card__summary"><?= gsk_e($consentLabels[$consentMode]) ?></span></button>
      <div class="gsk-card__body" data-gsk-card-body <?= $consentMode === 'off' ? 'hidden' : '' ?>>
        <p class="gsk-meta">When enabled, visitors can manage Analytics and Advertising separately. Optional Google scripts are not downloaded until the matching category is accepted. Site verification remains active because it does not set visitor cookies.</p>
        <div class="gsk-field-row">
          <div class="gsk-field">
            <label class="gsk-field__label" for="gsk-consent-mode">Consent behavior</label>
            <select id="gsk-consent-mode" name="consent_mode" class="inpud">
              <option value="off" <?= $consentMode === 'off' ? 'selected' : '' ?>>Disabled - load configured snippets normally</option>
              <option value="choices" <?= $consentMode === 'choices' ? 'selected' : '' ?>>Global privacy choices - category controls for everyone</option>
            </select>
            <span class="gsk-field__hint">The close button rejects optional services. Global Privacy Control is honored by keeping Advertising disabled.</span>
          </div>
        </div>
        <div class="gsk-field-row">
          <div class="gsk-field">
            <label class="gsk-field__label" for="gsk-privacy-url">Privacy policy URL</label>
            <input type="text" id="gsk-privacy-url" name="privacy_url" class="inpud" value="<?= gsk_e($privacyUrl) ?>" placeholder="/privacy-policy/">
            <span class="gsk-field__hint">Optional relative path or HTTP(S) URL displayed in the consent banner.</span>
          </div>
        </div>
        <p class="gsk-meta"><strong>Important:</strong> Tag Manager is treated as Advertising because a container can run arbitrary marketing tags. Jy Metrics controls only snippets injected by this plugin; review scripts added by themes or other plugins separately.</p>
      </div>
    </div>

    <div class="gsk-card gsk-card--collapsible" data-gsk-card>
      <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="false"><span class="gsk-card__title">Manual Overrides <span class="gsk-help" title="Optional public-site snippets. These IDs do not grant Jy Metrics access to Google APIs.">?</span></span><span class="gsk-card__summary"><?= $manualOverrideCount ?> override<?= $manualOverrideCount === 1 ? '' : 's' ?></span></button>
      <div class="gsk-card__body" data-gsk-card-body hidden>
        <p class="gsk-meta">Use these only when you already have a Google snippet ID. They inject the matching frontend snippet and do not grant Jy Metrics API access.</p>
        <div class="gsk-field-row">
          <div class="gsk-field"><label class="gsk-field__label" for="gsk-ga4-property-id">GA4 Property ID <span title="Required for GA4 reports. Find it in Google Analytics Admin, for example 123456789.">?</span></label><input type="text" id="gsk-ga4-property-id" name="ga4_property_id" class="inpud" value="<?= gsk_e($propertyId) ?>" placeholder="123456789 or properties/123456789"></div>
          <div class="gsk-field"><label class="gsk-field__label" for="gsk-measurement-id">GA4 Measurement ID</label><input type="text" id="gsk-measurement-id" name="measurement_id" class="inpud" value="<?= gsk_e($measurementId) ?>" placeholder="G-XXXXXXXXXX"></div>
        </div>
        <div class="gsk-field-row">
          <div class="gsk-field"><label class="gsk-field__label" for="gsk-gtm-id">Tag Manager Container ID</label><input type="text" id="gsk-gtm-id" name="gtm_id" class="inpud" value="<?= gsk_e($gtmId) ?>" placeholder="GTM-XXXXXXX"></div>
          <div class="gsk-field"><label class="gsk-field__label" for="gsk-adsense-client">AdSense Publisher ID</label><input type="text" id="gsk-adsense-client" name="adsense_client" class="inpud" value="<?= gsk_e($adsense) ?>" placeholder="ca-pub-XXXXXXXXXXXXXXXX"></div>
        </div>
        <div class="gsk-field-row">
          <div class="gsk-field"><label class="gsk-field__label" for="gsk-site-verification">Site Verification Token</label><input type="text" id="gsk-site-verification" name="site_verification" class="inpud" value="<?= gsk_e($verification) ?>" placeholder="verification_token"></div>
        </div>
      </div>
    </div>

    <div class="gsk-card gsk-card--collapsible" data-gsk-card>
      <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="<?= $hasPageSpeedKey ? 'true' : 'false' ?>"><span class="gsk-card__title">Optional Integrations <span class="gsk-help" title="These services work independently from the Google OAuth setup.">?</span></span><span class="gsk-card__summary">PageSpeed Insights · <?= $hasPageSpeedKey ? 'configured' : 'not configured' ?></span></button>
      <div class="gsk-card__body" data-gsk-card-body <?= $hasPageSpeedKey ? '' : 'hidden' ?>>
        <p class="gsk-meta">PageSpeed Insights reads public performance data. It needs an <strong>API key</strong>, not the OAuth Client ID above and not a Service Account.</p>
        <ol class="gsk-steps">
          <li><a href="<?= gsk_e($pageSpeedLibraryUrl) ?>" target="_blank" rel="noopener">Enable PageSpeed Insights API</a> in the Google Cloud project used for this site.</li>
          <li>Open <a href="<?= gsk_e($pageSpeedCredentialsUrl) ?>" target="_blank" rel="noopener">APIs &amp; Services → Credentials</a>, choose <strong>Create credentials → API key</strong>, then copy the key beginning with <code>AIza</code>.</li>
          <li>Restrict the key to <strong>PageSpeed Insights API</strong>. For a server-side Jy Metrics installation, use no application restriction or restrict by the server's static IP. Do not use HTTP referrer restriction.</li>
        </ol>
        <p class="gsk-meta"><strong>Do not use Help me choose:</strong> its User data path creates OAuth and its Application data path creates a Service Account. Neither is the API key Jy Metrics needs. If <strong>API key</strong> is absent from Create credentials, ask the project administrator to allow API key creation and grant <code>roles/serviceusage.apiKeysAdmin</code>.</p>
        <p class="gsk-meta">Jy Metrics caches each result for six hours.</p>
        <div class="gsk-field"><label class="gsk-field__label" for="gsk-pagespeed-api-key">PageSpeed Insights API Key</label><input type="password" id="gsk-pagespeed-api-key" name="pagespeed_api_key" class="inpud" value="<?= gsk_e($settings[GSK_PAGESPEED_API_KEY]) ?>" placeholder="AIza..."><span class="gsk-field__hint">This is an API key, not an OAuth Client ID or Client Secret.</span></div>
      </div>
    </div>

    <div class="gsk-form__foot">
      <a href="<?= gsk_e($wizardPage) ?>" class="adam-button gsk-wizard-cta">Run Setup Wizard</a>
      <button type="submit" class="adam-button">Save Settings</button>
    </div>
  </form>

  <div class="gsk-card gsk-card--collapsible" data-gsk-card>
    <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="<?= $connectedServiceCount > 0 ? 'true' : 'false' ?>"><span class="gsk-card__title">Connected Services <span class="gsk-help" title="Google resources selected through OAuth. Jy Metrics reads reports from these services.">?</span></span><span class="gsk-card__summary"><?= $connectedServiceCount ?> service<?= $connectedServiceCount === 1 ? '' : 's' ?> connected</span></button>
    <div class="gsk-card__body gsk-services" data-gsk-card-body <?= $connectedServiceCount > 0 ? '' : 'hidden' ?>>
      <p class="gsk-meta">These are Google resources authorized through OAuth and selected in the Setup Wizard. They provide data for Jy Metrics reports.</p>
      <?= gskServiceRow('Search Console', $connected && $siteUrl !== '', $siteUrl, $scLink, 'Open Search Console') ?>
      <?= gskServiceRow('Google Analytics 4', $connected && $measurementId !== '', $measurementId . ($propertyId !== '' ? ' (Property ' . $propertyId . ')' : ''), $gaLink, 'Open Analytics') ?>
    </div>
  </div>

  <div class="gsk-card gsk-card--collapsible" data-gsk-card>
    <button type="button" class="gsk-card__head gsk-card__toggle" aria-expanded="false"><span class="gsk-card__title">Active Snippets <span class="gsk-help" title="Public frontend code currently injected by Jy Metrics.">?</span></span><span class="gsk-card__summary"><?= $manualOverrideCount ?> active candidate<?= $manualOverrideCount === 1 ? '' : 's' ?></span></button>
    <div class="gsk-card__body" data-gsk-card-body hidden>
      <p class="gsk-meta">These are codes configured for the public site. A snippet can be active from a manual override even when its service is not connected through OAuth.<?= $consentMode !== 'off' ? ' Optional scripts remain blocked until the visitor accepts.' : '' ?></p>
      <ul class="gsk-list">
        <li><span class="gsk-dot gsk-dot--<?= $measurementId !== '' ? 'on' : 'off' ?>"></span> Google Analytics 4 — <?= $measurementId !== '' ? gsk_e($measurementId) : 'Not configured' ?></li>
        <li><span class="gsk-dot gsk-dot--<?= $gtmId !== '' ? 'on' : 'off' ?>"></span> Tag Manager — <?= $gtmId !== '' ? gsk_e($gtmId) : 'Not configured' ?></li>
        <li><span class="gsk-dot gsk-dot--<?= $adsense !== '' ? 'on' : 'off' ?>"></span> AdSense — <?= $adsense !== '' ? gsk_e($adsense) : 'Not configured' ?></li>
        <li><span class="gsk-dot gsk-dot--<?= $verification !== '' ? 'on' : 'off' ?>"></span> Site Verification — <?= $verification !== '' ? 'Configured' : 'Not configured' ?></li>
      </ul>
    </div>
  </div>
</div>

<style>
.gsk-admin { color: var(--adam-text); max-width: 900px; }
.gsk-admin__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.gsk-admin__title { font-size: 1.35rem; font-weight: 700; margin: 0 0 .25rem; }
.gsk-admin__subtitle { margin: 0; color: var(--adam-muted); font-size: .9rem; }
.gsk-alert { padding: .7rem .9rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
.gsk-alert--success { background: rgba(30, 143, 74, .12); color: var(--adam-success); border: 1px solid rgba(30, 143, 74, .25); }
.gsk-alert--error { background: rgba(220, 38, 38, .12); color: var(--adam-danger); border: 1px solid rgba(220, 38, 38, .25); }
.gsk-alert--info { background: rgba(59, 130, 246, .12); color: var(--adam-primary); border: 1px solid rgba(59, 130, 246, .25); }
.gsk-card { background: var(--adam-card); border: 1px solid var(--adam-border); border-radius: 12px; margin-bottom: 1rem; overflow: hidden; }
.gsk-card--info { border-left: 4px solid var(--adam-success); }
.gsk-card__head { padding: 1rem 1.25rem; border-bottom: 1px solid var(--adam-border); }
.gsk-card__toggle { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 1rem; border: 0; background: transparent; color: var(--adam-text); text-align: left; cursor: pointer; font: inherit; }
.gsk-card__summary { color: var(--adam-muted); font-size: .78rem; font-weight: 500; text-align: right; }
.gsk-help { display: inline-grid; place-items: center; width: 1rem; height: 1rem; margin-left: .2rem; border: 1px solid var(--adam-border-2); border-radius: 50%; color: var(--adam-muted); font-size: .65rem; vertical-align: middle; cursor: help; }
.gsk-card__toggle:hover .gsk-card__title { color: var(--adam-primary); }
.gsk-card__title { font-size: 1.05rem; font-weight: 600; margin: 0; }
.gsk-card__body { padding: 1.25rem; }
.gsk-account { display: flex; align-items: center; justify-content: space-between; gap: .75rem; font-size: .9rem; flex-wrap: wrap; }
.gsk-account__info { display: flex; align-items: center; gap: .5rem; }
.gsk-services > .gsk-service-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid var(--adam-border); }
.gsk-services > .gsk-service-row:last-child { border-bottom: none; }
.gsk-service-info { display: flex; align-items: center; gap: .75rem; }
.gsk-service-label { display: block; font-size: .85rem; color: var(--adam-muted); margin-bottom: .15rem; }
.gsk-service-link { font-size: .85rem; color: var(--adam-primary); text-decoration: none; white-space: nowrap; }
.gsk-service-link:hover { text-decoration: underline; }
.gsk-field { display: flex; flex-direction: column; gap: .25rem; flex: 1 1 300px; min-width: 260px; }
.gsk-field label { font-size: .75rem; color: var(--adam-muted); font-weight: 600; }
.gsk-field__hint { font-size: .75rem; color: var(--adam-muted); line-height: 1.45; }
.gsk-field-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.gsk-field-row:last-child { margin-bottom: 0; }
.gsk-copy-row { display: flex; gap: .5rem; align-items: center; }
.gsk-copy-row input { flex: 1; font-size: .8rem; }
.gsk-form__foot { display: flex; justify-content: flex-end; gap: .5rem; margin: 1rem 0 1.5rem; }
.gsk-meta { font-size: .85rem; color: var(--adam-muted); margin: 0 0 1rem; }
.gsk-meta a { color: var(--adam-primary); }
.gsk-setup { border-left: 4px solid var(--adam-primary); }
.gsk-flow { display: grid; gap: 0; }
.gsk-flow-step { position: relative; border: 1px solid var(--adam-border); border-bottom: 0; background: var(--adam-surface-2); }
.gsk-flow-step:first-child { border-radius: 10px 10px 0 0; }
.gsk-flow-step:last-child { border-bottom: 1px solid var(--adam-border); border-radius: 0 0 10px 10px; }
.gsk-flow-step::before { content: ''; position: absolute; z-index: 0; left: 29px; top: 0; bottom: 0; width: 2px; background: var(--adam-border); }
.gsk-flow-step.is-complete::before { background: var(--adam-success); }
.gsk-flow-step__toggle { display: flex; align-items: center; width: 100%; gap: .75rem; padding: .85rem 1rem; border: 0; background: transparent; color: var(--adam-text); text-align: left; cursor: pointer; font: inherit; }
.gsk-flow-step__number { flex: 0 0 1.7rem; height: 1.7rem; display: grid; place-items: center; border-radius: 50%; background: var(--adam-surface-3); color: var(--adam-primary); font-size: .8rem; font-weight: 700; z-index: 1; }
.gsk-flow-step__toggle strong { display: block; font-size: .9rem; }
.gsk-flow-step__toggle small { display: block; margin-top: .12rem; color: var(--adam-muted); font-size: .75rem; }
.gsk-flow-step__chevron { margin-left: auto; color: var(--adam-muted); transition: transform .2s ease; }
.gsk-flow-step.is-open .gsk-flow-step__chevron { transform: rotate(180deg); }
.gsk-flow-step__body { display: none; padding: 0 1rem 1rem 3.45rem; color: var(--adam-text); font-size: .85rem; }
.gsk-flow-step.is-open .gsk-flow-step__body { display: block; }
.gsk-flow-step__body ol { margin: 0 0 .75rem; padding-left: 1.1rem; line-height: 1.55; }
.gsk-flow-step__body p { margin: 0 0 .75rem; line-height: 1.5; }
.gsk-flow-step__body a { color: var(--adam-primary); text-decoration: none; }
.gsk-flow-step__complete { margin-left: .75rem; padding: .4rem .65rem; border: 1px solid var(--adam-primary); border-radius: 6px; background: transparent; color: var(--adam-primary); cursor: pointer; font: inherit; font-size: .78rem; }
.gsk-flow-step__complete:hover { background: var(--adam-primary); color: #fff; }
.gsk-flow-step.is-complete { background: rgba(30, 143, 74, .06); }
.gsk-flow-step.is-complete .gsk-flow-step__number { background: var(--adam-success); color: #fff; }
.gsk-flow-step.is-locked { opacity: .48; }
.gsk-flow-step.is-locked .gsk-flow-step__toggle { cursor: not-allowed; }
.gsk-form__foot .gsk-wizard-cta { border: 0; background: linear-gradient(135deg, #2563eb, #7c3aed) !important; color: #fff !important; box-shadow: 0 6px 18px rgba(79, 70, 229, .24); }
.gsk-form__foot .gsk-wizard-cta:hover { color: #fff !important; filter: brightness(1.08); transform: translateY(-1px); }
.gsk-flow-step__body .gsk-wizard-cta { border: 0; background: linear-gradient(135deg, #2563eb, #7c3aed) !important; color: #fff !important; box-shadow: 0 6px 18px rgba(79, 70, 229, .24); }
.gsk-flow-step__body .gsk-wizard-cta:hover { color: #fff !important; filter: brightness(1.08); transform: translateY(-1px); }
.gsk-list { list-style: none; padding: 0; margin: 0; }
.gsk-list li { display: flex; align-items: center; gap: .5rem; font-size: .9rem; margin-bottom: .5rem; }
.gsk-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.gsk-dot--on { background: var(--adam-success); }
.gsk-dot--off { background: var(--adam-muted); }
html.theme-dark .gsk-card { background: #0f1720; }
</style>

<script>
(function(){
  var csrf = <?= json_encode($csrf) ?>;
  var basePage = <?= json_encode($basePage) ?>;
  var disconnectEndpoint = basePage + '/api/disconnect&action=disconnect';

  window.gskToast = function(message, type){
    type = type || 'info';
    if (window.NewNotifToast && typeof window.NewNotifToast[type] === 'function') {
      window.NewNotifToast[type](message);
    } else { alert(message); }
  };

  window.gskCopySetting = function(id){
    var el = document.getElementById(id);
    if (!el) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(el.value).then(function(){ gskToast('Copied.', 'success'); });
    } else {
      el.select();
      try { document.execCommand('copy'); } catch(e){}
      gskToast('Copied.', 'success');
    }
  };

  window.gskDisconnect = async function(){
    var ok = true;
    if (window.NewNotifConfirm && typeof window.NewNotifConfirm.danger === 'function') {
      ok = await window.NewNotifConfirm.danger({ message: 'Disconnect this Google account and revoke tokens?', focus: 'cancel' });
    } else if (!confirm('Disconnect this Google account?')) { return; }
    if (!ok) return;
    var fd = new FormData();
    fd.append('csrf_token', csrf);
    fetch(disconnectEndpoint, { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (j.ok) location.replace(basePage + '&success=Disconnected');
        else gskToast(j.error || 'Disconnect failed', 'error');
      })
      .catch(function(){ gskToast('Disconnect failed', 'error'); });
  };

  var flow = document.querySelector('[data-gsk-flow]');
  if (flow) {
    var storageKey = 'jy-metrics-setup-step:' + location.host;
    var steps = Array.prototype.slice.call(flow.querySelectorAll('[data-step]'));
    var completed = Math.max(0, parseInt(localStorage.getItem(storageKey) || '0', 10) || 0);
    if (flow.dataset.oauthReady === '1') completed = Math.max(completed, 4);

    function renderFlow(openStep) {
      steps.forEach(function(step) {
        var number = parseInt(step.dataset.step, 10);
        var isComplete = number <= completed;
        var isLocked = number > completed + 1;
        step.classList.toggle('is-complete', isComplete);
        step.classList.toggle('is-locked', isLocked);
        step.classList.toggle('is-open', number === openStep && !isLocked);
        step.querySelector('.gsk-flow-step__toggle').setAttribute('aria-disabled', isLocked ? 'true' : 'false');
        step.querySelector('.gsk-flow-step__number').textContent = isComplete ? '✓' : number;
      });
    }

    renderFlow(Math.min(completed + 1, 5));
    steps.forEach(function(step) {
      var number = parseInt(step.dataset.step, 10);
      step.querySelector('.gsk-flow-step__toggle').addEventListener('click', function() {
        if (number > completed + 1) return gskToast('Complete the previous step first.', 'info');
        renderFlow(step.classList.contains('is-open') ? 0 : number);
      });
      var complete = step.querySelector('.gsk-flow-step__complete');
      if (complete) complete.addEventListener('click', function() {
        completed = Math.max(completed, number);
        localStorage.setItem(storageKey, String(completed));
        renderFlow(Math.min(completed + 1, 5));
        if (complete.hasAttribute('data-gsk-scroll-credentials')) {
          document.getElementById('gsk-oauth-credentials').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  document.querySelectorAll('[data-gsk-card]').forEach(function(card) {
    var toggle = card.querySelector('.gsk-card__toggle');
    var body = card.querySelector('[data-gsk-card-body]');
    if (!toggle || !body) return;
    toggle.addEventListener('click', function() {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      body.hidden = expanded;
    });
  });
})();
</script>
