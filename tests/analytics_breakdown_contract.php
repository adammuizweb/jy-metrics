<?php
declare(strict_types=1);

define('PLUGIN_SYSTEM_LOADED', true);
$GLOBALS['breakdown_contract_hooks'] = [];

function add_action(string $hook, callable $callback): void
{
    $GLOBALS['breakdown_contract_hooks'][$hook][] = $callback;
}

function settings_get(PDO $pdo, string $key, mixed $default = ''): mixed
{
    return $default;
}

function __(string $source, string $scope = 'default'): string
{
    return $source;
}

require dirname(__DIR__) . '/plugin.php';

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) $failures++;
};

$channel = gsk_analytics_breakdown_config('channel');
$device = gsk_analytics_breakdown_config('device');
$brand = gsk_analytics_breakdown_config('brand');
$check($channel === ['ga_dimension' => 'sessionDefaultChannelGroup', 'dimension_filter' => null], 'channel alias maps to the GA4 default channel dimension');
$check($device === ['ga_dimension' => 'deviceCategory', 'dimension_filter' => null], 'device alias maps to the GA4 device category dimension');
$check(($brand['ga_dimension'] ?? null) === 'mobileDeviceBranding', 'brand alias maps to the GA4 mobile device branding dimension');
$check(($brand['dimension_filter']['filter']['fieldName'] ?? null) === 'deviceCategory'
    && ($brand['dimension_filter']['filter']['inListFilter']['values'] ?? null) === ['mobile', 'tablet'],
    'brand reports include only mobile and tablet sessions');
$check(gsk_analytics_breakdown_config('browser-supplied-dimension') === null, 'unknown dimension aliases fail closed');

$apiSource = (string)file_get_contents(dirname(__DIR__) . '/api/analytics.php');
$adminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/index.php');
$pluginSource = (string)file_get_contents(dirname(__DIR__) . '/plugin.php');

$check(str_contains($apiSource, "if (\$action === 'breakdown')")
    && str_contains($apiSource, 'gsk_analytics_breakdown_config($dimension)')
    && str_contains($apiSource, "'metrics'    => [['name' => 'sessions']]")
    && str_contains($apiSource, "'rows' => \$rows"),
    'Analytics endpoint exposes one session-based breakdown response contract');
$check(str_contains($apiSource, "gsk_ga4_breakdown_v1_")
    && str_contains($apiSource, "'dimensionFilter'"),
    'breakdown responses are cached by selected dimension and apply server filters');
$check(str_contains($adminSource, 'data-gsk-lazy="breakdown"')
    && substr_count($adminSource, 'data-breakdown-dimension=') === 3
    && substr_count($adminSource, 'role="tabpanel"') === 3,
    'dashboard renders one lazy card with three accessible breakdown tabs and panels');
$check(str_contains($adminSource, "&action=breakdown")
    && str_contains($adminSource, "breakdownLoaded[dimension]")
    && str_contains($adminSource, "if (dimension === 'brand') breakdownPages.brand = 1"),
    'tabs load their own breakdown data and reset brand pagination for new results');
$check(str_contains($adminSource, "event.key === 'ArrowRight'")
    && str_contains($adminSource, "event.key === 'Home'")
    && str_contains($adminSource, "tab.setAttribute('aria-selected'"),
    'breakdown tabs support keyboard navigation and synchronized ARIA state');
$check(str_contains($adminSource, "var pageSize = 7")
    && str_contains($adminSource, "dimension === 'brand' && data.rows.length > pageSize")
    && str_contains($adminSource, 'gsk-breakdown-value'),
    'brand lists paginate compactly while every row shows count and percentage');
$check(str_contains($pluginSource, "GSK_I18N_VERSION_KEY) === '10'")
    && str_contains($pluginSource, "'Traffic breakdown' => 'Rincian traffic'")
    && str_contains($pluginSource, "'Mobile and tablet sessions grouped by device brand.'"),
    'traffic breakdown interface strings are registered for Indonesian localization');

echo $failures === 0 ? 'RESULT: ALL PASS' . PHP_EOL : "RESULT: {$failures} FAILURE(S)" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
