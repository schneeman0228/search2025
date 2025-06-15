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
        $category_id = (int)($_POST['category_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // バリデーション
        if (empty($title)) {
            $error = 'サイト名を入力してください。';
        } elseif (empty($url)) {
            $error = 'URLを入力してください。';
        } elseif (!isValidUrl($url)) {
            $error = '有効なURLを入力してください。';
        } elseif ($category_id <= 0) {
            $error = 'カテゴリを選択してください。';
        } elseif (empty($email)) {
            $error = 'メールアドレスを入力してください。';
        } elseif (!isValidEmail($email)) {
            $error = '有効なメールアドレスを入力してください。';
        } elseif (empty($password)) {
            $error = 'パスワードを入力してください。';
        } elseif (strlen($password) < 6) {
            $error = 'パスワードは6文字以上で入力してください。';
        } elseif ($password !== $password_confirm) {
            $error = 'パスワードが一致しません。';
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
                // メール重複チェック
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE email = ?");
                $stmt->execute([$email]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $error = 'このメールアドレスは既に使用されています。';
                } else {
                    // サイト数上限チェック
                    $totalSites = getSiteCount('approved') + getSiteCount('pending');
                    if ($totalSites >= $MAX_SITES) {
                        $error = 'サイト登録数が上限に達しています。';
                    } else {
                        // サイト登録実行
                        try {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("
                                INSERT INTO sites (title, url, description, category_id, email, password_hash, ip_address, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                            ");
                            $stmt->execute([$title, $url, $description, $category_id, $email, $password_hash, $ip_address]);
                            
                            $message = 'サイトを登録しました。管理者の承認をお待ちください。承認後、このメールアドレスとパスワードでログインして情報を編集できます。';
                            // フォームクリア
                            $title = $url = $description = $email = '';
                            $category_id = 0;
                        } catch (PDOException $e) {
                            $error = 'データベースエラーが発生しました。';
                        }
                    }
                }
            }
        }
    }
}

// カテゴリ一覧取得
$categories = getCategories();
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
                <li>登録されたサイトは管理者の承認後に掲載されます</li>
                <li>承認後、登録したメールアドレスとパスワードでログインして情報を編集できます</li>
                <li>不適切なサイトや規約に反するサイトは承認されない場合があります</li>
                <li>同一のURLまたはメールアドレスは重複して登録できません</li>
                <li>登録可能サイト数: <?php echo number_format($MAX_SITES); ?>サイト（現在: <?php echo number_format(getSiteCount('approved')); ?>サイト）</li>
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
                <label for="category_id">カテゴリ <span class="required">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">カテゴリを選択してください</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo (isset($category_id) && $category_id == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo h($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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

            <div class="form-row">
                <div class="form-group">
                    <label for="password">パスワード <span class="required">*</span></label>
                    <input type="password" id="password" name="password" minlength="6" required>
                    <div class="help-text">6文字以上で入力してください</div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">パスワード確認 <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" minlength="6" required>
                    <div class="help-text">確認のため再度入力してください</div>
                </div>
            </div>

            <button type="submit" class="submit-button">サイトを登録する</button>
        </form>
    </div>

    <script>
        // パスワード一致チェック
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
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
    </script>
</body>
</html>