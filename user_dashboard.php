<?php
require_once 'includes/config.php';
requireUserLogin();

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: user_login.php');
    exit;
}

// 現在のサイト情報取得
$site = getCurrentUserSite();

if (!$site) {
    session_destroy();
    header('Location: user_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?php echo $ROBOTS_CONTENT; ?>">
    <title>サイト情報管理 - <?php echo h($SITE_TITLE); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-user">
    <div class="header">
        <div class="header-content">
            <h1>サイト情報管理</h1>
            <div class="header-nav">
                <span>ようこそ、<?php echo h($_SESSION['user_email']); ?>さん</span>
                <a href="." target="_blank">サイト表示</a>
                <a href="?logout=1">ログアウト</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-box">
            <h2>
                <?php echo h($site['title']); ?>
                <span class="status-badge status-<?php echo $site['status']; ?>">
                    <?php echo $site['status'] === 'approved' ? '公開中' : '承認待ち'; ?>
                </span>
            </h2>
            <p>サイト情報の確認・編集ができます。情報を更新した場合、即座に反映されます（承認不要）。</p>
        </div>

        <?php if ($site['status'] === 'pending'): ?>
            <div class="note-box">
                <h4>⏳ 承認待ち状態です</h4>
                <p>現在、管理者による承認待ちの状態です。承認されると、サイト一覧に表示されるようになります。</p>
            </div>
        <?php endif; ?>

        <div class="site-info">
            <div class="section-header">
                <h3>サイト情報</h3>
                <div class="action-buttons">
                    <a href="user_edit.php" class="btn btn-primary">情報を編集</a>
                </div>
            </div>
            <div class="section-content">
                <div class="info-row">
                    <div class="info-label">サイト名</div>
                    <div class="info-value"><?php echo h($site['title']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">URL</div>
                    <div class="info-value site-url">
                        <a href="<?php echo h($site['url']); ?>" target="_blank" rel="noopener">
                            <?php echo h($site['url']); ?>
                        </a>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">カテゴリ</div>
                    <div class="info-value"><?php echo h($site['category_name']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">説明</div>
                    <div class="info-value">
                        <?php echo $site['description'] ? h($site['description']) : '<span style="color: #999;">（説明文なし）</span>'; ?>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">ステータス</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $site['status']; ?>">
                            <?php echo $site['status'] === 'approved' ? '公開中' : '承認待ち'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">登録日</div>
                    <div class="info-value"><?php echo date('Y年m月d日 H:i', strtotime($site['created_at'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">更新日</div>
                    <div class="info-value"><?php echo date('Y年m月d日 H:i', strtotime($site['updated_at'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">登録メール</div>
                    <div class="info-value"><?php echo h($site['email']); ?></div>
                </div>
            </div>
        </div>

        <div class="note-box">
            <h4>💡 ご利用について</h4>
            <ul>
                <li>サイト情報の編集は即座に反映されます（承認不要）</li>
                <li>URLやメールアドレスの変更も可能です</li>
                <li>パスワードの変更も編集画面から行えます</li>
                <li>ログイン情報を忘れた場合は、サイト運営者までお問い合わせください</li>
            </ul>
        </div>

        <div class="action-buttons">
            <a href="user_edit.php" class="btn btn-primary">サイト情報を編集</a>
            <a href="." class="btn btn-secondary">サイトトップへ</a>
        </div>
    </div>
</body>
</html>