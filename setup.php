<?php
// セットアップ完了チェック
$setup_file = __DIR__ . '/data/.setup_complete';
if (file_exists($setup_file)) {
    die('セットアップは既に完了しています。このファイルを削除するか、data/.setup_completeファイルを削除してから再実行してください。');
}

// エラー表示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// セッション開始（functions.phpで必要）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 基本定数の定義（functions.phpで使用される可能性があるため）
define('ADMIN_SESSION_NAME', 'search_admin_logged_in');

// 共通関数の読み込み（h()関数などを使用するため）
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';

// データベース初期化実行
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // データディレクトリ作成
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // SQLiteデータベース接続
        $dbFile = $dataDir . '/search.db';
        $db = new PDO('sqlite:' . $dbFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // カテゴリテーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                parent_id INTEGER DEFAULT NULL,
                sort_order INTEGER DEFAULT 0,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES categories(id)
            )
        ");
        
        // サイト情報テーブル（ユーザー編集機能対応版）
        $db->exec("
            CREATE TABLE IF NOT EXISTS sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                url TEXT NOT NULL UNIQUE,
                description TEXT,
                category_id INTEGER NOT NULL,
                email TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            )
        ");
        
        // 管理者テーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 設定テーブル
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // インデックス作成
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_category ON sites(category_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_status ON sites(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_email ON sites(email)");
        
        // 既存のsitesテーブルにカラムが不足している場合の対応
        $columns = $db->query("PRAGMA table_info(sites)")->fetchAll();
        $has_email = false;
        $has_password_hash = false;
        
        foreach ($columns as $column) {
            if ($column['name'] === 'email') $has_email = true;
            if ($column['name'] === 'password_hash') $has_password_hash = true;
        }
        
        if (!$has_email) {
            $db->exec("ALTER TABLE sites ADD COLUMN email TEXT");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_email ON sites(email)");
        }
        if (!$has_password_hash) {
            $db->exec("ALTER TABLE sites ADD COLUMN password_hash TEXT");
        }
        
        // 初期データ挿入
        // 管理者アカウントの確認
        $stmt = $db->query("SELECT COUNT(*) as count FROM admins");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // デフォルト管理者アカウント作成
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $db->exec("INSERT INTO admins (username, password_hash, email) VALUES ('admin', '$defaultPassword', 'admin@example.com')");
        }
        
        // 基本カテゴリの確認
        $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // 基本カテゴリ挿入
            $categories = [
                ['name' => '総合', 'description' => '総合カテゴリ'],
                ['name' => 'エンターテイメント', 'description' => 'エンターテイメント関連'],
                ['name' => '趣味・娯楽', 'description' => '趣味・娯楽関連'],
                ['name' => '創作', 'description' => '創作活動関連'],
                ['name' => 'その他', 'description' => 'その他のカテゴリ']
            ];
            
            foreach ($categories as $i => $category) {
                $stmt = $db->prepare("INSERT INTO categories (name, description, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$category['name'], $category['description'], $i]);
            }
        }
        
        // 基本設定の挿入
        $settings = [
            'site_title' => 'ディレクトリサーチ',
            'site_description' => 'ディレクトリ型サーチエンジン',
            'max_sites' => '2000',
            'sites_per_page' => '20',
            'require_approval' => '1'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
        
        // テストクエリ実行
        $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
        $result = $stmt->fetch();
        
        $message = 'データベースの初期化が完了しました！';
        
        // セットアップ完了ファイル作成
        file_put_contents($setup_file, date('Y-m-d H:i:s'));
        
    } catch (Exception $e) {
        $error = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// システム要件チェック
$requirements = [
    'PHP バージョン' => [
        'check' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'value' => PHP_VERSION,
        'required' => '7.4.0以上'
    ],
    'PDO SQLite' => [
        'check' => extension_loaded('pdo_sqlite'),
        'value' => extension_loaded('pdo_sqlite') ? '有効' : '無効',
        'required' => '有効'
    ],
    'セッション機能' => [
        'check' => function_exists('session_start'),
        'value' => function_exists('session_start') ? '有効' : '無効',
        'required' => '有効'
    ],
    'dataディレクトリ書き込み権限' => [
        'check' => is_writable(__DIR__) || mkdir(__DIR__ . '/data', 0755, true),
        'value' => is_writable(__DIR__) ? '書き込み可' : '書き込み不可',
        'required' => '書き込み可'
    ]
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ディレクトリサーチ - 初期セットアップ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-setup">
    <div class="container">
        <div class="header">
            <h1>ディレクトリサーチ</h1>
            <p>初期セットアップ</p>
        </div>

        <?php if ($message): ?>
            <div class="message success">
                <?php echo h($message); ?>
                <div style="margin-top: 15px;">
                    <a href="." style="color: #155724; font-weight: bold;">→ サイトトップページへ</a><br>
                    <a href="admin/login.php" style="color: #155724; font-weight: bold;">→ 管理画面へ</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
            <div class="info-box">
                <h3>セットアップについて</h3>
                <p>このセットアップでは以下の処理を行います：</p>
                <ul>
                    <li>SQLiteデータベースの作成</li>
                    <li>必要なテーブルの作成</li>
                    <li>初期データの投入（管理者アカウント、基本カテゴリ）</li>
                    <li>基本設定の初期化</li>
                </ul>
            </div>

            <div class="requirements">
                <h2>システム要件チェック</h2>
                <?php foreach ($requirements as $name => $req): ?>
                    <div class="requirement-item">
                        <div class="requirement-name"><?php echo h($name); ?></div>
                        <div class="requirement-value"><?php echo h($req['value']); ?></div>
                        <div class="requirement-status <?php echo $req['check'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $req['check'] ? 'OK' : 'ERROR'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php 
            $all_requirements_met = true;
            foreach ($requirements as $req) {
                if (!$req['check']) {
                    $all_requirements_met = false;
                    break;
                }
            }
            ?>

            <form method="POST" action="">
                <button type="submit" class="setup-button" <?php echo $all_requirements_met ? '' : 'disabled'; ?>>
                    <?php echo $all_requirements_met ? 'データベースを初期化する' : '要件を満たしていません'; ?>
                </button>
            </form>

        <?php else: ?>
            <div class="next-steps">
                <h3>次のステップ</h3>
                <ol>
                    <li><strong>setup.phpファイルを削除してください</strong>（セキュリティのため）</li>
                    <li>管理画面にログインして初期設定を確認してください
                        <ul>
                            <li>ユーザー名: <code>admin</code></li>
                            <li>パスワード: <code>admin123</code></li>
                        </ul>
                    </li>
                    <li>管理者パスワードを変更してください</li>
                    <li>サイトタイトルや説明文を設定してください</li>
                    <li>必要に応じてカテゴリを追加・編集してください</li>
                </ol>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>ファイル構成</h3>
            <div class="file-structure">/your-search-engine/
├── index.php              # メインページ
├── register.php           # サイト登録ページ
├── user_login.php         # ユーザーログイン
├── user_dashboard.php     # ユーザーダッシュボード
├── user_edit.php          # サイト情報編集
├── setup.php              # このファイル（セットアップ後削除）
├── style.css              # 統合スタイルシート
├── .htaccess              # Apache設定（検索避け）
├── robots.txt             # 検索避け設定
├── admin/
│   ├── login.php          # 管理画面ログイン
│   ├── dashboard.php      # ダッシュボード
│   └── manage.php         # サイト管理
├── includes/
│   ├── config.php         # 設定ファイル
│   ├── database.php       # データベース接続
│   └── functions.php      # 共通関数
└── data/
    ├── search.db          # SQLiteデータベース
    └── .setup_complete    # セットアップ完了フラグ</div>
        </div>
    </div>
</body>
</html>