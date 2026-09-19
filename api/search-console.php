<?php
// /plugins/jy-metrics/api/search-console.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    gsk_json_response(['ok' => false, 'error' => 'Database not available']);
}
adiwira_require_permission($pdo, 'plugin.jy-metrics.reports.read', true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    gsk_json_response(['ok' => false, 'error' => 'Method not allowed']);
}

if (!function_exists('csrf_check') || !csrf_check($_GET['csrf_token'] ?? '')) {
    gsk_json_response(['ok' => false, 'error' => 'Invalid CSRF token']);
}

$action = $_GET['action'] ?? 'stats';

if ($action === 'sites') {
    adiwira_require_permission($pdo, 'plugin.jy-metrics.integrations.manage', true);
    $sites = gsk_search_console_sites($pdo);
    gsk_json_response(['ok' => true, 'sites' => $sites]);
}

// Report readers are restricted to the site selected by an integration manager.
$siteUrl = gsk_setting($pdo, GSK_SITE_URL_KEY);

if ($siteUrl === '') {
    gsk_json_response(['ok' => false, 'error' => 'No site selected']);
}

$range = gsk_date_range($_GET['days'] ?? null);
$start = $range['start'];
$end = $range['end'];

if ($action === 'trend') {
    $cacheKey = 'gsk_sc_trend_v3_' . md5($siteUrl . $start . $end);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) gsk_json_response($cached);

    $body = json_encode([
        'startDate' => $start,
        'endDate'   => $end,
        'dimensions' => ['date'],
        'rowLimit'  => 1000,
    ]);
    $token = gsk_get_access_token($pdo);
    if (!$token) gsk_json_response(['ok' => false, 'error' => 'Not connected']);
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';
    $res = gsk_json_request($url, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $rows = $res['data']['rows'] ?? [];
    $trend = [];
    $latestAvailableDate = '';
    foreach ($rows as $r) {
        $date = (string)($r['keys'][0] ?? '');
        if ($date > $latestAvailableDate) $latestAvailableDate = $date;
        $trend[] = [
            'date'        => $date,
            'clicks'      => (float)($r['clicks'] ?? 0),
            'impressions' => (float)($r['impressions'] ?? 0),
        ];
    }
    $result = ['ok' => true, 'siteUrl' => $siteUrl, 'startDate' => $start, 'endDate' => $end, 'latestAvailableDate' => $latestAvailableDate, 'trend' => $trend];
    gsk_cache_set($pdo, $cacheKey, $result);
    gsk_json_response($result);
}

if ($action === 'pages') {
    $cacheKey = 'gsk_sc_pages_v2_' . md5($siteUrl . $start . $end);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) gsk_json_response($cached);

    $body = json_encode([
        'startDate' => $start,
        'endDate'   => $end,
        'dimensions' => ['page'],
        'rowLimit'  => 1000,
    ]);
    $token = gsk_get_access_token($pdo);
    if (!$token) gsk_json_response(['ok' => false, 'error' => 'Not connected']);
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';
    $res = gsk_json_request($url, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $rows = $res['data']['rows'] ?? [];
    $pages = [];
    foreach ($rows as $r) {
        $pages[] = [
            'page'        => $r['keys'][0] ?? '',
            'clicks'      => (float)($r['clicks'] ?? 0),
            'impressions' => (float)($r['impressions'] ?? 0),
        ];
    }
    $result = ['ok' => true, 'siteUrl' => $siteUrl, 'startDate' => $start, 'endDate' => $end, 'pages' => $pages];
    gsk_cache_set($pdo, $cacheKey, $result);
    gsk_json_response($result);
}

if ($action === 'stats') {
    gsk_json_response(gsk_search_console_stats($pdo, $siteUrl, $start, $end));
}

if ($action === 'summary') {
    gsk_json_response(gsk_search_console_summary($pdo, $siteUrl, $start, $end));
}

gsk_json_response(['ok' => false, 'error' => 'Unknown action']);
