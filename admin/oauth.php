<?php
// /plugins/jy-metrics/admin/oauth.php
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    gsk_redirect(gsk_page_url(''), 'Database not available', 'error');
}
adiwira_require_permission($pdo, 'plugin.jy-metrics.integrations.manage', false);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$state = isset($_GET['state']) ? trim((string)$_GET['state']) : '';
$error = isset($_GET['error']) ? trim((string)$_GET['error']) : '';
$errorDesc = isset($_GET['error_description']) ? trim((string)$_GET['error_description']) : '';
$basePage = gsk_page_url('');

if ($error !== '') {
    $msg = $errorDesc !== '' ? ($error . ': ' . $errorDesc) : $error;
    gsk_redirect($basePage, $msg, 'error');
}

if (function_exists('ensure_session_started')) {
    ensure_session_started(false);
}
$storedState = '';
if (session_status() === PHP_SESSION_ACTIVE) {
    $storedState = $_SESSION['gsk_oauth_state'] ?? '';
    unset($_SESSION['gsk_oauth_state']);
}

if ($code === '' || $state === '' || $state !== $storedState) {
    gsk_redirect($basePage, 'Invalid or missing OAuth state. Please try again.', 'error');
}

$clientId = gsk_setting($pdo, GSK_CLIENT_ID_KEY);
$clientSecret = gsk_setting($pdo, GSK_CLIENT_SECRET_KEY);
if ($clientId === '' || $clientSecret === '') {
    gsk_redirect($basePage, 'OAuth credentials not configured.', 'error');
}

$redirect = gsk_oauth_redirect_uri();
$res = gsk_http_request('https://oauth2.googleapis.com/token', 'POST', [
    'Content-Type: application/x-www-form-urlencoded',
], http_build_query([
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirect,
    'grant_type'    => 'authorization_code',
]));

if (!$res['ok']) {
    gsk_redirect($basePage, 'Token exchange failed (HTTP ' . $res['code'] . ')', 'error');
}

$data = json_decode($res['body'], true);
if (!is_array($data) || empty($data['access_token'])) {
    gsk_redirect($basePage, 'Invalid token response from Google.', 'error');
}

gsk_save_setting($pdo, GSK_ACCESS_TOKEN_KEY, $data['access_token']);
$expires = time() + (int)($data['expires_in'] ?? 3600);
gsk_save_setting($pdo, GSK_TOKEN_EXPIRES_KEY, (string)$expires);
if (!empty($data['refresh_token'])) {
    gsk_save_setting($pdo, GSK_REFRESH_TOKEN_KEY, $data['refresh_token']);
}
gsk_save_setting($pdo, GSK_SCOPES_KEY, $data['scope'] ?? gsk_scope_string());

$userRes = gsk_json_request('https://www.googleapis.com/oauth2/v2/userinfo', 'GET', [
    'Authorization: Bearer ' . $data['access_token'],
]);
if ($userRes['ok'] && !empty($userRes['data']['email'])) {
    gsk_save_setting($pdo, GSK_USER_EMAIL_KEY, $userRes['data']['email']);
}

gsk_cache_delete($pdo, 'gsk_%');
gsk_redirect($basePage, 'Connected to Google as ' . ($userRes['data']['email'] ?? 'account'), 'success');
