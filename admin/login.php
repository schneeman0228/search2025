<?php
require_once '../includes/config.php';

// 既にログイン済みの場合はダッシュボードへリダイレクト
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrf_token)) {
        $error = '無効なリクエストです。';
    } elseif (empty($username) || empty($password)) {
        $error = 'ユーザー名とパスワードを入力してください。';
    } elseif (authenticateAdmin($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'ユーザー名またはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title>管理画面ログイン - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="page-login">
    <div class="login-container">
        <div class="login-header">
            <h1>管理画面</h1>
            <p><?php echo h($SITE_TITLE); ?></p>
        </div>

        <div class="info-box">
            <strong>デフォルトログイン情報:</strong><br>
            ユーザー名: admin<br>
            パスワード: admin123<br>
            <small>※初回ログイン後、必ずパスワードを変更してください</small>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label for="username">ユーザー名</label>
                <input type="text" id="username" name="username" value="<?php echo h($username ?? ''); ?>" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-button">ログイン</button>
        </form>

        <div class="links">
            <a href="../">&laquo; サイトトップに戻る</a>
        </div>
    </div>
</body>
</html>