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
        
        // テーブル作成
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
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                url TEXT NOT NULL UNIQUE,
                description TEXT,
                category_id INTEGER NOT NULL,
                status TEXT DEFAULT 'pending',
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            )
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
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
    <style>
        body {
            font-family: 'Hiragino Sans', 'Meiryo', sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .header h1 {
            color: #333;
            margin: 0 0 10px 0;
        }
        .header p {
            color: #666;
            margin: 0;
        }
        .requirements {
            margin-bottom: 30px;
        }
        .requirements h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .requirement-item:last-child {
            border-bottom: none;
        }
        .requirement-name {
            font-weight: bold;
            flex: 1;
        }
        .requirement-value {
            margin: 0 20px;
            font-family: monospace;
        }
        .requirement-status {
            padding: 4px 12px;
            border-radius: 20px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        .status-ok {
            background: #28a745;
        }
        .status-error {
            background: #dc3545;
        }
        .setup-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        .setup-button:hover {
            transform: translateY(-2px);
        }
        .setup-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
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
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
            margin-bottom: 30px;
        }
        .info-box h3 {
            margin-top: 0;
            color: #333;
        }
        .file-structure {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
            overflow-x: auto;
        }
        .next-steps {
            background: #d1ecf1;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ディレクトリサーチ</h1>
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
├── setup.php              # このファイル（セットアップ後削除）
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