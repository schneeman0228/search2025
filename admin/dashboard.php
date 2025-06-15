<?php
require_once '../includes/config.php';
requireAdminLogin();

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 統計情報取得
$total_sites = getSiteCount('approved');
$pending_sites = getSiteCount('pending');
$total_categories = count(getCategories());

// 最新の承認待ちサイト
$pending_list = getSites(null, null, 1, 5, 'pending');

// 最新の承認済みサイト
$recent_sites = getSites(null, null, 1, 5);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title>管理画面ダッシュボード - <?php echo h($SITE_TITLE); ?></title>
    <style>
        body {
            font-family: 'Hiragino Sans', 'Meiryo', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .header-nav a:hover {
            background-color: rgba(255,255,255,0.2);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
        }
        .stat-card .label {
            color: #666;
            font-size: 14px;
        }
        .section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .section-header {
            background: #f8f9fa;
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        .section-header h2 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        .section-content {
            padding: 25px;
        }
        .site-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .site-item:last-child {
            border-bottom: none;
        }
        .site-info {
            flex: 1;
        }
        .site-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .site-url {
            color: #28a745;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .site-meta {
            font-size: 0.85em;
            color: #666;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-approve:hover {
            background: #218838;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        .btn-reject:hover {
            background: #c82333;
        }
        .btn-view {
            background: #007cba;
            color: white;
        }
        .btn-view:hover {
            background: #005a87;
        }
        .no-items {
            text-align: center;
            color: #666;
            padding: 20px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .quick-action {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .quick-action a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .quick-action a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>管理画面ダッシュボード</h1>
            <div class="header-nav">
                <span>ようこそ、<?php echo h($_SESSION['admin_username']); ?>さん</span>
                <a href="manage.php">サイト管理</a>
                <a href="../" target="_blank">サイト表示</a>
                <a href="?logout=1">ログアウト</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($pending_sites > 0): ?>
            <div class="alert alert-warning">
                <strong>承認待ちのサイトが <?php echo $pending_sites; ?> 件あります。</strong>
                <a href="manage.php">今すぐ確認する</a>
            </div>
        <?php endif; ?>

        <!-- 統計情報 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($total_sites); ?></div>
                <div class="label">承認済みサイト</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($pending_sites); ?></div>
                <div class="label">承認待ちサイト</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($total_categories); ?></div>
                <div class="label">カテゴリ数</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($MAX_SITES - $total_sites - $pending_sites); ?></div>
                <div class="label">登録可能数</div>
            </div>
        </div>

        <!-- クイックアクション -->
        <div class="quick-actions">
            <div class="quick-action">
                <a href="manage.php">サイト管理</a>
                <div style="font-size: 0.9em; color: #666; margin-top: 5px;">承認・編集・削除</div>
            </div>
            <div class="quick-action">
                <a href="manage.php?tab=categories">カテゴリ管理</a>
                <div style="font-size: 0.9em; color: #666; margin-top: 5px;">カテゴリの追加・編集</div>
            </div>
            <div class="quick-action">
                <a href="manage.php?tab=settings">設定</a>
                <div style="font-size: 0.9em; color: #666; margin-top: 5px;">サイト基本設定</div>
            </div>
        </div>

        <!-- 承認待ちサイト -->
        <?php if ($pending_sites > 0): ?>
            <div class="section">
                <div class="section-header">
                    <h2>承認待ちサイト (最新5件)</h2>
                    <a href="manage.php" class="btn btn-view">すべて表示</a>
                </div>
                <div class="section-content">
                    <?php foreach ($pending_list as $site): ?>
                        <div class="site-item">
                            <div class="site-info">
                                <div class="site-title"><?php echo h($site['title']); ?></div>
                                <div class="site-url"><?php echo h($site['url']); ?></div>
                                <div class="site-meta">
                                    カテゴリ: <?php echo h($site['category_name']); ?> | 
                                    登録日: <?php echo date('Y年m月d日 H:i', strtotime($site['created_at'])); ?>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <form method="POST" action="manage.php" style="display: inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <button type="submit" class="btn btn-approve">承認</button>
                                </form>
                                <a href="<?php echo h($site['url']); ?>" target="_blank" class="btn btn-view">確認</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 最新の承認済みサイト -->
        <div class="section">
            <div class="section-header">
                <h2>最新の承認済みサイト</h2>
                <a href="manage.php?status=approved" class="btn btn-view">すべて表示</a>
            </div>
            <div class="section-content">
                <?php if (empty($recent_sites)): ?>
                    <div class="no-items">まだ承認済みのサイトがありません。</div>
                <?php else: ?>
                    <?php foreach ($recent_sites as $site): ?>
                        <div class="site-item">
                            <div class="site-info">
                                <div class="site-title"><?php echo h($site['title']); ?></div>
                                <div class="site-url"><?php echo h($site['url']); ?></div>
                                <div class="site-meta">
                                    カテゴリ: <?php echo h($site['category_name']); ?> | 
                                    登録日: <?php echo date('Y年m月d日', strtotime($site['created_at'])); ?>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="<?php echo h($site['url']); ?>" target="_blank" class="btn btn-view">確認</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>