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

$check(gsk_valid_privacy_url('') && gsk_valid_privacy_url('/privacy/') && gsk_valid_privacy_url('https://example.com/privacy'), 'safe privacy URLs are accepted');
$check(!gsk_valid_privacy_url('//example.com') && !gsk_valid_privacy_url('/\\example.com') && !gsk_valid_privacy_url('javascript:alert(1)') && !gsk_valid_privacy_url('/bad path'), 'ambiguous and executable privacy URLs are rejected');

$GLOBALS['contract_settings'] = [GSK_CONSENT_MODE_KEY => 'regional', GSK_CONSENT_REGION_SOURCE_KEY => 'cloudflare'];
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';
$_SERVER['HTTP_X_VERCEL_IP_COUNTRY'] = 'US';
$check(gsk_consent_country_code($pdo) === 'DE' && gsk_consent_is_strict($pdo), 'only the selected provider determines an EU strict response');
$strictCountries = [
    'AT', 'AX', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR',
    'GF', 'GP', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MF', 'MQ', 'MT', 'NL',
    'NO', 'PL', 'PT', 'RE', 'RO', 'SE', 'SI', 'SK', 'YT', 'GB',
];
$allStrict = true;
foreach ($strictCountries as $country) {
    $_SERVER['HTTP_CF_IPCOUNTRY'] = $country;
    if (!gsk_consent_is_strict($pdo)) $allStrict = false;
}
$check($allStrict, 'EU, EEA, UK, and separately coded EU outermost regions receive strict choices');
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
$check(!gsk_consent_is_strict($pdo), 'a known non-EU country receives the simple regional banner');
unset($_SERVER['HTTP_CF_IPCOUNTRY']);
$check(gsk_consent_is_strict($pdo), 'a missing regional header fails safely to strict');
$GLOBALS['contract_settings'][GSK_CONSENT_REGION_SOURCE_KEY] = 'none';
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
$check(gsk_consent_country_code($pdo) === '' && gsk_consent_is_strict($pdo), 'untrusted country headers are ignored without an explicit provider');

$GLOBALS['contract_settings'] = [
    GSK_MEASUREMENT_ID_KEY => 'G-TEST',
    GSK_GTM_ID_KEY => 'GTM-TEST',
    GSK_ADSENSE_CLIENT_KEY => 'ca-pub-123',
    GSK_CONSENT_MODE_KEY => 'strict',
    GSK_CONSENT_REGION_SOURCE_KEY => 'none',
    GSK_PRIVACY_URL_KEY => '/privacy/',
];
$head = $render('jy_head');
$footer = $render('jy_footer');
$check(str_contains($head, "gtag('consent','default',denied)") && str_contains($head, "state!=='accepted'"), 'consent mode defaults optional storage to denied and gates loading');
$check(!str_contains($footer, '<script async src=') && !str_contains($footer, '<noscript>') && str_contains($footer, 'data-jym-reject'), 'strict mode renders choices without eager external tags');
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
