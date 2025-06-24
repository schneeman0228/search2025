<?php
require_once 'includes/config.php';

// パラメータ取得
$search = $_GET['search'] ?? '';
$category_ids = isset($_GET['categories']) && is_array($_GET['categories']) ? array_map('intval', $_GET['categories']) : [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// エラーメッセージ表示
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'site_not_found':
            $error_message = '指定されたサイトが見つからないか、現在承認待ちの状態です。';
            break;
    }
}

// 選択されたカテゴリ情報を取得
$selected_categories = [];
$selected_category_names = [];
if (!empty($category_ids)) {
    foreach ($category_ids as $cat_id) {
        $category = getCategory($cat_id);
        if ($category) {
            $selected_categories[] = $category;
            $parent = $category['parent_id'] ? getCategory($category['parent_id']) : null;
            $name = $parent ? $parent['name'] . ' > ' . $category['name'] : $category['name'];
            $selected_category_names[] = $name;
        }
    }
}

// サイト一覧取得
$sites = getSites($category_ids, $search, $page, $SITES_PER_PAGE);
$total_sites = getSitesCount($category_ids, $search);

// 階層カテゴリ一覧取得
$hierarchical_categories = getCategoriesHierarchical();

// ページネーション用URL
$base_url = '?';
if ($search) $base_url .= 'search=' . urlencode($search) . '&';
if (!empty($category_ids)) {
    foreach ($category_ids as $cat_id) {
        $base_url .= 'categories[]=' . $cat_id . '&';
    }
}
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
            <h1><a href ="index.php"><?php echo h($SITE_TITLE); ?></a></h1>
            <p><?php echo h($SITE_DESCRIPTION); ?></p>
        </div>

        <!-- エラーメッセージ表示 -->
        <?php if ($error_message): ?>
            <div class="message error"><?php echo h($error_message); ?></div>
        <?php endif; ?>

        <!-- 検索・フィルタフォーム -->
        <div class="search-filter-box">
            <form method="GET" action="" id="search-form">
                <div class="search-row">
                    <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="サイトを検索...">
                    <input type="submit" value="検索">
                    <?php if ($search || !empty($category_ids)): ?>
                        <a href="?" class="clear-button">クリア</a>
                    <?php endif; ?>
                </div>
                
                <!-- カテゴリフィルタ -->
                <div class="category-filter-container">
                    <button type="button" id="toggle-filter" class="filter-toggle">
                        🔽 カテゴリで絞り込み
                        <?php if (!empty($category_ids)): ?>
                            <span class="filter-count">(<?php echo count($category_ids); ?>件選択中)</span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="category-filter" id="category-filter" style="display: none;">
                        <?php foreach ($hierarchical_categories as $parent): ?>
                            <div class="filter-group">
                                <div class="filter-parent"><?php echo h($parent['name']); ?></div>
                                <?php if (!empty($parent['children'])): ?>
                                    <div class="filter-children">
                                        <?php foreach ($parent['children'] as $child): ?>
                                            <label class="filter-checkbox">
                                                <input type="checkbox" 
                                                       name="categories[]" 
                                                       value="<?php echo $child['id']; ?>"
                                                       <?php echo in_array($child['id'], $category_ids) ? 'checked' : ''; ?>
                                                       onchange="this.form.submit()">
                                                <span><?php echo h($child['name']); ?> (<?php echo $child['site_count']; ?>)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- 選択中のカテゴリ表示 -->
        <?php if (!empty($selected_categories) || $search): ?>
            <div class="current-filters">
                <h4>現在の絞り込み条件</h4>
                <div class="filter-tags">
                    <?php if ($search): ?>
                        <span class="filter-tag search-tag">
                            検索: "<?php echo h($search); ?>"
                            <a href="?<?php echo !empty($category_ids) ? 'categories[]=' . implode('&categories[]=', $category_ids) : ''; ?>" class="remove-filter">×</a>
                        </span>
                    <?php endif; ?>
                    
                    <?php foreach ($selected_categories as $i => $category): ?>
                        <span class="filter-tag category-tag">
                            <?php echo h($selected_category_names[$i]); ?>
                            <a href="?<?php 
                                $remaining_cats = array_diff($category_ids, [$category['id']]);
                                $url_parts = [];
                                if ($search) $url_parts[] = 'search=' . urlencode($search);
                                foreach ($remaining_cats as $cat_id) {
                                    $url_parts[] = 'categories[]=' . $cat_id;
                                }
                                echo implode('&', $url_parts);
                            ?>" class="remove-filter">×</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 階層カテゴリ一覧（フィルタ未使用時のみ表示） -->
        <?php if (!$search && empty($category_ids)): ?>
            <?php echo generateHierarchicalCategoryHTML($hierarchical_categories); ?>
        <?php endif; ?>

        <!-- サイト一覧 -->
        <div class="sites-section">
            <?php if ($search): ?>
                <h2>検索結果: "<?php echo h($search); ?>" (<?php echo $total_sites; ?>件)</h2>
            <?php elseif (!empty($selected_categories)): ?>
                <h2>カテゴリ: <?php echo implode(', ', $selected_category_names); ?> (<?php echo $total_sites; ?>件)</h2>
            <?php else: ?>
                <h2>新着サイト</h2>
            <?php endif; ?>

            <?php if (empty($sites)): ?>
                <div class="no-results">
                    <?php if ($search || !empty($category_ids)): ?>
                        検索条件に一致するサイトが見つかりませんでした。
                    <?php else: ?>
                        まだサイトが登録されていません。
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($sites as $site): ?>
                    <div class="site-item">
                        <div class="site-header">
                            <div class="site-title">
                                <a href="<?php echo h($site['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo h($site['title']); ?>
                                </a>
                            </div>
                            <div class="site-manage-link">
                                <a href="site_login.php?site_id=<?php echo $site['id']; ?>" class="manage-link">
                                    🛠️ 管理
                                </a>
                            </div>
                        </div>
                        <div class="site-url"><?php echo h($site['url']); ?></div>
                        <?php if ($site['description']): ?>
                            <div class="site-description"><?php echo h($site['description']); ?></div>
                        <?php endif; ?>
                        <div class="site-meta">
                            <div class="site-categories">
                                カテゴリ: 
                                <?php foreach ($site['categories'] as $i => $cat): ?>
                                    <?php if ($i > 0) echo ', '; ?>
                                    <a href="?categories[]=<?php echo $cat['id']; ?>" class="category-link">
                                        <?php echo $cat['parent_name'] ? h($cat['parent_name'] . ' > ' . $cat['name']) : h($cat['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="site-date">
                                登録日: <?php echo date('Y年m月d日', strtotime($site['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- ページネーション -->
                <?php echo generatePagination($page, $total_sites, $SITES_PER_PAGE, $base_url); ?>
            <?php endif; ?>
        </div>

        <!-- ナビゲーションリンク（サイト情報編集リンクを削除し、管理画面のみ残す） -->
        <div class="nav-links">
            <a href="register.php">📝 サイト登録</a>
            <a href="admin/login.php">⚙️ 管理画面</a>
        </div>
        
        <!-- 個別サイト管理についての案内 -->
        <div class="info-box" style="margin-top: 30px;">
            <h4>🛠️ サイト管理について</h4>
            <p>各サイトの「管理」ボタンから、そのサイト専用の管理画面にアクセスできます。</p>
            <ul>
                <li><strong>個別管理</strong>：各サイトごとに独立した管理画面</li>
                <li><strong>安全性</strong>：そのサイトの登録情報でのみログイン可能</li>
                <li><strong>即座反映</strong>：更新した情報は承認不要で即座に反映</li>
                <li><strong>複数カテゴリ</strong>：複数のカテゴリを自由に選択可能</li>
            </ul>
        </div>
    </div>

    <script>
        // カテゴリフィルタの開閉
        document.getElementById('toggle-filter').addEventListener('click', function() {
            const filter = document.getElementById('category-filter');
            if (filter.style.display === 'none') {
                filter.style.display = 'block';
                this.textContent = this.textContent.replace('🔽', '🔼');
            } else {
                filter.style.display = 'none';
                this.textContent = this.textContent.replace('🔼', '🔽');
            }
        });

        // URLパラメータにカテゴリがある場合は初期表示でフィルタを開く
        <?php if (!empty($category_ids)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('toggle-filter').click();
        });
        <?php endif; ?>
    </script>
</body>
</html>