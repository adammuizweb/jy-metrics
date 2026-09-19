<?php
// /plugins/jy-metrics/api/setup.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    gsk_json_response(['ok' => false, 'error' => 'Database not available']);
}
adiwira_require_permission($pdo, 'plugin.jy-metrics.integrations.manage', true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    gsk_json_response(['ok' => false, 'error' => 'Method not allowed']);
}

if (!function_exists('csrf_check') || !csrf_check($_GET['csrf_token'] ?? '')) {
    gsk_json_response(['ok' => false, 'error' => 'Invalid CSRF token']);
}

$action = $_GET['action'] ?? 'status';

if ($action === 'status') {
    gsk_json_response([
        'ok' => true,
        'connected' => gsk_is_connected($pdo),
        'email' => gsk_setting($pdo, GSK_USER_EMAIL_KEY),
        'authUrl' => gsk_google_auth_url($pdo),
    ]);
}

if ($action === 'options') {
    $sites = gsk_search_console_sites($pdo);
    $properties = gsk_ga4_properties($pdo);
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $hostAlt = preg_replace('/^www\./', '', $host);

    $matchedSite = '';
    foreach ($sites as $s) {
        $url = strtolower($s['siteUrl'] ?? '');
        if ($url === '' || $url === 'http://' || $url === 'https://') continue;
        $clean = preg_replace('#^sc-domain:|^https?://#', '', $url);
        $clean = rtrim($clean, '/');
        if ($clean === $host || $clean === $hostAlt || str_contains($clean, $host) || str_contains($clean, $hostAlt)) {
            $matchedSite = $s['siteUrl'];
            break;
        }
    }

    $matchedProperty = '';
    foreach ($properties as $p) {
        $display = strtolower($p['displayName'] ?? '');
        if (str_contains($display, $host) || str_contains($display, $hostAlt) || str_contains($display, str_replace('.', ' ', $host))) {
            $matchedProperty = $p['id'] ?? '';
            break;
        }
    }

    gsk_json_response([
        'ok' => true,
        'host' => $host,
        'matchedSite' => $matchedSite,
        'matchedProperty' => $matchedProperty,
        'searchConsoleSites' => $sites,
        'ga4Properties'      => $properties,
        'gtmContainers'      => gsk_gtm_containers($pdo),
        'adsenseAccounts'    => gsk_adsense_accounts($pdo),
    ]);
}

if ($action === 'streams') {
    $propertyId = $_GET['propertyId'] ?? '';
    if ($propertyId === '') gsk_json_response(['ok' => false, 'error' => 'No property selected']);
    $token = gsk_get_access_token($pdo);
    if (!$token) gsk_json_response(['ok' => false, 'error' => 'Not connected']);
    $propertyId = ltrim($propertyId, '/');
    if (!str_starts_with($propertyId, 'properties/')) $propertyId = 'properties/' . $propertyId;
    $res = gsk_json_request('https://analyticsadmin.googleapis.com/v1alpha/' . $propertyId . '/dataStreams', 'GET', [
        'Authorization: Bearer ' . $token,
    ]);
    if (!$res['ok']) gsk_json_response(['ok' => false, 'error' => 'Failed to load data streams', 'code' => $res['code']]);
    $streams = [];
    foreach ($res['data']['dataStreams'] ?? [] as $s) {
        if (($s['type'] ?? '') === 'WEB_DATA_STREAM' && !empty($s['webStreamData']['measurementId'])) {
            $streams[] = [
                'name'          => $s['name'] ?? '',
                'measurementId' => $s['webStreamData']['measurementId'],
                'defaultUri'    => $s['webStreamData']['defaultUri'] ?? '',
            ];
        }
    }
    gsk_json_response(['ok' => true, 'streams' => $streams]);
}

if ($action === 'matchProperty') {
    $host = strtolower($_GET['host'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
    $hostAlt = preg_replace('/^www\./', '', $host);
    $properties = gsk_ga4_properties($pdo);
    $token = gsk_get_access_token($pdo);
    if (!$token) gsk_json_response(['ok' => false, 'error' => 'Not connected']);
    foreach ($properties as $p) {
        $propertyId = ltrim($p['id'] ?? '', '/');
        if ($propertyId === '') continue;
        if (!str_starts_with($propertyId, 'properties/')) $propertyId = 'properties/' . $propertyId;
        $res = gsk_json_request('https://analyticsadmin.googleapis.com/v1alpha/' . $propertyId . '/dataStreams', 'GET', [
            'Authorization: Bearer ' . $token,
        ]);
        if (!$res['ok']) continue;
        foreach ($res['data']['dataStreams'] ?? [] as $s) {
            if (($s['type'] ?? '') === 'WEB_DATA_STREAM') {
                $uri = strtolower($s['webStreamData']['defaultUri'] ?? '');
                $clean = preg_replace('#^https?://#', '', $uri);
                $clean = rtrim($clean, '/');
                if ($clean === $host || $clean === $hostAlt || str_contains($clean, $host) || str_contains($clean, $hostAlt)) {
                    gsk_json_response([
                        'ok' => true,
                        'propertyId' => $p['id'],
                        'measurementId' => $s['webStreamData']['measurementId'] ?? '',
                        'defaultUri' => $s['webStreamData']['defaultUri'] ?? '',
                    ]);
                }
            }
        }
    }
    gsk_json_response(['ok' => true, 'propertyId' => '']);
}

gsk_json_response(['ok' => false, 'error' => 'Unknown action']);
