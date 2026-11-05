<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 3);
$allPeriods = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--all') {
        $allPeriods = true;
        continue;
    }
    if (str_starts_with($argument, '--root=')) {
        $root = substr($argument, 7);
        continue;
    }
    fwrite(STDERR, "Usage: php plugins/jy-metrics/tools/refresh-content-views.php [--root=/path/to/jyavani] [--all]\n");
    exit(2);
}

$root = realpath($root) ?: '';
if ($root === '' || !is_file($root . '/app/bootstrap_core.php') || !is_file($root . '/plugins/index.php')) {
    fwrite(STDERR, "Jyavani project root is invalid.\n");
    exit(2);
}

require $root . '/app/bootstrap_core.php';
if (function_exists('theme_lifecycle_reader_start')) theme_lifecycle_reader_start();
require $root . '/plugins/index.php';
plugin_load_active();

if (!function_exists('gsk_refresh_ga4_content_views') || !isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Jy Metrics is not active or the database is unavailable.\n");
    exit(1);
}

gsk_ensure_schema($pdo);
$periods = $allPeriods ? gsk_ga4_content_periods() : null;
$result = gsk_refresh_ga4_content_views($pdo, $periods, true);
if (($result['ok'] ?? false) !== true) {
    fwrite(STDERR, "Google Analytics content cache refresh failed.\n");
    exit(1);
}

$refreshed = array_map('strval', $result['refreshed'] ?? []);
fwrite(STDOUT, 'Refreshed Google Analytics content cache periods: ' . ($refreshed === [] ? 'none' : implode(', ', $refreshed)) . ".\n");
