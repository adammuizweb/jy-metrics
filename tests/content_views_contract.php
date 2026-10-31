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
    return $actorId === 7 && $permission === 'plugin.jy-metrics.reports.read';
}

function get_post_permalink(array $post): string
{
    return '/canonical/' . rawurlencode((string)$post['slug']) . '/';
}

function get_locale(): string
{
    return $GLOBALS['gsk_test_locale'];
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
    foreach ($rows as &$row) {
        $translation = $translations[(int)($row['id'] ?? 0)] ?? null;
        if (!is_array($translation)) continue;
        $row['title'] = $translation['title'];
        $row['slug'] = $translation['slug'];
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
$check(gsk_normalize_content_view_input(['popular_posts_period' => '7', 'popular_posts_limit' => '20', 'show_public_views' => '1']) === [
    'show_dashboard' => '0', 'show_public' => '1',
], 'content view settings normalize visibility without owning Sidebar widget configuration');

$validContext = [
    'schema' => 1, 'route' => 'single.post', 'status' => 'published', 'is_public' => true,
    'canonical_url' => '/article/', 'page' => 1, 'request_method' => 'GET',
];
$check(gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => 42], $validContext) === ['kind' => 'post', 'type' => 'article', 'id' => 42, 'locale' => 'en'], 'published schema-1 GET Article payload includes its bounded representation locale');
$check(gsk_view_payload(['kind' => 'category', 'type' => 'category', 'id' => 5], [...$validContext, 'route' => 'list.category']) === ['kind' => 'category', 'type' => 'category', 'id' => 5, 'locale' => 'en'], 'resolved Category payload includes its bounded representation locale');
$check(gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => 42], [...$validContext, 'request_method' => 'HEAD']) === null
    && gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => '42'], $validContext) === null
    && gsk_view_payload(['kind' => 'post', 'type' => 'article', 'id' => 42], [...$validContext, 'status' => 'draft']) === null,
    'HEAD, malformed, and unpublished lifecycle payloads are ignored');
$check(count($callbacks($GLOBALS['gsk_test_actions'], 'frontend_entity_viewed')) === 1, 'frontend entity view listener is registered once');
$listener = $callbacks($GLOBALS['gsk_test_actions'], 'frontend_entity_viewed')[0];
$listener('malformed', null, $pdo);
$check(!isset($GLOBALS['gsk_content_views_recorded']), 'registered lifecycle listener ignores non-array payloads without recording');
$GLOBALS['gsk_test_settings'][GSK_SHOW_DASHBOARD_VIEWS_KEY] = '1';
$check(gsk_post_list_view_select('', '', ['schema' => 1, 'type' => 'article', 'actor_id' => 7]) === ''
    && gsk_admin_content_view_action([], [], ['schema' => 1, 'content_type' => 'article', 'actor_id' => 7], $pdo) === [],
    'missing content-view schema fails closed without breaking dashboard content lists');
$GLOBALS['gsk_test_settings'] = [];

$pdo->exec('CREATE TABLE jy_metrics_content_views_daily (entity_kind TEXT, entity_id INTEGER, view_date_utc TEXT, entity_type TEXT, representation_locale TEXT, view_count INTEGER)');
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 42, '2026-10-30', 'article', '', 1234)");
$GLOBALS['gsk_test_settings'][GSK_SHOW_PUBLIC_VIEWS_KEY] = '1';
$GLOBALS['gsk_current_public_view_entity'] = ['kind' => 'post', 'type' => 'article', 'id' => 42];
$rendered = gsk_public_view_count('<p>Body</p>', ['id' => 42, 'type' => 'article']);
$renderedAgain = gsk_public_view_count($rendered, ['id' => 42, 'type' => 'article']);
$check(substr_count($renderedAgain, 'class="jym-public-views"') === 1
    && str_contains($renderedAgain, '<svg') && str_contains($renderedAgain, '1,234 views'),
    'public Article rendering is accessible, uses an SVG eye, and remains idempotent');
$check(gsk_public_view_count('<p>Theme</p>', ['id' => 42, 'type' => 'theme']) === '<p>Theme</p>', 'public count does not render for Theme Content');

$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, created_at TEXT, type TEXT, status TEXT, is_deleted INTEGER)');
$pdo->exec("INSERT INTO posts VALUES (2, '<Popular>', 'popular', '2026-10-29', 'article', 'published', 0)");
$pdo->exec("INSERT INTO posts VALUES (3, 'Draft', 'draft', '2026-10-29', 'article', 'draft', 0)");
$pdo->exec("INSERT INTO posts VALUES (4, 'Older popular', 'older', '2026-10-20', 'article', 'published', 0)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 2, '" . gmdate('Y-m-d') . "', 'article', '', 9)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 3, '" . gmdate('Y-m-d') . "', 'article', '', 99)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 4, '" . gmdate('Y-m-d', time() - 432000) . "', 'article', '', 99)");
$GLOBALS['gsk_test_settings'][GSK_POPULAR_POSTS_PERIOD_KEY] = '7';
$GLOBALS['gsk_test_settings'][GSK_POPULAR_POSTS_LIMIT_KEY] = '20';
$widget = gsk_render_popular_posts_widget($pdo, ['title' => '<Most read>', 'period' => '1', 'limit' => 1]);
$check(str_contains($widget, 'w-box') && str_contains($widget, 'w-title') && str_contains($widget, 'w-recent-list')
    && str_contains($widget, 'w-recent-item') && str_contains($widget, 'w-recent-title') && str_contains($widget, 'w-recent-date')
    && str_contains($widget, 'w-recent-body') && str_contains($widget, 'jym-popular-rank')
    && str_contains($widget, '&lt;Most read&gt;') && str_contains($widget, '&lt;Popular&gt;')
    && !str_contains($widget, '>Draft<') && !str_contains($widget, '>Older popular<'),
    'Popular Posts widget uses per-instance daily period and limit, established classes, escaped markup, and published Articles only');
$pdo->exec("INSERT INTO posts VALUES (5, 'Unavailable ID', 'unavailable-id', '2026-10-29', 'article', 'published', 0)");
$pdo->exec("INSERT INTO posts VALUES (6, 'English leader', 'english-leader', '2026-10-29', 'article', 'published', 0)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 2, '" . gmdate('Y-m-d') . "', 'article', 'id', 5)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 5, '" . gmdate('Y-m-d') . "', 'article', 'id', 100)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 6, '" . gmdate('Y-m-d') . "', 'article', 'en', 20)");
$pdo->exec("INSERT INTO jy_metrics_content_views_daily VALUES ('post', 6, '" . gmdate('Y-m-d') . "', 'article', 'id', 1)");
$GLOBALS['gsk_test_collection_excluded']['id'] = 5;
$GLOBALS['gsk_test_collection_translations'] = [
    'id' => [2 => ['title' => 'Populer ID', 'slug' => 'populer-id'], 6 => ['title' => 'Pemimpin ID', 'slug' => 'pemimpin-id']],
    'de' => [2 => ['title' => 'Beliebt DE', 'slug' => 'beliebt-de']],
];
unset($GLOBALS['gsk_popular_posts']);
$GLOBALS['gsk_test_locale'] = 'en';
$englishRows = gsk_popular_posts($pdo, 1, 1);
$GLOBALS['gsk_test_locale'] = 'id';
$indonesianRows = gsk_popular_posts($pdo, 1, 1);
$indonesianWidget = gsk_render_popular_posts_widget($pdo, ['period' => '1', 'limit' => 1]);
$GLOBALS['gsk_test_locale'] = 'de';
$germanRows = gsk_popular_posts($pdo, 1, 1);
$check((int)($englishRows[0]['id'] ?? 0) === 6 && (int)($englishRows[0]['view_count'] ?? 0) === 20
    && (int)($indonesianRows[0]['id'] ?? 0) === 2 && (int)($indonesianRows[0]['view_count'] ?? 0) === 14
    && (int)($germanRows[0]['id'] ?? 0) === 2 && (int)($germanRows[0]['view_count'] ?? 0) === 9,
    'Popular Posts combines the historical baseline with only the current locale and isolates locale cache entries');
$check(str_contains($indonesianWidget, '>Populer ID<') && str_contains($indonesianWidget, 'href="/canonical/populer-id/"')
    && !str_contains($indonesianWidget, 'Unavailable ID')
    && ($GLOBALS['gsk_test_collection_query_contexts'][0]['scope'] ?? null) === 'article_list'
    && ($GLOBALS['gsk_test_collection_query_contexts'][0]['required_translation_fields'] ?? null) === ['title', 'slug'],
    'Popular Posts applies generic publication constraints before LIMIT and localized row overlays before rendering');
$GLOBALS['gsk_test_locale'] = 'en';
$widgetTypes = [];
foreach ($callbacks($GLOBALS['gsk_test_filters'], 'sidebar_widget_types') as $callback) $widgetTypes = $callback($widgetTypes);
$check(isset($widgetTypes['jy_metrics_popular_posts'])
    && ($widgetTypes['jy_metrics_popular_posts']['default_config'] ?? null) === ['title' => 'Popular Posts', 'period' => '30', 'limit' => 5]
    && array_keys($widgetTypes['jy_metrics_popular_posts']['config_fields']['period']['options'] ?? []) === [1, 30, 365]
    && ($widgetTypes['jy_metrics_popular_posts']['config_fields']['limit']['max'] ?? null) === 20,
    'Popular Posts exposes per-widget daily, monthly, yearly, and item-limit controls to Core Sidebar Settings');

$GLOBALS['gsk_test_settings'][GSK_SHOW_DASHBOARD_VIEWS_KEY] = '1';
$select = gsk_post_list_view_select('', '', ['schema' => 1, 'type' => 'article', 'actor_id' => 7]);
$actions = gsk_admin_content_view_action([], ['gsk_view_count' => 12], ['schema' => 1, 'content_type' => 'article', 'actor_id' => 7], $pdo);
$check(str_contains($select, 'AS gsk_view_count') && ($actions[0]['icon'] ?? '') === 'eye' && str_contains((string)($actions[0]['label'] ?? ''), '12 views'), 'dashboard list select and row action expose one-query all-time counts with the Core eye icon');
$check(count($callbacks($GLOBALS['gsk_test_actions'], 'admin_category_row_actions')) === 1
    && count($callbacks($GLOBALS['gsk_test_filters'], 'admin_content_row_actions')) === 1,
    'dashboard Article, Page, Theme Content, and Category integrations are registered');

$root = dirname(__DIR__);
$pluginSource = (string)file_get_contents($root . '/plugin.php');
$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$migration = (string)file_get_contents($root . '/migrations/0001-content-views.sql');
$localeMigration = (string)file_get_contents($root . '/migrations/0002-content-view-locales.sql');
$settingsSource = (string)file_get_contents($root . '/admin/settings.php');
$check(($manifest['version'] ?? '') === '2.12.0' && ($manifest['requires']['jyavani'] ?? '') === '>=2.3.195', 'manifest version and Core requirement are updated');
$check(str_contains($migration, 'jy_metrics_content_views_daily') && str_contains($migration, "ENUM('post', 'category')")
    && str_contains($migration, 'PRIMARY KEY (`entity_kind`, `entity_id`, `view_date_utc`)')
    && str_contains($migration, '`entity_type` VARCHAR(16)') && str_contains($migration, '`view_count` BIGINT UNSIGNED')
    && str_contains($localeMigration, '`representation_locale` VARCHAR(16)')
    && str_contains($localeMigration, 'PRIMARY KEY (`entity_kind`, `entity_id`, `view_date_utc`, `representation_locale`)')
    && !preg_match('/visitor|user_id|ip|cookie|session/i', $migration . $localeMigration), 'migrations define bounded locale-aware UTC daily aggregates without personal identifiers');
$check(str_contains($pluginSource, "`representation_locale`, `view_count`) VALUES (?, ?, ?, ?, ?, 1)")
    && str_contains($pluginSource, "ON DUPLICATE KEY UPDATE `entity_type` = VALUES(`entity_type`), `view_count` = `view_count` + 1")
    && str_contains($pluginSource, "DROP TABLE IF EXISTS `' . GSK_CONTENT_VIEWS_TABLE")
    && str_contains($pluginSource, 'DELETE FROM sidebar_zone_items WHERE `type` = ?')
    && str_contains($settingsSource, 'These controls affect visibility, not collection.'),
    'atomic aggregation, complete data and widget uninstall, and consent-independent collection are explicit');
$check(str_contains($settingsSource, 'content_view_settings_present')
    && str_contains($settingsSource, '$savedContentViews = gsk_content_view_settings($pdo);')
    && !str_contains($settingsSource, 'name="popular_posts_period"')
    && !str_contains($settingsSource, 'name="popular_posts_limit"')
    && str_contains($settingsSource, '?page=admin/sidebar/index'),
    'plugin settings retain visibility while directing per-instance Popular Posts configuration to Sidebar Settings');

echo $failures === 0 ? 'RESULT: ALL PASS' . PHP_EOL : "RESULT: {$failures} FAILURE(S)" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
