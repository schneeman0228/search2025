<?php
require_once 'includes/config.php';

// サイトIDの取得と検証
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;

if ($site_id <= 0) {
    header('Location: .');
    exit;
}

// サイト情報を取得
$site_info = getSiteInfo($site_id);

if (!$site_info) {
    // サイトが存在しないか承認されていない場合
    header('Location: .?error=site_not_found');
    exit;
}

// 既にそのサイトでログイン済みの場合は管理画面へリダイレクト
if (isSiteLoggedIn($site_id)) {
    header("Location: site_manage.php?site_id=" . $site_id);
    exit;
}

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        $error = '無効なリクエストです。';
    } elseif (empty($email) || empty($password)) {
        $error = 'メールアドレスとパスワードを入力してください。';
    } elseif (authenticateSiteUser($site_id, $email, $password)) {
        header("Location: site_manage.php?site_id=" . $site_id);
        exit;
    } else {
        $error = 'メールアドレスまたはパスワードが正しくありません。<br>このサイト専用のログイン情報を入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title><?php echo h($site_info['title']); ?> - サイト管理ログイン - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-login">
    <div class="login-container">
        <div class="login-header">
            <h1>サイト管理ログイン</h1>
            <h2><?php echo h($site_info['title']); ?></h2>
            <p>このサイト専用の管理画面です</p>
        </div>

        <div class="site-info-box">
            <div class="site-preview">
                <div class="site-title-preview"><?php echo h($site_info['title']); ?></div>
                <div class="site-url-preview">
                    <a href="<?php echo h($site_info['url']); ?>" target="_blank" rel="noopener">
                        <?php echo h($site_info['url']); ?>
                    </a>
                </div>
                <div class="site-categories-preview">
                    カテゴリ: <?php echo h($site_info['category_names']); ?>
                </div>
            </div>
        </div>

        <div class="info-box">
            <strong>ログインについて</strong><br>
            このサイト登録時に設定したメールアドレスとパスワードでログインしてください。<br>
            <small>※他のサイトのログイン情報では利用できません</small>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" value="<?php echo h($email ?? ''); ?>" required autocomplete="email">
                <div class="help-text">このサイト登録時のメールアドレス</div>
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <div class="help-text">このサイト登録時のパスワード（半角英数字3〜8文字）</div>
            </div>

            <button type="submit" class="login-button">
                「<?php echo h(mb_strimwidth($site_info['title'], 0, 20, '...', 'UTF-8')); ?>」の管理画面へ
            </button>
        </form>

        <div class="links">
            <a href=".">&laquo; サイト一覧に戻る</a>
            <a href="<?php echo h($site_info['url']); ?>" target="_blank">サイトを確認</a>
        </div>
        
        <div class="security-notice">
            <h4>🔒 セキュリティについて</h4>
            <ul>
                <li>このページは「<?php echo h($site_info['title']); ?>」専用の管理画面です</li>
                <li>他のサイトの情報にはアクセスできません</li>
                <li>ログイン情報を忘れた場合は、サイト運営者までお問い合わせください</li>
            </ul>
        </div>
    </div>
</body>
</html>