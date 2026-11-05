<?php
declare(strict_types=1);

define('PLUGIN_SYSTEM_LOADED', true);
define('ADMIN_BASE_PATH', '/dashboard');
$GLOBALS['gsk_test_actions'] = [];
$GLOBALS['gsk_test_filters'] = [];
$GLOBALS['gsk_test_settings'] = [];
$GLOBALS['gsk_test_locale'] = 'en';
$GLOBALS['gsk_test_collection_excluded'] = [];
$GLOBALS['gsk_test_collection_translations'] = [];
$GLOBALS['gsk_test_permalink_structure'] = '/%slug%/';

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['gsk_test_actions'][$hook][$priority][] = $callback;
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['gsk_test_filters'][$hook][$priority][] = $callback;
}

function settings_get(PDO $pdo, string $key, mixed $default = ''): mixed
{
    return $GLOBALS['gsk_test_settings'][$key] ?? $default;
}

function __(string $source, string $scope = 'default'): string
{
    return $source;
}

function user_can(PDO $pdo, int $actorId, string $permission): bool
{
    return $actorId === 7 && in_array($permission, ['plugin.jy-metrics.reports.read', 'plugin.jy-metrics.integrations.manage', 'core.sidebar.manage'], true);
}

function get_post_permalink(array $post): string
{
    $prefix = $GLOBALS['gsk_test_locale'] === 'en' ? '' : '/' . $GLOBALS['gsk_test_locale'];
    return $prefix . '/' . rawurlencode((string)$post['slug']) . '/';
}

function get_page_permalink(array $post): string
{
    return '/pages/' . rawurlencode((string)$post['slug']) . '/';
}

function get_locale(): string
{
    return $GLOBALS['gsk_test_locale'];
}

function content_default_locale(): string
{
    return 'en';
}

function get_permalink_structure(PDO $pdo, string $type = 'post'): string
{
    return $GLOBALS['gsk_test_permalink_structure'];
}

function collection_query_clauses(array $clauses, array $context = []): array
{
    $GLOBALS['gsk_test_collection_query_contexts'][] = $context;
    $excluded = $GLOBALS['gsk_test_collection_excluded'][$GLOBALS['gsk_test_locale']] ?? null;
    if (is_int($excluded)) {
        $clauses['where'][] = 'p.id <> :test_excluded_post';
        $clauses['params'][':test_excluded_post'] = $excluded;
    }
    return $clauses;
}

function collection_filter_rows(array $rows, array $context = []): array
{
    $GLOBALS['gsk_test_collection_row_contexts'][] = $context;
    $translations = $GLOBALS['gsk_test_collection_translations'][$GLOBALS['gsk_test_locale']] ?? [];
    if ($GLOBALS['gsk_test_locale'] !== 'en') {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => isset($translations[(int)($row['id'] ?? 0)])));
    }
    foreach ($rows as &$row) {
        $translation = $translations[(int)($row['id'] ?? 0)] ?? null;
        if (!is_array($translation)) continue;
        $row['title'] = $translation['title'];
        $row['slug'] = $translation['slug'];
        $row['ct_locale'] = $GLOBALS['gsk_test_locale'];
        $row['ct_translated_slug'] = $translation['slug'];
    }
    unset($row);
    return $rows;
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['pdo'] = $pdo;
require dirname(__DIR__) . '/plugin.php';

$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) $failures++;
};
$callbacks = static function (array $registry, string $hook): array {
    $callbacks = [];
    foreach ($registry[$hook] ?? [] as $priorityCallbacks) $callbacks = array_merge($callbacks, $priorityCallbacks);
    return $callbacks;
};

$defaults = gsk_content_view_settings($pdo);
$check($defaults === ['show_dashboard' => false, 'show_public' => false, 'popular_period' => 30, 'popular_limit' => 5], 'content view visibility defaults disabled and widget defaults are bounded');
$GLOBALS['gsk_test_settings'][GSK_POPULAR_POSTS_PERIOD_KEY] = 'invalid';
$GLOBALS['gsk_test_settings'][GSK_POPULAR_POSTS_LIMIT_KEY] = '99';
$check(gsk_content_view_settings($pdo)['popular_period'] === 30 && gsk_content_view_settings($pdo)['popular_limit'] === 5, 'invalid persisted widget values normalize to defaults');
$GLOBALS['gsk_test_settings'] = [];

$validContext = [
    'schema' => 1, 'route' => 'single.post', 'status' => 'published', 'is_public' => true,
    'canonical_url' => '/article/', 'page' => 1, 'request_method' => 'GET',
];
$check(gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => 42], $validContext) === ['kind' => 'post', 'type' => 'article', 'id' => 42, 'locale' => 'en'], 'published schema-1 GET Article payload includes its bounded representation locale');
$check(gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => 42], [...$validContext, 'request_method' => 'HEAD']) === null
    && gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => '42'], $validContext) === null
    && gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => 42], [...$validContext, 'status' => 'draft']) === null,
    'HEAD, malformed, and unpublished lifecycle payloads are ignored');
$listener = $callbacks($GLOBALS['gsk_test_actions'], 'frontend_entity_viewed')[0] ?? null;
$check(is_callable($listener), 'frontend entity listener is registered once');
$listener(['kind' => 'post', 'type' => 'article', 'id' => 42], $validContext, $pdo);
$check(($GLOBALS['gsk_current_public_view_entity']['id'] ?? 0) === 42
    && gsk_ga4_content_tracking_context() === ['contentId' => 'article:42', 'contentType' => 'article', 'contentLocale' => 'en'],
    'frontend lifecycle exposes stable GA4 content identity without writing a local view counter');

$check(gsk_ga4_content_period_range(7) === ['start' => '7daysAgo', 'end' => 'yesterday']
    && gsk_ga4_content_period_range('all') === ['start' => '2020-10-14', 'end' => 'yesterday']
    && gsk_ga4_content_period_range(8) === null,
    'GA4 content periods use bounded complete-day ranges');
$check(gsk_normalize_page_path('https://example.test/id/sample/?utm=x') === '/id/sample'
    && gsk_normalize_page_path('/id//sample/') === '/id/sample',
    'GA4 and CMS page paths normalize to the same query-free identity');
$request = gsk_ga4_content_request(7, 'example.test');
$check(($request['dimensions'] ?? null) === [['name' => 'hostName'], ['name' => 'pagePath']]
    && ($request['metrics'] ?? null) === [['name' => 'screenPageViews']]
    && ($request['dimensionFilter']['filter']['fieldName'] ?? '') === 'hostName'
    && ($request['returnPropertyQuota'] ?? false) === true,
    'GA4 cache request asks for hostname-scoped page views and quota telemetry');

$pdo->exec('CREATE TABLE google_site_kit_cache (id INTEGER PRIMARY KEY AUTOINCREMENT, cache_key TEXT NOT NULL UNIQUE, data TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec('CREATE TABLE sidebar_zone_items (id INTEGER PRIMARY KEY, type TEXT, config TEXT, active INTEGER)');
$pdo->exec("INSERT INTO sidebar_zone_items VALUES (1, 'jy_metrics_popular_posts', '{\"period\":\"7\"}', 1)");
$pdo->exec('CREATE TABLE content_routes (post_id INTEGER, path TEXT, locale TEXT)');
$pdo->exec("INSERT INTO content_routes VALUES (6, 'english-leader', ''), (6, 'former-english-leader', ''), (2, 'popular', '')");
$pdo->exec('CREATE TABLE post_translations (post_id INTEGER, locale TEXT, title TEXT, slug TEXT, status TEXT)');
$pdo->exec("INSERT INTO post_translations VALUES (2, 'id', 'Populer ID', 'populer-id', 'published'), (6, 'id', 'Pemimpin ID', 'pemimpin-id', 'published'), (2, 'de', 'Beliebt DE', 'beliebt-de', 'published')");
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, created_at TEXT, type TEXT, status TEXT, is_deleted INTEGER)');
$pdo->exec("INSERT INTO posts VALUES (2, '<Popular>', 'popular', '2026-10-29', 'article', 'published', 0)");
$pdo->exec("INSERT INTO posts VALUES (3, 'Draft', 'draft', '2026-10-29', 'article', 'draft', 0)");
$pdo->exec("INSERT INTO posts VALUES (5, 'Unavailable ID', 'unavailable-id', '2026-10-29', 'article', 'published', 0)");
$pdo->exec("INSERT INTO posts VALUES (6, 'English leader', 'english-leader', '2026-10-29', 'article', 'published', 0)");

$GLOBALS['gsk_test_settings'] = [
    GSK_GA4_PROPERTY_ID_KEY => '123456',
    GSK_SITE_URL_KEY => 'https://example.test/',
    'site_host' => 'example.test',
    GSK_ACCESS_TOKEN_KEY => 'test-token',
    GSK_SHOW_DASHBOARD_VIEWS_KEY => '1',
    GSK_SHOW_PUBLIC_VIEWS_KEY => '1',
];
$check(gsk_ga4_content_host($pdo) === 'example.test' && gsk_ga4_content_request(7, '') === null,
    'content reports require one exact Core site hostname and never fall back to an unscoped GA4 property');
$GLOBALS['gsk_test_settings']['site_host'] = 'www.example.test';
$GLOBALS['gsk_test_settings'][GSK_SITE_URL_KEY] = 'sc-domain:example.test';
$check(gsk_ga4_content_host($pdo) === 'www.example.test', 'Search Console domain properties do not override the exact Core hostname');
$GLOBALS['gsk_test_settings']['site_host'] = 'example.test';
$GLOBALS['gsk_test_settings'][GSK_SITE_URL_KEY] = 'https://example.test/';
$check(gsk_ga4_content_required_periods($pdo) === [7, 'all'], 'refresh discovery includes active widget periods and all-time only for enabled counts');

$transportCalls = [];
$transport = static function (string $url, array $headers, string $body) use (&$transportCalls): array {
    $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $transportCalls[] = [$url, $headers, $request];
    $rows = (int)($request['offset'] ?? 0) > 0
        ? [['dimensionValues' => [['value' => 'example.test'], ['value' => '/former-english-leader/']], 'metricValues' => [['value' => '3']]]]
        : [
            ['dimensionValues' => [['value' => 'example.test'], ['value' => '/english-leader/']], 'metricValues' => [['value' => '20']]],
            ['dimensionValues' => [['value' => 'example.test'], ['value' => '/popular/']], 'metricValues' => [['value' => '9']]],
            ['dimensionValues' => [['value' => 'example.test'], ['value' => '/id/populer-id/']], 'metricValues' => [['value' => '14']]],
            ['dimensionValues' => [['value' => 'example.test'], ['value' => '/id/pemimpin-id/']], 'metricValues' => [['value' => '1']]],
            ['dimensionValues' => [['value' => 'example.test'], ['value' => '/de/beliebt-de/']], 'metricValues' => [['value' => '4']]],
            ['dimensionValues' => [['value' => 'example.test'], ['value' => '/pages/about/']], 'metricValues' => [['value' => '8']]],
            ['dimensionValues' => [['value' => 'other.test'], ['value' => '/english-leader/']], 'metricValues' => [['value' => '999']]],
        ];
    return [
        'ok' => true,
        'code' => 200,
        'data' => [
            'rows' => $rows,
            'rowCount' => 8,
            'propertyQuota' => ['tokensPerDay' => ['consumed' => 2, 'remaining' => 199998]],
        ],
    ];
};
$refresh = gsk_refresh_ga4_content_views($pdo, [7, 'all'], true, $transport);
$check(($refresh['ok'] ?? false) === true && ($refresh['refreshed'] ?? null) === [7, 'all'] && count($transportCalls) === 4
    && ($transportCalls[1][2]['offset'] ?? 0) === 7,
    'forced refresh paginates and stores one complete GA4 report for each requested cache period');
$check(str_ends_with($transportCalls[0][0], '/properties/123456:runReport')
    && ($transportCalls[0][1][0] ?? '') === 'Authorization: Bearer test-token',
    'GA4 refresh uses the configured property and OAuth token');
$weeklySnapshot = gsk_ga4_content_snapshot($pdo, 7);
$check(($weeklySnapshot['source'] ?? '') === 'google-analytics'
    && ($weeklySnapshot['views']['/english-leader'] ?? 0) === 20
    && ($weeklySnapshot['views']['/former-english-leader'] ?? 0) === 3
    && !isset($weeklySnapshot['views']['/unavailable-host']),
    'database snapshot stores normalized views for only the configured hostname');
$cachedRefresh = gsk_refresh_ga4_content_views($pdo, [7, 'all'], false, static function (): array {
    throw new RuntimeException('fresh cache must not call transport');
});
$check(($cachedRefresh['ok'] ?? false) === true && ($cachedRefresh['cached'] ?? null) === [7, 'all'], 'fresh cache prevents another Analytics request');
$weeklyCacheKey = gsk_ga4_content_cache_key('properties/123456', 'example.test', 7);
$stmt = $pdo->prepare("UPDATE google_site_kit_cache SET created_at = datetime('now', '-2 hours') WHERE cache_key = ?");
$stmt->execute([$weeklyCacheKey]);
$GLOBALS['gsk_ga4_content_snapshots'] = [];
$failedRefresh = gsk_refresh_ga4_content_views($pdo, [7], true, static fn(): array => [
    'ok' => false,
    'code' => 503,
    'data' => ['error' => ['message' => 'temporary provider failure']],
]);
$throttledRefresh = gsk_refresh_ga4_content_views($pdo, [7], false, static function (): array {
    throw new RuntimeException('error throttle must not call transport');
});
$check(($failedRefresh['ok'] ?? true) === false
    && ($throttledRefresh['ok'] ?? true) === false
    && str_contains((string)($throttledRefresh['error'] ?? ''), 'temporarily throttled')
    && (gsk_ga4_content_snapshot($pdo, 7)['views']['/english-leader'] ?? 0) === 20,
    'provider errors are throttled for 15 minutes while the 24-hour stale snapshot remains readable');

$GLOBALS['gsk_test_collection_excluded']['id'] = 5;
$GLOBALS['gsk_test_collection_translations'] = [
    'id' => [2 => ['title' => 'Populer ID', 'slug' => 'populer-id'], 6 => ['title' => 'Pemimpin ID', 'slug' => 'pemimpin-id']],
    'de' => [2 => ['title' => 'Beliebt DE', 'slug' => 'beliebt-de']],
];
$GLOBALS['gsk_test_locale'] = 'en';
unset($GLOBALS['ct_request_locale']);
$englishRows = gsk_popular_posts($pdo, 7, 1);
$GLOBALS['gsk_test_locale'] = 'id';
$GLOBALS['ct_request_locale'] = 'id';
$indonesianRows = gsk_popular_posts($pdo, 7, 1);
$indonesianWidget = gsk_render_popular_posts_widget($pdo, ['title' => '<Most read>', 'period' => '7', 'limit' => 1]);
$GLOBALS['gsk_test_locale'] = 'de';
$GLOBALS['ct_request_locale'] = 'de';
$germanRows = gsk_popular_posts($pdo, 7, 1);
$check((int)($englishRows[0]['id'] ?? 0) === 6 && (int)($englishRows[0]['view_count'] ?? 0) === 23
    && (int)($indonesianRows[0]['id'] ?? 0) === 2 && (int)($indonesianRows[0]['view_count'] ?? 0) === 14
    && (int)($germanRows[0]['id'] ?? 0) === 2 && (int)($germanRows[0]['view_count'] ?? 0) === 4,
    'Popular Posts ranks localized canonical and historical alias paths from the cached GA4 page views');
$check(str_contains($indonesianWidget, '>Populer ID<') && str_contains($indonesianWidget, 'href="/id/populer-id/"')
    && str_contains($indonesianWidget, '14 views') && !str_contains($indonesianWidget, 'Unavailable ID')
    && ($GLOBALS['gsk_test_collection_query_contexts'][0]['required_translation_fields'] ?? null) === ['title', 'slug'],
    'Popular Posts applies publication constraints and localized row overlays before GA4 path matching');

$GLOBALS['gsk_test_locale'] = 'en';
unset($GLOBALS['ct_request_locale']);
$candidateScores = gsk_ga4_content_candidate_scores($pdo, ['views' => ['/popular' => 9, '/draft' => 500]]);
$check(($candidateScores[2] ?? 0) === 9 && !isset($candidateScores[3]),
    'candidate scoring deduplicates route and slug identities and excludes unpublished content before ranking');

$pdo->exec("INSERT INTO posts VALUES (7, 'Structured URL', 'structured-url', '2026-10-29', 'article', 'published', 0)");
$GLOBALS['gsk_test_permalink_structure'] = '/%slug%/%year%/';
$structuredScores = gsk_ga4_content_candidate_scores($pdo, ['views' => ['/structured-url/2026' => 11]]);
$GLOBALS['gsk_test_permalink_structure'] = '/%slug%/';
$check(($structuredScores[7] ?? 0) === 11, 'legacy fallback discovery supports permalink structures where the slug is not the final segment');

$tieViews = [];
for ($id = 120; $id >= 100; $id--) {
    $pdo->exec("INSERT INTO posts VALUES ({$id}, 'Tie {$id}', 'tie-{$id}', '2026-10-29', 'article', 'published', 0)");
    $pdo->exec("INSERT INTO content_routes VALUES ({$id}, 'tie-{$id}', '')");
    $tieViews['/tie-' . $id] = 10;
}
$tieSnapshot = ['source' => 'google-analytics', 'views' => $tieViews];
gsk_cache_set($pdo, gsk_ga4_content_cache_key('properties/123456', 'example.test', 30), $tieSnapshot);
$GLOBALS['gsk_ga4_content_snapshots'] = [];
$GLOBALS['gsk_popular_posts'] = [];
$tieRows = gsk_popular_posts($pdo, 30, 5);
$check(array_map(static fn(array $row): int => (int)$row['id'], $tieRows) === [100, 101, 102, 103, 104],
    'Popular Posts evaluates equal-score candidates across chunks before applying the ascending-ID tie-break');

$GLOBALS['gsk_test_locale'] = 'id';
$GLOBALS['ct_request_locale'] = 'id';
$GLOBALS['gsk_current_public_view_entity'] = ['kind' => 'post', 'type' => 'article', 'id' => 2, 'locale' => 'id'];
$rendered = gsk_public_view_count('<p>Body</p>', ['id' => 2, 'type' => 'article', 'slug' => 'populer-id', 'ct_locale' => 'id', 'ct_translated_slug' => 'populer-id']);
$check(str_contains($rendered, '14 views') && str_contains($rendered, 'class="jym-public-views"'), 'public view count reads the locale-specific all-time GA4 cache');
$GLOBALS['gsk_test_locale'] = 'en';
unset($GLOBALS['ct_request_locale']);
$listSelect = gsk_post_list_view_select('', '', ['schema' => 1, 'type' => 'article', 'actor_id' => 7]);
$actions = gsk_admin_content_view_action([], [
    'id' => 6,
    'slug' => 'english-leader',
    'gsk_ga4_route_paths' => '["english-leader","former-english-leader"]',
], ['schema' => 1, 'content_type' => 'article', 'actor_id' => 7], $pdo);
$pageActions = gsk_admin_content_view_action([], [
    'id' => 9,
    'slug' => 'about',
    'gsk_ga4_route_paths' => '[]',
], ['schema' => 1, 'content_type' => 'page', 'actor_id' => 7], $pdo);
$check(str_contains((string)($actions[0]['label'] ?? ''), '23 views') && ($actions[0]['icon'] ?? '') === 'eye'
    && str_contains($listSelect, 'AS gsk_ga4_route_paths')
    && str_contains((string)($pageActions[0]['label'] ?? ''), '8 views'),
    'dashboard list rows preload route aliases for cached counts and Page counts use the Page permalink resolver');

$widgetTypes = [];
foreach ($callbacks($GLOBALS['gsk_test_filters'], 'sidebar_widget_types') as $callback) $widgetTypes = $callback($widgetTypes);
$check(isset($widgetTypes['jy_metrics_popular_posts'])
    && ($widgetTypes['jy_metrics_popular_posts']['default_config'] ?? null) === ['title' => 'Popular Posts', 'period' => '30', 'limit' => 5]
    && array_keys($widgetTypes['jy_metrics_popular_posts']['config_fields']['period']['options'] ?? []) === [1, 7, 30, 365]
    && ($widgetTypes['jy_metrics_popular_posts']['config_fields']['limit']['max'] ?? null) === 20,
    'Popular Posts exposes daily, weekly, monthly, yearly, and item-limit controls to Core Sidebar Settings');

$root = dirname(__DIR__);
$pluginSource = (string)file_get_contents($root . '/plugin.php');
$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$settingsSource = (string)file_get_contents($root . '/admin/settings.php');
$refreshTool = (string)file_get_contents($root . '/tools/refresh-content-views.php');
$check(($manifest['version'] ?? '') === '2.13.0' && ($manifest['requires']['jyavani'] ?? '') === '>=2.3.195', 'manifest uses the reviewed minor-release identity and Core requirement');
$check(!str_contains($pluginSource, 'function gsk_record_content_view')
    && !str_contains($pluginSource, 'ON DUPLICATE KEY UPDATE `entity_type`')
    && str_contains($pluginSource, "gsk_ga4_content_v1_")
    && str_contains($pluginSource, "user_can(\$pdo, \$actorId, 'core.sidebar.manage')")
    && str_contains($pluginSource, "user_can(\$pdo, \$actorId, 'plugin.jy-metrics.reports.read')"),
    'runtime no longer increments first-party views and refreshes GA4 cache only on relevant authorized dashboard routes');
$check(str_contains($pluginSource, "GSK_I18N_VERSION_KEY) === '15'")
    && str_contains($pluginSource, "'Weekly (7 days)' => 'Mingguan (7 hari)'")
    && str_contains($pluginSource, 'p.content_group=c.contentLocale'),
    'weekly localization and stable content identity are included in GA4 tracking');
$check(str_contains($settingsSource, 'Google Analytics cache · visibility %s')
    && !str_contains($settingsSource, 'always collects anonymous daily totals')
    && str_contains($refreshTool, 'gsk_refresh_ga4_content_views'),
    'settings and CLI tooling document and operate the database-backed GA4 cache');

echo $failures === 0 ? 'RESULT: ALL PASS' . PHP_EOL : "RESULT: {$failures} FAILURE(S)" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
