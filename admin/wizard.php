<?php
// /plugins/jy-metrics/admin/wizard.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    echo '<p>Database not available.</p>';
    return;
}
adiwira_require_permission($pdo, 'plugin.jy-metrics.integrations.manage', false);

$csrf = function_exists('csrf_token') ? csrf_token() : '';
$connected = gsk_is_connected($pdo);
$basePage = gsk_page_url('');
$settings = gsk_all_settings($pdo);
?>

<div class="gsk-admin">
  <div class="gsk-admin__head">
    <div>
      <h1 class="gsk-admin__title">Jy Metrics Setup Wizard</h1>
      <p class="gsk-admin__subtitle">Connect your provider account and choose services automatically.</p>
    </div>
    <a href="<?= gsk_e($basePage) ?>" class="adam-cancle">← Back to Dashboard</a>
  </div>

  <div class="gsk-wizard">
    <!-- Step 1: Connect -->
    <div class="gsk-card">
      <div class="gsk-card__head">
        <div class="gsk-step">1</div>
        <h2 class="gsk-card__title">Connect Google Account</h2>
      </div>
      <div class="gsk-card__body" id="gsk-step-connect">
        <input type="hidden" id="gsk-csrf" value="<?= gsk_e($csrf) ?>">
        <?php if ($connected): ?>
          <p class="gsk-metric">
            <span class="gsk-metric__label">Connected as</span>
            <span class="gsk-metric__value"><?= gsk_e($settings[GSK_USER_EMAIL_KEY]) ?></span>
          </p>
          <p class="gsk-meta">Your account is authorized for Search Console, Analytics, Tag Manager, and AdSense read-only access.</p>
        <?php else: ?>
          <p class="gsk-meta">Authorize Jyavani to read your Google Search Console, Analytics, Tag Manager, and AdSense data.</p>
          <div id="gsk-connect-actions">
            <a id="gsk-connect-btn" href="#" class="adam-button">Connect Google Account</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Step 2: Services -->
    <div class="gsk-card">
      <div class="gsk-card__head">
        <div class="gsk-step">2</div>
        <h2 class="gsk-card__title">Choose Services</h2>
      </div>
      <div class="gsk-card__body">
        <div id="gsk-wizard-options" class="gsk-wizard-form">
          <p class="gsk-meta">Loading available Google resources…</p>
        </div>

        <div class="gsk-wizard-foot" style="margin-top:1rem">
          <button type="button" id="gsk-wizard-save" class="adam-button" disabled>Save & Finish</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.gsk-admin { color: var(--adam-text); max-width: 760px; }
.gsk-admin__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.gsk-admin__title { font-size: 1.35rem; font-weight: 700; margin: 0 0 .25rem; }
.gsk-admin__subtitle { margin: 0; color: var(--adam-muted); font-size: .9rem; }
.gsk-wizard { display: flex; flex-direction: column; gap: 1rem; }
.gsk-card { background: var(--adam-card); border: 1px solid var(--adam-border); border-radius: 12px; overflow: hidden; }
.gsk-card__head { display: flex; align-items: center; gap: .6rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--adam-border); }
.gsk-card__title { font-size: 1.05rem; font-weight: 600; margin: 0; }
.gsk-card__body { padding: 1.25rem; }
.gsk-step { width: 26px; height: 26px; border-radius: 50%; background: var(--adam-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 700; }
.gsk-wizard-form { display: grid; gap: 1rem; }
.gsk-field { display: flex; flex-direction: column; gap: .25rem; }
.gsk-field label { font-size: .75rem; color: var(--adam-muted); font-weight: 600; }
.gsk-field select, .gsk-field input { width: 100%; }
.gsk-field__hint { font-size: .75rem; color: var(--adam-muted); }
.gsk-metric { display: flex; flex-direction: column; gap: .1rem; margin: 0 0 .75rem; }
.gsk-metric__label { font-size: .75rem; color: var(--adam-muted); text-transform: uppercase; letter-spacing: .04em; }
.gsk-metric__value { font-size: 1.1rem; font-weight: 600; }
.gsk-meta { font-size: .85rem; color: var(--adam-muted); margin: 0 0 .75rem; }
.gsk-loading { display: flex; align-items: center; gap: .5rem; color: var(--adam-muted); font-size: .85rem; }
.gsk-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid var(--adam-border); border-top-color: var(--adam-primary); border-radius: 50%; animation: gsk-spin .8s linear infinite; }
@keyframes gsk-spin { to { transform: rotate(360deg); } }
html.theme-dark .gsk-card { background: #0f1720; }
</style>

<script>
(function(){
  var csrf = <?= json_encode($csrf) ?>;
  var basePage = <?= json_encode($basePage) ?>;
  var setupEndpoint = basePage + '/api/setup';
  var saveEndpoint = basePage + '/api/save-wizard&action=save';
  var connected = <?= json_encode($connected) ?>;

  function gskToast(message, type){
    type = type || 'info';
    if (window.NewNotifToast && typeof window.NewNotifToast[type] === 'function') {
      window.NewNotifToast[type](message);
    } else { alert(message); }
  }

  function gskSpinner(){
    return '<span class="gsk-spinner"></span>';
  }

  function gskEscape(str){
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  function gskSelect(id, label, options, value, hint, allowManual){
    var html = '<div class="gsk-field">';
    html += '<label for="' + id + '" >' + gskEscape(label) + '</label>';
    html += '<select id="' + id + '" class="inpud"' + (connected ? '' : ' disabled') + '>';
    html += '<option value="">' + (connected ? '-- choose automatically --' : 'Connect Google account first') + '</option>';
    options.forEach(function(o){
      var optVal = typeof o === 'string' ? o : (o.value || o.id || o.siteUrl || o.publicId || o.publisherId || '');
      var optLabel = typeof o === 'string' ? o : (o.label || o.displayName || o.name || optVal);
      html += '<option value="' + gskEscape(optVal) + '"' + (value === optVal ? ' selected' : '') + '>' + gskEscape(optLabel) + '</option>';
    });
    html += '</select>';
    if (allowManual) {
      html += '<input type="text" id="' + id + '-manual" class="inpud" style="margin-top:.4rem" placeholder="Or enter manually" value="' + gskEscape(value) + '" >';
    }
    if (hint) html += '<span class="gsk-field__hint">' + gskEscape(hint) + '</span>';
    html += '</div>';
    return html;
  }

  var wizardState = {
    sites: [],
    properties: [],
    containers: [],
    accounts: [],
    measurementId: <?= json_encode($settings[GSK_MEASUREMENT_ID_KEY]) ?>
  };

  function gskLoadStatus(){
    var container = document.getElementById('gsk-connect-actions');
    if (!container) return;
    fetch(setupEndpoint + '&action=status&csrf_token=' + encodeURIComponent(csrf))
      .then(function(r){ return r.text(); })
      .then(function(text){
        window.gskStatusDebug = { statusText: text };
        var j;
        try { j = JSON.parse(text); } catch(e) { j = { ok: false, error: 'Invalid JSON: ' + text }; }
        if (!j.ok) {
          var btn = document.getElementById('gsk-connect-btn');
          if (btn) { btn.href = basePage + '/settings'; btn.textContent = 'Configure OAuth'; }
          gskToast(j.error || 'Could not load Google status', 'error');
          return;
        }
        connected = j.connected;
        var btn = document.getElementById('gsk-connect-btn');
        if (btn) {
          if (j.authUrl) { btn.href = j.authUrl; }
          else { btn.classList.add('disabled'); btn.textContent = 'Set OAuth credentials first'; btn.href = basePage + '/settings'; }
        }
        if (connected) {
          gskLoadOptions();
        } else {
          document.getElementById('gsk-wizard-options').innerHTML = '<p class="gsk-meta">Connect your Google account to discover available resources.</p>';
        }
      })
      .catch(function(err){
        window.gskStatusDebug = { error: err.message };
        var btn = document.getElementById('gsk-connect-btn');
        if (btn) { btn.href = basePage + '/settings'; btn.textContent = 'Configure OAuth'; }
        gskToast('Could not load Google status: ' + err.message, 'error');
      });
  }

  function gskLoadOptions(){
    var container = document.getElementById('gsk-wizard-options');
    container.innerHTML = '<div class="gsk-loading">' + gskSpinner() + ' Loading Google resources…</div>';
    fetch(setupEndpoint + '&action=options&csrf_token=' + encodeURIComponent(csrf))
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j.ok) { container.innerHTML = '<p class="gsk-meta">Failed to load options: ' + gskEscape(j.error) + '</p>'; return; }
        wizardState.sites = j.searchConsoleSites || [];
        wizardState.properties = j.ga4Properties || [];
        wizardState.containers = j.gtmContainers || [];
        wizardState.accounts = j.adsenseAccounts || [];

        // Prefer resources that match current hostname; fall back to saved settings
        var savedSite = <?= json_encode($settings[GSK_SITE_URL_KEY]) ?>;
        var savedProperty = <?= json_encode($settings[GSK_GA4_PROPERTY_ID_KEY]) ?>;
        var defaultSite = savedSite || j.matchedSite || (wizardState.sites[0] ? wizardState.sites[0].siteUrl : '');
        var defaultProperty = savedProperty || '';

        var html = '';
        html += gskSelect('gsk-site-url', 'Search Console Site', wizardState.sites, defaultSite, 'Site used for Search Console stats.', true);
        html += gskSelect('gsk-ga4-property', 'GA4 Property', wizardState.properties, defaultProperty, 'Select property to enable dashboard reporting and auto-detect Measurement ID.', false);
        html += gskSelect('gsk-gtm-container', 'Tag Manager Container', wizardState.containers, <?= json_encode($settings[GSK_GTM_ID_KEY]) ?>, 'Container injected into the site header and footer.', true);
        html += gskSelect('gsk-adsense-account', 'AdSense Account', wizardState.accounts, <?= json_encode($settings[GSK_ADSENSE_CLIENT_KEY]) ?>, 'Optional: used for AdSense auto-ads snippet.', true);
        container.innerHTML = html;

        // Try to find a GA4 property whose web stream matches the current host
        fetch(setupEndpoint + '&action=matchProperty&host=' + encodeURIComponent(location.hostname) + '&csrf_token=' + encodeURIComponent(csrf))
          .then(function(r){ return r.json(); })
          .then(function(m){
            var gaSelect = document.getElementById('gsk-ga4-property');
            if (!gaSelect) return;
            var target = (m.ok && m.propertyId) ? m.propertyId : (savedProperty || (wizardState.properties[0] ? wizardState.properties[0].id : ''));
            if (target && gaSelect.value !== target) {
              gaSelect.value = target;
            }
            if (m.ok && m.measurementId) wizardState.measurementId = m.measurementId;
            else if (gaSelect.value) gskAutoDetectMeasurement(gaSelect.value);
          })
          .catch(function(){
            var gaSelect = document.getElementById('gsk-ga4-property');
            if (gaSelect && gaSelect.value) gskAutoDetectMeasurement(gaSelect.value);
          });

        var gaSelect = document.getElementById('gsk-ga4-property');
        if (gaSelect) gaSelect.addEventListener('change', function(){
          gskAutoDetectMeasurement(this.value);
        });

        document.getElementById('gsk-adsense-account').addEventListener('change', function(){
          var val = this.value;
          if (!val) return;
          var match = wizardState.accounts.find(function(a){ return a.name === val || a.publisherId === val; });
          var publisherId = match ? (match.publisherId || '') : '';
          if (publisherId) {
            var input = document.getElementById('gsk-adsense-account-manual');
            if (input) input.value = 'ca-pub-' + publisherId;
          }
        });

        document.getElementById('gsk-wizard-save').disabled = false;
      })
      .catch(function(){
        container.innerHTML = '<p class="gsk-meta">Failed to load Google resources. Make sure your account is connected and has access.</p>';
      });
  }

  function gskAutoDetectMeasurement(propertyId){
    if (!propertyId) return;
    fetch(setupEndpoint + '&action=streams&propertyId=' + encodeURIComponent(propertyId) + '&csrf_token=' + encodeURIComponent(csrf))
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j.ok || !j.streams || !j.streams.length) return;
        wizardState.measurementId = j.streams[0].measurementId;
      })
      .catch(function(){});
  }

  function gskWizardValue(selectId){
    var manual = document.getElementById(selectId + '-manual');
    var select = document.getElementById(selectId);
    return (manual && manual.value.trim()) ? manual.value.trim() : (select ? select.value : '');
  }

  document.getElementById('gsk-wizard-save').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('site_url', gskWizardValue('gsk-site-url'));
    fd.append('ga4_property_id', document.getElementById('gsk-ga4-property') ? document.getElementById('gsk-ga4-property').value : '');
    fd.append('measurement_id', wizardState.measurementId || (document.getElementById('gsk-ga4-property-manual') ? document.getElementById('gsk-ga4-property-manual').value : ''));
    fd.append('gtm_id', gskWizardValue('gsk-gtm-container'));
    fd.append('adsense_client', gskWizardValue('gsk-adsense-account'));
    fetch(saveEndpoint, { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (j.ok) location.replace(basePage + '&success=Setup+completed');
        else { gskToast(j.error || 'Save failed', 'error'); btn.disabled = false; }
      })
      .catch(function(){ gskToast('Save failed', 'error'); btn.disabled = false; });
  });

  if (!connected) {
    gskLoadStatus();
  } else {
    gskLoadOptions();
  }
})();
</script>
