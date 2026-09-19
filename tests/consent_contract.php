<?php
declare(strict_types=1);

define('PLUGIN_SYSTEM_LOADED', true);
$GLOBALS['contract_hooks'] = [];
$GLOBALS['contract_settings'] = [];

function add_action(string $hook, callable $callback): void
{
    $GLOBALS['contract_hooks'][$hook][] = $callback;
}

function settings_get(PDO $pdo, string $key, mixed $default = ''): mixed
{
    return $GLOBALS['contract_settings'][$key] ?? $default;
}

function __(string $source, string $scope = 'default'): string
{
    return $source;
}

$pdo = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
$GLOBALS['pdo'] = $pdo;
require dirname(__DIR__) . '/plugin.php';

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) $failures++;
};
$render = static function (string $hook): string {
    ob_start();
    foreach ($GLOBALS['contract_hooks'][$hook] ?? [] as $callback) $callback();
    return (string)ob_get_clean();
};
$settingsSource = (string)file_get_contents(dirname(__DIR__) . '/admin/settings.php');

$check(gsk_valid_privacy_url('') && gsk_valid_privacy_url('/privacy/') && gsk_valid_privacy_url('https://example.com/privacy'), 'safe privacy URLs are accepted');
$check(!gsk_valid_privacy_url('//example.com') && !gsk_valid_privacy_url('/\\example.com') && !gsk_valid_privacy_url('javascript:alert(1)') && !gsk_valid_privacy_url('/bad path'), 'ambiguous and executable privacy URLs are rejected');
$check(!str_contains($settingsSource, 'privacy_page_linked') && !str_contains($settingsSource, 'site footer'), 'privacy settings remain tenant-neutral without a footer-specific confirmation');
$check(str_contains($settingsSource, 'aria-describedby=') && str_contains($settingsSource, 'role="tooltip"') && str_contains($settingsSource, ':focus-within .gsk-help-tip'), 'settings architecture help is keyboard and assistive-technology accessible');

$GLOBALS['contract_settings'] = [GSK_CONSENT_MODE_KEY => 'choices'];
$check(gsk_consent_mode($pdo) === 'choices', 'global category consent mode is accepted');
$GLOBALS['contract_settings'][GSK_CONSENT_MODE_KEY] = 'regional';
$check(gsk_consent_mode($pdo) === 'choices', 'legacy regional mode migrates safely to global choices');
$GLOBALS['contract_settings'][GSK_CONSENT_MODE_KEY] = 'strict';
$check(gsk_consent_mode($pdo) === 'choices', 'legacy strict mode migrates safely to global choices');
$GLOBALS['contract_settings'][GSK_CONSENT_MODE_KEY] = 'invalid';
$check(gsk_consent_mode($pdo) === 'choices', 'unknown nonempty consent modes fail safely to category choices');

$GLOBALS['contract_settings'] = [
    GSK_MEASUREMENT_ID_KEY => 'G-TEST',
    GSK_GTM_ID_KEY => 'GTM-TEST',
    GSK_ADSENSE_CLIENT_KEY => 'ca-pub-123',
    GSK_CONSENT_MODE_KEY => 'choices',
    GSK_PRIVACY_URL_KEY => '/privacy/',
];
$head = $render('jy_head');
$footer = $render('jy_footer');
$check(str_contains($head, "gtag('consent','default',denied)") && str_contains($head, "key='jy_metrics_consent_v2'") && str_contains($head, "legacyKey='jy_metrics_consent_v1'"), 'category consent defaults optional storage to denied and migrates schema 1');
$check(str_contains($head, 'navigator.globalPrivacyControl===true') && str_contains($head, "advertising:next.advertising===true&&!gpc"), 'Global Privacy Control cannot be overridden by an advertising choice');
$check(str_contains($head, 'if(prefs.analytics&&!loaded.analytics)') && str_contains($head, 'if(prefs.advertising&&!loaded.advertising)'), 'Analytics and Advertising load through separate category gates');
$check(str_contains($head, '"privacyUrl":"/privacy/"') && !str_contains($head, 'privacyPageLinked') && str_contains($head, 'if(c.privacyUrl)') && str_contains($head, 'reopenOnCurrentPage:!localPrivacyPage||onPrivacyPage'), 'same-site privacy URLs scope the persistent reopen control without tenant-specific confirmation');
$GLOBALS['contract_settings'][GSK_PRIVACY_URL_KEY] = '//example.com/privacy';
$invalidPrivacyHead = $render('jy_head');
$GLOBALS['contract_settings'][GSK_PRIVACY_URL_KEY] = '/privacy/';
$check(str_contains($invalidPrivacyHead, '"privacyUrl":""'), 'invalid persisted privacy URLs retain the global withdrawal fallback');
$check(str_contains($head, 'localPrivacyPage=privacyTarget.origin===location.origin'), 'external privacy URLs retain the global withdrawal fallback');
$check(str_contains($head, 'sameQuery=!privacyTarget.search||privacyTarget.search===location.search') && str_contains($head, 'sameHash=!privacyTarget.hash||privacyTarget.hash===location.hash'), 'configured query strings and fragments participate in policy-page matching');
$check(str_contains($head, "addEventListener('storage'") && str_contains($head, 'adoptPreferences(incoming'), 'preference changes synchronize across open tabs');
$check(str_contains($head, 'Number.isFinite(savedAt)') && str_contains($head, 'savedAt<=now+300000'), 'stored consent requires a bounded finite timestamp');
$check(str_contains($head, 'savedAt:Number(legacy.savedAt)') && str_contains($head, 'savedAt:saved.savedAt'), 'automatic migration and GPC correction preserve the original consent timestamp');
$check(str_contains($head, 'clearCookies(!prefs.analytics,!prefs.advertising)'), 'denied and expired categories clear known identifiers during startup');
$check(str_contains($head, "name==='_ga'||name.indexOf('_ga_')===0") && str_contains($head, "name.indexOf('_gac')===0") && str_contains($head, "name.indexOf('__eoi')===0"), 'withdrawal separates known browser-readable analytics and advertising identifiers');
$check(!str_contains($footer, '<script async src=') && !str_contains($footer, '<noscript>') && str_contains($footer, 'data-jym-manage') && str_contains($footer, 'data-jym-close'), 'category mode renders Accept, Manage, and rejecting close controls without eager external tags');
$check(str_contains($footer, 'data-jym-analytics') && str_contains($footer, 'data-jym-advertising') && str_contains($footer, 'data-jym-reject-all'), 'preference panel exposes category controls and Reject all');
$check(str_contains($footer, 'open.hidden=!api.reopenOnCurrentPage'), 'footer behavior hides the floating reopen control away from a configured local policy page');
$check(str_contains($footer, 'restoreFocus=banner.contains(document.activeElement)') && str_contains($footer, 'target.focus({preventScroll:true})'), 'a decision synchronized from another tab dismisses the initial prompt without stranding keyboard focus');
$check(str_contains($footer, 'document.querySelector("main")||document.body'), 'hidden banner actions return keyboard focus to visible page content');
$check(str_contains($footer, 'role="region"') && str_contains($footer, 'Privacy policy'), 'consent UI exposes accessible semantics and the configured policy link');

$GLOBALS['contract_settings'][GSK_MEASUREMENT_ID_KEY] = '</script><script>alert(1)</script>';
$head = $render('jy_head');
$check(!str_contains($head, '</script><script>alert(1)</script>') && str_contains($head, '\\u003C/script\\u003E'), 'inline tracking configuration hex-escapes markup');

$GLOBALS['contract_settings'][GSK_CONSENT_MODE_KEY] = 'off';
$head = $render('jy_head');
$footer = $render('jy_footer');
$check(!str_contains($footer, 'id="jym-consent"') && str_contains($footer, '<noscript>') && str_contains($footer, '<script async src='), 'disabled mode preserves legacy snippet behavior without consent UI');

echo $failures === 0 ? 'RESULT: ALL PASS' . PHP_EOL : "RESULT: {$failures} FAILURE(S)" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
