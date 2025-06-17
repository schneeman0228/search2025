<?php
require_once 'includes/config.php';

// パラメータ取得
$search = $_GET['search'] ?? '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// カテゴリ情報
$current_category = null;
$category_path = [];
if ($category_id) {
    $current_category = getCategory($category_id);
    $category_path = getCategoryPath($category_id);
}

// サイト一覧取得
$sites = getSites($category_id, $search, $page, $SITES_PER_PAGE);
$total_sites = getSitesCount($category_id, $search);

// 階層カテゴリ一覧取得
$hierarchical_categories = getCategoriesHierarchical();

// ページネーション用URL
$base_url = '?';
if ($search) $base_url .= 'search=' . urlencode($search) . '&';
if ($category_id) $base_url .= 'category=' . $category_id . '&';
$base_url = rtrim($base_url, '&');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title><?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-index">
    <div class="container">
        <div class="header">
            <h1><?php echo h($SITE_TITLE); ?></h1>
            <p><?php echo h($SITE_DESCRIPTION); ?></p>
        </div>

        <!-- 検索フォーム -->
        <div class="search-box">
            <form method="GET" action="">
                <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="サイトを検索...">
                <?php if ($category_id): ?>
                    <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                <?php endif; ?>
                <input type="submit" value="検索">
                <?php if ($search || $category_id): ?>
                    <a href="?" style="margin-left: 10px; color: #666;">クリア</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- パンくずナビ（改良版） -->
        <?php if (!empty($category_path) || $search): ?>
            <div class="breadcrumb-enhanced">
                <a href="?">ホーム</a>
                <?php if (!empty($category_path)): ?>
                    <?php foreach ($category_path as $index => $cat): ?>
                        <span class="separator">></span>
                        <?php if ($index === 0): ?>
                            <span class="parent-category"><?php echo h($cat['name']); ?></span>
                        <?php else: ?>
                            <span class="child-category"><?php echo h($cat['name']); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($search): ?>
                    <span class="separator">></span>
                    <span>検索: "<?php echo h($search); ?>"</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 階層カテゴリ一覧（トップページのみ） -->
        <?php if (!$search && !$category_id): ?>
            <?php echo generateHierarchicalCategoryHTML($hierarchical_categories); ?>
        <?php endif; ?>

        <!-- サイト一覧 -->
        <div class="sites-section">
            <?php if ($search): ?>
                <h2>検索結果: "<?php echo h($search); ?>" (<?php echo $total_sites; ?>件)</h2>
            <?php elseif ($current_category): ?>
                <h2><?php echo h($current_category['name']); ?> (<?php echo $total_sites; ?>件)</h2>
            <?php else: ?>
                <h2>新着サイト</h2>
            <?php endif; ?>

            <?php if (empty($sites)): ?>
                <div class="no-results">
                    <?php if ($search): ?>
                        検索条件に一致するサイトが見つかりませんでした。
                    <?php elseif ($current_category): ?>
                        このカテゴリにはまだサイトが登録されていません。
                    <?php else: ?>
                        まだサイトが登録されていません。
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($sites as $site): ?>
                    <div class="site-item">
                        <div class="site-title">
                            <a href="<?php echo h($site['url']); ?>" target="_blank" rel="noopener">
                                <?php echo h($site['title']); ?>
                            </a>
                        </div>
                        <div class="site-url"><?php echo h($site['url']); ?></div>
                        <?php if ($site['description']): ?>
                            <div class="site-description"><?php echo h($site['description']); ?></div>
                        <?php endif; ?>
                        <div class="site-meta">
                            カテゴリ: <a href="?category=<?php echo $site['category_id']; ?>"><?php echo h($site['category_name']); ?></a>
                            | 登録日: <?php echo date('Y年m月d日', strtotime($site['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- ページネーション -->
                <?php echo generatePagination($page, $total_sites, $SITES_PER_PAGE, $base_url); ?>
            <?php endif; ?>
        </div>

        <!-- ナビゲーションリンク -->
        <div class="nav-links">
            <a href="register.php">サイト登録</a>
            <a href="user_login.php">サイト情報編集</a>
            <a href="admin/login.php">管理画面</a>
        </div>
    </div>
</body>
</html>