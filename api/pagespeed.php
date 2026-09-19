<?php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) gsk_json_response(['ok' => false, 'error' => 'Database not available']);
adiwira_require_permission($pdo, 'plugin.jy-metrics.reports.read', true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    gsk_json_response(['ok' => false, 'error' => 'Method not allowed']);
}
if (!function_exists('csrf_check') || !csrf_check($_GET['csrf_token'] ?? '')) gsk_json_response(['ok' => false, 'error' => 'Invalid CSRF token']);

$key = gsk_setting($pdo, GSK_PAGESPEED_API_KEY);
if ($key === '') gsk_json_response(['ok' => false, 'needsKey' => true, 'error' => 'Add a PageSpeed Insights API key in Optional Integrations.']);

$strategy = ($_GET['strategy'] ?? 'mobile') === 'desktop' ? 'desktop' : 'mobile';
$url = gsk_setting($pdo, GSK_SITE_URL_KEY, gsk_base_url());
// Search Console domain properties use values such as "sc-domain:example.com",
// which PageSpeed cannot analyze. Convert them to the site's public HTTPS URL.
if (str_starts_with($url, 'sc-domain:')) $url = 'https://' . substr($url, strlen('sc-domain:'));
if (!filter_var($url, FILTER_VALIDATE_URL)) $url = gsk_base_url();
$cacheKey = 'gsk_pagespeed_v2_' . $strategy . '_' . md5($url);
$cached = gsk_cache_get($pdo, $cacheKey, 21600);
if ($cached !== null) gsk_json_response(['ok' => true, 'cached' => true] + $cached);

$endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query([
    'url' => $url, 'strategy' => $strategy, 'key' => $key,
]) . '&category=performance&category=accessibility&category=best-practices&category=seo';
$res = gsk_json_request($endpoint);
if (!$res['ok']) {
    $errorBody = json_decode((string)($res['body'] ?? ''), true);
    gsk_json_response(['ok' => false, 'error' => $errorBody['error']['message'] ?? $res['error'] ?? 'PageSpeed request failed']);
}
$cats = $res['data']['lighthouseResult']['categories'] ?? [];
$audits = $res['data']['lighthouseResult']['audits'] ?? [];
$screenshot = (string)($audits['final-screenshot']['details']['data'] ?? '');
$data = ['strategy' => $strategy, 'url' => $url, 'scores' => [
    'performance' => (int)round(($cats['performance']['score'] ?? 0) * 100),
    'accessibility' => (int)round(($cats['accessibility']['score'] ?? 0) * 100),
    'bestPractices' => (int)round(($cats['best-practices']['score'] ?? 0) * 100),
    'seo' => (int)round(($cats['seo']['score'] ?? 0) * 100),
], 'screenshot' => str_starts_with($screenshot, 'data:image/') ? $screenshot : ''];
gsk_cache_set($pdo, $cacheKey, $data);
gsk_json_response(['ok' => true, 'cached' => false] + $data);
