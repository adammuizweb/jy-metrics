<?php
// /plugins/jy-metrics/api/disconnect.php
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

gsk_disconnect($pdo);
gsk_json_response(['ok' => true]);
