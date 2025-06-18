<?php
require_once 'includes/config.php';

// 既にログイン済みの場合はダッシュボードへリダイレクト
if (isUserLoggedIn()) {
    header('Location: user_dashboard.php');
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
    } elseif (authenticateUser($email, $password)) {
        header('Location: user_dashboard.php');
        exit;
    } else {
        $error = 'メールアドレスまたはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title>サイト情報編集 - ログイン - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-login">
    <div class="login-container">
        <div class="login-header">
            <h1>サイト情報編集</h1>
            <p><?php echo h($SITE_TITLE); ?></p>
        </div>

        <div class="info-box">
            <strong>ログインについて</strong><br>
            サイト登録時に設定したメールアドレスとパスワードでログインしてください。
            ログイン後、サイト情報の編集が可能になります（承認不要）。
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" value="<?php echo h($email ?? ''); ?>" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-button">ログイン</button>
        </form>

        <div class="links">
            <a href=".">&laquo; サイトトップに戻る</a>
            <a href="register.php">新規サイト登録</a>
        </div>
    </div>
</body>
</html>