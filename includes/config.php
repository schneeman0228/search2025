<?php
// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// エラー表示設定（本番環境では非表示にする）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// データベース接続
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// グローバル設定
define('SITE_ROOT', dirname(__DIR__));
define('ADMIN_SESSION_NAME', 'search_admin_logged_in');

// データベースインスタンス
$database = new Database();
$db = $database->getConnection();

// サイト設定を取得
function getSetting($key, $default = '') {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['value'] : $default;
}

// サイト設定を更新
function updateSetting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))");
    return $stmt->execute([$key, $value]);
}

// 基本設定
$SITE_TITLE = getSetting('site_title', 'ディレクトリサーチ');
$SITE_DESCRIPTION = getSetting('site_description', 'ディレクトリ型サーチエンジン');
$MAX_SITES = (int)getSetting('max_sites', 2000);
$SITES_PER_PAGE = (int)getSetting('sites_per_page', 20);
$REQUIRE_APPROVAL = (bool)getSetting('require_approval', true);

// 検索避け設定
$ROBOTS_CONTENT = "noindex, nofollow, noarchive, nosnippet";
?>