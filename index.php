<?php
require_once 'includes/config.php';

// パラメータ取得
$search = $_GET['search'] ?? '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// カテゴリ情報
$current_category = null;
if ($category_id) {
    $current_category = getCategory($category_id);
}

// サイト一覧取得
$sites = getSites($category_id, $search, $page, $SITES_PER_PAGE);
$total_sites = getSitesCount($category_id, $search);

// カテゴリ一覧取得
$categories = getCategories();

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
    <style>
        body {
            font-family: 'Hiragino Sans', 'Meiryo', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .header h1 {
            color: #333;
            margin: 0 0 10px 0;
        }
        .header p {
            color: #666;
            margin: 0;
        }
        .search-box {
            margin-bottom: 30px;
            text-align: center;
        }
        .search-box input[type="text"] {
            width: 300px;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
        }
        .search-box input[type="submit"] {
            padding: 10px 20px;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }
        .search-box input[type="submit"]:hover {
            background: #005a87;
        }
        .categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .category-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .category-item a {
            text-decoration: none;
            color: #007cba;
            font-weight: bold;
        }
        .category-item a:hover {
            text-decoration: underline;
        }
        .site-count {
            color: #666;
            font-size: 0.9em;
        }
        .sites-section h2 {
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 5px;
        }
        .site-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .site-item:last-child {
            border-bottom: none;
        }
        .site-title {
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        .site-title a {
            color: #007cba;
            text-decoration: none;
        }
        .site-title a:hover {
            text-decoration: underline;
        }
        .site-url {
            color: #28a745;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .site-description {
            color: #666;
            margin-bottom: 5px;
        }
        .site-meta {
            font-size: 0.85em;
            color: #999;
        }
        .pagination {
            text-align: center;
            margin-top: 30px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #007cba;
        }
        .pagination a:hover {
            background: #f5f5f5;
        }
        .pagination .current {
            background: #007cba;
            color: white;
            border-color: #007cba;
        }
        .nav-links {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .nav-links a {
            margin: 0 15px;
            color: #007cba;
            text-decoration: none;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .breadcrumb a {
            color: #007cba;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .no-results {
            text-align: center;
            color: #666;
            padding: 30px;
        }
    </style>
</head>
<body>
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

        <!-- パンくずナビ -->
        <?php if ($current_category || $search): ?>
            <div class="breadcrumb">
                <a href="?">ホーム</a>
                <?php if ($current_category): ?>
                    &gt; <?php echo h($current_category['name']); ?>
                <?php endif; ?>
                <?php if ($search): ?>
                    &gt; 検索: "<?php echo h($search); ?>"
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- カテゴリ一覧（トップページのみ） -->
        <?php if (!$search && !$category_id): ?>
            <div class="categories">
                <?php foreach ($categories as $category): ?>
                    <div class="category-item">
                        <div>
                            <a href="?category=<?php echo $category['id']; ?>">
                                <?php echo h($category['name']); ?>
                            </a>
                        </div>
                        <div class="site-count">
                            (<?php echo getCategorySiteCount($category['id']); ?>)
                        </div>
                        <?php if ($category['description']): ?>
                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">
                                <?php echo h($category['description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
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
            <a href="admin/">管理画面</a>
        </div>
    </div>
</body>
</html>