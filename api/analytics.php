<?php
// /plugins/jy-metrics/api/analytics.php
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

$action = $_GET['action'] ?? 'report';
$propertyId = gsk_setting($pdo, GSK_GA4_PROPERTY_ID_KEY);
if ($propertyId === '') {
    gsk_json_response(['ok' => false, 'error' => 'No GA4 property configured']);
}
$propertyId = ltrim($propertyId, '/');
if (!str_starts_with($propertyId, 'properties/')) {
    $propertyId = 'properties/' . $propertyId;
}

$range = gsk_date_range($_GET['days'] ?? null);
$start = $range['start'];
$end = $range['end'];

$token = gsk_get_access_token($pdo);
if (!$token) gsk_json_response(['ok' => false, 'error' => 'Not connected']);

$baseUrl = 'https://analyticsdata.googleapis.com/v1beta/' . $propertyId . ':runReport';

if ($action === 'report') {
    gsk_json_response(gsk_analytics_report($pdo, $propertyId, $start, $end));
}

if ($action === 'trend') {
    $cacheKey = 'gsk_ga4_trend_v2_' . md5($propertyId . $start . $end);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) gsk_json_response($cached);

    $body = json_encode([
        'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
        'dimensions' => [['name' => 'date']],
        'metrics'    => [['name' => 'activeUsers']],
        // The API does not guarantee row order without an explicit orderBy.
        'orderBys'   => [['dimension' => ['dimensionName' => 'date']]],
    ], JSON_UNESCAPED_UNICODE);
    $res = gsk_json_request($baseUrl, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $trend = [];
    foreach ($res['data']['rows'] ?? [] as $row) {
        $trend[] = [
            'date' => $row['dimensionValues'][0]['value'] ?? '',
            'activeUsers' => (int)($row['metricValues'][0]['value'] ?? 0),
        ];
    }
    $result = ['ok' => true, 'startDate' => $start, 'endDate' => $end, 'trend' => $trend];
    gsk_cache_set($pdo, $cacheKey, $result);
    gsk_json_response($result);
}

if ($action === 'channels') {
    $cacheKey = 'gsk_ga4_channels_' . md5($propertyId . $start . $end);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) gsk_json_response($cached);

    $body = json_encode([
        'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
        'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
        'metrics'    => [['name' => 'sessions']],
        'orderBys'   => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
    ], JSON_UNESCAPED_UNICODE);
    $res = gsk_json_request($baseUrl, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $channels = [];
    $total = 0;
    foreach ($res['data']['rows'] ?? [] as $row) {
        $sessions = (int)($row['metricValues'][0]['value'] ?? 0);
        $total += $sessions;
        $channels[] = [
            'channel' => $row['dimensionValues'][0]['value'] ?? '(unknown)',
            'sessions' => $sessions,
        ];
    }
    $result = ['ok' => true, 'startDate' => $start, 'endDate' => $end, 'total' => $total, 'channels' => $channels];
    gsk_cache_set($pdo, $cacheKey, $result);
    gsk_json_response($result);
}

if ($action === 'locations') {
    $dimensions = ['country' => 'country', 'region' => 'region', 'city' => 'city'];
    $dimension = $_GET['dimension'] ?? 'country';
    if (!isset($dimensions[$dimension])) {
        gsk_json_response(['ok' => false, 'error' => 'Invalid location dimension']);
    }

    $country = trim((string)($_GET['country'] ?? ''));
    $region = trim((string)($_GET['region'] ?? ''));
    if ($dimension !== 'city') $region = '';
    if ($dimension === 'country') $country = '';

    $cacheKey = 'gsk_ga4_locations_v2_' . md5($propertyId . $start . $end . $dimension . $country . $region);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) gsk_json_response($cached);

    $body = json_encode([
        'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
        'dimensions' => [['name' => $dimensions[$dimension]]],
        'metrics'    => [['name' => 'activeUsers']],
        'orderBys'   => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
        'limit'      => 1000,
    ], JSON_UNESCAPED_UNICODE);
    $request = json_decode($body, true);
    $filters = [];
    if ($country !== '') {
        $filters[] = ['filter' => ['fieldName' => 'country', 'stringFilter' => ['matchType' => 'EXACT', 'value' => $country]]];
    }
    if ($region !== '') {
        $filters[] = ['filter' => ['fieldName' => 'region', 'stringFilter' => ['matchType' => 'EXACT', 'value' => $region]]];
    }
    if (count($filters) === 1) {
        $request['dimensionFilter'] = $filters[0];
    } elseif ($filters !== []) {
        $request['dimensionFilter'] = ['andGroup' => ['expressions' => $filters]];
    }
    $body = json_encode($request, JSON_UNESCAPED_UNICODE);
    $res = gsk_json_request($baseUrl, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $locations = [];
    $total = 0;
    foreach ($res['data']['rows'] ?? [] as $row) {
        $users = (int)($row['metricValues'][0]['value'] ?? 0);
        $total += $users;
        $locations[] = [
            'location' => $row['dimensionValues'][0]['value'] ?? '(unknown)',
            'activeUsers' => $users,
        ];
    }
    $result = ['ok' => true, 'dimension' => $dimension, 'country' => $country, 'region' => $region, 'startDate' => $start, 'endDate' => $end, 'total' => $total, 'locations' => $locations];
    gsk_cache_set($pdo, $cacheKey, $result);
    gsk_json_response($result);
}

if ($action === 'pages') {
    $cacheKey = 'gsk_ga4_pages_' . md5($propertyId . $start . $end);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) gsk_json_response($cached);

    $body = json_encode([
        'dateRanges' => [['startDate' => $start, 'endDate' => $end]],
        'dimensions' => [['name' => 'pageTitle'], ['name' => 'pagePath']],
        'metrics'    => [['name' => 'screenPageViews'], ['name' => 'sessions'], ['name' => 'engagementRate'], ['name' => 'averageSessionDuration']],
        'orderBys'   => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
        'limit'      => 1000,
    ], JSON_UNESCAPED_UNICODE);
    $res = gsk_json_request($baseUrl, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $pages = [];
    foreach ($res['data']['rows'] ?? [] as $row) {
        $vals = $row['metricValues'] ?? [];
        $pages[] = [
            'title' => $row['dimensionValues'][0]['value'] ?? '',
            'path' => $row['dimensionValues'][1]['value'] ?? '',
            'pageviews' => (int)($vals[0]['value'] ?? 0),
            'sessions' => (int)($vals[1]['value'] ?? 0),
            'engagementRate' => (float)($vals[2]['value'] ?? 0),
            'avgSessionDuration' => (float)($vals[3]['value'] ?? 0),
        ];
    }
    $result = ['ok' => true, 'startDate' => $start, 'endDate' => $end, 'pages' => $pages];
    gsk_cache_set($pdo, $cacheKey, $result);
    gsk_json_response($result);
}

if ($action === 'realtime') {
    // Realtime API has limited dimensions and no caching.
    // Valid dimensions include: country, city, region, minutesAgo, eventName.
    $realtimeUrl = 'https://analyticsdata.googleapis.com/v1beta/' . $propertyId . ':runRealtimeReport';

    // 1. Totals only (no dimensions) -> true activeUsers in last 30 min
    $totalsBody = json_encode([
        'minuteRanges' => [['startMinutesAgo' => 29, 'endMinutesAgo' => 0]],
        'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
    ], JSON_UNESCAPED_UNICODE);
    $totalsRes = gsk_json_request($realtimeUrl, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $totalsBody);
    $totals = ['activeUsers' => 0, 'screenPageViews' => 0];
    if ($totalsRes['ok']) {
        $values = $totalsRes['data']['totals'][0]['metricValues'] ?? ($totalsRes['data']['rows'][0]['metricValues'] ?? []);
        foreach ($totalsRes['data']['metricHeaders'] ?? [] as $i => $h) {
            $name = $h['name'] ?? '';
            if ($name === 'activeUsers') {
                $totals['activeUsers'] = (int)($values[$i]['value'] ?? 0);
            }
            if ($name === 'screenPageViews') {
                $totals['screenPageViews'] = (int)($values[$i]['value'] ?? 0);
            }
        }
    }

    // 2. Breakdown by minutesAgo + country + city
    $body = json_encode([
        'minuteRanges' => [['startMinutesAgo' => 29, 'endMinutesAgo' => 0]],
        'metrics'    => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
        'dimensions' => [['name' => 'minutesAgo'], ['name' => 'country'], ['name' => 'city']],
        'orderBys'   => [['dimension' => ['dimensionName' => 'minutesAgo'], 'desc' => true]],
        'limit'      => 500,
    ], JSON_UNESCAPED_UNICODE);
    $res = gsk_json_request($realtimeUrl, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ($res['body'] ?? ('HTTP ' . $res['code']));
        gsk_json_response(['ok' => false, 'error' => $err, 'code' => $res['code']]);
    }
    $countries = [];
    $cities = [];
    $minutes = [];
    foreach ($res['data']['rows'] ?? [] as $row) {
        $dims = $row['dimensionValues'] ?? [];
        $vals = $row['metricValues'] ?? [];
        $minute = (int)($dims[0]['value'] ?? 0);
        $country = $dims[1]['value'] ?? '(unknown)';
        $city = $dims[2]['value'] ?? '(unknown)';
        $users = (int)($vals[0]['value'] ?? 0);
        $views = (int)($vals[1]['value'] ?? 0);
        $countries[$country] = ($countries[$country] ?? 0) + $users;
        $cities[$city . ' (' . $country . ')'] = ($cities[$city . ' (' . $country . ')'] ?? 0) + $users;
        $minutes[$minute] = ($minutes[$minute] ?? 0) + $users;
    }
    arsort($countries);
    arsort($cities);
    ksort($minutes);
    $countryList = [];
    foreach ($countries as $c => $v) $countryList[] = ['country' => $c, 'activeUsers' => $v];
    $cityList = [];
    foreach ($cities as $c => $v) $cityList[] = ['city' => $c, 'activeUsers' => $v];
    $trend = [];
    foreach ($minutes as $m => $v) $trend[] = ['minute' => $m, 'activeUsers' => $v];
    gsk_json_response([
        'ok' => true,
        'propertyId' => $propertyId,
        'totals' => $totals,
        'countries' => array_slice($countryList, 0, 10),
        'cities' => array_slice($cityList, 0, 10),
        'trend' => $trend,
    ]);
}

gsk_json_response(['ok' => false, 'error' => 'Unknown action']);
