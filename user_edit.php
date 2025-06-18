<?php
require_once 'includes/config.php';
requireUserLogin();

$message = '';
$error = '';
$password_message = '';
$password_error = '';

// 現在のサイト情報取得
$site = getCurrentUserSite();

if (!$site) {
    session_destroy();
    header('Location: user_login.php');
    exit;
}

// 現在のカテゴリIDを取得
$current_category_ids = array_column($site['categories'], 'id');

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        $error = '無効なリクエストです。';
    } elseif ($action === 'update_site') {
        // サイト情報更新
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
        
        // バリデーション
        if (empty($title)) {
            $error = 'サイト名を入力してください。';
        } elseif (empty($url)) {
            $error = 'URLを入力してください。';
        } elseif (!isValidUrl($url)) {
            $error = '有効なURLを入力してください。';
        } elseif (empty($category_ids)) {
            $error = '少なくとも1つのカテゴリを選択してください。';
        } elseif (strlen($title) > 100) {
            $error = 'サイト名は100文字以内で入力してください。';
        } elseif (strlen($description) > 500) {
            $error = '説明文は500文字以内で入力してください。';
        } else {
            // サイト情報更新実行
            $result = updateUserSite($site['id'], $title, $url, $description, $category_ids, $_SESSION['user_email']);
            
            if ($result['success']) {
                $message = $result['message'];
                // サイト情報再取得
                $site = getCurrentUserSite();
                $current_category_ids = array_column($site['categories'], 'id');
            } else {
                $error = $result['message'];
            }
        }
    } elseif ($action === 'change_password') {
        // パスワード変更
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $new_password_confirm = $_POST['new_password_confirm'] ?? '';
        
        // バリデーション
        if (empty($current_password)) {
            $password_error = '現在のパスワードを入力してください。';
        } elseif (empty($new_password)) {
            $password_error = '新しいパスワードを入力してください。';
        } elseif (!isValidPassword($new_password)) {
            $password_error = '新しいパスワードは半角英数字3〜8文字で入力してください。';
        } elseif ($new_password !== $new_password_confirm) {
            $password_error = '新しいパスワードが一致しません。';
        } else {
            // パスワード変更実行
            $result = updateUserPassword($site['id'], $current_password, $new_password, $_SESSION['user_email']);
            
            if ($result['success']) {
                $password_message = $result['message'];
            } else {
                $password_error = $result['message'];
            }
        }
    }
}

// 階層カテゴリ一覧取得
$hierarchical_categories = getCategoriesHierarchical();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title>サイト情報編集 - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-user">
    <div class="header">
        <div class="header-content">
            <h1>サイト情報編集</h1>
            <div class="header-nav">
                <a href="user_dashboard.php">ダッシュボード</a>
                <a href="." target="_blank">サイト表示</a>
                <a href="?logout=1">ログアウト</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- サイト情報編集 -->
        <div class="section">
            <div class="section-header">
                <h2>サイト情報編集</h2>
            </div>
            <div class="section-content">
                <?php if ($message): ?>
                    <div class="message success"><?php echo h($message); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="message error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="current-info">
                    <h4>現在の情報</h4>
                    <div class="info-item">
                        <span class="info-label">ステータス:</span>
                        <?php echo $site['status'] === 'approved' ? '公開中' : '承認待ち'; ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">更新日:</span>
                        <?php echo date('Y年m月d日 H:i', strtotime($site['updated_at'])); ?>
                    </div>
                    <div class="info-item">
                        <span class="info-label">現在のカテゴリ:</span>
                        <?php echo h($site['category_names']); ?>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update_site">

                    <div class="form-group">
                        <label for="title">サイト名 <span class="required">*</span></label>
                        <input type="text" id="title" name="title" value="<?php echo h($site['title']); ?>" maxlength="100" required>
                        <div class="help-text">100文字以内で入力してください</div>
                    </div>

                    <div class="form-group">
                        <label for="url">URL <span class="required">*</span></label>
                        <input type="url" id="url" name="url" value="<?php echo h($site['url']); ?>" required>
                        <div class="help-text">http://またはhttps://から始まる完全なURLを入力してください</div>
                    </div>

                    <div class="form-group">
                        <label>カテゴリ <span class="required">*</span></label>
                        <div class="help-text" style="margin-bottom: 15px;">該当するカテゴリを複数選択できます。サイトの特徴に合うものをすべて選択してください。</div>
                        
                        <div class="category-selection">
                            <?php foreach ($hierarchical_categories as $parent): ?>
                                <div class="category-group-selection">
                                    <div class="category-parent-header">
                                        <h4><?php echo h($parent['name']); ?></h4>
                                        <p class="category-parent-desc"><?php echo h($parent['description']); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($parent['children'])): ?>
                                        <div class="category-children-selection">
                                            <?php foreach ($parent['children'] as $child): ?>
                                                <label class="category-checkbox">
                                                    <input type="checkbox" 
                                                           name="category_ids[]" 
                                                           value="<?php echo $child['id']; ?>"
                                                           <?php echo in_array($child['id'], $current_category_ids) ? 'checked' : ''; ?>>
                                                    <span class="checkbox-label">
                                                        <strong><?php echo h($child['name']); ?></strong>
                                                        <?php if ($child['description']): ?>
                                                            <small><?php echo h($child['description']); ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">サイト説明</label>
                        <textarea id="description" name="description" maxlength="500" placeholder="サイトの内容について簡潔に説明してください"><?php echo h($site['description']); ?></textarea>
                        <div class="help-text">500文字以内で入力してください（任意）</div>
                    </div>

                    <!-- 選択されたカテゴリの表示 -->
                    <div class="selected-categories" id="selected-categories">
                        <h4>選択中のカテゴリ</h4>
                        <div id="selected-list"></div>
                    </div>

                    <button type="submit" class="btn btn-primary">サイト情報を更新</button>
                </form>
            </div>
        </div>

        <!-- パスワード変更 -->
        <div class="section">
            <div class="section-header">
                <h2>パスワード変更</h2>
            </div>
            <div class="section-content">
                <?php if ($password_message): ?>
                    <div class="message success"><?php echo h($password_message); ?></div>
                <?php endif; ?>

                <?php if ($password_error): ?>
                    <div class="message error"><?php echo h($password_error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">現在のパスワード <span class="required">*</span></label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">新しいパスワード <span class="required">*</span></label>
                            <input type="password" id="new_password" name="new_password" minlength="3" maxlength="8" required>
                            <div class="help-text">半角英数字3〜8文字で入力してください</div>
                        </div>

                        <div class="form-group">
                            <label for="new_password_confirm">新しいパスワード確認 <span class="required">*</span></label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm" minlength="3" maxlength="8" required>
                            <div class="help-text">確認のため再度入力してください</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning">パスワードを変更</button>
                </form>
            </div>
        </div>

        <div class="back-link">
            <a href="user_dashboard.php">&laquo; ダッシュボードに戻る</a>
            <a href=".">サイトトップへ</a>
        </div>
    </div>

    <script>
        // パスワード一致チェック
        document.getElementById('new_password_confirm').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            const helpText = this.nextElementSibling;
            
            if (confirm && password !== confirm) {
                this.style.borderColor = '#dc3545';
                helpText.style.color = '#dc3545';
                helpText.textContent = 'パスワードが一致しません';
            } else {
                this.style.borderColor = '#ddd';
                helpText.style.color = '#666';
                helpText.textContent = '確認のため再度入力してください';
            }
        });

        // 文字数カウンター
        document.getElementById('title').addEventListener('input', function() {
            const maxLength = 100;
            const currentLength = this.value.length;
            const helpText = this.nextElementSibling;
            helpText.textContent = `${currentLength}/${maxLength}文字`;
            if (currentLength > maxLength * 0.9) {
                helpText.style.color = '#dc3545';
            } else {
                helpText.style.color = '#666';
            }
        });

        document.getElementById('description').addEventListener('input', function() {
            const maxLength = 500;
            const currentLength = this.value.length;
            const helpText = this.nextElementSibling;
            helpText.textContent = `${currentLength}/${maxLength}文字`;
            if (currentLength > maxLength * 0.9) {
                helpText.style.color = '#dc3545';
            } else {
                helpText.style.color = '#666';
            }
        });

        // 選択されたカテゴリの表示更新
        function updateSelectedCategories() {
            const checkboxes = document.querySelectorAll('input[name="category_ids[]"]:checked');
            const selectedDiv = document.getElementById('selected-categories');
            const listDiv = document.getElementById('selected-list');
            
            if (checkboxes.length > 0) {
                selectedDiv.style.display = 'block';
                let html = '';
                
                checkboxes.forEach(function(checkbox) {
                    const label = checkbox.parentNode.querySelector('.checkbox-label strong').textContent;
                    const parentGroup = checkbox.closest('.category-group-selection').querySelector('h4').textContent;
                    html += '<span class="selected-tag">' + parentGroup + ' > ' + label + '</span>';
                });
                
                listDiv.innerHTML = html;
            } else {
                selectedDiv.style.display = 'none';
            }
        }

        // カテゴリチェックボックスの変更を監視
        document.querySelectorAll('input[name="category_ids[]"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateSelectedCategories);
        });

        // 初期表示時の選択状態を反映
        updateSelectedCategories();
    </script>
</body>
</html>