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
                $category_id = (int)($_POST['category_id'] ?? 0);
                
                if ($site_id > 0 && !empty($title) && !empty($url) && $category_id > 0) {
                    $stmt = $db->prepare("UPDATE sites SET title = ?, url = ?, description = ?, category_id = ?, updated_at = datetime('now') WHERE id = ?");
                    if ($stmt->execute([$title, $url, $description, $category_id, $site_id])) {
                        $message = 'サイト情報を更新しました。';
                    } else {
                        $error = '更新に失敗しました。';
                    }
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
        }
    }
}

// パラメータ取得
$tab = $_GET['tab'] ?? 'sites';
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// サイト一覧取得
$sites = getSites($category_id, $search, $page, $SITES_PER_PAGE, $status);
$total_sites = getSitesCount($category_id, $search, $status);

// カテゴリ一覧取得
$categories = getCategories();

// 編集対象のサイト情報
$edit_site = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_site = $stmt->fetch();
}

// ページネーション用URL
$base_url = "?tab={$tab}&status={$status}";
if ($search) $base_url .= '&search=' . urlencode($search);
if ($category_id) $base_url .= '&category=' . $category_id;
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
            <div class="message error"><?php echo h($error); ?></div>
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
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>カテゴリ</label>
                                    <select name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $edit_site['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo h($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>説明</label>
                                <textarea name="description" rows="3"><?php echo h($edit_site['description']); ?></textarea>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <button type="submit" class="btn btn-approve">更新</button>
                                <a href="?" class="btn" style="background: #6c757d; color: white;">キャンセル</a>
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
                        
                        <select name="category" onchange="this.form.submit()">
                            <option value="">全カテゴリ</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="text" name="search" value="<?php echo h($search); ?>" placeholder="検索...">
                        <button type="submit" class="btn">検索</button>
                        
                        <?php if ($search || $category_id): ?>
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
                                        <td><?php echo h($site['category_name']); ?></td>
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
                <p>※カテゴリ管理機能は今後実装予定です。現在はデータベースで直接編集してください。</p>
                
                <table class="site-table">
                    <thead>
                        <tr>
                            <th>カテゴリ名</th>
                            <th>説明</th>
                            <th>サイト数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo h($category['name']); ?></td>
                                <td><?php echo h($category['description']); ?></td>
                                <td><?php echo getCategorySiteCount($category['id']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($tab === 'settings'): ?>
                <h2>基本設定</h2>
                <p>※設定機能は今後実装予定です。現在はデータベースのsettingsテーブルで直接編集してください。</p>
                
                <table class="site-table">
                    <thead>
                        <tr>
                            <th>設定項目</th>
                            <th>現在値</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>サイトタイトル</td>
                            <td><?php echo h($SITE_TITLE); ?></td>
                        </tr>
                        <tr>
                            <td>サイト説明</td>
                            <td><?php echo h($SITE_DESCRIPTION); ?></td>
                        </tr>
                        <tr>
                            <td>最大サイト数</td>
                            <td><?php echo number_format($MAX_SITES); ?></td>
                        </tr>
                        <tr>
                            <td>1ページあたりサイト数</td>
                            <td><?php echo $SITES_PER_PAGE; ?></td>
                        </tr>
                        <tr>
                            <td>承認制</td>
                            <td><?php echo $REQUIRE_APPROVAL ? '有効' : '無効'; ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 全選択機能
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.site-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // 一括操作の確認
        document.getElementById('bulk-form').addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('.site-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('操作するサイトを選択してください。');
            }
        });
    </script>
</body>
</html>