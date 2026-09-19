<?php
// /plugins/jy-metrics/api/save-wizard.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    gsk_json_response(['ok' => false, 'error' => 'Database not available']);
}
adiwira_require_permission($pdo, 'plugin.jy-metrics.integrations.manage', true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    gsk_json_response(['ok' => false, 'error' => 'Method not allowed']);
}

if (!function_exists('csrf_check') || !csrf_check($_POST['csrf_token'] ?? '')) {
    gsk_json_response(['ok' => false, 'error' => 'Invalid CSRF token']);
}

$save = [
    GSK_SITE_URL_KEY        => trim((string)($_POST['site_url'] ?? '')),
    GSK_MEASUREMENT_ID_KEY  => trim((string)($_POST['measurement_id'] ?? '')),
    GSK_GA4_PROPERTY_ID_KEY => trim((string)($_POST['ga4_property_id'] ?? '')),
    GSK_GTM_ID_KEY          => trim((string)($_POST['gtm_id'] ?? '')),
    GSK_ADSENSE_CLIENT_KEY  => trim((string)($_POST['adsense_client'] ?? '')),
];

foreach ($save as $k => $v) {
    gsk_save_setting($pdo, $k, $v);
}

gsk_cache_delete($pdo, 'gsk_%');

gsk_json_response(['ok' => true, 'saved' => array_keys($save)]);
