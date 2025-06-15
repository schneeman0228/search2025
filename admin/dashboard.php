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
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-admin">
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