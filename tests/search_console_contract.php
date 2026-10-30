<?php
declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__) . '/plugin.php');
$adminSource = (string)file_get_contents(dirname(__DIR__) . '/admin/index.php');
$failures = 0;
$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$condition) $failures++;
};

$statsStart = strpos($source, 'function gsk_search_console_stats');
$statsEnd = strpos($source, 'function gsk_ga4_properties', $statsStart ?: 0);
$stats = $statsStart !== false && $statsEnd !== false ? substr($source, $statsStart, $statsEnd - $statsStart) : '';
$errorCheck = strpos($stats, "if (!\$queriesRes['ok'])");
$queryLoop = strpos($stats, "foreach (\$queriesRes['data']['rows']");
$cacheWrite = strpos($stats, 'gsk_cache_set($pdo, $cacheKey, $result)');

$check(str_contains($stats, "'rowLimit'  => 25000"), 'Search Console requests the provider maximum of 25,000 query rows');
$check(str_contains($stats, "gsk_sc_stats_v3_"), 'expanded query results use a revised cache key');
$check($errorCheck !== false && $queryLoop !== false && $cacheWrite !== false && $errorCheck < $queryLoop && $errorCheck < $cacheWrite
    && str_contains($stats, "return ['ok' => false, 'error' => \$err"), 'failed query requests propagate an error and are not cached as successful empty data');
$check(!str_contains($stats, 'usort(') && !str_contains($stats, 'array_reverse('), 'provider query order is retained for the existing client paginator');
$check(str_contains($adminSource, "gskRenderQueryTable('gsk-sc-queries', j.queries || [])")
    && str_contains($adminSource, 'j.error || searchQueriesLoadError')
    && str_contains($adminSource, "gsk_t('Could not load search queries')")
    && !str_contains($adminSource, "gskRenderQueryTable('gsk-sc-queries', j.ok ? j.queries : [])"),
    'dashboard distinguishes Search Console request failures from successful empty query data');

echo $failures === 0 ? 'RESULT: ALL PASS' . PHP_EOL : "RESULT: {$failures} FAILURE(S)" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
