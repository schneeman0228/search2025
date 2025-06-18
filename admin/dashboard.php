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
            <div class="message success">
                <strong>承認待ちのサイトが <?php echo $pending_sites; ?> 件あります。</strong>
                <a href="manage.php" style="color: #155724; font-weight: bold;">今すぐ確認する</a>
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
        <div class="grid grid-auto mb-30">
            <div class="card">
                <div class="card-body text-center">
                    <a href="manage.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">サイト管理</a>
                    <div class="text-small text-muted">承認・編集・削除</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body text-center">
                    <a href="manage.php?tab=categories" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">カテゴリ管理</a>
                    <div class="text-small text-muted">カテゴリの確認・編集</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body text-center">
                    <a href="manage.php?tab=settings" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">設定</a>
                    <div class="text-small text-muted">サイト基本設定</div>
                </div>
            </div>
        </div>

        <!-- 承認待ちサイト -->
        <?php if ($pending_sites > 0): ?>
            <div class="card mb-30">
                <div class="card-header flex flex-between flex-center">
                    <h2 class="mt-0 mb-0">承認待ちサイト (最新5件)</h2>
                    <a href="manage.php" class="btn btn-primary">すべて表示</a>
                </div>
                <div class="card-body">
                    <div class="grid" style="grid-template-columns: 1fr; gap: 15px;">
                        <?php foreach ($pending_list as $site): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="flex flex-between flex-center mb-10">
                                        <div class="text-bold"><?php echo h($site['title']); ?></div>
                                        <form method="POST" action="manage.php" style="display: inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <button type="submit" class="btn btn-approve">承認</button>
                                        </form>
                                    </div>
                                    <div class="site-url mb-10">
                                        <a href="<?php echo h($site['url']); ?>" target="_blank"><?php echo h($site['url']); ?></a>
                                    </div>
                                    <div class="flex flex-between text-small text-muted">
                                        <div>カテゴリ: <?php echo formatCategoryNames($site['category_names']); ?></div>
                                        <div>登録日: <?php echo date('Y/m/d H:i', strtotime($site['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 最新の承認済みサイト -->
        <div class="card">
            <div class="card-header">
                <h2>最新の承認済みサイト</h2>
                <a href="manage.php?status=approved" class="btn btn-view">すべて表示</a>
            </div>
            <div class="card-content">
                <?php if (empty($recent_sites)): ?>
                    <div class="no-items">まだ承認済みのサイトがありません。</div>
                <?php else: ?>
                    <?php foreach ($recent_sites as $site): ?>
                        <div class="site-item">
                            <div class="site-info">
                                <div class="site-title"><?php echo h($site['title']); ?></div>
                                <div class="site-url"><?php echo h($site['url']); ?></div>
                                <?php if ($site['description']): ?>
                                    <div class="site-description"><?php echo h(mb_substr($site['description'], 0, 100)) . (mb_strlen($site['description']) > 100 ? '...' : ''); ?></div>
                                <?php endif; ?>
                                <div class="site-meta">
                                    <div class="site-categories-admin">
                                        カテゴリ: 
                                        <?php if (!empty($site['categories'])): ?>
                                            <?php foreach ($site['categories'] as $i => $cat): ?>
                                                <?php if ($i > 0) echo ', '; ?>
                                                <span class="category-badge-admin">
                                                    <?php echo $cat['parent_name'] ? h($cat['parent_name'] . ' > ' . $cat['name']) : h($cat['name']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="no-category">（カテゴリ未設定）</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="site-date">
                                        登録日: <?php echo date('Y年m月d日', strtotime($site['created_at'])); ?>
                                    </div>
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