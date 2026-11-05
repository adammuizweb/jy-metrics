<?php
// /plugins/jy-metrics/plugin.php
declare(strict_types=1);

if (!defined('PLUGIN_SYSTEM_LOADED')) {
    return;
}

const GSK_PLUGIN_NAME = 'jy-metrics';
const GSK_CACHE_TABLE = 'google_site_kit_cache';
const GSK_CONTENT_VIEWS_TABLE = 'jy_metrics_content_views_daily';
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
const GSK_CONSENT_MODE_KEY = 'gsk_consent_mode';
const GSK_CONSENT_REGION_SOURCE_KEY = 'gsk_consent_region_source';
const GSK_PRIVACY_URL_KEY = 'gsk_privacy_url';
const GSK_PRIVACY_PAGE_LINKED_KEY = 'gsk_privacy_page_linked';
const GSK_CONSENT_ANALYTICS_KEY = 'gsk_consent_analytics_enabled';
const GSK_CONSENT_ADVERTISING_KEY = 'gsk_consent_advertising_enabled';
const GSK_CONSENT_POLICY_KEY = 'gsk_consent_category_policy';
const GSK_SHOW_DASHBOARD_VIEWS_KEY = 'gsk_show_dashboard_view_counts';
const GSK_SHOW_PUBLIC_VIEWS_KEY = 'gsk_show_public_view_count';
const GSK_POPULAR_POSTS_PERIOD_KEY = 'gsk_popular_posts_period';
const GSK_POPULAR_POSTS_LIMIT_KEY = 'gsk_popular_posts_limit';

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
        GSK_CONSENT_MODE_KEY, GSK_CONSENT_REGION_SOURCE_KEY, GSK_PRIVACY_URL_KEY, GSK_PRIVACY_PAGE_LINKED_KEY,
        GSK_CONSENT_ANALYTICS_KEY, GSK_CONSENT_ADVERTISING_KEY, GSK_CONSENT_POLICY_KEY,
        GSK_SHOW_DASHBOARD_VIEWS_KEY, GSK_SHOW_PUBLIC_VIEWS_KEY,
        GSK_POPULAR_POSTS_PERIOD_KEY, GSK_POPULAR_POSTS_LIMIT_KEY,
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

function gsk_consent_mode(PDO $pdo): string {
    $mode = gsk_setting($pdo, GSK_CONSENT_MODE_KEY, 'off');
    if ($mode === '' || $mode === 'off') return 'off';
    return 'choices';
}

function gsk_consent_categories(PDO $pdo): array {
    return [
        'analytics' => gsk_setting($pdo, GSK_CONSENT_ANALYTICS_KEY, '1') !== '0',
        'advertising' => gsk_setting($pdo, GSK_CONSENT_ADVERTISING_KEY, '1') !== '0',
    ];
}

function gsk_consent_category_policy(PDO $pdo): string {
    $policy = gsk_setting($pdo, GSK_CONSENT_POLICY_KEY, 'legacy');
    return preg_match('/\A[a-f0-9]{64}\z/', $policy) === 1 ? $policy : 'legacy';
}

function gsk_content_view_settings(PDO $pdo): array {
    $period = (int)gsk_setting($pdo, GSK_POPULAR_POSTS_PERIOD_KEY, '30');
    if (!in_array($period, [7, 30, 365], true)) $period = 30;
    $limit = filter_var(gsk_setting($pdo, GSK_POPULAR_POSTS_LIMIT_KEY, '5'), FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 20) $limit = 5;
    return [
        'show_dashboard' => gsk_setting($pdo, GSK_SHOW_DASHBOARD_VIEWS_KEY, '0') === '1',
        'show_public' => gsk_setting($pdo, GSK_SHOW_PUBLIC_VIEWS_KEY, '0') === '1',
        'popular_period' => $period,
        'popular_limit' => $limit,
    ];
}

function gsk_normalize_content_view_input(array $input): ?array {
    return [
        'show_dashboard' => isset($input['show_dashboard_views']) ? '1' : '0',
        'show_public' => isset($input['show_public_views']) ? '1' : '0',
    ];
}

function gsk_analytics_breakdown_config(string $dimension): ?array {
    $configs = [
        'channel' => [
            'ga_dimension' => 'sessionDefaultChannelGroup',
            'dimension_filter' => null,
        ],
        'device' => [
            'ga_dimension' => 'deviceCategory',
            'dimension_filter' => null,
        ],
        'brand' => [
            'ga_dimension' => 'mobileDeviceBranding',
            'dimension_filter' => [
                'filter' => [
                    'fieldName' => 'deviceCategory',
                    'inListFilter' => [
                        'values' => ['mobile', 'tablet'],
                        'caseSensitive' => false,
                    ],
                ],
            ],
        ],
    ];
    return $configs[$dimension] ?? null;
}

function gsk_valid_privacy_url(string $url): bool {
    if ($url === '') return true;
    if (preg_match('/[\x00-\x20\x7F]/', $url) === 1 || str_contains($url, '\\')) return false;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return true;
    $parts = parse_url($url);
    return is_array($parts)
        && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        && (string)($parts['host'] ?? '') !== '';
}

function gsk_help_tooltip(string $id, string $label, string $description): string {
    return '<button type="button" class="gsk-metric__help" aria-label="' . gsk_e($label) . '" aria-describedby="' . gsk_e($id) . '">?</button>'
        . '<span class="gsk-metric__tooltip" id="' . gsk_e($id) . '" role="tooltip">' . gsk_e($description) . '</span>';
}

function gsk_register_translations(PDO $pdo): void {
    if (gsk_setting($pdo, GSK_I18N_VERSION_KEY) === '15') return;

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
        'Traffic breakdown' => 'Rincian traffic',
        'Devices' => 'Perangkat',
        'Brands' => 'Merek',
        'Sessions' => 'Sesi',
        'Desktop' => 'Desktop',
        'Mobile' => 'Seluler',
        'Tablet' => 'Tablet',
        'Unknown' => 'Tidak diketahui',
        'Other' => 'Lainnya',
        'Compare sessions by acquisition channel, device category, or mobile and tablet device brand.' => 'Bandingkan sesi berdasarkan saluran akuisisi, kategori perangkat, atau merek perangkat seluler dan tablet.',
        'Sessions grouped by GA4 default traffic source categories.' => 'Sesi yang dikelompokkan berdasarkan kategori sumber traffic bawaan GA4.',
        'Sessions grouped by device category.' => 'Sesi yang dikelompokkan berdasarkan kategori perangkat.',
        'Mobile and tablet sessions grouped by device brand.' => 'Sesi seluler dan tablet yang dikelompokkan berdasarkan merek perangkat.',
        'Could not load traffic breakdown' => 'Tidak dapat memuat rincian traffic',
        'No data' => 'Tidak ada data',
        'Previous' => 'Sebelumnya',
        'Next' => 'Berikutnya',
        'Showing {start}-{end} of {total}' => 'Menampilkan {start}-{end} dari {total}',
        'Loading…' => 'Memuat…',
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
        'Privacy choices' => 'Pilihan privasi',
        'Cookies on this site' => 'Cookie di situs ini',
        'We use optional analytics and advertising cookies. You can accept or reject them without losing access to the site.' => 'Kami menggunakan cookie analitik dan iklan yang bersifat opsional. Anda dapat menerima atau menolaknya tanpa kehilangan akses ke situs.',
        'We use optional analytics and advertising services to understand site usage. They are loaded only after you accept.' => 'Kami menggunakan layanan analitik dan iklan opsional untuk memahami penggunaan situs. Layanan tersebut hanya dimuat setelah Anda menyetujuinya.',
        'Accept all' => 'Terima semua',
        'Accept cookies' => 'Terima cookie',
        'Reject optional' => 'Tolak yang opsional',
        'Privacy policy' => 'Kebijakan privasi',
        'We use optional analytics and advertising services. Choose what this site may load.' => 'Kami menggunakan layanan analitik dan iklan opsional. Pilih layanan yang boleh dimuat situs ini.',
        'We use optional analytics services. Choose what this site may load.' => 'Kami menggunakan layanan analitik opsional. Pilih layanan yang boleh dimuat situs ini.',
        'We use optional advertising services. Choose what this site may load.' => 'Kami menggunakan layanan iklan opsional. Pilih layanan yang boleh dimuat situs ini.',
        'Manage choices' => 'Atur pilihan',
        'Close and reject optional services' => 'Tutup dan tolak layanan opsional',
        'Necessary' => 'Diperlukan',
        'Required for core site functions and always active.' => 'Diperlukan untuk fungsi inti situs dan selalu aktif.',
        'Analytics' => 'Analitik',
        'Helps understand site usage through Google Analytics.' => 'Membantu memahami penggunaan situs melalui Google Analytics.',
        'Advertising' => 'Periklanan',
        'Allows Tag Manager, AdSense, and advertising personalization.' => 'Mengizinkan Tag Manager, AdSense, dan personalisasi iklan.',
        'Always active' => 'Selalu aktif',
        'Save choices' => 'Simpan pilihan',
        'Reject all' => 'Tolak semua',
        'Global Privacy Control is active. Advertising remains disabled.' => 'Global Privacy Control aktif. Periklanan tetap dinonaktifkan.',
        'Tag Manager is treated as advertising because its container may run marketing tags.' => 'Tag Manager diperlakukan sebagai periklanan karena containernya dapat menjalankan tag pemasaran.',
        '%s view' => '%s tampilan',
        '%s views' => '%s tampilan',
        'Popular Posts' => 'Artikel Populer',
        'Published articles ranked by cached Google Analytics views.' => 'Artikel terbit yang diurutkan berdasarkan tampilan Google Analytics yang disimpan dalam cache.',
        'No popular posts in this period.' => 'Belum ada artikel populer dalam periode ini.',
        'Rank %d' => 'Peringkat %d',
        'Open Jy Metrics dashboard' => 'Buka dasbor Jy Metrics',
        'Content Views' => 'Tampilan Konten',
        'Google Analytics cache · visibility %s' => 'Cache Google Analytics · visibilitas %s',
        'enabled' => 'aktif',
        'disabled' => 'nonaktif',
        'View counts come from Google Analytics and are cached in this database. Public requests only read the cache; an authorized dashboard request refreshes stale data at most once per hour.' => 'Jumlah tampilan berasal dari Google Analytics dan disimpan dalam cache database ini. Permintaan publik hanya membaca cache; permintaan dasbor yang berwenang memperbarui data kedaluwarsa paling banyak sekali per jam.',
        'Show dashboard view counts' => 'Tampilkan jumlah tampilan di dasbor',
        'Add cached all-time Google Analytics counts to Article and Page row actions for report readers.' => 'Tambahkan jumlah sepanjang waktu Google Analytics yang disimpan dalam cache ke tindakan baris Artikel dan Halaman untuk pembaca laporan.',
        'Show public view count on single Article/Page' => 'Tampilkan jumlah tampilan publik pada Artikel/Halaman tunggal',
        'Prepend an accessible all-time count to single Article and Page content only.' => 'Tambahkan jumlah sepanjang waktu yang aksesibel di awal konten Artikel dan Halaman tunggal saja.',
        'Popular Posts period' => 'Periode Artikel Populer',
        '%d days' => '%d hari',
        'Popular Posts item limit' => 'Batas item Artikel Populer',
        'Global values used by every Jy Metrics Popular Posts Sidebar widget. Period and item limit are not configured per widget.' => 'Nilai global yang digunakan oleh setiap widget Sidebar Artikel Populer Jy Metrics. Periode dan batas item tidak diatur per widget.',
        'Invalid privacy consent or content view settings.' => 'Pengaturan persetujuan privasi atau tampilan konten tidak valid.',
        'Could not load search queries' => 'Tidak dapat memuat kueri penelusuran',
        'Published articles ranked by cached Google Analytics views using each widget\'s period and item limit.' => 'Artikel terbit yang diurutkan berdasarkan tampilan Google Analytics yang disimpan dalam cache menggunakan periode dan batas item setiap widget.',
        'Popular period' => 'Periode populer',
        'Today' => 'Hari ini',
        'Weekly (7 days)' => 'Mingguan (7 hari)',
        'Monthly (30 days)' => 'Bulanan (30 hari)',
        'Yearly (365 days)' => 'Tahunan (365 hari)',
        'Item limit' => 'Batas item',
        'Configure each Popular Posts widget in Sidebar Settings.' => 'Atur setiap widget Artikel Populer di Pengaturan Sidebar.',
        'Open Sidebar Settings' => 'Buka Pengaturan Sidebar',
    ];
    $germanTranslations = [
        '%s view' => '%s Aufruf',
        '%s views' => '%s Aufrufe',
        'Popular Posts' => 'Beliebte Beiträge',
        'No popular posts in this period.' => 'Keine beliebten Beiträge in diesem Zeitraum.',
        'Rank %d' => 'Rang %d',
        'Popular Posts period' => 'Zeitraum beliebter Beiträge',
        '%d days' => '%d Tage',
        'Popular Posts item limit' => 'Anzahl beliebter Beiträge',
        'Published articles ranked by cached Google Analytics views using each widget\'s period and item limit.' => 'Veröffentlichte Artikel, sortiert nach zwischengespeicherten Google-Analytics-Aufrufen im Zeitraum und mit dem Beitragslimit des jeweiligen Widgets.',
        'Popular period' => 'Beliebter Zeitraum',
        'Today' => 'Heute',
        'Weekly (7 days)' => 'Wöchentlich (7 Tage)',
        'Monthly (30 days)' => 'Monatlich (30 Tage)',
        'Yearly (365 days)' => 'Jährlich (365 Tage)',
        'Item limit' => 'Beitragslimit',
        'Configure each Popular Posts widget in Sidebar Settings.' => 'Konfigurieren Sie jedes Widget für beliebte Beiträge in den Sidebar-Einstellungen.',
        'Open Sidebar Settings' => 'Sidebar-Einstellungen öffnen',
        'View counts come from Google Analytics and are cached in this database. Public requests only read the cache; an authorized dashboard request refreshes stale data at most once per hour.' => 'Aufrufzahlen stammen aus Google Analytics und werden in dieser Datenbank zwischengespeichert. Öffentliche Anfragen lesen nur den Cache; eine autorisierte Dashboard-Anfrage aktualisiert veraltete Daten höchstens einmal pro Stunde.',
    ];

    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO ui_translations (`scope`, `source`, `value`, `locale`) VALUES (?, ?, ?, ?)');
        foreach ($translations as $source => $value) {
            $stmt->execute([GSK_I18N_SCOPE, $source, $value, 'id']);
        }
        foreach ($germanTranslations as $source => $value) {
            $stmt->execute([GSK_I18N_SCOPE, $source, $value, 'de']);
        }
        gsk_save_setting($pdo, GSK_I18N_VERSION_KEY, '15');
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

function gsk_cache_set(PDO $pdo, string $key, array $data): bool {
    try {
        $sql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "INSERT INTO `" . GSK_CACHE_TABLE . "` (`cache_key`, `data`) VALUES (?, ?) ON CONFLICT(`cache_key`) DO UPDATE SET `data` = excluded.`data`, `created_at` = CURRENT_TIMESTAMP"
            : "INSERT INTO `" . GSK_CACHE_TABLE . "` (`cache_key`, `data`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `created_at` = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$key, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    } catch (Throwable $e) {
        error_log('[jy-metrics] Cache write failed: ' . $e->getMessage());
        return false;
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

function gsk_ga4_content_periods(): array {
    return [1, 7, 30, 365, 'all'];
}

function gsk_ga4_content_period_range(int|string $period): ?array {
    return match ($period) {
        1 => ['start' => 'today', 'end' => 'today'],
        7 => ['start' => '7daysAgo', 'end' => 'yesterday'],
        30 => ['start' => '30daysAgo', 'end' => 'yesterday'],
        365 => ['start' => '365daysAgo', 'end' => 'yesterday'],
        'all' => ['start' => '2020-10-14', 'end' => 'yesterday'],
        default => null,
    };
}

function gsk_ga4_content_host(PDO $pdo): string {
    $candidates = [];
    if (function_exists('settings_get')) $candidates[] = (string)settings_get($pdo, 'site_host', '');
    $candidates[] = (string)($_SERVER['HTTP_HOST'] ?? '');
    $searchProperty = strtolower(trim(gsk_setting($pdo, GSK_SITE_URL_KEY)));
    if ($searchProperty !== '' && !str_starts_with($searchProperty, 'sc-domain:')) $candidates[] = $searchProperty;
    foreach ($candidates as $candidate) {
        $candidate = strtolower(trim($candidate));
        $host = (string)(parse_url(str_contains($candidate, '://') ? $candidate : 'https://' . $candidate, PHP_URL_HOST) ?: '');
        if (preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,62})\.)*[a-z0-9](?:[a-z0-9-]{0,62})\z/D', $host) === 1) return $host;
    }
    return '';
}

function gsk_normalize_page_path(string $value): string {
    $path = parse_url(trim($value), PHP_URL_PATH);
    if (!is_string($path) || $path === '' || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) return '';
    $path = rawurldecode($path);
    $path = preg_replace('#/{2,}#', '/', $path) ?? '';
    if ($path === '' || !str_starts_with($path, '/')) return '';
    return $path === '/' ? '/' : rtrim($path, '/');
}

function gsk_ga4_content_cache_key(string $propertyId, string $host, int|string $period): string {
    return 'gsk_ga4_content_v1_' . md5($propertyId . "\0" . $host . "\0" . (string)$period);
}

function gsk_ga4_content_snapshot(PDO $pdo, int|string $period, int $ttl = 86400): ?array {
    if (!in_array($period, gsk_ga4_content_periods(), true)) return null;
    $propertyId = ltrim(gsk_setting($pdo, GSK_GA4_PROPERTY_ID_KEY), '/');
    if ($propertyId === '') return null;
    if (!str_starts_with($propertyId, 'properties/')) $propertyId = 'properties/' . $propertyId;
    $host = gsk_ga4_content_host($pdo);
    if ($host === '') return null;
    $cacheKey = gsk_ga4_content_cache_key($propertyId, $host, $period);
    $runtimeKey = $cacheKey . ':' . $ttl;
    if (array_key_exists($runtimeKey, $GLOBALS['gsk_ga4_content_snapshots'] ?? [])) {
        return $GLOBALS['gsk_ga4_content_snapshots'][$runtimeKey];
    }
    $snapshot = gsk_cache_get($pdo, $cacheKey, $ttl);
    $snapshot = is_array($snapshot['views'] ?? null) ? $snapshot : null;
    $GLOBALS['gsk_ga4_content_snapshots'][$runtimeKey] = $snapshot;
    return $snapshot;
}

function gsk_ga4_content_required_periods(PDO $pdo): array {
    $periods = [];
    try {
        $stmt = $pdo->prepare('SELECT `config` FROM `sidebar_zone_items` WHERE `type` = ? AND `active` = 1');
        $stmt->execute(['jy_metrics_popular_posts']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $configJson) {
            $config = json_decode((string)$configJson, true);
            $period = filter_var(is_array($config) ? ($config['period'] ?? 30) : 30, FILTER_VALIDATE_INT);
            if ($period !== false && in_array($period, [1, 7, 30, 365], true)) $periods[$period] = $period;
        }
    } catch (Throwable $e) {
        return [];
    }
    $settings = gsk_content_view_settings($pdo);
    if ($settings['show_dashboard'] || $settings['show_public']) $periods['all'] = 'all';
    $ordered = [];
    foreach (gsk_ga4_content_periods() as $period) if (isset($periods[$period])) $ordered[] = $period;
    return $ordered;
}

function gsk_ga4_content_request(int|string $period, string $host, int $offset = 0): ?array {
    $range = gsk_ga4_content_period_range($period);
    if ($range === null || $host === '' || $offset < 0) return null;
    $request = [
        'dateRanges' => [['startDate' => $range['start'], 'endDate' => $range['end']]],
        'dimensions' => [['name' => 'hostName'], ['name' => 'pagePath']],
        'metrics' => [['name' => 'screenPageViews']],
        'orderBys' => [
            ['dimension' => ['dimensionName' => 'hostName']],
            ['dimension' => ['dimensionName' => 'pagePath']],
        ],
        'limit' => 10000,
        'returnPropertyQuota' => true,
        'dimensionFilter' => ['filter' => [
            'fieldName' => 'hostName',
            'stringFilter' => ['matchType' => 'EXACT', 'value' => $host, 'caseSensitive' => false],
        ]],
    ];
    if ($offset > 0) $request['offset'] = $offset;
    return $request;
}

function gsk_ga4_content_parse(array $response, string $host): array {
    $views = [];
    foreach ($response['rows'] ?? [] as $row) {
        $dimensions = $row['dimensionValues'] ?? [];
        $rowHost = strtolower(trim((string)($dimensions[0]['value'] ?? '')));
        if ($host !== '' && !hash_equals($host, $rowHost)) continue;
        $path = gsk_normalize_page_path((string)($dimensions[1]['value'] ?? ''));
        $count = max(0, (int)($row['metricValues'][0]['value'] ?? 0));
        if ($path !== '' && $count > 0) $views[$path] = ($views[$path] ?? 0) + $count;
    }
    arsort($views, SORT_NUMERIC);
    return $views;
}

function gsk_refresh_ga4_content_views(PDO $pdo, ?array $periods = null, bool $force = false, ?callable $transport = null): array {
    $periods ??= gsk_ga4_content_required_periods($pdo);
    $periods = array_values(array_unique(array_filter($periods, static fn($period): bool => in_array($period, gsk_ga4_content_periods(), true)), SORT_REGULAR));
    if ($periods === []) return ['ok' => true, 'refreshed' => [], 'cached' => []];

    $propertyId = ltrim(gsk_setting($pdo, GSK_GA4_PROPERTY_ID_KEY), '/');
    if ($propertyId === '') return ['ok' => false, 'error' => 'No GA4 property configured', 'refreshed' => [], 'cached' => []];
    if (!str_starts_with($propertyId, 'properties/')) $propertyId = 'properties/' . $propertyId;
    $host = gsk_ga4_content_host($pdo);
    if ($host === '') return ['ok' => false, 'error' => 'No exact site hostname is configured', 'refreshed' => [], 'cached' => []];
    $pending = [];
    $cached = [];
    foreach ($periods as $period) {
        $key = gsk_ga4_content_cache_key($propertyId, $host, $period);
        if (!$force && gsk_cache_get($pdo, $key, 3600) !== null) $cached[] = $period;
        else $pending[] = $period;
    }
    if ($pending === []) return ['ok' => true, 'refreshed' => [], 'cached' => $cached];

    $errorKey = 'gsk_ga4_content_error_' . md5($propertyId . "\0" . $host);
    if (!$force && gsk_cache_get($pdo, $errorKey, 900) !== null) {
        return ['ok' => false, 'error' => 'Google Analytics refresh is temporarily throttled after an error.', 'refreshed' => [], 'cached' => $cached];
    }

    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $lockName = 'jym-ga4-' . substr(hash('sha256', $propertyId . "\0" . $host), 0, 48);
    $locked = true;
    if ($driver === 'mysql') {
        try {
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $lock->execute([$lockName]);
            $locked = (int)$lock->fetchColumn() === 1;
        } catch (Throwable $e) {
            $locked = false;
        }
    }
    if (!$locked) return ['ok' => true, 'refreshed' => [], 'cached' => $cached, 'busy' => true];

    $refreshed = [];
    try {
        if (!$force) {
            if (gsk_cache_get($pdo, $errorKey, 900) !== null) {
                return ['ok' => false, 'error' => 'Google Analytics refresh is temporarily throttled after an error.', 'refreshed' => [], 'cached' => $cached];
            }
            $stillPending = [];
            foreach ($pending as $period) {
                $key = gsk_ga4_content_cache_key($propertyId, $host, $period);
                if (gsk_cache_get($pdo, $key, 3600) !== null) $cached[] = $period;
                else $stillPending[] = $period;
            }
            $pending = $stillPending;
            if ($pending === []) return ['ok' => true, 'refreshed' => [], 'cached' => $cached];
        }
        $token = gsk_get_access_token($pdo);
        if ($token === null) throw new RuntimeException('Google Analytics is not connected.');
        $url = 'https://analyticsdata.googleapis.com/v1beta/' . $propertyId . ':runReport';
        $transport ??= static fn(string $requestUrl, array $headers, string $body): array => gsk_json_request($requestUrl, 'POST', $headers, $body);
        foreach ($pending as $period) {
            $views = [];
            $quota = null;
            $offset = 0;
            do {
                $request = gsk_ga4_content_request($period, $host, $offset);
                if ($request === null) throw new RuntimeException('Invalid Google Analytics content report request.');
                $response = $transport($url, ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], json_encode($request, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                if (($response['ok'] ?? false) !== true || !is_array($response['data'] ?? null)) {
                    $message = (string)($response['data']['error']['message'] ?? $response['error'] ?? ('HTTP ' . (int)($response['code'] ?? 0)));
                    throw new RuntimeException($message !== '' ? $message : 'Google Analytics request failed.');
                }
                $pageRows = is_array($response['data']['rows'] ?? null) ? $response['data']['rows'] : [];
                foreach (gsk_ga4_content_parse($response['data'], $host) as $path => $count) {
                    $views[$path] = ($views[$path] ?? 0) + $count;
                }
                $quota = $response['data']['propertyQuota'] ?? $quota;
                $rowCount = max(0, (int)($response['data']['rowCount'] ?? count($pageRows)));
                if ($rowCount > 100000) throw new RuntimeException('Google Analytics content report exceeds the supported 100,000-row limit.');
                $offset += count($pageRows);
            } while ($pageRows !== [] && $offset < $rowCount);
            arsort($views, SORT_NUMERIC);
            $range = gsk_ga4_content_period_range($period);
            $snapshot = [
                'source' => 'google-analytics',
                'propertyId' => $propertyId,
                'host' => $host,
                'period' => $period,
                'startDate' => $range['start'],
                'endDate' => $range['end'],
                'fetchedAt' => gmdate(DATE_ATOM),
                'views' => $views,
                'quota' => $quota,
            ];
            if (!gsk_cache_set($pdo, gsk_ga4_content_cache_key($propertyId, $host, $period), $snapshot)) {
                throw new RuntimeException('Could not persist the Google Analytics content cache.');
            }
            $GLOBALS['gsk_ga4_content_snapshots'] = [];
            $refreshed[] = $period;
        }
        return ['ok' => true, 'refreshed' => $refreshed, 'cached' => $cached];
    } catch (Throwable $e) {
        gsk_cache_set($pdo, $errorKey, ['message' => $e->getMessage(), 'failedAt' => gmdate(DATE_ATOM)]);
        error_log('[jy-metrics] Google Analytics content cache refresh failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage(), 'refreshed' => $refreshed, 'cached' => $cached];
    } finally {
        if ($driver === 'mysql') {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            } catch (Throwable $e) {
                error_log('[jy-metrics] Could not release Google Analytics refresh lock: ' . $e->getMessage());
            }
        }
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
    $cacheKey = 'gsk_sc_stats_v3_' . md5($siteUrl . $startDate . $endDate);
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
        'rowLimit'  => 25000,
    ]);
    $queriesRes = gsk_json_request($url, 'POST', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ], $queriesBody);
    if (!$queriesRes['ok']) {
        $err = $queriesRes['data']['error']['message'] ?? ($queriesRes['error'] ?? ('HTTP ' . ($queriesRes['code'] ?? 0)));
        return ['ok' => false, 'error' => $err, 'code' => (int)($queriesRes['code'] ?? 0)];
    }
    $queries = [];
    foreach ($queriesRes['data']['rows'] ?? [] as $r) {
        $queries[] = [
            'query'       => $r['keys'][0] ?? '',
            'clicks'      => (float)($r['clicks'] ?? 0),
            'impressions' => (float)($r['impressions'] ?? 0),
            'ctr'         => (float)($r['ctr'] ?? 0),
            'position'    => (float)($r['position'] ?? 0),
        ];
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

function gsk_content_locale(): string {
    $locale = function_exists('get_locale') ? get_locale() : '';
    if (!is_string($locale)) return '';
    $locale = strtolower(str_replace('_', '-', trim($locale)));
    return strlen($locale) <= 16 && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $locale) === 1 ? $locale : '';
}

function gsk_view_payload(array $entity, array $context): ?array {
    if (($context['schema'] ?? null) !== 1
        || ($context['status'] ?? null) !== 'published'
        || ($context['is_public'] ?? null) !== true
        || ($context['request_method'] ?? null) !== 'GET'
        || !is_int($context['page'] ?? null)
        || $context['page'] < 1
        || !is_int($entity['id'] ?? null)
        || $entity['id'] < 1) return null;

    $kind = $entity['kind'] ?? null;
    $type = $entity['type'] ?? null;
    $route = $context['route'] ?? null;
    $routes = ['article' => 'single.post', 'page' => 'single.page', 'theme' => 'single.theme'];
    $valid = ($kind === 'post' && is_string($type) && isset($routes[$type]) && $route === $routes[$type])
        || ($kind === 'category' && $type === 'category' && $route === 'list.category');
    if (!$valid) return null;
    return ['kind' => $kind, 'type' => $type, 'id' => $entity['id'], 'locale' => gsk_content_locale()];
}

function gsk_frontend_entity_viewed(mixed $entity, mixed $context, mixed $pdo): void {
    if (!is_array($entity) || !is_array($context) || !($pdo instanceof PDO)) return;
    $view = gsk_view_payload($entity, $context);
    if ($view === null) return;
    if ($view['kind'] === 'post' && in_array($view['type'], ['article', 'page'], true)) {
        $GLOBALS['gsk_current_public_view_entity'] = $view;
    }
}

function gsk_ga4_content_tracking_context(): array {
    $current = $GLOBALS['gsk_current_public_view_entity'] ?? null;
    if (!is_array($current) || !in_array($current['type'] ?? null, ['article', 'page'], true)
        || (int)($current['id'] ?? 0) < 1) return [];
    return [
        'contentId' => (string)$current['type'] . ':' . (int)$current['id'],
        'contentType' => (string)$current['type'],
        'contentLocale' => (string)($current['locale'] ?? ''),
    ];
}

function gsk_ga4_post_view_count(PDO $pdo, array $post, int|string $period = 'all', ?array $paths = null): int {
    $snapshot = gsk_ga4_content_snapshot($pdo, $period);
    if ($snapshot === null) return 0;
    if ($paths === null) {
        $paths = gsk_ga4_content_paths($pdo, [$post])[(int)($post['id'] ?? 0)] ?? [];
        $resolver = ($post['type'] ?? null) === 'page' ? 'get_page_permalink' : 'get_post_permalink';
        if (function_exists($resolver)) {
            try {
                $paths[] = gsk_normalize_page_path($resolver($post));
            } catch (Throwable $e) {
                // The bulk route and fallback paths remain usable.
            }
        }
    }
    $count = 0;
    foreach (array_unique(array_filter($paths, 'is_string')) as $path) {
        $count += max(0, (int)($snapshot['views'][gsk_normalize_page_path($path)] ?? 0));
    }
    return $count;
}

function gsk_ga4_content_fallback_path(PDO $pdo, array $post): string {
    $locale = strtolower(trim((string)($post['ct_locale'] ?? '')));
    $translatedSlug = trim((string)($post['ct_translated_slug'] ?? ''));
    if ($locale !== '' && $translatedSlug !== '' && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $locale) === 1) {
        return gsk_normalize_page_path('/' . rawurlencode($locale) . '/' . rawurlencode($translatedSlug) . '/');
    }
    $type = ($post['type'] ?? null) === 'page' ? 'page' : 'post';
    $structure = function_exists('get_permalink_structure') ? (string)get_permalink_structure($pdo, $type) : '/%slug%/';
    if (str_contains($structure, '%cat%')) {
        $resolver = $type === 'page' ? 'get_page_permalink' : 'get_post_permalink';
        if (!function_exists($resolver)) return '';
        try {
            return gsk_normalize_page_path($resolver($post));
        } catch (Throwable $e) {
            return '';
        }
    }
    $created = (string)($post['created_at'] ?? '');
    $timestamp = $created !== '' ? strtotime($created) : false;
    $path = str_replace(
        ['%year%', '%monthnum%', '%day%', '%slug%'],
        [$timestamp === false ? '' : date('Y', $timestamp), $timestamp === false ? '' : date('m', $timestamp), $timestamp === false ? '' : date('d', $timestamp), rawurlencode((string)($post['slug'] ?? ''))],
        $structure
    );
    if (!str_starts_with($path, '/')) $path = '/' . $path;
    return gsk_normalize_page_path($path);
}

function gsk_ga4_content_paths(PDO $pdo, array $posts): array {
    $paths = [];
    $groups = [];
    foreach ($posts as $post) {
        $postId = (int)($post['id'] ?? 0);
        if ($postId < 1) continue;
        $locale = strtolower(trim((string)($post['ct_locale'] ?? '')));
        if ($locale === '' || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $locale) !== 1) $locale = '';
        $groups[$locale][$postId] = $postId;
    }
    try {
        foreach ($groups as $locale => $ids) {
            foreach (array_chunk(array_values($ids), 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmt = $pdo->prepare('SELECT `post_id`, `path`, `locale` FROM `content_routes` WHERE `locale` = ? AND `post_id` IN (' . $placeholders . ')');
                $stmt->execute(array_merge([$locale], $chunk));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $route) {
                    $routeLocale = (string)($route['locale'] ?? '');
                    $path = function_exists('content_route_public_path')
                        ? content_route_public_path((string)$route['path'], $routeLocale)
                        : '/' . ($routeLocale !== '' ? rawurlencode($routeLocale) . '/' : '') . trim((string)$route['path'], '/') . '/';
                    $path = gsk_normalize_page_path($path);
                    if ($path !== '') $paths[(int)$route['post_id']][] = $path;
                }
            }
        }
    } catch (Throwable $e) {
        // Legacy installations may not have canonical routes for every article.
    }
    foreach ($posts as $post) {
        $postId = (int)($post['id'] ?? 0);
        if ($postId < 1) continue;
        if (($paths[$postId] ?? []) === []) {
            $fallback = gsk_ga4_content_fallback_path($pdo, $post);
            if ($fallback !== '') $paths[$postId][] = $fallback;
        }
        $paths[$postId] = array_values(array_unique($paths[$postId] ?? []));
    }
    return $paths;
}

function gsk_ga4_content_route_locale(): string {
    $locale = strtolower(trim((string)($GLOBALS['ct_request_locale'] ?? '')));
    if ($locale === '') {
        $current = strtolower(trim(gsk_content_locale()));
        $default = function_exists('content_default_locale')
            ? strtolower(trim((string)content_default_locale()))
            : $current;
        if ($current !== '' && $current !== $default) $locale = $current;
    }
    return preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $locale) === 1 ? $locale : '';
}

function gsk_ga4_content_candidate_scores(PDO $pdo, array $snapshot, array $where = [], array $params = [], ?string $locale = null): array {
    $views = is_array($snapshot['views'] ?? null) ? $snapshot['views'] : [];
    $locale ??= gsk_ga4_content_route_locale();
    if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $locale) !== 1) $locale = '';
    $routeViews = [];
    foreach ($views as $path => $count) {
        $path = gsk_normalize_page_path((string)$path);
        $count = max(0, (int)$count);
        if ($path === '' || $count < 1) continue;
        $relative = trim($path, '/');
        if ($locale !== '') {
            $prefix = $locale . '/';
            if (!str_starts_with($relative, $prefix)) continue;
            $relative = substr($relative, strlen($prefix));
        }
        if ($relative === '') continue;
        $routeViews[$relative] = ['path' => $path, 'count' => $count];
    }
    if ($routeViews === []) return [];

    $where = array_merge(['p.type = \'article\'', 'p.status = \'published\'', 'p.is_deleted = 0'], array_values(array_filter(
        $where,
        static fn(mixed $clause): bool => is_string($clause) && trim($clause) !== ''
    )));
    $matchedPaths = [];
    $add = static function (int $postId, string $relative) use (&$matchedPaths, $routeViews): void {
        $match = $routeViews[$relative] ?? null;
        if ($postId < 1 || !is_array($match)) return;
        $matchedPaths[$postId][(string)$match['path']] = (int)$match['count'];
    };

    foreach (array_chunk(array_keys($routeViews), 500) as $chunkIndex => $routes) {
        $bindings = $params;
        $bindings[':gsk_route_locale'] = $locale;
        $placeholders = [];
        foreach ($routes as $index => $route) {
            $name = ':gsk_route_' . $chunkIndex . '_' . $index;
            $placeholders[] = $name;
            $bindings[$name] = $route;
        }
        try {
            $stmt = $pdo->prepare('SELECT p.id, cr.path FROM content_routes cr INNER JOIN posts p ON p.id = cr.post_id WHERE cr.locale = :gsk_route_locale AND cr.path IN (' . implode(',', $placeholders) . ') AND ' . implode(' AND ', $where));
            $stmt->execute($bindings);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $add((int)$row['id'], (string)$row['path']);
        } catch (Throwable $e) {
            // Legacy installations can still match candidates through their fallback permalink.
        }
        if ($locale !== '') {
            try {
                $translationBindings = $params;
                $translationBindings[':gsk_translation_locale'] = $locale;
                foreach ($routes as $index => $route) {
                    $translationBindings[':gsk_route_' . $chunkIndex . '_' . $index] = $route;
                }
                $stmt = $pdo->prepare("SELECT p.id, pt.slug FROM post_translations pt INNER JOIN posts p ON p.id = pt.post_id WHERE pt.locale = :gsk_translation_locale AND pt.status = 'published' AND TRIM(pt.title) <> '' AND TRIM(pt.slug) <> '' AND pt.slug IN (" . implode(',', $placeholders) . ') AND ' . implode(' AND ', $where));
                $stmt->execute($translationBindings);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $add((int)$row['id'], (string)$row['slug']);
            } catch (Throwable $e) {
                // Content Translation is optional.
            }
        }
    }

    if ($locale === '') {
        try {
            $bindings = $params;
            $bindings[':gsk_fallback_locale'] = $locale;
            $stmt = $pdo->prepare('SELECT p.id, p.title, p.slug, p.type, p.created_at FROM posts p WHERE ' . implode(' AND ', $where)
                . ' AND NOT EXISTS (SELECT 1 FROM content_routes gsk_existing_route WHERE gsk_existing_route.post_id = p.id AND gsk_existing_route.locale = :gsk_fallback_locale)');
            $stmt->execute($bindings);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $path = gsk_ga4_content_fallback_path($pdo, $row);
                $normalized = gsk_normalize_page_path($path);
                if ($normalized !== '' && isset($views[$normalized])) {
                    $matchedPaths[(int)$row['id']][$normalized] = max(0, (int)$views[$normalized]);
                }
            }
        } catch (Throwable $e) {
            // A missing route table makes the route query above fail closed as well.
        }
    }

    $scores = [];
    foreach ($matchedPaths as $postId => $matches) {
        $scores[$postId] = array_sum($matches);
    }
    arsort($scores, SORT_NUMERIC);
    return $scores;
}

function gsk_localized_view_count(int $count): string {
    $count = max(0, $count);
    $locale = function_exists('get_locale') ? get_locale() : 'en';
    $number = in_array(substr(strtolower($locale), 0, 2), ['de', 'id'], true)
        ? number_format($count, 0, ',', '.')
        : number_format($count);
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $localized = $formatter->format($count);
        if (is_string($localized)) $number = $localized;
    }
    return sprintf(gsk_t($count === 1 ? '%s view' : '%s views'), $number);
}

function gsk_public_view_count(string $html, array $post = []): string {
    if (str_contains($html, 'class="jym-public-views"')) return $html;
    $current = $GLOBALS['gsk_current_public_view_entity'] ?? null;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!is_array($current) || !($pdo instanceof PDO) || !gsk_content_view_settings($pdo)['show_public']) return $html;
    if (!in_array($current['type'] ?? null, ['article', 'page'], true)
        || (int)($post['id'] ?? 0) !== $current['id']
        || ($post['type'] ?? null) !== $current['type']) return $html;
    $label = gsk_localized_view_count(gsk_ga4_post_view_count($pdo, $post));
    $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    return '<div class="jym-public-views" aria-label="' . gsk_e($label) . '">' . $icon . '<span>' . gsk_e($label) . '</span></div>' . $html;
}

function gsk_can_show_dashboard_views(PDO $pdo, array $context): bool {
    $actorId = (int)($context['actor_id'] ?? 0);
    return gsk_content_view_settings($pdo)['show_dashboard']
        && $actorId > 0 && function_exists('user_can')
        && user_can($pdo, $actorId, 'plugin.jy-metrics.reports.read');
}

function gsk_post_list_view_select(string $select, string $where, array $context = []): string {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO) || ($context['schema'] ?? null) !== 1
        || !in_array($context['type'] ?? null, ['article', 'page'], true)
        || !gsk_can_show_dashboard_views($pdo, $context)) return $select;
    return $select . ", COALESCE((SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(gsk_cr.path) ORDER BY gsk_cr.id SEPARATOR ','), ']') FROM content_routes gsk_cr WHERE gsk_cr.post_id = p.id AND gsk_cr.locale = ''), '[]') AS gsk_ga4_route_paths";
}

function gsk_admin_content_paths(PDO $pdo, array $row): ?array {
    if (!array_key_exists('gsk_ga4_route_paths', $row)) return null;
    $routes = json_decode((string)$row['gsk_ga4_route_paths'], true);
    if (!is_array($routes)) return null;
    $paths = [];
    foreach ($routes as $route) {
        if (!is_string($route)) continue;
        $path = gsk_normalize_page_path('/' . trim($route, '/') . '/');
        if ($path !== '') $paths[] = $path;
    }
    if ($paths === []) {
        $resolver = ($row['type'] ?? null) === 'page' ? 'get_page_permalink' : 'get_post_permalink';
        try {
            $fallback = function_exists($resolver)
                ? gsk_normalize_page_path($resolver($row))
                : gsk_ga4_content_fallback_path($pdo, $row);
        } catch (Throwable $e) {
            $fallback = gsk_ga4_content_fallback_path($pdo, $row);
        }
        if ($fallback !== '') $paths[] = $fallback;
    }
    return array_values(array_unique($paths));
}

function gsk_admin_content_view_action(mixed $items, array $row, array $context, PDO $pdo): array {
    if (!is_array($items)) $items = [];
    if (($context['schema'] ?? null) !== 1
        || !in_array($context['content_type'] ?? null, ['article', 'page'], true)
        || !gsk_can_show_dashboard_views($pdo, $context)) return $items;
    $row['type'] = (string)$context['content_type'];
    $items[] = [
        'key' => 'jy-metrics.views',
        'label' => gsk_localized_view_count(gsk_ga4_post_view_count($pdo, $row, 'all', gsk_admin_content_paths($pdo, $row))),
        'icon' => 'eye',
        'url' => gsk_page_url(''),
        'title' => gsk_t('Open Jy Metrics dashboard'),
    ];
    return $items;
}

function gsk_popular_posts_collection_context(int $period, int $limit): array {
    return [
        'scope' => 'article_list',
        'table_alias' => 'p',
        'required_translation_fields' => ['title', 'slug'],
        'source' => GSK_PLUGIN_NAME,
        'consumer' => 'popular_posts',
        'surface' => 'frontend.sidebar',
        'period' => $period,
        'limit' => $limit,
    ];
}

function gsk_popular_posts(PDO $pdo, int $period, int $limit): array {
    if (!in_array($period, [1, 7, 30, 365], true)) $period = 30;
    $limit = max(1, min(20, $limit));
    $snapshot = gsk_ga4_content_snapshot($pdo, $period);
    if ($snapshot === null) return [];
    $locale = gsk_content_locale();
    $context = gsk_popular_posts_collection_context($period, $limit);
    $clauses = function_exists('collection_query_clauses')
        ? collection_query_clauses(['where' => [], 'params' => []], $context)
        : ['where' => [], 'params' => []];
    $cacheKey = spl_object_id($pdo) . ':' . hash('sha256', serialize([$period, $limit, $locale, $clauses]));
    if (isset($GLOBALS['gsk_popular_posts'][$cacheKey])) return $GLOBALS['gsk_popular_posts'][$cacheKey];
    try {
        $where = [];
        foreach ($clauses['where'] ?? [] as $clause) {
            if (is_string($clause) && trim($clause) !== '') $where[] = '(' . $clause . ')';
        }
        $collectionParams = [];
        foreach ($clauses['params'] ?? [] as $name => $value) {
            if (!is_string($name) || str_starts_with($name, ':gsk_')
                || preg_match('/\A:[A-Za-z][A-Za-z0-9_]*\z/D', $name) !== 1 || !is_scalar($value)) {
                throw new UnexpectedValueException('Invalid collection query parameter.');
            }
            $collectionParams[$name] = $value;
        }
        $candidateScores = gsk_ga4_content_candidate_scores($pdo, $snapshot, $where, $collectionParams);
        if ($candidateScores === []) return $GLOBALS['gsk_popular_posts'][$cacheKey] = [];
        $ranked = [];
        $candidateIds = array_keys($candidateScores);
        $chunkSize = max(20, min(50, $limit * 4));
        foreach (array_chunk($candidateIds, $chunkSize) as $chunkIndex => $chunk) {
            $candidateParams = [];
            foreach ($chunk as $index => $postId) $candidateParams[':gsk_candidate_' . $index] = (int)$postId;
            $placeholders = implode(',', array_keys($candidateParams));
            $sql = 'SELECT p.id, p.title, p.slug, p.type, p.created_at FROM posts p WHERE p.id IN (' . $placeholders . ") AND p.type = 'article' AND p.status = 'published' AND p.is_deleted = 0"
                . ($where !== [] ? ' AND ' . implode(' AND ', $where) : '');
            $stmt = $pdo->prepare($sql);
            foreach ($candidateParams as $name => $postId) $stmt->bindValue($name, $postId, PDO::PARAM_INT);
            foreach ($collectionParams as $name => $value) {
                $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (function_exists('collection_filter_rows')) $rows = collection_filter_rows($rows, $context);
            foreach ($rows as $row) {
                $postId = (int)($row['id'] ?? 0);
                $count = (int)($candidateScores[$postId] ?? 0);
                if ($count < 1) continue;
                $row['view_count'] = $count;
                $ranked[] = $row;
            }
            usort($ranked, static fn(array $a, array $b): int => ((int)$b['view_count'] <=> (int)$a['view_count']) ?: ((int)$a['id'] <=> (int)$b['id']));
            $ranked = array_slice($ranked, 0, $limit);
            $nextIndex = (($chunkIndex + 1) * $chunkSize);
            $nextId = $candidateIds[$nextIndex] ?? null;
            $minimum = $ranked === [] ? 0 : (int)$ranked[array_key_last($ranked)]['view_count'];
            if (count($ranked) >= $limit && ($nextId === null || (int)($candidateScores[$nextId] ?? 0) < $minimum)) break;
        }
        $rows = $ranked;
        $GLOBALS['gsk_popular_posts'][$cacheKey] = $rows;
        return $rows;
    } catch (Throwable $e) {
        error_log('[jy-metrics] Could not load popular posts: ' . $e->getMessage());
        return [];
    }
}

function gsk_render_popular_posts_widget(PDO $pdo, array $config = []): string {
    $settings = gsk_content_view_settings($pdo);
    $period = filter_var($config['period'] ?? $settings['popular_period'], FILTER_VALIDATE_INT);
    if ($period === false || !in_array($period, [1, 7, 30, 365], true)) $period = 30;
    $limit = filter_var($config['limit'] ?? $settings['popular_limit'], FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 20) $limit = 5;
    $rows = gsk_popular_posts($pdo, $period, $limit);
    $title = trim((string)($config['title'] ?? gsk_t('Popular Posts')));
    $html = '<div class="w-box jym-popular-posts">';
    if ($title !== '') $html .= '<div class="w-title jym-popular-title">' . gsk_e($title) . '</div>';
    if ($rows === []) return $html . '<p class="jym-popular-empty">' . gsk_e(gsk_t('No popular posts in this period.')) . '</p></div>';
    $html .= '<ol class="w-recent-list jym-popular-list">';
    foreach ($rows as $index => $row) {
        try {
            $url = function_exists('get_post_permalink') ? get_post_permalink($row) : '/' . rawurlencode((string)($row['slug'] ?? '')) . '/';
        } catch (Throwable $e) {
            $url = '/' . rawurlencode((string)($row['slug'] ?? '')) . '/';
        }
        $date = (string)($row['created_at'] ?? '');
        $timestamp = $date !== '' ? strtotime($date) : false;
        $dateLabel = $date !== '' && function_exists('app_display_date') ? app_display_date($date) : ($timestamp !== false ? date('d M Y', $timestamp) : '');
        $views = gsk_localized_view_count((int)($row['view_count'] ?? 0));
        $html .= '<li class="w-recent-item jym-popular-item"><span class="jym-popular-rank" aria-label="' . gsk_e(sprintf(gsk_t('Rank %d'), $index + 1)) . '">' . ($index + 1) . '</span><div class="w-recent-body jym-popular-body">'
            . '<a class="w-recent-title jym-popular-link" href="' . gsk_e($url) . '">' . gsk_e((string)($row['title'] ?? '')) . '</a>'
            . '<span class="w-recent-date jym-popular-meta">' . gsk_e(trim($dateLabel . ($dateLabel !== '' ? ' · ' : '') . $views)) . '</span></div></li>';
    }
    return $html . '</ol></div>';
}

// ── Schema initialization ──
add_action('frontend_entity_viewed', 'gsk_frontend_entity_viewed');
add_filter('post_content', 'gsk_public_view_count', 20);
add_filter('post_list_select', 'gsk_post_list_view_select', 10);
add_filter('admin_content_row_actions', 'gsk_admin_content_view_action', 10);
add_filter('sidebar_widget_types', function (array $types): array {
    $types['jy_metrics_popular_posts'] = [
        'label' => gsk_t('Popular Posts'),
        'desc' => gsk_t('Published articles ranked by cached Google Analytics views using each widget\'s period and item limit.'),
        'default_config' => ['title' => gsk_t('Popular Posts'), 'period' => '30', 'limit' => 5],
        'config_fields' => [
            'period' => [
                'label' => gsk_t('Popular period'),
                'control' => 'select',
                'options' => [
                    '1' => gsk_t('Today'),
                    '7' => gsk_t('Weekly (7 days)'),
                    '30' => gsk_t('Monthly (30 days)'),
                    '365' => gsk_t('Yearly (365 days)'),
                ],
            ],
            'limit' => ['label' => gsk_t('Item limit'), 'control' => 'number', 'min' => 1, 'max' => 20],
        ],
    ];
    return $types;
});
add_filter('render_sidebar_widget', function ($html, string $type, array $config, PDO $pdo): string {
    return $type === 'jy_metrics_popular_posts' ? gsk_render_popular_posts_widget($pdo, $config) : $html;
}, 10);

add_action('admin_init', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    gsk_ensure_schema($pdo);
    $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : '';
    $actorId = function_exists('current_user_id') ? current_user_id() : (int)($_SESSION['user_id'] ?? 0);
    $isMetricsPage = $page === 'admin/tools/jy-metrics' || str_starts_with($page, 'admin/tools/jy-metrics/');
    $canRefresh = $actorId > 0 && function_exists('user_can') && (
        ($page === 'admin/sidebar/index' && user_can($pdo, $actorId, 'core.sidebar.manage'))
        || ($isMetricsPage && (user_can($pdo, $actorId, 'plugin.jy-metrics.reports.read')
            || user_can($pdo, $actorId, 'plugin.jy-metrics.integrations.manage')))
    );
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $canRefresh) {
        gsk_refresh_ga4_content_views($pdo);
    }
});

add_action('plugins_loaded', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) gsk_register_translations($pdo);
});

// ── Frontend snippet injection ──
add_action('jy_head', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    echo '<style>.jym-popular-item{display:flex;align-items:flex-start;gap:.7rem}.jym-popular-rank{display:grid;flex:0 0 1.7rem;height:1.7rem;place-items:center;border:1px solid currentColor;border-radius:50%;background:rgba(127,127,127,.12);font-size:.75rem;font-weight:700;opacity:.75}.jym-popular-empty{margin:.75rem 0 0;opacity:.72}</style>' . "\n";
    if (gsk_content_view_settings($pdo)['show_public']) {
        echo '<style>.jym-public-views{display:flex;align-items:center;gap:.4rem;margin:0 0 1rem;color:inherit;font-size:.9em;opacity:.72}.jym-public-views svg{flex:0 0 auto}</style>' . "\n";
    }
    $measurementId = gsk_setting($pdo, GSK_MEASUREMENT_ID_KEY);
    $gtmId = gsk_setting($pdo, GSK_GTM_ID_KEY);
    $adsense = gsk_setting($pdo, GSK_ADSENSE_CLIENT_KEY);
    $consentMode = gsk_consent_mode($pdo);
    $consentCategories = gsk_consent_categories($pdo);
    $contentTracking = gsk_ga4_content_tracking_context();
    $consentMeasurementId = $consentCategories['analytics'] ? $measurementId : '';
    $consentGtmId = $consentCategories['advertising'] ? $gtmId : '';
    $consentAdsense = $consentCategories['advertising'] ? $adsense : '';
    if ($consentMode !== 'off' && ($consentMeasurementId !== '' || $consentGtmId !== '' || $consentAdsense !== '')) {
        $privacyUrl = gsk_setting($pdo, GSK_PRIVACY_URL_KEY);
        if (!gsk_valid_privacy_url($privacyUrl)) $privacyUrl = '';
        $trackingConfig = json_encode([
            'measurementId' => $consentMeasurementId,
            'gtmId' => $consentGtmId,
            'adsense' => $consentAdsense,
            'privacyUrl' => $privacyUrl,
            'categories' => $consentCategories,
            'categoryPolicy' => gsk_consent_category_policy($pdo),
            'contentId' => $contentTracking['contentId'] ?? '',
            'contentType' => $contentTracking['contentType'] ?? '',
            'contentLocale' => $contentTracking['contentLocale'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script>(function(c){' . "\n";
        echo "window.dataLayer=window.dataLayer||[];\n";
        echo "window.gtag=window.gtag||function(){dataLayer.push(arguments);};\n";
        echo "var denied={analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'};gtag('consent','default',denied);\n";
        echo "var key='jy_metrics_consent_v2',legacyKey='jy_metrics_consent_v1',maxAge=15552000000,gpc=navigator.globalPrivacyControl===true,decided=false,prefs={analytics:false,advertising:false};\n";
        echo "function validSavedAt(value){var savedAt=Number(value),now=Date.now();return Number.isFinite(savedAt)&&savedAt>0&&savedAt<=now+300000&&now-savedAt<=maxAge;}function normalize(value){if(!value||typeof value!=='object'||value.policyVersion!==2||!validSavedAt(value.savedAt))return null;if(typeof value.analytics!=='boolean'||typeof value.advertising!=='boolean')return null;if(value.categoryPolicy!==undefined&&value.categoryPolicy!==c.categoryPolicy)return null;if(value.categoryPolicy===undefined&&c.categoryPolicy!=='legacy')return null;return{analytics:c.categories.analytics&&value.analytics,advertising:c.categories.advertising&&!gpc&&value.advertising,savedAt:Number(value.savedAt)};}\n";
        echo "try{var rawSaved=JSON.parse(localStorage.getItem(key)||'null'),saved=normalize(rawSaved);if(saved){prefs=saved;decided=true;if(rawSaved.analytics!==prefs.analytics||rawSaved.advertising!==prefs.advertising||rawSaved.categoryPolicy!==c.categoryPolicy)localStorage.setItem(key,JSON.stringify({analytics:prefs.analytics,advertising:prefs.advertising,savedAt:saved.savedAt,policyVersion:2,categoryPolicy:c.categoryPolicy}));}else{var legacy=JSON.parse(localStorage.getItem(legacyKey)||'null');if(c.categoryPolicy==='legacy'&&legacy&&validSavedAt(legacy.savedAt)&&(legacy.choice==='accepted'||legacy.choice==='rejected')){decided=true;prefs={analytics:c.categories.analytics&&legacy.choice==='accepted',advertising:c.categories.advertising&&legacy.choice==='accepted'&&!gpc};localStorage.setItem(key,JSON.stringify({analytics:prefs.analytics,advertising:prefs.advertising,savedAt:Number(legacy.savedAt),policyVersion:2,categoryPolicy:c.categoryPolicy}));localStorage.removeItem(legacyKey);}}}catch(e){}\n";
        echo "function consentValues(){return{analytics_storage:prefs.analytics?'granted':'denied',ad_storage:prefs.advertising?'granted':'denied',ad_user_data:prefs.advertising?'granted':'denied',ad_personalization:prefs.advertising?'granted':'denied'};}function applyConsent(){gtag('consent','update',consentValues());}applyConsent();\n";
        echo "var loaded={analytics:false,advertising:false};function add(src,crossOrigin){var script=document.createElement('script'),first=document.scripts[0];script.async=true;script.src=src;if(crossOrigin)script.crossOrigin='anonymous';first.parentNode.insertBefore(script,first);}\n";
        echo "function analyticsParams(){var p={};if(c.contentId)p.content_id=c.contentId;if(c.contentType)p.content_type=c.contentType;if(c.contentLocale)p.content_group=c.contentLocale;return p;}function loadAllowed(){if(prefs.analytics&&!loaded.analytics){loaded.analytics=true;if(c.measurementId){gtag('js',new Date());gtag('config',c.measurementId,analyticsParams());add('https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(c.measurementId));}}if(prefs.advertising&&!loaded.advertising){loaded.advertising=true;if(c.gtmId){dataLayer.push({'gtm.start':Date.now(),event:'gtm.js'});add('https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(c.gtmId));}if(c.adsense)add('https://pagead2.googlesyndication.com/pagead/js?client='+encodeURIComponent(c.adsense),true);}}\n";
        echo "function clearCookies(analytics,advertising){var domains=[''],parts=location.hostname.split('.');for(var i=0;i<parts.length-1;i++)domains.push('.'+parts.slice(i).join('.'));document.cookie.split(';').forEach(function(value){var name=value.split('=')[0].trim(),remove=analytics&&(name==='_gid'||name==='_gat'||name.indexOf('_gat_')===0||name==='_ga'||name.indexOf('_ga_')===0||name==='AMP_TOKEN'||name==='FPLC')||advertising&&(name==='FPAU'||name.indexOf('_gcl')===0||name.indexOf('_gac')===0||name.indexOf('__gads')===0||name.indexOf('__gpi')===0||name.indexOf('__eoi')===0);if(!remove)return;domains.forEach(function(domain){document.cookie=name+'=; Max-Age=0; Path=/; SameSite=Lax'+(domain?'; Domain='+domain:'');});});}clearCookies(!prefs.analytics,!prefs.advertising);\n";
        echo "function notify(){dispatchEvent(new CustomEvent('jy-metrics-consent-change',{detail:{preferences:{analytics:prefs.analytics,advertising:prefs.advertising},gpc:gpc}}));}function persist(savedAt){try{localStorage.setItem(key,JSON.stringify({analytics:prefs.analytics,advertising:prefs.advertising,savedAt:savedAt===undefined?Date.now():savedAt,policyVersion:2,categoryPolicy:c.categoryPolicy}));localStorage.removeItem(legacyKey);}catch(e){}}\n";
        echo "function normalizePath(path){path=path.replace(/\\/+$/,'');return path||'/';}var localPrivacyPage=false,onPrivacyPage=false;try{if(c.privacyUrl){var privacyTarget=new URL(c.privacyUrl,location.origin),samePath=normalizePath(privacyTarget.pathname)===normalizePath(location.pathname),sameQuery=!privacyTarget.search||privacyTarget.search===location.search,sameHash=!privacyTarget.hash||privacyTarget.hash===location.hash;localPrivacyPage=privacyTarget.origin===location.origin;onPrivacyPage=localPrivacyPage&&samePath&&sameQuery&&sameHash;}}catch(e){}var api={preferences:{analytics:prefs.analytics,advertising:prefs.advertising},categories:{analytics:c.categories.analytics,advertising:c.categories.advertising},decided:decided,gpc:gpc,hasLocalPrivacyPage:localPrivacyPage,reopenOnCurrentPage:!localPrivacyPage||onPrivacyPage};function adoptPreferences(next,write,savedAt){var previous={analytics:prefs.analytics,advertising:prefs.advertising};prefs={analytics:c.categories.analytics&&next.analytics===true,advertising:c.categories.advertising&&next.advertising===true&&!gpc};decided=true;api.preferences={analytics:prefs.analytics,advertising:prefs.advertising};api.decided=true;if(write)persist(savedAt);applyConsent();var revokedAnalytics=previous.analytics&&!prefs.analytics,revokedAdvertising=previous.advertising&&!prefs.advertising;clearCookies(revokedAnalytics,revokedAdvertising);if((revokedAnalytics&&loaded.analytics)||(revokedAdvertising&&loaded.advertising)){location.reload();return;}loadAllowed();notify();}api.set=function(next){adoptPreferences(next,true);};api.acceptAll=function(){adoptPreferences({analytics:c.categories.analytics,advertising:c.categories.advertising},true);};api.rejectAll=function(){adoptPreferences({analytics:false,advertising:false},true);};window.jyMetricsConsent=api;\n";
        echo "addEventListener('storage',function(event){if(event.key!==key||!event.newValue)return;try{var raw=JSON.parse(event.newValue),incoming=normalize(raw);if(!incoming)return;adoptPreferences(incoming,gpc&&raw.advertising===true,incoming.savedAt);}catch(e){}});if(decided&&(prefs.analytics||prefs.advertising)){['pointerdown','touchstart','keydown','scroll'].forEach(function(event){addEventListener(event,loadAllowed,{once:true,passive:true});});function afterLoad(){setTimeout(loadAllowed,5000);}if(document.readyState==='complete')afterLoad();else addEventListener('load',afterLoad,{once:true});}\n";
        echo '})(' . $trackingConfig . ');</script>' . "\n";
    } elseif ($consentMode === 'off' && ($consentMeasurementId !== '' || $consentGtmId !== '')) {
        $trackingConfig = json_encode([
            'measurementId' => $consentMeasurementId,
            'gtmId' => $consentGtmId,
            'contentId' => $contentTracking['contentId'] ?? '',
            'contentType' => $contentTracking['contentType'] ?? '',
            'contentLocale' => $contentTracking['contentLocale'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script>(function(c){' . "\n";
        echo "window.dataLayer=window.dataLayer||[];\n";
        echo "if(c.measurementId){window.gtag=window.gtag||function(){dataLayer.push(arguments);};var page={};if(c.contentId)page.content_id=c.contentId;if(c.contentType)page.content_type=c.contentType;if(c.contentLocale)page.content_group=c.contentLocale;gtag('js',new Date());gtag('config',c.measurementId,page);}\n";
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
    $consentMode = gsk_consent_mode($pdo);
    $consentCategories = gsk_consent_categories($pdo);
    $gtmId = gsk_setting($pdo, GSK_GTM_ID_KEY);
    if ($consentMode === 'off' && $consentCategories['advertising'] && $gtmId !== '') {
        $id = gsk_e($gtmId);
        echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }
    $adsense = gsk_setting($pdo, GSK_ADSENSE_CLIENT_KEY);
    if ($consentMode === 'off' && $consentCategories['advertising'] && $adsense !== '') {
        $client = gsk_e($adsense);
        echo "<script async src=\"https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={$client}\" crossorigin=\"anonymous\"></script>\n";
    }
    $hasConsentAnalytics = $consentCategories['analytics'] && gsk_setting($pdo, GSK_MEASUREMENT_ID_KEY) !== '';
    $hasConsentAdvertising = $consentCategories['advertising'] && ($gtmId !== '' || $adsense !== '');
    if ($consentMode === 'off' || (!$hasConsentAnalytics && !$hasConsentAdvertising)) return;

    $privacyUrl = gsk_setting($pdo, GSK_PRIVACY_URL_KEY);
    if (!gsk_valid_privacy_url($privacyUrl)) $privacyUrl = '';
    echo '<aside class="jym-consent" id="jym-consent" role="region" aria-live="polite" aria-label="' . gsk_e(gsk_t('Privacy choices')) . '" tabindex="-1" hidden>';
    echo '<button type="button" class="jym-consent__close" data-jym-close aria-label="' . gsk_e(gsk_t('Close and reject optional services')) . '" title="' . gsk_e(gsk_t('Close and reject optional services')) . '">&times;</button>';
    $consentMessage = $consentCategories['analytics'] && $consentCategories['advertising']
        ? 'We use optional analytics and advertising services. Choose what this site may load.'
        : ($consentCategories['analytics']
            ? 'We use optional analytics services. Choose what this site may load.'
            : 'We use optional advertising services. Choose what this site may load.');
    echo '<div data-jym-summary><div class="jym-consent__copy"><strong id="jym-consent-title">' . gsk_e(gsk_t('Privacy choices')) . '</strong><p>' . gsk_e(gsk_t($consentMessage)) . '</p>';
    if ($privacyUrl !== '') echo '<a href="' . gsk_e($privacyUrl) . '">' . gsk_e(gsk_t('Privacy policy')) . '</a>';
    echo '</div><div class="jym-consent__actions"><button type="button" class="jym-consent__secondary" data-jym-manage>' . gsk_e(gsk_t('Manage choices')) . '</button><button type="button" class="jym-consent__primary" data-jym-accept-all>' . gsk_e(gsk_t('Accept all')) . '</button></div></div>';
    echo '<div class="jym-consent__details" data-jym-details hidden><strong>' . gsk_e(gsk_t('Manage choices')) . '</strong>';
    echo '<div class="jym-consent__option"><span><b>' . gsk_e(gsk_t('Necessary')) . '</b><small>' . gsk_e(gsk_t('Required for core site functions and always active.')) . '</small></span><span class="jym-consent__always">' . gsk_e(gsk_t('Always active')) . '</span></div>';
    if ($consentCategories['analytics']) {
        echo '<label class="jym-consent__option"><span><b>' . gsk_e(gsk_t('Analytics')) . '</b><small>' . gsk_e(gsk_t('Helps understand site usage through Google Analytics.')) . '</small></span><input type="checkbox" data-jym-analytics><i aria-hidden="true"></i></label>';
    }
    if ($consentCategories['advertising']) {
        echo '<label class="jym-consent__option"><span><b>' . gsk_e(gsk_t('Advertising')) . '</b><small>' . gsk_e(gsk_t('Allows Tag Manager, AdSense, and advertising personalization.')) . '</small></span><input type="checkbox" data-jym-advertising><i aria-hidden="true"></i></label>';
        echo '<p class="jym-consent__notice" data-jym-gpc hidden>' . gsk_e(gsk_t('Global Privacy Control is active. Advertising remains disabled.')) . '</p><p class="jym-consent__note">' . gsk_e(gsk_t('Tag Manager is treated as advertising because its container may run marketing tags.')) . '</p>';
    }
    echo '<div class="jym-consent__actions"><button type="button" class="jym-consent__secondary" data-jym-reject-all>' . gsk_e(gsk_t('Reject all')) . '</button><button type="button" class="jym-consent__secondary" data-jym-save>' . gsk_e(gsk_t('Save choices')) . '</button><button type="button" class="jym-consent__primary" data-jym-detail-accept>' . gsk_e(gsk_t('Accept all')) . '</button></div></div></aside>';
    echo '<button type="button" class="jym-consent-choice" data-jym-open hidden>' . gsk_e(gsk_t('Privacy choices')) . '</button>';
    echo '<style>.jym-consent{position:fixed;z-index:2147483000;left:1rem;right:1rem;bottom:1rem;max-width:920px;box-sizing:border-box;margin:auto;padding:1.1rem 3rem 1.1rem 1.1rem;border:1px solid rgba(148,163,184,.35);border-radius:14px;background:#111827;color:#f8fafc;box-shadow:0 18px 50px rgba(15,23,42,.35);font:14px/1.45 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.jym-consent[hidden],.jym-consent [hidden],.jym-consent-choice[hidden]{display:none}.jym-consent [data-jym-summary]{display:flex;align-items:center;justify-content:space-between;gap:1.25rem}.jym-consent__copy strong,.jym-consent__details>strong{display:block;margin-bottom:.2rem;font-size:1rem}.jym-consent__copy p{margin:0;color:#cbd5e1}.jym-consent__copy a{display:inline-block;margin-top:.35rem;color:#93c5fd}.jym-consent__close{position:absolute;top:.55rem;right:.65rem;width:34px;height:34px;padding:0;border:0;background:transparent;color:#cbd5e1;font:24px/1 system-ui;cursor:pointer}.jym-consent__actions{display:flex;align-items:center;justify-content:flex-end;gap:.6rem;flex:0 0 auto;margin-top:.8rem}.jym-consent [data-jym-summary] .jym-consent__actions{margin-top:0}.jym-consent__actions button,.jym-consent-choice{min-height:40px;padding:.55rem .85rem;border-radius:9px;border:1px solid #64748b;font:600 13px/1 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;cursor:pointer}.jym-consent__secondary{background:transparent;color:#f8fafc}.jym-consent__primary{border-color:#2563eb!important;background:#2563eb;color:#fff}.jym-consent__details{display:grid;gap:.55rem}.jym-consent__option{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.7rem .8rem;border:1px solid #374151;border-radius:10px;background:#1f2937}.jym-consent__option b,.jym-consent__option small{display:block}.jym-consent__option small{margin-top:.12rem;color:#cbd5e1}.jym-consent__always{color:#86efac;font-size:.78rem;font-weight:700}.jym-consent__option input{position:absolute;opacity:0;pointer-events:none}.jym-consent__option i{position:relative;flex:0 0 42px;width:42px;height:24px;border-radius:999px;background:#64748b;transition:.18s}.jym-consent__option i:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.18s}.jym-consent__option input:checked+i{background:#2563eb}.jym-consent__option input:checked+i:after{transform:translateX(18px)}.jym-consent__option input:focus-visible+i{outline:3px solid #93c5fd;outline-offset:2px}.jym-consent__option input:disabled+i{opacity:.45;cursor:not-allowed}.jym-consent__notice{margin:.2rem 0 0;padding:.55rem .7rem;border-radius:8px;background:#1e3a5f;color:#bfdbfe}.jym-consent__note{margin:.2rem 0 0;color:#cbd5e1;font-size:.78rem}.jym-consent-choice{position:fixed;z-index:2147482999;left:1rem;bottom:1rem;background:#111827;color:#fff;box-shadow:0 8px 24px rgba(15,23,42,.25)}@media(max-width:680px){.jym-consent [data-jym-summary]{align-items:stretch;flex-direction:column}.jym-consent [data-jym-summary] .jym-consent__actions,.jym-consent__details .jym-consent__actions{display:grid;grid-template-columns:1fr}.jym-consent__actions button{width:100%}}</style>';
    echo '<script>(function(){var api=window.jyMetricsConsent,banner=document.getElementById("jym-consent"),summary=banner&&banner.querySelector("[data-jym-summary]"),details=banner&&banner.querySelector("[data-jym-details]"),open=document.querySelector("[data-jym-open]"),analytics=banner&&banner.querySelector("[data-jym-analytics]"),advertising=banner&&banner.querySelector("[data-jym-advertising]"),gpc=banner&&banner.querySelector("[data-jym-gpc]");if(!api||!banner||!summary||!details||!open)return;function sync(){if(analytics)analytics.checked=api.preferences.analytics;if(advertising){advertising.checked=api.preferences.advertising;advertising.disabled=api.gpc;}if(gpc)gpc.hidden=!api.gpc;}function showSummary(focus){sync();banner.hidden=false;summary.hidden=false;details.hidden=true;open.hidden=true;if(focus)banner.querySelector("[data-jym-manage]").focus();}function showDetails(focus){sync();banner.hidden=false;summary.hidden=true;details.hidden=false;open.hidden=true;if(focus)(analytics||(advertising&&!advertising.disabled?advertising:null)||banner.querySelector("[data-jym-reject-all]")).focus();}function focusPage(){var target=document.querySelector("main")||document.body;if(!target.hasAttribute("tabindex"))target.setAttribute("tabindex","-1");target.focus({preventScroll:true});}function hide(){banner.hidden=true;open.hidden=!api.reopenOnCurrentPage;if(!open.hidden)open.focus();else focusPage();}if(api.decided){banner.hidden=true;open.hidden=!api.reopenOnCurrentPage;}else showSummary(false);banner.querySelector("[data-jym-close]").addEventListener("click",function(){api.rejectAll();hide();});banner.querySelector("[data-jym-manage]").addEventListener("click",function(){showDetails(true);});banner.querySelector("[data-jym-accept-all]").addEventListener("click",function(){api.acceptAll();hide();});banner.querySelector("[data-jym-reject-all]").addEventListener("click",function(){api.rejectAll();hide();});banner.querySelector("[data-jym-save]").addEventListener("click",function(){api.set({analytics:analytics?analytics.checked:false,advertising:advertising?advertising.checked:false});hide();});banner.querySelector("[data-jym-detail-accept]").addEventListener("click",function(){api.acceptAll();hide();});open.addEventListener("click",function(){showDetails(true);});addEventListener("jy-metrics-consent-change",sync);})();</script>' . "\n";
    echo '<script>addEventListener("jy-metrics-consent-change",function(){var api=window.jyMetricsConsent,banner=document.getElementById("jym-consent"),open=document.querySelector("[data-jym-open]");if(!api||!api.decided||!banner||!open)return;var restoreFocus=banner.contains(document.activeElement);banner.hidden=true;open.hidden=!api.reopenOnCurrentPage;if(restoreFocus){var target=document.querySelector("main")||document.body;if(!target.hasAttribute("tabindex"))target.setAttribute("tabindex","-1");target.focus({preventScroll:true});}});</script>';
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
        GSK_ADSENSE_CLIENT_KEY, GSK_SITE_VERIFICATION_KEY, GSK_PAGESPEED_API_KEY,
        GSK_CONSENT_MODE_KEY, GSK_CONSENT_REGION_SOURCE_KEY, GSK_PRIVACY_URL_KEY, GSK_PRIVACY_PAGE_LINKED_KEY,
        GSK_CONSENT_ANALYTICS_KEY, GSK_CONSENT_ADVERTISING_KEY, GSK_CONSENT_POLICY_KEY, GSK_I18N_VERSION_KEY,
        GSK_SHOW_DASHBOARD_VIEWS_KEY, GSK_SHOW_PUBLIC_VIEWS_KEY,
        GSK_POPULAR_POSTS_PERIOD_KEY, GSK_POPULAR_POSTS_LIMIT_KEY,
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
    try {
        $stmt = $pdo->prepare('DELETE FROM sidebar_zone_items WHERE `type` = ?');
        $stmt->execute(['jy_metrics_popular_posts']);
    } catch (Throwable $e) {
        error_log('[jy-metrics] Could not remove Sidebar widgets during uninstall: ' . $e->getMessage());
    }
    $pdo->exec('DROP TABLE IF EXISTS `' . GSK_CACHE_TABLE . '`');
    $pdo->exec('DROP TABLE IF EXISTS `' . GSK_CONTENT_VIEWS_TABLE . '`');
});
