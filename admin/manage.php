<?php
require_once '../includes/config.php';
requireAdminLogin();

$message = '';
$error = '';

// アクション処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        $error = '無効なリクエストです。';
    } else {
        switch ($action) {
            // サイト管理アクション
            case 'approve':
                $site_id = (int)($_POST['site_id'] ?? 0);
                if ($site_id > 0) {
                    $stmt = $db->prepare("UPDATE sites SET status = 'approved', updated_at = datetime('now') WHERE id = ?");
                    if ($stmt->execute([$site_id])) {
                        $message = 'サイトを承認しました。';
                    } else {
                        $error = '承認に失敗しました。';
                    }
                }
                break;
                
            case 'reject':
                $site_id = (int)($_POST['site_id'] ?? 0);
                if ($site_id > 0) {
                    $stmt = $db->prepare("DELETE FROM sites WHERE id = ?");
                    if ($stmt->execute([$site_id])) {
                        $message = 'サイトを削除しました。';
                    } else {
                        $error = '削除に失敗しました。';
                    }
                }
                break;
                
            case 'edit':
                $site_id = (int)($_POST['site_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $url = trim($_POST['url'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
                
                if ($site_id > 0 && !empty($title) && !empty($url) && !empty($category_ids)) {
                    try {
                        $db->beginTransaction();
                        
                        $stmt = $db->prepare("UPDATE sites SET title = ?, url = ?, description = ?, updated_at = datetime('now') WHERE id = ?");
                        $stmt->execute([$title, $url, $description, $site_id]);
                        
                        if (!updateSiteCategories($site_id, $category_ids)) {
                            throw new Exception('カテゴリの更新に失敗しました。');
                        }
                        
                        $db->commit();
                        $message = 'サイト情報を更新しました。';
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollback();
                        }
                        $error = '更新に失敗しました。';
                    }
                } else {
                    $error = '必要な情報が入力されていません。';
                }
                break;
                
            case 'bulk_approve':
                $site_ids = $_POST['site_ids'] ?? [];
                if (!empty($site_ids)) {
                    $placeholders = str_repeat('?,', count($site_ids) - 1) . '?';
                    $stmt = $db->prepare("UPDATE sites SET status = 'approved', updated_at = datetime('now') WHERE id IN ($placeholders)");
                    if ($stmt->execute($site_ids)) {
                        $message = count($site_ids) . '件のサイトを承認しました。';
                    } else {
                        $error = '一括承認に失敗しました。';
                    }
                }
                break;
                
            case 'bulk_delete':
                $site_ids = $_POST['site_ids'] ?? [];
                if (!empty($site_ids)) {
                    $placeholders = str_repeat('?,', count($site_ids) - 1) . '?';
                    $stmt = $db->prepare("DELETE FROM sites WHERE id IN ($placeholders)");
                    if ($stmt->execute($site_ids)) {
                        $message = count($site_ids) . '件のサイトを削除しました。';
                    } else {
                        $error = '一括削除に失敗しました。';
                    }
                }
                break;

            // カテゴリ管理アクション
            case 'add_category':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $parent_id = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                
                if (empty($name)) {
                    $error = 'カテゴリ名を入力してください。';
                } elseif (isCategoryNameExists($name, $parent_id)) {
                    $error = '同じ階層に同名のカテゴリが既に存在します。';
                } elseif (addCategory($name, $description, $parent_id, $sort_order)) {
                    $message = 'カテゴリを追加しました。';
                } else {
                    $error = 'カテゴリの追加に失敗しました。';
                }
                break;
                
            case 'update_category':
                $id = (int)($_POST['category_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sort_order = (int)($_POST['sort_order'] ?? 0);
                
                if ($id <= 0) {
                    $error = '無効なカテゴリIDです。';
                } elseif (empty($name)) {
                    $error = 'カテゴリ名を入力してください。';
                } elseif (updateCategory($id, $name, $description, $sort_order)) {
                    $message = 'カテゴリを更新しました。';
                } else {
                    $error = 'カテゴリの更新に失敗しました。';
                }
                break;
                
            case 'delete_category':
                $id = (int)($_POST['category_id'] ?? 0);
                if ($id > 0) {
                    $result = deleteCategory($id);
                    if ($result['success']) {
                        $message = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                } else {
                    $error = '無効なカテゴリIDです。';
                }
                break;
                
            case 'reorder_categories':
                $parent_id = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;
                if (reorderCategories($parent_id)) {
                    $message = 'カテゴリの並び順を調整しました。';
                } else {
                    $error = '並び順の調整に失敗しました。';
                }
                break;

            // 設定管理アクション
            case 'update_settings':
                $settings = [
                    'site_title' => trim($_POST['site_title'] ?? ''),
                    'site_description' => trim($_POST['site_description'] ?? ''),
                    'max_sites' => (int)($_POST['max_sites'] ?? 0),
                    'sites_per_page' => (int)($_POST['sites_per_page'] ?? 0),
                    'require_approval' => $_POST['require_approval'] === '1' ? '1' : '0'
                ];
                
                $validation_errors = validateSettings($settings);
                if (!empty($validation_errors)) {
                    $error = implode('<br>', $validation_errors);
                } else {
                    $result = updateSettings($settings);
                    if ($result['success']) {
                        $message = $result['message'];
                        // グローバル設定変数を更新
                        $SITE_TITLE = $settings['site_title'];
                        $SITE_DESCRIPTION = $settings['site_description'];
                        $MAX_SITES = $settings['max_sites'];
                        $SITES_PER_PAGE = $settings['sites_per_page'];
                        $REQUIRE_APPROVAL = (bool)$settings['require_approval'];
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
        }
    }
}

// パラメータ取得
$tab = $_GET['tab'] ?? 'sites';
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$category_ids = isset($_GET['categories']) && is_array($_GET['categories']) ? array_map('intval', $_GET['categories']) : [];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// サイト一覧取得（サイト管理タブ用）
if ($tab === 'sites') {
    $sites = getSites($category_ids, $search, $page, $SITES_PER_PAGE, $status);
    $total_sites = getSitesCount($category_ids, $search, $status);
}

// 階層カテゴリ一覧取得
$hierarchical_categories = getCategoriesHierarchical();

// 編集対象のサイト情報
$edit_site = null;
$edit_site_categories = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_site = $stmt->fetch();
    if ($edit_site) {
        $edit_site_categories = array_column(getSiteCategories($edit_id), 'id');
    }
}

// カテゴリ管理用データ
if ($tab === 'categories') {
    $parent_categories = getParentCategories();
    $edit_category = null;
    if (isset($_GET['edit_category'])) {
        $edit_category_id = (int)$_GET['edit_category'];
        $edit_category = getCategoryDetails($edit_category_id);
    }
}

// 設定管理用データ
if ($tab === 'settings') {
    $current_settings = getAllSettings();
}

// ページネーション用URL
$base_url = "?tab={$tab}&status={$status}";
if ($search) $base_url .= '&search=' . urlencode($search);
foreach ($category_ids as $cat_id) {
    $base_url .= '&categories[]=' . $cat_id;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title>サイト管理 - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-admin">
    <div class="header">
        <div class="header-content">
            <h1>サイト管理</h1>
            <div class="header-nav">
                <a href="dashboard.php">ダッシュボード</a>
                <a href="../" target="_blank">サイト表示</a>
                <a href="?logout=1">ログアウト</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- タブナビゲーション -->
        <div class="tabs">
            <a href="?tab=sites" class="tab <?php echo $tab === 'sites' ? 'active' : ''; ?>">サイト管理</a>
            <a href="?tab=categories" class="tab <?php echo $tab === 'categories' ? 'active' : ''; ?>">カテゴリ管理</a>
            <a href="?tab=settings" class="tab <?php echo $tab === 'settings' ? 'active' : ''; ?>">設定</a>
        </div>

        <div class="content">
            <?php if ($tab === 'sites'): ?>
                <!-- 編集フォーム -->
                <?php if ($edit_site): ?>
                    <div class="edit-form">
                        <h3>サイト編集</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="site_id" value="<?php echo $edit_site['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>サイト名</label>
                                    <input type="text" name="title" value="<?php echo h($edit_site['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>URL</label>
                                    <input type="url" name="url" value="<?php echo h($edit_site['url']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>カテゴリ</label>
                                <div class="category-selection-admin">
                                    <?php foreach ($hierarchical_categories as $parent): ?>
                                        <div class="category-group-admin">
                                            <strong><?php echo h($parent['name']); ?></strong>
                                            <?php if (!empty($parent['children'])): ?>
                                                <div class="category-children-admin">
                                                    <?php foreach ($parent['children'] as $child): ?>
                                                        <label class="category-checkbox-admin">
                                                            <input type="checkbox" 
                                                                   name="category_ids[]" 
                                                                   value="<?php echo $child['id']; ?>"
                                                                   <?php echo in_array($child['id'], $edit_site_categories) ? 'checked' : ''; ?>>
                                                            <?php echo h($child['name']); ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>説明</label>
                                <textarea name="description" rows="3"><?php echo h($edit_site['description']); ?></textarea>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <button type="submit" class="btn btn-approve">更新</button>
                                <a href="?tab=sites&status=<?php echo $status; ?>" class="btn" style="background: #6c757d; color: white;">キャンセル</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- フィルタ -->
                <div class="filters">
                    <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                        <input type="hidden" name="tab" value="sites">
                        
                        <select name="status" onchange="this.form.submit()">
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>承認待ち</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>承認済み</option>
                        </select>
                        
                        <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="検索...">
                        
                        <!-- カテゴリフィルタ -->
                        <details class="category-filter-dropdown">
                            <summary>カテゴリ絞り込み 
                                <?php if (!empty($category_ids)): ?>
                                    (<?php echo count($category_ids); ?>件選択)
                                <?php endif; ?>
                            </summary>
                            <div class="filter-content">
                                <?php foreach ($hierarchical_categories as $parent): ?>
                                    <div class="filter-group-admin">
                                        <div class="filter-parent-admin"><?php echo h($parent['name']); ?></div>
                                        <?php if (!empty($parent['children'])): ?>
                                            <div class="filter-children-admin">
                                                <?php foreach ($parent['children'] as $child): ?>
                                                    <label class="filter-checkbox-admin">
                                                        <input type="checkbox" 
                                                               name="categories[]" 
                                                               value="<?php echo $child['id']; ?>"
                                                               <?php echo in_array($child['id'], $category_ids) ? 'checked' : ''; ?>>
                                                        <?php echo h($child['name']); ?> (<?php echo $child['site_count']; ?>)
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        
                        <button type="submit" class="btn">検索</button>
                        
                        <?php if ($search || !empty($category_ids)): ?>
                            <a href="?tab=sites&status=<?php echo $status; ?>" class="btn" style="background: #6c757d;">クリア</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- 一括操作 -->
                <?php if (!empty($sites)): ?>
                    <form method="POST" action="" id="bulk-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div class="bulk-actions">
                            <label>
                                <input type="checkbox" id="select-all"> 全選択
                            </label>
                            <?php if ($status === 'pending'): ?>
                                <button type="submit" name="action" value="bulk_approve" class="btn-approve">選択を承認</button>
                            <?php endif; ?>
                            <button type="submit" name="action" value="bulk_delete" class="btn-delete" onclick="return confirm('選択したサイトを削除しますか？')">選択を削除</button>
                        </div>

                        <!-- サイト一覧テーブル -->
                        <table class="site-table">
                            <thead>
                                <tr>
                                    <th width="30"></th>
                                    <th>サイト情報</th>
                                    <th>カテゴリ</th>
                                    <th>登録日</th>
                                    <th width="120">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sites as $site): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="site_ids[]" value="<?php echo $site['id']; ?>" class="site-checkbox">
                                        </td>
                                        <td>
                                            <div class="site-title"><?php echo h($site['title']); ?></div>
                                            <div class="site-url">
                                                <a href="<?php echo h($site['url']); ?>" target="_blank"><?php echo h($site['url']); ?></a>
                                            </div>
                                            <?php if ($site['description']): ?>
                                                <div style="color: #666; font-size: 0.9em; margin-top: 5px;">
                                                    <?php echo h(mb_substr($site['description'], 0, 100)) . (mb_strlen($site['description']) > 100 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="categories-display">
                                                <?php foreach ($site['categories'] as $cat): ?>
                                                    <span class="category-badge">
                                                        <?php echo $cat['parent_name'] ? h($cat['parent_name'] . ' > ' . $cat['name']) : h($cat['name']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('Y/m/d H:i', strtotime($site['created_at'])); ?></td>
                                        <td>
                                            <div class="actions">
                                                <?php if ($status === 'pending'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <button type="submit" class="btn btn-approve">承認</button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <a href="?tab=sites&edit=<?php echo $site['id']; ?>&status=<?php echo $status; ?>" class="btn btn-edit">編集</a>
                                                
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="site_id" value="<?php echo $site['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <button type="submit" class="btn btn-delete" onclick="return confirm('このサイトを削除しますか？')">削除</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>

                    <!-- ページネーション -->
                    <?php echo generatePagination($page, $total_sites, $SITES_PER_PAGE, $base_url); ?>
                <?php else: ?>
                    <div class="no-results">
                        <?php if ($status === 'pending'): ?>
                            承認待ちのサイトはありません。
                        <?php else: ?>
                            承認済みのサイトがありません。
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($tab === 'categories'): ?>
                <h2>カテゴリ管理</h2>
                
                <!-- カテゴリ追加フォーム -->
                <?php if (!isset($_GET['edit_category'])): ?>
                    <div class="edit-form">
                        <h3>新規カテゴリ追加</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_category">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>親カテゴリ</label>
                                    <select name="parent_id">
                                        <option value="">-- トップレベル --</option>
                                        <?php foreach ($parent_categories as $parent): ?>
                                            <option value="<?php echo $parent['id']; ?>"><?php echo h($parent['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>並び順</label>
                                    <input type="number" name="sort_order" value="0" min="0">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>カテゴリ名 <span class="required">*</span></label>
                                <input type="text" name="name" required maxlength="100">
                            </div>
                            
                            <div class="form-group">
                                <label>説明</label>
                                <textarea name="description" rows="2" maxlength="500"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">カテゴリを追加</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- カテゴリ編集フォーム -->
                <?php if ($edit_category): ?>
                    <div class="edit-form">
                        <h3>カテゴリ編集</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_category">
                            <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>階層</label>
                                    <input type="text" value="<?php echo $edit_category['parent_name'] ? h($edit_category['parent_name']) . ' > ' . h($edit_category['name']) : 'トップレベル > ' . h($edit_category['name']); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>並び順</label>
                                    <input type="number" name="sort_order" value="<?php echo $edit_category['sort_order']; ?>" min="0">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>カテゴリ名 <span class="required">*</span></label>
                                <input type="text" name="name" value="<?php echo h($edit_category['name']); ?>" required maxlength="100">
                            </div>
                            
                            <div class="form-group">
                                <label>説明</label>
                                <textarea name="description" rows="2" maxlength="500"><?php echo h($edit_category['description']); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">更新</button>
                            <a href="?tab=categories" class="btn" style="background: #6c757d; color: white;">キャンセル</a>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- カテゴリ一覧 -->
                <div class="bulk-actions">
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="reorder_categories">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" class="btn btn-secondary">並び順を自動調整</button>
                    </form>
                </div>

                <table class="site-table">
                    <thead>
                        <tr>
                            <th>階層</th>
                            <th>カテゴリ名</th>
                            <th>説明</th>
                            <th>並び順</th>
                            <th>サイト数</th>
                            <th width="150">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hierarchical_categories as $parent): ?>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td>親カテゴリ</td>
                                <td><?php echo h($parent['name']); ?></td>
                                <td><?php echo h($parent['description']); ?></td>
                                <td><?php echo $parent['sort_order']; ?></td>
                                <td><?php echo $parent['total_sites']; ?></td>
                                <td>
                                    <a href="?tab=categories&edit_category=<?php echo $parent['id']; ?>" class="btn btn-edit">編集</a>
                                    <?php if ($parent['total_sites'] == 0 && empty($parent['children'])): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('このカテゴリを削除しますか？')">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?php echo $parent['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <button type="submit" class="btn btn-delete">削除</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($parent['children'])): ?>
                                <?php foreach ($parent['children'] as $child): ?>
                                    <tr>
                                        <td style="padding-left: 30px;">├ 子カテゴリ</td>
                                        <td><?php echo h($child['name']); ?></td>
                                        <td><?php echo h($child['description']); ?></td>
                                        <td><?php echo $child['sort_order']; ?></td>
                                        <td><?php echo $child['site_count']; ?></td>
                                        <td>
                                            <a href="?tab=categories&edit_category=<?php echo $child['id']; ?>" class="btn btn-edit">編集</a>
                                            <?php if ($child['site_count'] == 0): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('このカテゴリを削除しますか？')">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <button type="submit" class="btn btn-delete">削除</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($tab === 'settings'): ?>
                <h2>サイト設定</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    
                    <div class="form-group">
                        <label for="site_title">サイトタイトル <span class="required">*</span></label>
                        <input type="text" id="site_title" name="site_title" value="<?php echo h($current_settings['site_title'] ?? ''); ?>" required maxlength="100">
                        <div class="help-text">サイトの名前を入力してください（100文字以内）</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_description">サイト説明</label>
                        <textarea id="site_description" name="site_description" rows="3" maxlength="500"><?php echo h($current_settings['site_description'] ?? ''); ?></textarea>
                        <div class="help-text">サイトの説明を入力してください（500文字以内）</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_sites">最大サイト数 <span class="required">*</span></label>
                            <input type="number" id="max_sites" name="max_sites" value="<?php echo h($current_settings['max_sites'] ?? '2000'); ?>" min="1" max="10000" required>
                            <div class="help-text">登録可能な最大サイト数（1-10000）</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="sites_per_page">1ページあたりサイト数 <span class="required">*</span></label>
                            <input type="number" id="sites_per_page" name="sites_per_page" value="<?php echo h($current_settings['sites_per_page'] ?? '20'); ?>" min="1" max="100" required>
                            <div class="help-text">1ページに表示するサイト数（1-100）</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>承認制</label>
                        <label style="font-weight: normal; display: block; margin-top: 10px;">
                            <input type="radio" name="require_approval" value="1" <?php echo ($current_settings['require_approval'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            有効（管理者の承認後に公開）
                        </label>
                        <label style="font-weight: normal; display: block; margin-top: 5px;">
                            <input type="radio" name="require_approval" value="0" <?php echo ($current_settings['require_approval'] ?? '1') === '0' ? 'checked' : ''; ?>>
                            無効（登録後すぐに公開）
                        </label>
                        <div class="help-text">サイト登録時の承認制の有無を設定します</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">設定を保存</button>
                </form>
                
                <div class="info-box" style="margin-top: 30px;">
                    <h4>📊 現在の状況</h4>
                    <table class="site-table">
                        <tr>
                            <td>承認済みサイト数</td>
                            <td><?php echo number_format(getSiteCount('approved')); ?> サイト</td>
                        </tr>
                        <tr>
                            <td>承認待ちサイト数</td>
                            <td><?php echo number_format(getSiteCount('pending')); ?> サイト</td>
                        </tr>
                        <tr>
                            <td>残り登録可能数</td>
                            <td><?php echo number_format(($current_settings['max_sites'] ?? 2000) - getSiteCount('approved') - getSiteCount('pending')); ?> サイト</td>
                        </tr>
                        <tr>
                            <td>カテゴリ数</td>
                            <td><?php echo count($hierarchical_categories); ?> カテゴリ</td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 全選択機能
        if (document.getElementById('select-all')) {
            document.getElementById('select-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.site-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }

        // 一括操作の確認
        if (document.getElementById('bulk-form')) {
            document.getElementById('bulk-form').addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.site-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('操作するサイトを選択してください。');
                }
            });
        }
    </script>
</body>
</html>