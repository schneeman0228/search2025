
<?php
require_once 'includes/config.php';

$message = '';
$error = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        $error = '無効なリクエストです。';
    } else {
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // バリデーション
        if (empty($title)) {
            $error = 'サイト名を入力してください。';
        } elseif (empty($url)) {
            $error = 'URLを入力してください。';
        } elseif (!isValidUrl($url)) {
            $error = '有効なURLを入力してください。';
        } elseif (empty($category_ids)) {
            $error = '少なくとも1つのカテゴリを選択してください。';
        } elseif (empty($email)) {
            $error = 'メールアドレスを入力してください。';
        } elseif (!isValidEmail($email)) {
            $error = '有効なメールアドレスを入力してください。';
        } elseif (empty($password)) {
            $error = 'パスワードを入力してください。';
        } elseif (!isValidPassword($password)) {
            $error = 'パスワードは半角英数字3〜8文字で入力してください。';
        } elseif (strlen($title) > 100) {
            $error = 'サイト名は100文字以内で入力してください。';
        } elseif (strlen($description) > 500) {
            $error = '説明文は500文字以内で入力してください。';
        } elseif (!checkIpLimit($ip_address)) {
            $error = '短時間での登録回数が上限に達しています。しばらく時間をおいてから再度お試しください。';
        } else {
            // URL重複チェック
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE url = ?");
            $stmt->execute([$url]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $error = 'このURLは既に登録されています。';
            } else {
                // サイト数上限チェック
                $totalSites = getSiteCount('approved') + getSiteCount('pending');
                if ($totalSites >= $MAX_SITES) {
                    $error = 'サイト登録数が上限に達しています。';
                } else {
                    // サイト登録実行
                    $result = registerSiteWithUser($title, $url, $description, $category_ids, $email, $password, $ip_address);
                    
                    if ($result['success']) {
                        $message = $result['message'];
                        // フォームクリア
                        $title = $url = $description = $email = '';
                        $category_ids = [];
                    } else {
                        $error = $result['message'];
                    }
                }
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
    <title>サイト登録 - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-register">
    <div class="container">
        <div class="back-link">
            <a href=".">&laquo; トップページに戻る</a>
            <a href="user_login.php">既に登録済みの方はこちら</a>
        </div>

        <div class="header">
            <h1>サイト登録</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="security-note">
            <h4>🔒 ログイン情報について</h4>
            <p>登録時に設定するメールアドレスとパスワードは、後でサイト情報を編集する際に必要になります。安全なパスワードを設定し、忘れないようにしてください。</p>
        </div>

        <div class="guidelines">
            <h3>登録について</h3>
            <ul>
                <?php if ($REQUIRE_APPROVAL): ?>
                    <li><strong>承認制です</strong> - 登録されたサイトは管理者の承認後に掲載されます</li>
                    <li>承認後、登録したメールアドレスとパスワードでログインして情報を編集できます</li>
                <?php else: ?>
                    <li><strong>自動承認です</strong> - 登録されたサイトはすぐにサイト一覧に掲載されます</li>
                    <li>登録後、すぐにメールアドレスとパスワードでログインして情報を編集できます</li>
                <?php endif; ?>
                <li>不適切なサイトや規約に反するサイトは削除される場合があります</li>
                <li>同一のURLは重複して登録できません（メールアドレスは重複OK）</li>
                <li>登録可能サイト数: <?php echo number_format($MAX_SITES); ?>サイト（現在: <?php echo number_format(getSiteCount('approved')); ?>サイト）</li>
                <li><strong>複数のカテゴリを選択可能です</strong>（例：漫画 + 全年齢 + オリジナル + 交流）</li>
            </ul>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label for="title">サイト名 <span class="required">*</span></label>
                <input type="text" id="title" name="title" value="<?php echo h($title ?? ''); ?>" maxlength="100" required>
                <div class="help-text">100文字以内で入力してください</div>
            </div>

            <div class="form-group">
                <label for="url">URL <span class="required">*</span></label>
                <input type="url" id="url" name="url" value="<?php echo h($url ?? ''); ?>" required>
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
                                                   <?php echo (isset($category_ids) && in_array($child['id'], $category_ids)) ? 'checked' : ''; ?>>
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
                <textarea id="description" name="description" maxlength="500" placeholder="サイトの内容について簡潔に説明してください"><?php echo h($description ?? ''); ?></textarea>
                <div class="help-text">500文字以内で入力してください（任意）</div>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 20px; color: #333;">編集用ログイン情報</h3>

            <div class="form-group">
                <label for="email">メールアドレス <span class="required">*</span></label>
                <input type="email" id="email" name="email" value="<?php echo h($email ?? ''); ?>" required>
                <div class="help-text">サイト情報編集時のログインに使用します</div>
            </div>

            <div class="form-group">
                <label for="password">パスワード <span class="required">*</span></label>
                <input type="password" id="password" name="password" minlength="3" maxlength="8" required>
                <div class="help-text">半角英数字3〜8文字で入力してください</div>
            </div>

            <!-- 選択されたカテゴリの表示 -->
            <div class="selected-categories" id="selected-categories" style="display: none;">
                <h4>選択中のカテゴリ</h4>
                <div id="selected-list"></div>
            </div>

            <button type="submit" class="submit-button">サイトを登録する</button>
        </form>
    </div>

    <script>
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