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
        } elseif (strlen($title) > 100) {
            $error = 'サイト名は100文字以内で入力してください。';
        } elseif (strlen($description) > 500) {
            $error = '説明文は500文字以内で入力してください。';
        } elseif (!checkIpLimit($ip_address)) {
            $error = '短時間での登録回数が上限に達しています。しばらく時間をおいてから再度お試しください。';
        } else {
            // サイト登録実行
            $result = registerSite($title, $url, $description, $category_id, $ip_address);
            
            if ($result['success']) {
                $message = $result['message'];
                // フォームクリア
                $title = $url = $description = '';
                $category_id = 0;
            } else {
                $error = $result['message'];
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
    <style>
        body {
            font-family: 'Hiragino Sans', 'Meiryo', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
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
        .back-link {
            text-align: center;
            margin-bottom: 20px;
        }
        .back-link a {
            color: #007cba;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group .required {
            color: #dc3545;
        }
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .form-group .help-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .submit-button {
            background: #007cba;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .submit-button:hover {
            background: #005a87;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .guidelines {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007cba;
        }
        .guidelines h3 {
            margin-top: 0;
            color: #333;
        }
        .guidelines ul {
            margin-bottom: 0;
        }
        .guidelines li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href=".">&laquo; トップページに戻る</a>
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

        <div class="guidelines">
            <h3>登録について</h3>
            <ul>
                <li>登録されたサイトは管理者の承認後に掲載されます</li>
                <li>不適切なサイトや規約に反するサイトは承認されない場合があります</li>
                <li>同一のURLは重複して登録できません</li>
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
    </script>
</body>
</html>