<?php
// /plugins/jy-metrics/plugin.php
declare(strict_types=1);

if (!defined('PLUGIN_SYSTEM_LOADED')) {
    return;
}

const GSK_PLUGIN_NAME = 'jy-metrics';
const GSK_CACHE_TABLE = 'google_site_kit_cache';
const GSK_I18N_SCOPE = 'jy-metrics';
const GSK_I18N_VERSION_KEY = 'jym_i18n_version';

// Settings keys
const GSK_CLIENT_ID_KEY     = 'gsk_client_id';
const GSK_CLIENT_SECRET_KEY = 'gsk_client_secret';
const GSK_ACCESS_TOKEN_KEY  = 'gsk_access_token';
const GSK_REFRESH_TOKEN_KEY = 'gsk_refresh_token';
const GSK_TOKEN_EXPIRES_KEY = 'gsk_token_expires';
const GSK_USER_EMAIL_KEY    = 'gsk_user_email';
const GSK_SCOPES_KEY        = 'gsk_scopes';
const GSK_SITE_URL_KEY      = 'gsk_site_url';
const GSK_MEASUREMENT_ID_KEY = 'gsk_measurement_id';
const GSK_GA4_PROPERTY_ID_KEY = 'gsk_ga4_property_id';
const GSK_GTM_ID_KEY        = 'gsk_gtm_id';
const GSK_ADSENSE_CLIENT_KEY = 'gsk_adsense_client';
const GSK_SITE_VERIFICATION_KEY = 'gsk_site_verification';
const GSK_PAGESPEED_API_KEY = 'gsk_pagespeed_api_key';

function gsk_plugin_dir(): string {
    return defined('PLUGIN_PATH') ? PLUGIN_PATH . '/' . GSK_PLUGIN_NAME : __DIR__;
}

function gsk_setting(PDO $pdo, string $key, string $default = ''): string {
    if (!function_exists('settings_get')) return $default;
    $val = settings_get($pdo, $key, $default);
    return is_string($val) ? $val : $default;
}

function gsk_save_setting(PDO $pdo, string $key, string $value): void {
    if (function_exists('settings_set')) {
        settings_set($pdo, $key, $value, 1);
    }
}

function gsk_all_settings(PDO $pdo): array {
    $keys = [
        GSK_CLIENT_ID_KEY, GSK_CLIENT_SECRET_KEY,
        GSK_ACCESS_TOKEN_KEY, GSK_REFRESH_TOKEN_KEY, GSK_TOKEN_EXPIRES_KEY,
        GSK_USER_EMAIL_KEY, GSK_SCOPES_KEY,
        GSK_SITE_URL_KEY, GSK_MEASUREMENT_ID_KEY, GSK_GA4_PROPERTY_ID_KEY, GSK_GTM_ID_KEY,
        GSK_ADSENSE_CLIENT_KEY, GSK_SITE_VERIFICATION_KEY, GSK_PAGESPEED_API_KEY,
    ];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = gsk_setting($pdo, $k, '');
    }
    return $out;
}

function gsk_t(string $source): string {
    return function_exists('__') ? __($source, GSK_I18N_SCOPE) : $source;
}

function gsk_help_tooltip(string $id, string $label, string $description): string {
    return '<button type="button" class="gsk-metric__help" aria-label="' . gsk_e($label) . '" aria-describedby="' . gsk_e($id) . '">?</button>'
        . '<span class="gsk-metric__tooltip" id="' . gsk_e($id) . '" role="tooltip">' . gsk_e($description) . '</span>';
}

function gsk_register_translations(PDO $pdo): void {
    if (gsk_setting($pdo, GSK_I18N_VERSION_KEY) === '6') return;

    $translations = [
        'Active users' => 'Pengguna aktif',
        'Page views' => 'Tampilan halaman',
        'Visitors' => 'Pengunjung',
        'Impressions' => 'Impresi',
        'Clicks' => 'Klik',
        'Avg. Position' => 'Rata-rata posisi',
        'People active on the site during the last 30 minutes.' => 'Orang yang aktif di situs dalam 30 menit terakhir.',
        'Pages viewed during the last 30 minutes. Repeat views are included.' => 'Halaman yang dilihat dalam 30 menit terakhir. Tampilan berulang tetap dihitung.',
        'Distinct active users during the selected period. One person may visit more than once.' => 'Pengguna aktif yang berbeda selama periode yang dipilih. Satu orang dapat berkunjung lebih dari sekali.',
        'How often pages from this site appeared in Google Search results, whether clicked or not.' => 'Seberapa sering halaman situs ini muncul di hasil Google Penelusuran, baik diklik maupun tidak.',
        'Visits to this site from Google Search results.' => 'Kunjungan ke situs ini dari hasil Google Penelusuran.',
        'Average position of this site in Google Search results. A lower number is better: position 1 is at the top.' => 'Rata-rata posisi situs ini di hasil Google Penelusuran. Angka yang lebih kecil lebih baik: posisi 1 berada di paling atas.',
        'Realtime - last 30 minutes' => 'Waktu nyata - 30 menit terakhir',
        'Daily active users' => 'Pengguna aktif harian',
        'Channels' => 'Saluran',
        'Countries' => 'Negara',
        'Locations' => 'Lokasi',
        'Provinces' => 'Provinsi',
        'Cities' => 'Kota',
        'Country' => 'Negara',
        'Province' => 'Provinsi',
        'All countries' => 'Semua negara',
        'All provinces' => 'Semua provinsi',
        'Unknown location' => 'Lokasi tidak diketahui',
        'Google Analytics could not determine this visitor location. It may occur when location data is unavailable, consent limits data collection, or the visit lacks a usable IP address.' => 'Google Analytics tidak dapat menentukan lokasi pengunjung ini. Hal ini dapat terjadi saat data lokasi tidak tersedia, persetujuan membatasi pengumpulan data, atau kunjungan tidak memiliki alamat IP yang dapat digunakan.',
        'Search traffic' => 'Traffic penelusuran',
        'Top content' => 'Konten teratas',
        'Live activity on your site during the last 30 minutes.' => 'Aktivitas langsung di situs Anda dalam 30 menit terakhir.',
        'The number of active users for each day in the selected period.' => 'Jumlah pengguna aktif untuk setiap hari dalam periode yang dipilih.',
        'Sessions grouped by GA4 default traffic source categories, such as Organic Search or Direct.' => 'Sesi yang dikelompokkan berdasarkan kategori sumber traffic bawaan GA4, seperti Penelusuran Organik atau Langsung.',
        'Countries where active users were located during the selected period.' => 'Negara tempat pengguna aktif berada selama periode yang dipilih.',
        'Active users by country, province, or city during the selected period.' => 'Pengguna aktif berdasarkan negara, provinsi, atau kota selama periode yang dipilih.',
        'Google Search clicks and impressions from Search Console for the selected period.' => 'Klik dan impresi Google Penelusuran dari Search Console untuk periode yang dipilih.',
        'Pages with the most views during the selected period.' => 'Halaman dengan tampilan terbanyak selama periode yang dipilih.',
        'Search Console data is usually available 2-3 days later. Latest available: {date}.' => 'Data Search Console biasanya tersedia 2-3 hari kemudian. Data terbaru yang tersedia: {date}.',
    ];

    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO ui_translations (`scope`, `source`, `value`, `locale`) VALUES (?, ?, ?, ?)');
        foreach ($translations as $source => $value) {
            $stmt->execute([GSK_I18N_SCOPE, $source, $value, 'id']);
        }
        gsk_save_setting($pdo, GSK_I18N_VERSION_KEY, '6');
    } catch (Throwable $e) {
        error_log('[jy-metrics] Could not register translations: ' . $e->getMessage());
    }
}

function gsk_ensure_schema(PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `" . GSK_CACHE_TABLE . "` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `cache_key` VARCHAR(100) NOT NULL,
            `data` LONGTEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `cache_key` (`cache_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('[jy-metrics] Could not initialize cache: ' . $e->getMessage());
    }
}

function gsk_cache_get(PDO $pdo, string $key, int $ttl = 3600): ?array {
    try {
        $stmt = $pdo->prepare("SELECT `data`, `created_at` FROM `" . GSK_CACHE_TABLE . "` WHERE `cache_key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $ts = strtotime($row['created_at']);
        if ($ts === false || time() - $ts > $ttl) return null;
        $data = json_decode($row['data'], true);
        return is_array($data) ? $data : null;
    } catch (Throwable $e) {
        error_log('[jy-metrics] Cache read failed: ' . $e->getMessage());
        return null;
    }
}

function gsk_cache_set(PDO $pdo, string $key, array $data): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO `" . GSK_CACHE_TABLE . "` (`cache_key`, `data`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `created_at` = CURRENT_TIMESTAMP");
        $stmt->execute([$key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) {
        error_log('[jy-metrics] Cache write failed: ' . $e->getMessage());
    }
}

function gsk_cache_delete(PDO $pdo, string $pattern): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM `" . GSK_CACHE_TABLE . "` WHERE `cache_key` LIKE ?");
        $stmt->execute([$pattern]);
    } catch (Throwable $e) {
        error_log('[jy-metrics] Cache delete failed: ' . $e->getMessage());
    }
}

function gsk_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function gsk_admin_path(): string {
    if (function_exists('get_admin_path')) {
        try {
            $pdo = $GLOBALS['pdo'] ?? null;
            return ($pdo instanceof PDO) ? (string)get_admin_path($pdo) : (defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '/adiwira');
        } catch (Throwable $e) {
            // fallthrough
        }
    }
    return defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '/adiwira';
}

function gsk_admin_url(string $page): string {
    $base = gsk_admin_path();
    return $base . '/?page=' . $page;
}

function gsk_absolute_admin_url(string $page): string {
    return gsk_base_url() . gsk_admin_url($page);
}

function gsk_oauth_redirect_uri(): string {
    return gsk_absolute_admin_url('admin/tools/jy-metrics/oauth');
}

function gsk_scopes(): array {
    return [
        'openid',
        'email',
        'profile',
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];
}

function gsk_scope_string(): string {
    return implode(' ', gsk_scopes());
}

function gsk_google_auth_url(PDO $pdo): string {
    $clientId = gsk_setting($pdo, GSK_CLIENT_ID_KEY);
    if ($clientId === '') return '';
    $redirect = gsk_oauth_redirect_uri();
    $state = bin2hex(random_bytes(16));
    if (function_exists('ensure_session_started')) {
        ensure_session_started(false);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['gsk_oauth_state'] = $state;
    }
    $params = [
        'client_id'     => $clientId,
        'redirect_uri' => $redirect,
        'response_type' => 'code',
        'scope'         => gsk_scope_string(),
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function gsk_http_request(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL extension not available', 'code' => 0, 'body' => ''];
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['ok' => false, 'error' => $err, 'code' => $code, 'body' => ''];
    }
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => (string)$resp];
}

function gsk_json_request(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array {
    $res = gsk_http_request($url, $method, $headers, $body);

    // Google can revoke an access token before its recorded expiry. Refresh once
    // and retry so a transient 401 does not break dashboard reports.
    $hasBearerToken = false;
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), 'authorization: bearer ')) {
            $hasBearerToken = true;
            break;
        }
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$res['ok'] && $res['code'] === 401 && $hasBearerToken && $pdo instanceof PDO && gsk_refresh_access_token($pdo)) {
        $token = gsk_setting($pdo, GSK_ACCESS_TOKEN_KEY);
        foreach ($headers as $i => $header) {
            if (str_starts_with(strtolower($header), 'authorization: bearer ')) {
                $headers[$i] = 'Authorization: Bearer ' . $token;
            }
        }
        $res = gsk_http_request($url, $method, $headers, $body);
    }
    if (!$res['ok']) return $res;
    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON response', 'code' => $res['code'], 'body' => $res['body']];
    }
    return ['ok' => true, 'code' => $res['code'], 'data' => $data];
}

function gsk_token_expired(PDO $pdo): bool {
    $expires = (int)gsk_setting($pdo, GSK_TOKEN_EXPIRES_KEY, '0');
    return $expires > 0 && $expires <= time();
}

function gsk_get_access_token(PDO $pdo): ?string {
    $token = gsk_setting($pdo, GSK_ACCESS_TOKEN_KEY);
    if ($token === '') return null;
    if (gsk_token_expired($pdo)) {
        if (!gsk_refresh_access_token($pdo)) return null;
        $token = gsk_setting($pdo, GSK_ACCESS_TOKEN_KEY);
    }
    return $token !== '' ? $token : null;
}

function gsk_refresh_access_token(PDO $pdo): bool {
    $refresh = gsk_setting($pdo, GSK_REFRESH_TOKEN_KEY);
    $clientId = gsk_setting($pdo, GSK_CLIENT_ID_KEY);
    $clientSecret = gsk_setting($pdo, GSK_CLIENT_SECRET_KEY);
    if ($refresh === '' || $clientId === '' || $clientSecret === '') return false;
    $res = gsk_http_request('https://oauth2.googleapis.com/token', 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
    ]));
    if (!$res['ok']) return false;
    $data = json_decode($res['body'], true);
    if (!is_array($data) || empty($data['access_token'])) return false;
    gsk_save_setting($pdo, GSK_ACCESS_TOKEN_KEY, $data['access_token']);
    $expires = time() + (int)($data['expires_in'] ?? 3600);
    gsk_save_setting($pdo, GSK_TOKEN_EXPIRES_KEY, (string)$expires);
    return true;
}

function gsk_revoke_token(string $token): bool {
    if ($token === '') return true;
    $res = gsk_http_request('https://oauth2.googleapis.com/revoke', 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query(['token' => $token]));
    return $res['code'] === 200 || $res['code'] === 400;
}

function gsk_is_connected(PDO $pdo): bool {
    return gsk_get_access_token($pdo) !== null && gsk_setting($pdo, GSK_USER_EMAIL_KEY) !== '';
}

function gsk_disconnect(PDO $pdo): void {
    $access = gsk_setting($pdo, GSK_ACCESS_TOKEN_KEY);
    $refresh = gsk_setting($pdo, GSK_REFRESH_TOKEN_KEY);
    gsk_revoke_token($access);
    gsk_revoke_token($refresh);
    $keys = [
        GSK_ACCESS_TOKEN_KEY, GSK_REFRESH_TOKEN_KEY, GSK_TOKEN_EXPIRES_KEY,
        GSK_USER_EMAIL_KEY, GSK_SCOPES_KEY,
    ];
    foreach ($keys as $k) {
        gsk_save_setting($pdo, $k, '');
    }
    gsk_cache_delete($pdo, 'gsk_%');
}

function gsk_search_console_sites(PDO $pdo): array {
    $token = gsk_get_access_token($pdo);
    if (!$token) return [];
    $cacheKey = 'gsk_sc_sites';
    $cached = gsk_cache_get($pdo, $cacheKey, 86400);
    if ($cached !== null) return $cached['sites'] ?? [];
    $res = gsk_json_request('https://www.googleapis.com/webmasters/v3/sites', 'GET', [
        'Authorization: Bearer ' . $token,
    ]);
    if (!$res['ok']) return [];
    $sites = [];
    foreach ($res['data']['siteEntry'] ?? [] as $s) {
        $sites[] = [
            'siteUrl' => $s['siteUrl'] ?? '',
            'permissionLevel' => $s['permissionLevel'] ?? '',
        ];
    }
    gsk_cache_set($pdo, $cacheKey, ['sites' => $sites]);
    return $sites;
}

function gsk_date_range(?string $daysInput): array {
    $end = date('Y-m-d', strtotime('-1 day'));
    $days = (string)($daysInput ?? '30');
    if ($days === 'today') {
        $today = date('Y-m-d');
        return ['start' => $today, 'end' => $today];
    }
    $start = match ($days) {
        '7' => date('Y-m-d', strtotime('-7 days')),
        '30' => date('Y-m-d', strtotime('-30 days')),
        '90' => date('Y-m-d', strtotime('-90 days')),
        '180' => date('Y-m-d', strtotime('-180 days')),
        '365' => date('Y-m-d', strtotime('-365 days')),
        'all' => date('Y-m-d', strtotime('-2 years')),
        default => date('Y-m-d', strtotime('-28 days')),
    };
    return ['start' => $start, 'end' => $end];
}

function gsk_search_console_summary(PDO $pdo, string $siteUrl, string $startDate, string $endDate): array {
    $token = gsk_get_access_token($pdo);
    if (!$token) return ['ok' => false, 'error' => 'Not connected'];
    if ($siteUrl === '') return ['ok' => false, 'error' => 'No site selected'];
    $cacheKey = 'gsk_sc_summary_' . md5($siteUrl . $startDate . $endDate);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) return $cached;
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';

    // Overall site totals (no dimensions)
    $totalsBody = json_encode([
        'startDate' => $startDate,
        'endDate'   => $endDate,
        'dimensions' => [],
        'rowLimit'  => 1,
    ]);
    $totalsRes = gsk_json_request($url, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $totalsBody);
    if (!$totalsRes['ok']) {
        $err = $totalsRes['data']['error']['message'] ?? ('HTTP ' . $totalsRes['code']);
        return ['ok' => false, 'error' => $err, 'code' => $totalsRes['code']];
    }
    $totalsRow = $totalsRes['data']['rows'][0] ?? null;
    $totalClicks = (float)($totalsRow['clicks'] ?? 0);
    $totalImpressions = (float)($totalsRow['impressions'] ?? 0);
    $avgCtr = $totalImpressions > 0 ? round($totalClicks / $totalImpressions, 4) : 0;
    $avgPosition = isset($totalsRow['position']) ? round((float)$totalsRow['position'], 2) : 0;

    $result = [
        'ok'            => true,
        'siteUrl'       => $siteUrl,
        'startDate'     => $startDate,
        'endDate'       => $endDate,
        'clicks'        => $totalClicks,
        'impressions'   => $totalImpressions,
        'avgCtr'        => $avgCtr,
        'avgPosition'   => $avgPosition,
    ];
    gsk_cache_set($pdo, $cacheKey, $result);
    return $result;
}

function gsk_search_console_stats(PDO $pdo, string $siteUrl, string $startDate, string $endDate): array {
    $cacheKey = 'gsk_sc_stats_v2_' . md5($siteUrl . $startDate . $endDate);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) return $cached;

    $summary = gsk_search_console_summary($pdo, $siteUrl, $startDate, $endDate);
    if (!$summary['ok']) return $summary;
    $token = gsk_get_access_token($pdo);
    if (!$token) return ['ok' => false, 'error' => 'Not connected'];
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/searchAnalytics/query';

    // Top queries
    $queriesBody = json_encode([
        'startDate' => $startDate,
        'endDate'   => $endDate,
        'dimensions' => ['query'],
        'rowLimit'  => 10,
    ]);
    $queriesRes = gsk_json_request($url, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $queriesBody);
    $queries = [];
    if ($queriesRes['ok']) {
        foreach ($queriesRes['data']['rows'] ?? [] as $r) {
            $queries[] = [
                'query'       => $r['keys'][0] ?? '',
                'clicks'      => (float)($r['clicks'] ?? 0),
                'impressions' => (float)($r['impressions'] ?? 0),
                'ctr'         => (float)($r['ctr'] ?? 0),
                'position'    => (float)($r['position'] ?? 0),
            ];
        }
    }

    $result = $summary;
    $result['queries'] = $queries;
    gsk_cache_set($pdo, $cacheKey, $result);
    return $result;
}

function gsk_ga4_properties(PDO $pdo): array {
    $token = gsk_get_access_token($pdo);
    if (!$token) return [];
    $cacheKey = 'gsk_ga4_properties';
    $cached = gsk_cache_get($pdo, $cacheKey, 86400);
    if ($cached !== null) return $cached['properties'] ?? [];
    $res = gsk_json_request('https://analyticsadmin.googleapis.com/v1alpha/accountSummaries', 'GET', [
        'Authorization: Bearer ' . $token,
    ]);
    if (!$res['ok']) return [];
    $properties = [];
    foreach ($res['data']['accountSummaries'] ?? [] as $account) {
        $accountName = $account['displayName'] ?? '';
        foreach ($account['propertySummaries'] ?? [] as $prop) {
            $properties[] = [
                'name'        => $prop['property'] ?? '',
                'displayName' => ($accountName ? $accountName . ' → ' : '') . ($prop['displayName'] ?? ''),
                'id'          => $prop['property'] ?? '',
            ];
        }
    }
    gsk_cache_set($pdo, $cacheKey, ['properties' => $properties]);
    return $properties;
}

function gsk_gtm_containers(PDO $pdo): array {
    $token = gsk_get_access_token($pdo);
    if (!$token) return [];
    $cacheKey = 'gsk_gtm_containers';
    $cached = gsk_cache_get($pdo, $cacheKey, 86400);
    if ($cached !== null) return $cached['containers'] ?? [];
    $res = gsk_json_request('https://tagmanager.googleapis.com/v2/accounts/containers', 'GET', [
        'Authorization: Bearer ' . $token,
    ]);
    if (!$res['ok']) return [];
    $containers = [];
    foreach ($res['data']['container'] ?? [] as $c) {
        $containers[] = [
            'containerId' => $c['containerId'] ?? '',
            'name'        => $c['name'] ?? '',
            'publicId'    => $c['publicId'] ?? '',
        ];
    }
    gsk_cache_set($pdo, $cacheKey, ['containers' => $containers]);
    return $containers;
}

function gsk_adsense_accounts(PDO $pdo): array {
    $token = gsk_get_access_token($pdo);
    if (!$token) return [];
    $cacheKey = 'gsk_adsense_accounts';
    $cached = gsk_cache_get($pdo, $cacheKey, 86400);
    if ($cached !== null) return $cached['accounts'] ?? [];
    $res = gsk_json_request('https://adsense.googleapis.com/v2/accounts', 'GET', [
        'Authorization: Bearer ' . $token,
    ]);
    if (!$res['ok']) return [];
    $accounts = [];
    foreach ($res['data']['accounts'] ?? [] as $a) {
        $accounts[] = [
            'name'        => $a['name'] ?? '',
            'displayName' => $a['displayName'] ?? '',
            'publisherId' => $a['publisherId'] ?? '',
        ];
    }
    gsk_cache_set($pdo, $cacheKey, ['accounts' => $accounts]);
    return $accounts;
}

function gsk_analytics_report(PDO $pdo, string $propertyId, string $startDate, string $endDate): array {
    $token = gsk_get_access_token($pdo);
    if (!$token) return ['ok' => false, 'error' => 'Not connected'];
    $propertyId = ltrim($propertyId, '/');
    if (!str_starts_with($propertyId, 'properties/')) {
        $propertyId = 'properties/' . $propertyId;
    }
    $cacheKey = 'gsk_ga4_report_' . md5($propertyId . $startDate . $endDate);
    $cached = gsk_cache_get($pdo, $cacheKey, 3600);
    if ($cached !== null) return $cached;
    $body = json_encode([
        'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
        'metrics'    => [['name' => 'sessions'], ['name' => 'activeUsers'], ['name' => 'screenPageViews']],
    ], JSON_UNESCAPED_UNICODE);
    $url = 'https://analyticsdata.googleapis.com/v1beta/' . $propertyId . ':runReport';
    $res = gsk_json_request($url, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $body);
    if (!$res['ok']) {
        $err = $res['data']['error']['message'] ?? ('HTTP ' . $res['code']);
        return ['ok' => false, 'error' => $err, 'code' => $res['code']];
    }
    $totals = ['sessions' => 0, 'activeUsers' => 0, 'screenPageViews' => 0];
    $headers = $res['data']['metricHeaders'] ?? [];
    foreach ($res['data']['rows'] ?? [] as $row) {
        foreach ($row['metricValues'] ?? [] as $i => $m) {
            $name = $headers[$i]['name'] ?? '';
            $totals[$name] += (int)($m['value'] ?? 0);
        }
    }
    $result = [
        'ok'              => true,
        'propertyId'      => $propertyId,
        'startDate'       => $startDate,
        'endDate'         => $endDate,
        'totals'          => $totals,
    ];
    gsk_cache_set($pdo, $cacheKey, $result);
    return $result;
}

function gsk_setup_complete(PDO $pdo): bool {
    $measurement = gsk_setting($pdo, GSK_MEASUREMENT_ID_KEY);
    $propertyId = gsk_setting($pdo, GSK_GA4_PROPERTY_ID_KEY);
    $site = gsk_setting($pdo, GSK_SITE_URL_KEY);
    return ($measurement !== '' || $propertyId !== '') && $site !== '';
}

function gsk_wizard_url(): string {
    return gsk_admin_url('admin/tools/jy-metrics/wizard');
}

function gsk_page_url(string $page): string {
    return gsk_admin_url('admin/tools/jy-metrics' . ($page !== '' ? '/' . $page : ''));
}

function gsk_absolute_page_url(string $page): string {
    return gsk_absolute_admin_url('admin/tools/jy-metrics' . ($page !== '' ? '/' . $page : ''));
}

function gsk_json_response(array $data): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function gsk_redirect(string $url, string $message = '', string $type = 'info'): void {
    if ($message !== '') {
        $sep = strpos($url, '?') === false ? '?' : '&';
        $url .= $sep . $type . '=' . rawurlencode($message);
    }
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>location.replace(' . json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');</script>';
    exit;
}

function gsk_e(string $s): string {
    if (function_exists('e')) return e($s);
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ── Schema initialization ──
add_action('admin_init', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    gsk_ensure_schema($pdo);
});

add_action('plugins_loaded', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) gsk_register_translations($pdo);
});

// ── Frontend snippet injection ──
add_action('jy_head', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    $measurementId = gsk_setting($pdo, GSK_MEASUREMENT_ID_KEY);
    $gtmId = gsk_setting($pdo, GSK_GTM_ID_KEY);
    if ($measurementId !== '' || $gtmId !== '') {
        $trackingConfig = json_encode([
            'measurementId' => $measurementId,
            'gtmId' => $gtmId,
        ], JSON_UNESCAPED_SLASHES);
        echo '<script>(function(c){' . "\n";
        echo "window.dataLayer=window.dataLayer||[];\n";
        echo "if(c.measurementId){window.gtag=window.gtag||function(){dataLayer.push(arguments);};gtag('js',new Date());gtag('config',c.measurementId);}\n";
        echo "var loaded=false;function load(){if(loaded)return;loaded=true;var first=document.scripts[0];function add(src){var script=document.createElement('script');script.async=true;script.src=src;first.parentNode.insertBefore(script,first);}\n";
        echo "if(c.measurementId)add('https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(c.measurementId));\n";
        echo "if(c.gtmId){dataLayer.push({'gtm.start':Date.now(),event:'gtm.js'});add('https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(c.gtmId));}}\n";
        echo "['pointerdown','touchstart','keydown','scroll'].forEach(function(event){addEventListener(event,load,{once:true,passive:true});});\n";
        echo "function afterLoad(){setTimeout(load,5000);}if(document.readyState==='complete')afterLoad();else addEventListener('load',afterLoad,{once:true});\n";
        echo '})(' . $trackingConfig . ');</script>' . "\n";
    }
    $verification = gsk_setting($pdo, GSK_SITE_VERIFICATION_KEY);
    if ($verification !== '') {
        echo '<meta name="google-site-verification" content="' . gsk_e($verification) . '" />' . "\n";
    }
});

add_action('jy_footer', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    $gtmId = gsk_setting($pdo, GSK_GTM_ID_KEY);
    if ($gtmId !== '') {
        $id = gsk_e($gtmId);
        echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }
    $adsense = gsk_setting($pdo, GSK_ADSENSE_CLIENT_KEY);
    if ($adsense !== '') {
        $client = gsk_e($adsense);
        echo "<script async src=\"https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$client}\" crossorigin=\"anonymous\"></script>\n";
    }
});

// ── Uninstall cleanup ──
add_action('plugin_uninstall', function (string $name): void {
    if ($name !== GSK_PLUGIN_NAME) return;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    $keys = [
        GSK_CLIENT_ID_KEY, GSK_CLIENT_SECRET_KEY,
        GSK_ACCESS_TOKEN_KEY, GSK_REFRESH_TOKEN_KEY, GSK_TOKEN_EXPIRES_KEY,
        GSK_USER_EMAIL_KEY, GSK_SCOPES_KEY,
        GSK_SITE_URL_KEY, GSK_MEASUREMENT_ID_KEY, GSK_GA4_PROPERTY_ID_KEY, GSK_GTM_ID_KEY,
        GSK_ADSENSE_CLIENT_KEY, GSK_SITE_VERIFICATION_KEY, GSK_PAGESPEED_API_KEY, GSK_I18N_VERSION_KEY,
    ];
    gsk_disconnect($pdo);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $deleteSettings = $pdo->prepare('DELETE FROM settings WHERE `key` IN (' . $placeholders . ')');
    $deleteSettings->execute($keys);
    if (isset($GLOBALS['__jy_settings_autoload_cache']) && is_array($GLOBALS['__jy_settings_autoload_cache'])) {
        foreach ($keys as $key) unset($GLOBALS['__jy_settings_autoload_cache'][$key]);
    }
    $stmt = $pdo->prepare('DELETE FROM ui_translations WHERE `scope` = ?');
    $stmt->execute([GSK_I18N_SCOPE]);
    $pdo->exec('DROP TABLE IF EXISTS `' . GSK_CACHE_TABLE . '`');
});
