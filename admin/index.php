<?php
// /plugins/jy-metrics/admin/index.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    echo '<p>Database not available.</p>';
    return;
}
[$uid] = adiwira_require_permission($pdo, 'plugin.jy-metrics.reports.read', false);
$canManage = user_can($pdo, $uid, 'plugin.jy-metrics.integrations.manage');

$csrf = function_exists('csrf_token') ? csrf_token() : '';
$connected = gsk_is_connected($pdo);
$authUrl = $canManage ? gsk_google_auth_url($pdo) : '';
$basePage = gsk_page_url('');
$settingsPage = gsk_page_url('settings');
$wizardPage = gsk_page_url('wizard');
$setupComplete = gsk_setup_complete($pdo);
$selectedSite = gsk_setting($pdo, GSK_SITE_URL_KEY);

$message = '';
$messageType = 'info';
if (!empty($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
} elseif (!empty($_GET['error'])) {
    $message = $_GET['error'];
    $messageType = 'error';
}
?>
<div class="gsk-admin">
  <div class="gsk-admin__head">
    <div class="gsk-admin__title-wrap">
      <h1 class="gsk-admin__title">Jy Metrics</h1>
      <p class="gsk-admin__subtitle">Search Console, Analytics, Tag Manager, and AdSense integration.</p>
    </div>
    <?php if ($canManage): ?><div class="gsk-admin__actions">
      <?php if (!$setupComplete): ?>
        <a href="<?= gsk_e($wizardPage) ?>" class="adam-button">Setup Wizard</a>
      <?php endif; ?>
      <a href="<?= gsk_e($settingsPage) ?>" class="adam-button adam-button--secondary">Settings</a>
      <?php if (!$connected): ?>
        <?php if ($authUrl !== ''): ?>
          <a href="<?= gsk_e($authUrl) ?>" class="adam-button">Connect Google Account</a>
        <?php else: ?>
          <a href="<?= gsk_e($settingsPage) ?>" class="adam-button">Start Google Setup</a>
        <?php endif; ?>
      <?php endif; ?>
    </div><?php endif; ?>
  </div>

  <?php if ($message !== ''): ?>
    <div class="gsk-alert gsk-alert--<?= gsk_e($messageType) ?>"><?= gsk_e($message) ?></div>
  <?php endif; ?>

  <?php if (!$connected): ?>
    <div class="gsk-card gsk-card--wide">
      <div class="gsk-card__head"><h2 class="gsk-card__title">Connect Google Account</h2></div>
      <div class="gsk-card__body">
        <p class="gsk-meta">Connect your Google account to enable Search Console stats, Analytics reports, and auto-discovery of resources.</p>
        <?php if (!$canManage): ?>
          <p class="gsk-meta">Google integration is not connected. Ask a permitted administrator to configure it.</p>
        <?php elseif ($authUrl !== ''): ?>
          <a href="<?= gsk_e($authUrl) ?>" class="adam-button">Connect Google Account</a>
        <?php else: ?>
          <a href="<?= gsk_e($settingsPage) ?>" class="adam-button">Start Google Setup</a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>

  <div class="gsk-card gsk-card--wide gsk-period-bar">
    <div class="gsk-card__body">
      <span class="gsk-period-label">Period:</span>
      <div class="gsk-period-btns" id="gsk-period-btns">
        <button type="button" class="adam-button adam-button--small" data-days="today">Today</button>
        <button type="button" class="adam-button adam-button--small" data-days="7">7 days</button>
        <button type="button" class="adam-button adam-button--small" data-days="30">30 days</button>
        <button type="button" class="adam-button adam-button--small" data-days="90">3 months</button>
        <button type="button" class="adam-button adam-button--small" data-days="180">6 months</button>
        <button type="button" class="adam-button adam-button--small" data-days="365">1 year</button>
        <button type="button" class="adam-button adam-button--small" data-days="all">All time</button>
      </div>
    </div>
  </div>

  <div class="gsk-card gsk-card--wide gsk-realtime" data-gsk-lazy="realtime">
    <div class="gsk-card__head">
      <h2 class="gsk-card__title"><?= gsk_e(gsk_t('Realtime - last 30 minutes')) ?><?= gsk_help_tooltip('gsk-help-realtime', gsk_t('Realtime - last 30 minutes'), gsk_t('Live activity on your site during the last 30 minutes.')) ?></h2>
      <div class="gsk-realtime-meta">
        <span class="gsk-live-dot"></span>
        <span id="gsk-realtime-status">Live</span>
        <button type="button" class="adam-button adam-button--small adam-button--secondary" onclick="gskRefreshRealtime()">Refresh</button>
      </div>
    </div>
    <div class="gsk-card__body">
      <div class="gsk-grid gsk-grid--4">
        <div class="gsk-metric-card">
          <span class="gsk-metric__label"><span class="gsk-metric__label-text"><?= gsk_e(gsk_t('Active users')) ?></span><button type="button" class="gsk-metric__help" aria-label="<?= gsk_e(gsk_t('Active users')) ?>" aria-describedby="gsk-help-realtime-users">?</button><span class="gsk-metric__tooltip" id="gsk-help-realtime-users" role="tooltip"><?= gsk_e(gsk_t('People active on the site during the last 30 minutes.')) ?></span></span>
          <div class="gsk-metric__value" id="gsk-realtime-users">—</div>
        </div>
        <div class="gsk-metric-card">
          <span class="gsk-metric__label"><span class="gsk-metric__label-text"><?= gsk_e(gsk_t('Page views')) ?></span><button type="button" class="gsk-metric__help" aria-label="<?= gsk_e(gsk_t('Page views')) ?>" aria-describedby="gsk-help-realtime-views">?</button><span class="gsk-metric__tooltip" id="gsk-help-realtime-views" role="tooltip"><?= gsk_e(gsk_t('Pages viewed during the last 30 minutes. Repeat views are included.')) ?></span></span>
          <div class="gsk-metric__value" id="gsk-realtime-views">—</div>
        </div>
        <div>
          <h3 class="gsk-section-title">Top countries</h3>
          <div id="gsk-realtime-countries" class="gsk-chart-table"><p class="gsk-meta">Loading…</p></div>
        </div>
        <div>
          <h3 class="gsk-section-title">Top cities</h3>
          <div id="gsk-realtime-cities" class="gsk-chart-table"><p class="gsk-meta">Loading…</p></div>
        </div>
      </div>
    </div>
  </div>

  <div class="gsk-grid gsk-grid--4" data-gsk-lazy="summary">
    <div class="gsk-card gsk-metric-card">
      <div class="gsk-card__body">
        <span class="gsk-metric__label"><span class="gsk-metric__label-text"><?= gsk_e(gsk_t('Visitors')) ?></span><button type="button" class="gsk-metric__help" aria-label="<?= gsk_e(gsk_t('Visitors')) ?>" aria-describedby="gsk-help-visitors">?</button><span class="gsk-metric__tooltip" id="gsk-help-visitors" role="tooltip"><?= gsk_e(gsk_t('Distinct active users during the selected period. One person may visit more than once.')) ?></span></span>
        <div class="gsk-metric__value" id="gsk-metric-users">—</div>
      </div>
    </div>
    <div class="gsk-card gsk-metric-card">
      <div class="gsk-card__body">
        <span class="gsk-metric__label"><span class="gsk-metric__label-text"><?= gsk_e(gsk_t('Impressions')) ?></span><button type="button" class="gsk-metric__help" aria-label="<?= gsk_e(gsk_t('Impressions')) ?>" aria-describedby="gsk-help-impressions">?</button><span class="gsk-metric__tooltip" id="gsk-help-impressions" role="tooltip"><?= gsk_e(gsk_t('How often pages from this site appeared in Google Search results, whether clicked or not.')) ?></span></span>
        <div class="gsk-metric__value" id="gsk-metric-impressions">—</div>
      </div>
    </div>
    <div class="gsk-card gsk-metric-card">
      <div class="gsk-card__body">
        <span class="gsk-metric__label"><span class="gsk-metric__label-text"><?= gsk_e(gsk_t('Clicks')) ?></span><button type="button" class="gsk-metric__help" aria-label="<?= gsk_e(gsk_t('Clicks')) ?>" aria-describedby="gsk-help-clicks">?</button><span class="gsk-metric__tooltip" id="gsk-help-clicks" role="tooltip"><?= gsk_e(gsk_t('Visits to this site from Google Search results.')) ?></span></span>
        <div class="gsk-metric__value" id="gsk-metric-clicks">—</div>
      </div>
    </div>
    <div class="gsk-card gsk-metric-card">
      <div class="gsk-card__body">
        <span class="gsk-metric__label"><span class="gsk-metric__label-text"><?= gsk_e(gsk_t('Avg. Position')) ?></span><button type="button" class="gsk-metric__help" aria-label="<?= gsk_e(gsk_t('Avg. Position')) ?>" aria-describedby="gsk-help-position">?</button><span class="gsk-metric__tooltip" id="gsk-help-position" role="tooltip"><?= gsk_e(gsk_t('Average position of this site in Google Search results. A lower number is better: position 1 is at the top.')) ?></span></span>
        <div class="gsk-metric__value" id="gsk-metric-position">—</div>
      </div>
    </div>
  </div>

  <div class="gsk-card gsk-card--wide" data-gsk-lazy="trend">
    <div class="gsk-card__head"><h2 class="gsk-card__title"><?= gsk_e(gsk_t('Daily active users')) ?><?= gsk_help_tooltip('gsk-help-daily-active-users', gsk_t('Daily active users'), gsk_t('The number of active users for each day in the selected period.')) ?></h2></div>
    <div class="gsk-card__body">
      <div id="gsk-traffic-chart" class="gsk-chart-wrap"><p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p></div>
    </div>
  </div>

  <div class="gsk-grid">
    <div class="gsk-card" data-gsk-lazy="channels">
      <div class="gsk-card__head"><h2 class="gsk-card__title"><?= gsk_e(gsk_t('Channels')) ?><?= gsk_help_tooltip('gsk-help-channels', gsk_t('Channels'), gsk_t('Sessions grouped by GA4 default traffic source categories, such as Organic Search or Direct.')) ?></h2></div>
      <div class="gsk-card__body"><div id="gsk-channels" class="gsk-chart-table"><p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p></div></div>
    </div>
    <div class="gsk-card" data-gsk-lazy="locations">
      <div class="gsk-card__head"><h2 class="gsk-card__title"><?= gsk_e(gsk_t('Locations')) ?><?= gsk_help_tooltip('gsk-help-locations', gsk_t('Locations'), gsk_t('Active users by country, province, or city during the selected period.')) ?></h2></div>
      <div class="gsk-card__body">
        <div class="gsk-location-tabs" role="tablist" aria-label="<?= gsk_e(gsk_t('Locations')) ?>">
          <button type="button" class="gsk-location-tab active" data-dimension="country" role="tab" aria-selected="true"><?= gsk_e(gsk_t('Countries')) ?></button>
          <button type="button" class="gsk-location-tab" data-dimension="region" role="tab" aria-selected="false"><?= gsk_e(gsk_t('Provinces')) ?></button>
          <button type="button" class="gsk-location-tab" data-dimension="city" role="tab" aria-selected="false"><?= gsk_e(gsk_t('Cities')) ?></button>
        </div>
        <div id="gsk-location-filters" class="gsk-location-filters" hidden>
          <label id="gsk-location-country-wrap" class="gsk-location-filter" hidden><?= gsk_e(gsk_t('Country')) ?><select id="gsk-location-country"><option value=""><?= gsk_e(gsk_t('All countries')) ?></option></select></label>
          <label id="gsk-location-region-wrap" class="gsk-location-filter" hidden><?= gsk_e(gsk_t('Province')) ?><select id="gsk-location-region" disabled><option value=""><?= gsk_e(gsk_t('All provinces')) ?></option></select></label>
        </div>
        <div id="gsk-locations" class="gsk-chart-table"><p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p></div>
      </div>
    </div>
  </div>

  <input type="hidden" id="gsk-sc-site" value="<?= gsk_e($selectedSite) ?>">

  <div class="gsk-card gsk-card--wide" data-gsk-lazy="search">
    <div class="gsk-card__head">
      <h2 class="gsk-card__title"><?= gsk_e(gsk_t('Search traffic')) ?><?= gsk_help_tooltip('gsk-help-search-traffic', gsk_t('Search traffic'), gsk_t('Google Search clicks and impressions from Search Console for the selected period.')) ?></h2>
      <span id="gsk-sc-data-status" class="gsk-data-status" aria-live="polite"></span>
      <button type="button" class="adam-button adam-button--small" onclick="gskRefreshSc()">Refresh</button>
    </div>
    <div class="gsk-card__body">
      <div id="gsk-sc-chart" class="gsk-chart-wrap" style="margin-bottom:1.5rem"><p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p></div>
      <div class="gsk-grid gsk-grid--2">
        <div>
          <h3 class="gsk-section-title">Top search queries</h3>
          <div id="gsk-sc-queries" class="gsk-table-wrap"><p class="gsk-meta">Loading…</p></div>
        </div>
        <div>
          <h3 class="gsk-section-title">Top pages from Search</h3>
          <div id="gsk-sc-pages" class="gsk-table-wrap"><p class="gsk-meta">Loading…</p></div>
        </div>
      </div>
    </div>
  </div>

  <div class="gsk-card gsk-card--wide" data-gsk-lazy="content">
    <div class="gsk-card__head"><h2 class="gsk-card__title"><?= gsk_e(gsk_t('Top content')) ?><?= gsk_help_tooltip('gsk-help-top-content', gsk_t('Top content'), gsk_t('Pages with the most views during the selected period.')) ?></h2></div>
    <div class="gsk-card__body">
      <div id="gsk-ga-pages" class="gsk-table-wrap"><p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p></div>
    </div>
  </div>

  <?php endif; ?>

  <div class="gsk-card gsk-card--wide gsk-pagespeed" id="gsk-pagespeed-card" data-gsk-lazy="pagespeed">
    <div class="gsk-card__head">
      <div>
        <h2 class="gsk-card__title">Page Experience<?= gsk_help_tooltip('gsk-help-pagespeed', 'Page Experience', 'Google Lighthouse scores for the site homepage. Results are cached for six hours.') ?></h2>
        <p class="gsk-meta gsk-pagespeed__url" id="gsk-pagespeed-url">Loading site analysis…</p>
      </div>
      <div class="gsk-pagespeed__actions" role="group" aria-label="PageSpeed device strategy">
        <button type="button" class="adam-button adam-button--small active" data-gsk-pagespeed-strategy="desktop">Desktop</button>
        <button type="button" class="adam-button adam-button--small adam-button--secondary" data-gsk-pagespeed-strategy="mobile">Mobile</button>
        <button type="button" class="adam-button adam-button--small adam-button--secondary gsk-pagespeed__reload" id="gsk-pagespeed-reload" title="<?= gsk_e(gsk_t('Refresh Desktop report')) ?>" aria-label="<?= gsk_e(gsk_t('Refresh Desktop report')) ?>"><?= svg_ico('refresh-cw') ?></button>
      </div>
    </div>
    <div class="gsk-card__body">
      <div id="gsk-pagespeed-results" class="gsk-pagespeed__results" aria-live="polite"><p class="gsk-loading"><span class="gsk-spinner"></span> Loading PageSpeed Insights…</p></div>
    </div>
  </div>
</div>

<style>
.gsk-admin { color: var(--adam-text); padding: 0 0 2rem; }
.gsk-admin__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.gsk-admin__title { font-size: 1.35rem; font-weight: 700; margin: 0 0 .25rem; }
.gsk-admin__subtitle { margin: 0; color: var(--adam-muted); font-size: .9rem; }
.gsk-admin__actions { display: flex; gap: .5rem; flex-wrap: wrap; }
.gsk-alert { padding: .7rem .9rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
.gsk-alert--success { background: rgba(30, 143, 74, .12); color: var(--adam-success); border: 1px solid rgba(30, 143, 74, .25); }
.gsk-alert--error { background: rgba(220, 38, 38, .12); color: var(--adam-danger); border: 1px solid rgba(220, 38, 38, .25); }
.gsk-alert--info { background: rgba(59, 130, 246, .12); color: var(--adam-primary); border: 1px solid rgba(59, 130, 246, .25); }
.gsk-period-bar { margin-bottom: 1rem; }
.gsk-period-bar .gsk-card__body { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; padding: .75rem 1.25rem; }
.gsk-period-label { font-size: .85rem; color: var(--adam-muted); }
.gsk-period-btns { display: flex; gap: .4rem; flex-wrap: wrap; }
.gsk-period-btns button { padding: .35rem .7rem; }
.gsk-period-btns button:not(.active), .gsk-paginator__controls button:not(.active), .gsk-pagespeed__actions button:not(.active) { background: var(--adam-surface-3); border: 1px solid var(--adam-border-2); color: var(--adam-text); }
.gsk-period-btns button.active, .gsk-paginator__controls button.active, .gsk-pagespeed__actions button.active { background: var(--adam-primary-gradient); border-color: transparent; color: #fff; }
.gsk-period-btns button.active:hover, .gsk-paginator__controls button.active:hover, .gsk-pagespeed__actions button.active:hover { background: var(--adam-primary-gradient-hover); color: #fff; }
    .gsk-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem; }
    .gsk-card .gsk-grid { margin-bottom: 0; }
    .gsk-grid--2 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
    .gsk-grid--4 { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
.gsk-card { background: var(--adam-card); border: 1px solid var(--adam-border); border-radius: 12px; overflow: hidden; margin-bottom: .75rem; }
.gsk-card.gsk-metric-card { overflow: visible; }
.gsk-card--wide { grid-column: 1 / -1; }
.gsk-card + .gsk-card { margin-top: .25rem; }
.gsk-grid .gsk-card { margin-bottom: 0; }
.gsk-grid .gsk-card + .gsk-card { margin-top: 0; }
.gsk-card__head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--adam-border); flex-wrap: wrap; }
.gsk-card__title { display: flex; align-items: center; position: relative; width: fit-content; gap: .4rem; font-size: 1.05rem; font-weight: 600; margin: 0; }
.gsk-card__body { padding: 1.25rem; }
.gsk-metric-card .gsk-card__body { padding: 1rem 1.25rem; }
.gsk-metric__label { display: flex; align-items: center; position: relative; width: fit-content; gap: .35rem; font-size: .75rem; color: var(--adam-muted); margin-bottom: .3rem; }
.gsk-metric__label-text { text-transform: uppercase; letter-spacing: .04em; }
.gsk-metric__help { display: inline-grid; place-items: center; width: 1.1rem; height: 1.1rem; padding: 0; border: 1px solid var(--adam-border-2); border-radius: 50%; background: var(--adam-surface-3); color: var(--adam-text-2); font: inherit; font-size: .7rem; font-weight: 700; line-height: 1; cursor: help; }
.gsk-metric__help:hover { background: var(--adam-hover); border-color: var(--adam-primary); color: var(--adam-primary); }
.gsk-metric__help:focus-visible { outline: 2px solid var(--adam-focus); outline-offset: 2px; }
.gsk-metric__tooltip { position: absolute; z-index: 4; top: calc(100% + .4rem); left: 0; width: max-content; max-width: min(18rem, calc(100vw - 3rem)); padding: .55rem .65rem; border: 1px solid var(--adam-border); border-radius: 7px; background: var(--adam-card); color: var(--adam-text); box-shadow: var(--adam-shadow); font-size: .78rem; font-weight: 400; line-height: 1.4; letter-spacing: normal; text-transform: none; opacity: 0; pointer-events: none; transform: translateY(-.2rem); visibility: hidden; transition: opacity var(--transition-fast), transform var(--transition-fast), visibility var(--transition-fast); }
.gsk-metric__help:hover + .gsk-metric__tooltip, .gsk-metric__help:focus + .gsk-metric__tooltip { opacity: 1; transform: translateY(0); visibility: visible; }
.gsk-metric-card:focus-within { position: relative; z-index: 2; }
.gsk-metric__value { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
.gsk-meta { font-size: .85rem; color: var(--adam-muted); margin: 0 0 .75rem; }
.gsk-pagespeed__url { margin: .25rem 0 0; word-break: break-all; }
.gsk-pagespeed__actions { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.gsk-pagespeed__reload { display: inline-grid; place-items: center; width: 2rem; height: 2rem; padding: 0; }
.gsk-pagespeed__reload svg { width: 15px; height: 15px; }
.gsk-pagespeed__results { display: grid; grid-template-columns: minmax(180px, 240px) minmax(0, 1fr); gap: 1rem; align-items: stretch; }
.gsk-pagespeed__screenshot { display: flex; flex-direction: column; gap: .45rem; margin: 0; }
.gsk-pagespeed__screenshot img { display: block; width: 100%; height: 100%; min-height: 180px; max-height: 260px; object-fit: cover; object-position: top; border: 1px solid var(--adam-border); border-radius: 9px; background: var(--adam-surface-3); }
.gsk-pagespeed__screenshot figcaption { color: var(--adam-muted); font-size: .76rem; }
.gsk-pagespeed__scores { display: grid; grid-template-columns: repeat(2, minmax(120px, 1fr)); gap: .75rem; }
.gsk-pagespeed-score { padding: 1rem; border: 1px solid var(--adam-border); border-radius: 10px; text-align: center; }
.gsk-pagespeed-score__value { display: block; font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
.gsk-pagespeed-score__label { display: block; margin-top: .35rem; color: var(--adam-muted); font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
.gsk-pagespeed-score--good .gsk-pagespeed-score__value { color: #16a34a; }
.gsk-pagespeed-score--needs-improvement .gsk-pagespeed-score__value { color: #d97706; }
.gsk-pagespeed-score--poor .gsk-pagespeed-score__value { color: #dc2626; }
@media (max-width: 640px) { .gsk-pagespeed__results { grid-template-columns: 1fr; } .gsk-pagespeed__screenshot img { max-height: 320px; } }

.gsk-row { display: flex; align-items: flex-end; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; }
.gsk-section-title { font-size: .9rem; color: var(--adam-muted); text-transform: uppercase; letter-spacing: .04em; margin: 0 0 .75rem; }
.gsk-loading { display: flex; align-items: center; gap: .5rem; color: var(--adam-muted); font-size: .85rem; }
.gsk-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid var(--adam-border); border-top-color: var(--adam-primary); border-radius: 50%; animation: gsk-spin .8s linear infinite; }
@keyframes gsk-spin { to { transform: rotate(360deg); } }
.gsk-table-wrap { overflow-x: auto; }
.gsk-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.gsk-table th, .gsk-table td { padding: .55rem .65rem; border-bottom: 1px solid var(--adam-border); text-align: left; }
.gsk-table th { font-weight: 600; color: var(--adam-muted); }
.gsk-table td { vertical-align: top; }
.gsk-table a { color: var(--adam-primary); word-break: break-all; }
.gsk-chart-wrap { width: 100%; height: 260px; position: relative; }
.gsk-chart-table { display: flex; flex-direction: column; gap: .6rem; }
.gsk-bar-row { display: flex; align-items: center; gap: .75rem; font-size: .85rem; }
.gsk-bar-label { width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--adam-muted); }
.gsk-bar-track { flex: 1; height: 10px; background: var(--adam-bg); border-radius: 5px; overflow: hidden; }
.gsk-bar-fill { height: 100%; background: var(--adam-primary); border-radius: 5px; }
.gsk-bar-value { width: 55px; text-align: right; font-weight: 600; }
.gsk-chart-svg { width: 100%; height: 100%; }
.gsk-chart-line { fill: none; stroke: var(--adam-primary); stroke-width: 2; }
.gsk-chart-line2 { fill: none; stroke: #f59e0b; stroke-width: 2; }
.gsk-chart-area { fill: rgba(59,130,246,.12); stroke: none; }
.gsk-chart-axis { stroke: var(--adam-border); stroke-width: 1; }
.gsk-chart-dot { fill: var(--adam-primary); }
.gsk-chart-grid { stroke: var(--adam-border); stroke-dasharray: 3,3; opacity: .5; }
.gsk-chart-label { fill: var(--adam-muted); font-size: 10px; }
html.theme-dark .gsk-card { background: #0f1720; }
html.theme-dark .gsk-bar-track { background: #0a111a; }
.gsk-realtime { border-left: 4px solid #ef4444; }
.gsk-chart-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 2; cursor: crosshair; pointer-events: auto; }
.gsk-chart-svg { pointer-events: none; }
.gsk-chart-crosshair { stroke: var(--adam-muted); stroke-width: 1; stroke-dasharray: 3,3; }
.gsk-chart-tooltip { position: absolute; display: none; background: var(--adam-card); border: 1px solid var(--adam-border); border-radius: 8px; padding: .5rem .7rem; font-size: .8rem; color: var(--adam-text); box-shadow: 0 4px 12px rgba(0,0,0,.12); pointer-events: none; z-index: 3; max-width: 220px; }
.gsk-chart-tooltip strong { display: block; margin-bottom: .25rem; color: var(--adam-muted); }
.gsk-chart-tooltip div { margin-bottom: .15rem; }
.gsk-query-link { color: var(--adam-primary); text-decoration: none; }
.gsk-query-link:hover { text-decoration: underline; }
.gsk-paginator { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--adam-border); flex-wrap: wrap; }
.gsk-paginator__info { font-size: .8rem; color: var(--adam-muted); }
.gsk-paginator__controls { display: flex; align-items: center; gap: .4rem; }
.gsk-paginator__controls button { padding: .3rem .55rem; min-width: 32px; }
.gsk-paginator__controls button:disabled { opacity: .5; cursor: not-allowed; }
.gsk-paginator__controls button.active { background: var(--adam-primary); color: #fff; border-color: var(--adam-primary); }
.gsk-paginator__perpage { font-size: .8rem; color: var(--adam-muted); display: flex; align-items: center; gap: .4rem; }
.gsk-paginator__perpage select { padding: .25rem .4rem; border-radius: 6px; border: 1px solid var(--adam-border); background: var(--adam-card); color: var(--adam-text); }
.gsk-realtime-meta { display: flex; align-items: center; gap: .6rem; font-size: .85rem; color: var(--adam-muted); }
.gsk-data-status { font-size: .78rem; color: var(--adam-muted); }
.gsk-location-tabs { display: flex; gap: .4rem; margin-bottom: 1rem; flex-wrap: wrap; }
.gsk-location-tab { padding: .35rem .7rem; border: 1px solid var(--adam-border-2); border-radius: 999px; background: var(--adam-surface-3); color: var(--adam-text-2); font: inherit; font-size: .8rem; cursor: pointer; }
.gsk-location-tab:hover { border-color: var(--adam-primary); color: var(--adam-primary); }
.gsk-location-tab.active { background: var(--adam-primary); border-color: var(--adam-primary); color: #fff; }
.gsk-location-tab:focus-visible { outline: 2px solid var(--adam-focus); outline-offset: 2px; }
.gsk-location-filters { display: flex; gap: .75rem; margin: 0 0 1rem; flex-wrap: wrap; }
.gsk-location-filter { display: flex; align-items: center; gap: .4rem; color: var(--adam-muted); font-size: .8rem; }
.gsk-location-filters[hidden], .gsk-location-filter[hidden] { display: none !important; }
.gsk-location-filter select { max-width: 220px; padding: .35rem .5rem; border: 1px solid var(--adam-border-2); border-radius: 6px; background: var(--adam-surface-3); color: var(--adam-text); font: inherit; }
.gsk-location-filter select:disabled { cursor: not-allowed; opacity: .6; }
.gsk-live-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; animation: gsk-pulse 1.5s infinite; }
@keyframes gsk-pulse { 0% { opacity: 1; } 50% { opacity: .4; } 100% { opacity: 1; } }
</style>

<script>
(function(){
  var csrf = <?= json_encode($csrf) ?>;
  var basePage = <?= json_encode($basePage) ?>;
  var scEndpoint = basePage + '/api/search-console';
  var gaEndpoint = basePage + '/api/analytics';
  var pageSpeedEndpoint = basePage + '/api/pagespeed';
  var unknownLocationLabel = <?= json_encode(gsk_t('Unknown location')) ?>;
  var unknownLocationDescription = <?= json_encode(gsk_t('Google Analytics could not determine this visitor location. It may occur when location data is unavailable, consent limits data collection, or the visit lacks a usable IP address.')) ?>;
  var scDataDelayTemplate = <?= json_encode(gsk_t('Search Console data is usually available 2-3 days later. Latest available: {date}.')) ?>;
  var connected = <?= json_encode($connected) ?>;
  var currentDays = '30';
  var currentLocationDimension = 'country';
  var currentGeneration = 0;
  var requestCache = {};
  var requestVersions = {};
  var lazyActivated = {};

  function gskToast(message, type){
    type = type || 'info';
    if (window.NewNotifToast && typeof window.NewNotifToast[type] === 'function') {
      window.NewNotifToast[type](message);
    } else { alert(message); }
  }

  function gskEscape(str){
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function gskNumber(n){
    return (n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
  }

  function gskPercent(n){
    return ((n || 0) * 100).toFixed(2) + '%';
  }

  function gskDuration(s){
    if (!s) return '0s';
    var m = Math.floor(s / 60);
    var sec = Math.floor(s % 60);
    return m + 'm ' + sec + 's';
  }

  function gskFormatDate(date){
    var s = String(date || '');
    var parts = s.match(/^(\d{4})[-]?(\d{2})[-]?(\d{2})/);
    if (!parts) return s;
    var month = parseInt(parts[2], 10);
    var day = parseInt(parts[3], 10);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[month - 1] + ' ' + day;
  }

  function gskFetch(url, force){
    if (!force && requestCache[url]) return requestCache[url];
    var version = (requestVersions[url] || 0) + 1;
    requestVersions[url] = version;
    var request = fetch(url).then(function(r){ return r.json(); }).then(function(j){
      if (requestVersions[url] !== version) return {ok:false, stale:true};
      if (!j || j.ok === false) delete requestCache[url];
      return j;
    }).catch(function(e){
      if (requestVersions[url] !== version) return {ok:false, stale:true};
      if (requestVersions[url] === version) delete requestCache[url];
      return {ok:false, error:e.message};
    });
    requestCache[url] = request;
    return request;
  }

  function gskIsCurrent(days, generation){
    return currentDays === days && currentGeneration === generation;
  }

  var pageSpeedStrategy = 'desktop';
  var pageSpeedRefreshLabels = {
    desktop: <?= json_encode(gsk_t('Refresh Desktop report')) ?>,
    mobile: <?= json_encode(gsk_t('Refresh Mobile report')) ?>
  };

  function gskUpdatePageSpeedRefreshLabel(){
    var reload = document.getElementById('gsk-pagespeed-reload');
    if (!reload) return;
    var label = pageSpeedRefreshLabels[pageSpeedStrategy] || pageSpeedRefreshLabels.desktop;
    reload.title = label;
    reload.setAttribute('aria-label', label);
  }

  function gskPageSpeedScoreClass(score){
    if (score >= 90) return 'good';
    if (score >= 50) return 'needs-improvement';
    return 'poor';
  }

  function gskLoadPageSpeed(force){
    var results = document.getElementById('gsk-pagespeed-results');
    if (!results) return;
    var strategy = pageSpeedStrategy;
    results.innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading PageSpeed Insights…</p>';
    gskFetch(pageSpeedEndpoint + '&action=pagespeed&strategy=' + encodeURIComponent(strategy) + '&csrf_token=' + encodeURIComponent(csrf), force).then(function(j){
      if (j.stale || strategy !== pageSpeedStrategy) return;
      if (!j.ok) {
        var setupLink = j.needsKey ? ' <a href="' + gskEscape(<?= json_encode($settingsPage) ?>) + '">Open Optional Integrations</a>' : '';
        results.innerHTML = '<p class="gsk-meta">' + gskEscape(j.error || 'Could not load PageSpeed Insights.') + setupLink + '</p>';
        return;
      }
      var labels = { performance: 'Performance', accessibility: 'Accessibility', bestPractices: 'Best practices', seo: 'SEO' };
      var html = j.screenshot ? '<figure class="gsk-pagespeed__screenshot"><img src="' + gskEscape(j.screenshot) + '" alt="Google Lighthouse ' + gskEscape(strategy) + ' screenshot"><figcaption>Google Lighthouse screenshot</figcaption></figure>' : '';
      html += '<div class="gsk-pagespeed__scores">';
      Object.keys(labels).forEach(function(key){
        var score = Number(j.scores[key] || 0);
        html += '<div class="gsk-pagespeed-score gsk-pagespeed-score--' + gskPageSpeedScoreClass(score) + '"><span class="gsk-pagespeed-score__value">' + score + '</span><span class="gsk-pagespeed-score__label">' + labels[key] + '</span></div>';
      });
      html += '</div>';
      results.innerHTML = html;
      document.getElementById('gsk-pagespeed-url').textContent = j.url + ' · ' + (j.cached ? 'Cached result, refreshed within 6 hours' : 'Fresh analysis');
    });
  }

  function gskRenderSparkline(containerId, data, valueKey, opts){
    opts = opts || {};
    var container = document.getElementById(containerId);
    if (!container || !data || !data.length) { if(container) container.innerHTML = '<p class="gsk-meta">No data</p>'; return; }
    var values = data.map(function(d){ return d[valueKey] || 0; });
    var max = Math.max.apply(null, values);
    var w = Math.floor(container.getBoundingClientRect().width) || container.clientWidth || 800;
    var h = 260;
    var pad = { top: 20, right: 30, bottom: 35, left: 45 };
    var cw = Math.max(w - pad.left - pad.right, 0);
    var ch = h - pad.top - pad.bottom;
    var n = values.length;
    function x(i){ return pad.left + (n <= 1 ? cw/2 : (i / (n - 1)) * cw); }
    function y(v){ return pad.top + ch - (max ? (v / max) * ch : 0); }
    var path = 'M' + values.map(function(v,i){ return x(i) + ',' + y(v); }).join(' L');
    var area = path + ' L' + x(n-1) + ',' + (pad.top + ch) + ' L' + x(0) + ',' + (pad.top + ch) + ' Z';
    var gridLines = '';
    for (var g = 1; g <= 4; g++) {
      var gy = pad.top + (ch / 5) * g;
      gridLines += '<line x1="' + pad.left + '" y1="' + gy + '" x2="' + (w - pad.right) + '" y2="' + gy + '" class="gsk-chart-grid" />';
    }
    var dots = values.map(function(v,i){ return '<circle cx="' + x(i) + '" cy="' + y(v) + '" r="3" class="gsk-chart-dot" data-idx="' + i + '" />'; }).join('');
    var step = Math.max(1, Math.ceil(n / 7));
    var xLabels = data.map(function(d,i){ return (i % step === 0) ? '<text x="' + x(i) + '" y="' + (h - 10) + '" text-anchor="middle" class="gsk-chart-label">' + gskEscape(gskFormatDate(d.date)) + '</text>' : ''; }).join('');
    var yMax = '<text x="' + (pad.left - 8) + '" y="' + (pad.top + 4) + '" text-anchor="end" class="gsk-chart-label">' + gskNumber(max) + '</text>';
    var yZero = '<text x="' + (pad.left - 8) + '" y="' + (pad.top + ch + 4) + '" text-anchor="end" class="gsk-chart-label">0</text>';
    var line2 = '';
    if (opts.line2Key && data[0][opts.line2Key] !== undefined) {
      var v2 = data.map(function(d){ return d[opts.line2Key] || 0; });
      var max2 = Math.max.apply(null, v2);
      var path2 = 'M' + v2.map(function(v,i){ return x(i) + ',' + (pad.top + ch - (max2 ? (v / max2) * ch : 0)); }).join(' L');
      line2 = '<path d="' + path2 + '" class="gsk-chart-line2" /><g fill="#f59e0b">' + v2.map(function(v,i){ return '<circle cx="' + x(i) + '" cy="' + (pad.top + ch - (max2 ? (v / max2) * ch : 0)) + '" r="2" data-idx="' + i + '" />'; }).join('') + '</g>';
    }
    container.innerHTML = '<svg class="gsk-chart-svg" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="xMidYMid meet">' +
      gridLines + '<path d="' + area + '" class="gsk-chart-area" /><path d="' + path + '" class="gsk-chart-line" />' + line2 +
      '<line x1="' + pad.left + '" y1="' + (pad.top + ch) + '" x2="' + (w - pad.right) + '" y2="' + (pad.top + ch) + '" class="gsk-chart-axis" />' +
      '<line x1="' + pad.left + '" y1="' + pad.top + '" x2="' + pad.left + '" y2="' + (pad.top + ch) + '" class="gsk-chart-axis" />' +
      dots + xLabels + yMax + yZero + '</svg>';

    var svg = container.querySelector('svg');
    var overlay = document.createElement('div');
    overlay.className = 'gsk-chart-overlay';
    container.appendChild(overlay);
    var tooltip = document.createElement('div');
    tooltip.className = 'gsk-chart-tooltip';
    container.appendChild(tooltip);
    var crosshair = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    crosshair.setAttribute('class', 'gsk-chart-crosshair');
    crosshair.setAttribute('y1', String(pad.top));
    crosshair.setAttribute('y2', String(pad.top + ch));
    crosshair.style.display = 'none';
    svg.appendChild(crosshair);

    function updateHover(clientX){
      var rect = container.getBoundingClientRect();
      var mx = clientX - rect.left;
      var scale = rect.width / w;
      var chartMx = (mx / scale) - pad.left;
      var i = Math.max(0, Math.min(n - 1, Math.round((chartMx / cw) * (n - 1))));
      var d = data[i];
      var xi = x(i);
      crosshair.setAttribute('x1', String(xi));
      crosshair.setAttribute('x2', String(xi));
      crosshair.style.display = 'block';
      var label = gskEscape(gskFormatDate(d.date));
      var html = '<strong>' + label + '</strong>';
      if (opts.line2Key && d[opts.line2Key] !== undefined) {
        html += '<div>Impressions: ' + gskNumber(d[valueKey]) + '</div><div>Clicks: ' + gskNumber(d[opts.line2Key]) + '</div>';
      } else {
        html += '<div>' + (opts.label || 'Value') + ': ' + gskNumber(d[valueKey]) + '</div>';
      }
      tooltip.innerHTML = html;
      tooltip.style.display = 'block';
      var tipW = tooltip.offsetWidth;
      var tipH = tooltip.offsetHeight;
      var tx = mx + 12;
      if (tx + tipW > rect.width) tx = mx - tipW - 12;
      var ty = Math.max(8, Math.min(rect.height - tipH - 8, (rect.height / 2) - (tipH / 2)));
      tooltip.style.left = tx + 'px';
      tooltip.style.top = ty + 'px';
    }

    overlay.addEventListener('mousemove', function(e){ updateHover(e.clientX); });
    overlay.addEventListener('mouseleave', function(){ tooltip.style.display = 'none'; crosshair.style.display = 'none'; });
    overlay.addEventListener('touchstart', function(e){ if(e.touches[0]) updateHover(e.touches[0].clientX); }, {passive:true});
    overlay.addEventListener('touchmove', function(e){ if(e.touches[0]) updateHover(e.touches[0].clientX); }, {passive:true});
    overlay.addEventListener('touchend', function(){ tooltip.style.display = 'none'; crosshair.style.display = 'none'; });
  }

  function gskRenderBarTable(containerId, rows, labelKey, valueKey){
    var container = document.getElementById(containerId);
    if (!container) return;
    if (!rows || !rows.length) { container.innerHTML = '<p class="gsk-meta">No data</p>'; return; }
    var max = Math.max.apply(null, rows.map(function(r){ return r[valueKey] || 0; }));
    var html = '';
    rows.forEach(function(r){
      var pct = max ? ((r[valueKey] || 0) / max) * 100 : 0;
      html += '<div class="gsk-bar-row">' +
        '<span class="gsk-bar-label" title="' + gskEscape(r[labelKey]) + '">' + gskEscape(r[labelKey]) + '</span>' +
        '<div class="gsk-bar-track"><div class="gsk-bar-fill" style="width:' + pct + '%"></div></div>' +
        '<span class="gsk-bar-value">' + gskNumber(r[valueKey]) + '</span></div>';
    });
    container.innerHTML = html;
  }

  var gskPaginationState = {};

  function gskBuildPaginator(containerId, totalRows){
    var state = gskPaginationState[containerId];
    var totalPages = Math.max(1, Math.ceil(totalRows / state.perPage));
    state.page = Math.min(state.page, totalPages);
    var start = (state.page - 1) * state.perPage + 1;
    var end = Math.min(state.page * state.perPage, totalRows);
    var html = '<div class="gsk-paginator">' +
      '<div class="gsk-paginator__info">Showing ' + start + '–' + end + ' of ' + totalRows + '</div>' +
      '<div class="gsk-paginator__controls">' +
        '<button type="button" class="adam-button adam-button--secondary" onclick="gskSetPage(\'' + containerId + '\', ' + (state.page - 1) + ')"' + (state.page <= 1 ? ' disabled' : '') + '>Prev</button>';
    var minPage = Math.max(1, state.page - 2);
    var maxPage = Math.min(totalPages, minPage + 4);
    for (var p = minPage; p <= maxPage; p++) {
      html += '<button type="button" class="adam-button' + (p === state.page ? ' active' : '') + '" onclick="gskSetPage(\'' + containerId + '\', ' + p + ')">' + p + '</button>';
    }
    html += '<button type="button" class="adam-button adam-button--secondary" onclick="gskSetPage(\'' + containerId + '\', ' + (state.page + 1) + ')"' + (state.page >= totalPages ? ' disabled' : '') + '>Next</button>' +
      '</div>' +
      '<label class="gsk-paginator__perpage">Per page ' +
        '<select onchange="gskSetPerPage(\'' + containerId + '\', this.value)">' +
          '<option value="10"' + (state.perPage === 10 ? ' selected' : '') + '>10</option>' +
          '<option value="20"' + (state.perPage === 20 ? ' selected' : '') + '>20</option>' +
          '<option value="50"' + (state.perPage === 50 ? ' selected' : '') + '>50</option>' +
          '<option value="100"' + (state.perPage === 100 ? ' selected' : '') + '>100</option>' +
        '</select>' +
      '</label>' +
      '</div>';
    return html;
  }

  window.gskSetPage = function(containerId, page){
    var state = gskPaginationState[containerId];
    if (!state) return;
    state.page = page;
    gskRenderPaginated(containerId);
  };

  window.gskSetPerPage = function(containerId, perPage){
    var state = gskPaginationState[containerId];
    if (!state) return;
    state.perPage = parseInt(perPage, 10);
    state.page = 1;
    gskRenderPaginated(containerId);
  };

  function gskRenderPaginated(containerId){
    var state = gskPaginationState[containerId];
    if (!state) return;
    var container = document.getElementById(containerId);
    if (!container) return;
    if (!state.allRows || !state.allRows.length) { container.innerHTML = '<p class="gsk-meta">No data</p>'; return; }
    var totalRows = state.allRows.length;
    var totalPages = Math.max(1, Math.ceil(totalRows / state.perPage));
    var page = Math.max(1, Math.min(state.page, totalPages));
    state.page = page;
    var start = (page - 1) * state.perPage;
    var slice = state.allRows.slice(start, start + state.perPage);
    var html = state.renderFn(slice);
    html += gskBuildPaginator(containerId, totalRows);
    container.innerHTML = html;
  }

  function gskInitPaginated(containerId, rows, renderFn, defaultPerPage){
    gskPaginationState[containerId] = {
      allRows: rows || [],
      renderFn: renderFn,
      perPage: defaultPerPage || 10,
      page: 1,
    };
    gskRenderPaginated(containerId);
  }

  function gskRenderQueryTable(containerId, rows){
    var renderFn = function(slice){
      var html = '<table class="gsk-table"><thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead><tbody>';
      slice.forEach(function(q){
        var queryLink = 'https://www.google.com/search?q=' + encodeURIComponent(q.query);
        html += '<tr><td><a class="gsk-query-link" href="' + gskEscape(queryLink) + '" target="_blank" rel="noopener">' + gskEscape(q.query) + '</a></td><td>' + gskNumber(q.clicks) + '</td><td>' + gskNumber(q.impressions) + '</td><td>' + gskPercent(q.ctr) + '</td><td>' + gskNumber(q.position) + '</td></tr>';
      });
      html += '</tbody></table>';
      return html;
    };
    gskInitPaginated(containerId, rows, renderFn, 10);
  }

  function gskRenderPageTable(containerId, rows, isGa){
    var renderFn = function(slice){
      var html = '<table class="gsk-table"><thead><tr>';
      if (isGa) {
        html += '<th>Title / Path</th><th>Pageviews</th><th>Sessions</th><th>Engagement</th><th>Duration</th>';
      } else {
        html += '<th>Page</th><th>Clicks</th><th>Impressions</th>';
      }
      html += '</tr></thead><tbody>';
      slice.forEach(function(p){
        if (isGa) {
          var url = 'https://' + (location.hostname.replace(/^www\./,'')) + p.path;
          html += '<tr><td><a href="' + gskEscape(url) + '" target="_blank" rel="noopener">' + gskEscape(p.title || p.path) + '</a><br><small>' + gskEscape(p.path) + '</small></td>' +
            '<td>' + gskNumber(p.pageviews) + '</td><td>' + gskNumber(p.sessions) + '</td>' +
            '<td>' + gskPercent(p.engagementRate) + '</td><td>' + gskDuration(p.avgSessionDuration) + '</td></tr>';
        } else {
          var path = (p.page || '').replace(/^https?:\/\/[^\/]+/, '');
          var host = (p.page || '').match(/^https?:\/\/([^\/]+)/);
          var label = path && path !== '/' ? path : (host ? host[1] : '/');
          html += '<tr><td><a href="' + gskEscape(p.page) + '" target="_blank" rel="noopener">' + gskEscape(label) + '</a></td>' +
            '<td>' + gskNumber(p.clicks) + '</td><td>' + gskNumber(p.impressions) + '</td></tr>';
        }
      });
      html += '</tbody></table>';
      return html;
    };
    gskInitPaginated(containerId, rows, renderFn, 10);
  }

  function gskRenderPaginatedBarTable(containerId, rows, labelKey, valueKey){
    var renderFn = function(slice){
      var max = Math.max.apply(null, rows.map(function(r){ return r[valueKey] || 0; }));
      var html = '';
      slice.forEach(function(r){
        var pct = max ? ((r[valueKey] || 0) / max) * 100 : 0;
        html += '<div class="gsk-bar-row">' +
          '<span class="gsk-bar-label" title="' + gskEscape(r.tooltip || r[labelKey]) + '">' + gskEscape(r[labelKey]) + '</span>' +
          '<div class="gsk-bar-track"><div class="gsk-bar-fill" style="width:' + pct + '%"></div></div>' +
          '<span class="gsk-bar-value">' + gskNumber(r[valueKey]) + '</span></div>';
      });
      return html;
    };
    gskInitPaginated(containerId, rows, renderFn, 10);
  }

  function gskLoadAnalyticsSummary(days, generation, force){
    var d = '&days=' + encodeURIComponent(days);
    document.getElementById('gsk-metric-users').textContent = '—';
    return gskFetch(gaEndpoint + '&action=report' + d + '&csrf_token=' + encodeURIComponent(csrf), force).then(function(j){
      if (j.stale) return j;
      if (gskIsCurrent(days, generation) && j.ok && j.totals) document.getElementById('gsk-metric-users').textContent = gskNumber(j.totals.activeUsers);
      return j;
    });
  }

  function gskLoadAnalyticsTrend(days, generation, force){
    var d = '&days=' + encodeURIComponent(days);
    gskFetch(gaEndpoint + '&action=trend' + d + '&csrf_token=' + encodeURIComponent(csrf), force).then(function(j){
      if (j.stale || !gskIsCurrent(days, generation)) return;
      if (j.ok && j.trend) {
        gskRenderSparkline('gsk-traffic-chart', j.trend, 'activeUsers', { label: 'Daily active users' });
        var usersEl = document.getElementById('gsk-metric-users');
        if (usersEl && usersEl.textContent === '—') {
          var total = j.trend.reduce(function(s, d){ return s + (d.activeUsers || 0); }, 0);
          usersEl.textContent = gskNumber(total);
        }
      } else {
        document.getElementById('gsk-traffic-chart').innerHTML = '<p class="gsk-meta">' + gskEscape(j.error || 'Could not load traffic trend') + '</p>';
      }
    });
  }

  function gskLoadChannels(days, generation, force){
    var d = '&days=' + encodeURIComponent(days);
    gskFetch(gaEndpoint + '&action=channels' + d + '&csrf_token=' + encodeURIComponent(csrf), force).then(function(j){
      if (j.stale || !gskIsCurrent(days, generation)) return;
      if (j.ok && j.channels) gskRenderBarTable('gsk-channels', j.channels, 'channel', 'sessions');
      else document.getElementById('gsk-channels').innerHTML = '<p class="gsk-meta">' + gskEscape(j.error || 'Could not load channels') + '</p>';
    });
  }

  function gskLoadContent(days, generation, force){
    var d = '&days=' + encodeURIComponent(days);
    gskFetch(gaEndpoint + '&action=pages' + d + '&csrf_token=' + encodeURIComponent(csrf), force).then(function(j){
      if (j.stale || !gskIsCurrent(days, generation)) return;
      if (j.ok) gskRenderPageTable('gsk-ga-pages', j.pages || [], true);
      else document.getElementById('gsk-ga-pages').innerHTML = '<p class="gsk-meta">' + gskEscape(j.error || 'Could not load top content') + '</p>';
    });
  }

  var locationRequestVersions = {};

  function gskLoadLocations(days, dimension, generation, force){
    generation = generation === undefined ? currentGeneration : generation;
    var locationVersion = (locationRequestVersions[dimension] || 0) + 1;
    locationRequestVersions[dimension] = locationVersion;
    var container = document.getElementById('gsk-locations');
    var country = dimension === 'country' ? '' : locationFilters.country;
    var region = dimension === 'city' ? locationFilters.region : '';
    var d = '&days=' + encodeURIComponent(days) + '&dimension=' + encodeURIComponent(dimension) +
      '&country=' + encodeURIComponent(country) + '&region=' + encodeURIComponent(region);
    gskFetch(gaEndpoint + '&action=locations' + d + '&csrf_token=' + encodeURIComponent(csrf), force).then(function(j){
      if (j.stale || locationRequestVersions[dimension] !== locationVersion || !gskIsCurrent(days, generation)) return;
      if (j.ok && j.locations) {
        if (dimension === 'country') gskSetLocationOptions(countrySelect, j.locations, locationFilters.country);
        if (dimension === 'region' && country !== '') gskSetLocationOptions(regionSelect, j.locations, locationFilters.region);
        if (dimension === currentLocationDimension && country === locationFilters.country && region === locationFilters.region) {
          var displayRows = j.locations.map(function(row){
            if (row.location !== '(not set)') {
              return { location: row.location, label: row.location, activeUsers: row.activeUsers };
            }
            return { location: row.location, label: unknownLocationLabel, tooltip: unknownLocationDescription, activeUsers: row.activeUsers };
          });
          gskRenderPaginatedBarTable('gsk-locations', displayRows, 'label', 'activeUsers');
        }
      } else if (container && dimension === currentLocationDimension && country === locationFilters.country && region === locationFilters.region) {
        container.innerHTML = '<p class="gsk-meta">' + gskEscape(j.error || 'Could not load locations') + '</p>';
      }
    });
  }

  var locationFilters = { country: '', region: '' };
  var countrySelect = document.getElementById('gsk-location-country');
  var regionSelect = document.getElementById('gsk-location-region');

  function gskSetLocationOptions(select, rows, selected){
    if (!select) return;
    var placeholder = select.options[0] ? select.options[0].textContent : '';
    select.innerHTML = '';
    var empty = document.createElement('option');
    empty.value = '';
    empty.textContent = placeholder;
    select.appendChild(empty);
    rows.forEach(function(row){
      var option = document.createElement('option');
      option.value = row.location;
      option.textContent = row.location;
      option.selected = row.location === selected;
      select.appendChild(option);
    });
    select.disabled = false;
  }

  function gskUpdateLocationFilters(){
    var filters = document.getElementById('gsk-location-filters');
    var countryWrap = document.getElementById('gsk-location-country-wrap');
    var regionWrap = document.getElementById('gsk-location-region-wrap');
    if (!filters || !countryWrap || !regionWrap) return;
    filters.hidden = currentLocationDimension === 'country';
    countryWrap.hidden = currentLocationDimension === 'country';
    regionWrap.hidden = currentLocationDimension !== 'city';
    if (currentLocationDimension === 'city') {
      regionSelect.disabled = locationFilters.country === '';
      if (locationFilters.country !== '') gskLoadLocations(currentDays, 'region', currentGeneration);
    }
  }

  window.gskSelectLocation = function(dimension){
    currentLocationDimension = dimension;
    document.querySelectorAll('.gsk-location-tab').forEach(function(tab){
      var active = tab.dataset.dimension === dimension;
      tab.classList.toggle('active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.getElementById('gsk-locations').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    gskUpdateLocationFilters();
    lazyActivated.locations = true;
    gskLoadLocations(currentDays, dimension, currentGeneration, true);
  };

  if (countrySelect) countrySelect.addEventListener('change', function(){
    locationFilters.country = this.value;
    locationFilters.region = '';
    locationRequestVersions.region = (locationRequestVersions.region || 0) + 1;
    if (regionSelect) {
      regionSelect.value = '';
      regionSelect.disabled = locationFilters.country === '';
    }
    document.getElementById('gsk-locations').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    if (currentLocationDimension === 'city' && locationFilters.country !== '') gskLoadLocations(currentDays, 'region', currentGeneration);
    gskLoadLocations(currentDays, currentLocationDimension, currentGeneration, true);
  });

  if (regionSelect) regionSelect.addEventListener('change', function(){
    locationFilters.region = this.value;
    document.getElementById('gsk-locations').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    gskLoadLocations(currentDays, 'city', currentGeneration, true);
  });

  var realtimeTimer = null;
  var realtimeInFlight = false;

  function gskLoadRealtime(){
    if (realtimeInFlight || document.hidden) return;
    realtimeInFlight = true;
    gskFetch(gaEndpoint + '&action=realtime&csrf_token=' + encodeURIComponent(csrf), true).then(function(j){
      realtimeInFlight = false;
      if (j.stale) return;
      if (!j.ok) {
        document.getElementById('gsk-realtime-status').textContent = 'Error: ' + (j.error || 'failed');
        return;
      }
      document.getElementById('gsk-realtime-status').textContent = 'Live · updated ' + new Date().toLocaleTimeString();
      document.getElementById('gsk-realtime-users').textContent = gskNumber(j.totals.activeUsers);
      document.getElementById('gsk-realtime-views').textContent = gskNumber(j.totals.screenPageViews);
      gskRenderBarTable('gsk-realtime-countries', j.countries || [], 'country', 'activeUsers');
      gskRenderBarTable('gsk-realtime-cities', j.cities || [], 'city', 'activeUsers');
    });
  }

  window.gskRefreshRealtime = function(){
    gskLoadRealtime();
  };

  function gskStartRealtime(){
    if (realtimeTimer) clearInterval(realtimeTimer);
    if (document.hidden) return;
    gskLoadRealtime();
    realtimeTimer = setInterval(gskLoadRealtime, 30000);
  }

  function gskStopRealtime(){
    if (realtimeTimer) clearInterval(realtimeTimer);
    realtimeTimer = null;
  }

  document.addEventListener('visibilitychange', function(){
    if (!lazyActivated.realtime) return;
    if (document.hidden) gskStopRealtime();
    else gskStartRealtime();
  });

  window.gskRefreshSc = function(){
    var site = document.getElementById('gsk-sc-site').value;
    if (!site) { gskToast('Select a site first.', 'warning'); return; }
    lazyActivated.search = true;
    document.getElementById('gsk-sc-queries').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    document.getElementById('gsk-sc-pages').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    gskLoadSearchConsole(site, currentDays, currentGeneration, true);
  };

  function gskLoadSearchSummary(siteUrl, days, generation, force){
    if (!siteUrl) return Promise.resolve({ok:false});
    var d = '&days=' + encodeURIComponent(days);
    var base = scEndpoint + '&siteUrl=' + encodeURIComponent(siteUrl) + d + '&csrf_token=' + encodeURIComponent(csrf);
    document.getElementById('gsk-metric-impressions').textContent = '—';
    document.getElementById('gsk-metric-clicks').textContent = '—';
    document.getElementById('gsk-metric-position').textContent = '—';
    return gskFetch(base + '&action=summary', force).then(function(j){
      if (j.stale) return j;
      if (gskIsCurrent(days, generation) && j.ok) {
        document.getElementById('gsk-metric-impressions').textContent = gskNumber(j.impressions);
        document.getElementById('gsk-metric-clicks').textContent = gskNumber(j.clicks);
        document.getElementById('gsk-metric-position').textContent = gskNumber(j.avgPosition);
      }
      return j;
    });
  }

  function gskLoadSearchConsole(siteUrl, days, generation, force){
    if (!siteUrl) return;
    var d = '&days=' + encodeURIComponent(days);
    var base = scEndpoint + '&siteUrl=' + encodeURIComponent(siteUrl) + d + '&csrf_token=' + encodeURIComponent(csrf);
    gskFetch(base + '&action=stats', force).then(function(j){
      if (j.stale) return;
      if (gskIsCurrent(days, generation)) gskRenderQueryTable('gsk-sc-queries', j.ok ? j.queries : []);
    });
    gskFetch(base + '&action=trend', force).then(function(j){
      if (j.stale || !gskIsCurrent(days, generation)) return;
      if (j.ok && j.trend) {
        gskRenderSparkline('gsk-sc-chart', j.trend, 'impressions', { line2Key: 'clicks', label: 'Impressions' });
        var status = document.getElementById('gsk-sc-data-status');
        if (status) {
          status.textContent = j.latestAvailableDate && j.latestAvailableDate < j.endDate
            ? scDataDelayTemplate.replace('{date}', gskFormatDate(j.latestAvailableDate))
            : '';
        }
      } else {
        document.getElementById('gsk-sc-chart').innerHTML = '<p class="gsk-meta">' + gskEscape(j.error || 'Could not load trend') + '</p>';
      }
    });
    gskFetch(base + '&action=pages', force).then(function(j){
      if (j.stale || !gskIsCurrent(days, generation)) return;
      gskRenderPageTable('gsk-sc-pages', j.ok ? j.pages : [], false);
    });
  }

  function gskRunLazySection(section, force){
    var generation = currentGeneration;
    var siteSelect = document.getElementById('gsk-sc-site');
    var site = siteSelect ? siteSelect.value : '';
    if (section === 'realtime') gskStartRealtime();
    if (section === 'summary') {
      gskLoadAnalyticsSummary(currentDays, generation, force);
      gskLoadSearchSummary(site, currentDays, generation, force);
    }
    if (section === 'trend') gskLoadAnalyticsTrend(currentDays, generation, force);
    if (section === 'channels') gskLoadChannels(currentDays, generation, force);
    if (section === 'locations') gskLoadLocations(currentDays, currentLocationDimension, generation, force);
    if (section === 'search') gskLoadSearchConsole(site, currentDays, generation, force);
    if (section === 'content') gskLoadContent(currentDays, generation, force);
    if (section === 'pagespeed') gskLoadPageSpeed(force);
  }

  function gskActivateLazySection(element){
    var section = element.dataset.gskLazy;
    if (!section || lazyActivated[section]) return;
    lazyActivated[section] = true;
    gskRunLazySection(section, false);
  }

  function gskInitLazyLoading(){
    var sections = document.querySelectorAll('[data-gsk-lazy]');
    if (!('IntersectionObserver' in window)) {
      var pending = Array.prototype.slice.call(sections);
      var checkVisible = function(){
        pending = pending.filter(function(section){
          var rect = section.getBoundingClientRect();
          if (rect.top <= window.innerHeight && rect.bottom >= 0) {
            gskActivateLazySection(section);
            return false;
          }
          return true;
        });
        if (!pending.length) {
          window.removeEventListener('scroll', checkVisible);
          window.removeEventListener('resize', checkVisible);
        }
      };
      window.addEventListener('scroll', checkVisible, {passive:true});
      window.addEventListener('resize', checkVisible);
      checkVisible();
      return;
    }
    var observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        gskActivateLazySection(entry.target);
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px' });
    sections.forEach(function(section){ observer.observe(section); });
  }

  function gskSetPeriod(days){
    currentDays = days;
    currentGeneration++;
    var btns = document.querySelectorAll('#gsk-period-btns button');
    btns.forEach(function(b){ b.classList.toggle('active', b.dataset.days === days); });
    if (lazyActivated.trend) document.getElementById('gsk-traffic-chart').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    if (lazyActivated.channels) document.getElementById('gsk-channels').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    if (lazyActivated.locations) document.getElementById('gsk-locations').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    if (lazyActivated.search) {
      document.getElementById('gsk-sc-chart').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
      document.getElementById('gsk-sc-queries').innerHTML = '<p class="gsk-meta">Loading…</p>';
      document.getElementById('gsk-sc-pages').innerHTML = '<p class="gsk-meta">Loading…</p>';
      document.getElementById('gsk-sc-data-status').textContent = '';
    }
    if (lazyActivated.content) document.getElementById('gsk-ga-pages').innerHTML = '<p class="gsk-loading"><span class="gsk-spinner"></span> Loading…</p>';
    ['summary', 'trend', 'channels', 'locations', 'search', 'content'].forEach(function(section){
      if (lazyActivated[section]) gskRunLazySection(section, false);
    });
  }

  if (connected) {
    document.querySelectorAll('#gsk-period-btns button').forEach(function(btn){
      btn.addEventListener('click', function(){ gskSetPeriod(this.dataset.days); });
    });
    document.querySelectorAll('.gsk-location-tab').forEach(function(tab){
      tab.addEventListener('click', function(){ gskSelectLocation(this.dataset.dimension); });
    });
    gskSetPeriod('30');
  }

  gskInitLazyLoading();

  document.querySelectorAll('[data-gsk-pagespeed-strategy]').forEach(function(button){
    button.addEventListener('click', function(){
      pageSpeedStrategy = this.dataset.gskPagespeedStrategy;
      document.querySelectorAll('[data-gsk-pagespeed-strategy]').forEach(function(item){
        var active = item === button;
        item.classList.toggle('active', active);
        item.classList.toggle('adam-button--secondary', !active);
      });
      gskUpdatePageSpeedRefreshLabel();
      lazyActivated.pagespeed = true;
      gskLoadPageSpeed(false);
    });
  });
  var pageSpeedReload = document.getElementById('gsk-pagespeed-reload');
  if (pageSpeedReload) pageSpeedReload.addEventListener('click', function(){
    lazyActivated.pagespeed = true;
    gskLoadPageSpeed(true);
  });
})();
</script>
