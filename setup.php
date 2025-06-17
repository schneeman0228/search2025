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
        
        // 階層カテゴリの確認と挿入
        $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // 親カテゴリの定義
            $parent_categories = [
                [
                    'name' => 'コンテンツ',
                    'description' => 'コンテンツの種類',
                    'sort_order' => 1,
                    'children' => [
                        ['name' => '漫画', 'description' => '漫画・コミック', 'sort_order' => 1],
                        ['name' => '小説', 'description' => '小説・文章', 'sort_order' => 2],
                        ['name' => 'イラスト', 'description' => 'イラスト・絵', 'sort_order' => 3]
                    ]
                ],
                [
                    'name' => '年齢制限',
                    'description' => '年齢制限の有無',
                    'sort_order' => 2,
                    'children' => [
                        ['name' => '全年齢', 'description' => '全年齢対象', 'sort_order' => 1],
                        ['name' => '成人向け', 'description' => '18禁・成人向け', 'sort_order' => 2]
                    ]
                ],
                [
                    'name' => '作品名',
                    'description' => '取り扱い作品・ジャンル',
                    'sort_order' => 3,
                    'children' => [
                        ['name' => 'AAA', 'description' => 'AAAシリーズ', 'sort_order' => 1],
                        ['name' => 'BBB', 'description' => 'BBBシリーズ', 'sort_order' => 2]
                    ]
                ],
                [
                    'name' => '備考',
                    'description' => 'サイトの特徴・備考',
                    'sort_order' => 4,
                    'children' => [
                        ['name' => '雑多', 'description' => '雑多・複数ジャンル', 'sort_order' => 4],
                        ['name' => 'オフライン活動', 'description' => 'オフライン・イベント活動', 'sort_order' => 6],
                        ['name' => 'URL請求制', 'description' => 'URL請求制', 'sort_order' => 14],
                        ['name' => 'パスワード制', 'description' => 'パスワード制', 'sort_order' => 15],
                        ['name' => '一部パスワード制', 'description' => '一部パスワード制', 'sort_order' => 16],
                        ]
                ]
            ];
            
            // 親カテゴリと子カテゴリを順次挿入
            foreach ($parent_categories as $parent) {
                // 親カテゴリを挿入
                $stmt = $db->prepare("INSERT INTO categories (name, description, parent_id, sort_order) VALUES (?, ?, NULL, ?)");
                $stmt->execute([$parent['name'], $parent['description'], $parent['sort_order']]);
                $parent_id = $db->lastInsertId();
                
                // 子カテゴリを挿入
                foreach ($parent['children'] as $child) {
                    $stmt = $db->prepare("INSERT INTO categories (name, description, parent_id, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$child['name'], $child['description'], $parent_id, $child['sort_order']]);
                }
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
        
        $message = 'データベースの初期化が完了しました！階層カテゴリ（' . $result['count'] . '件）が作成されました。';
        
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
            <p>初期セットアップ（階層カテゴリ対応版）</p>
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
                <h3>階層カテゴリセットアップについて</h3>
                <p>このセットアップでは以下の処理を行います：</p>
                <ul>
                    <li>SQLiteデータベースの作成</li>
                    <li>必要なテーブルの作成（階層対応）</li>
                    <li><strong>2層構造カテゴリの作成</strong>
                        <ul>
                            <li>コンテンツ（漫画、小説、イラスト）</li>
                            <li>年齢制限（全年齢、成人向け）</li>
                            <li>作品名（AAA、BBB）</li>
                            <li>備考（30種類の詳細分類）</li>
                        </ul>
                    </li>
                    <li>初期データの投入（管理者アカウント、基本設定）</li>
                    <li>基本設定の初期化</li>
                </ul>
            </div>

            <div class="info-box">
                <h4>🎨 同人サイト向け特別仕様</h4>
                <p>今回のセットアップでは、同人サイト運営者のニーズに特化した階層カテゴリを構築します：</p>
                <ul>
                    <li><strong>コンテンツ分類</strong>: 創作形態による分類</li>
                    <li><strong>年齢制限</strong>: 明確なレーティング分類</li>
                    <li><strong>作品名</strong>: 取り扱いジャンル・作品分類</li>
                    <li><strong>備考</strong>: サイトの特徴・技術仕様・更新頻度などの詳細分類</li>
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
                    <?php echo $all_requirements_met ? '階層カテゴリで初期化する' : '要件を満たしていません'; ?>
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
                    <li><strong>管理者パスワードを変更してください</strong></li>
                    <li>サイトタイトルや説明文を設定してください</li>
                    <li>作成されたカテゴリ構造を確認してください</li>
                    <li>必要に応じてカテゴリを追加・編集してください</li>
                </ol>
            </div>
            
            <div class="info-box">
                <h4>📋 作成されたカテゴリ構造</h4>
                <p><strong>コンテンツ</strong>: 漫画, 小説, イラスト</p>
                <p><strong>年齢制限</strong>: 全年齢, 成人向け</p>
                <p><strong>作品名</strong>: AAA, BBB</p>
                <p><strong>備考</strong>: 交流壁打ち, 自己満足, 日記メイン, 雑多, 同人誌装丁, オフライン活動, ドット絵, アナログ絵, 講座・メイキング, 仕事募集中, サイト作成支援, 作品数50↑, 作品数100↑, URL請求制, パスワード制, 一部パスワード制, アクセス対策中, HTML/CSS, WordPress, forestpage+, Xfolio, Privatter+, モバイル用, てがろぐ, マイペース, 不定期更新, 期間限定, ポートフォリオ, 倉庫, 更新休止中</p>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>ファイル構成</h3>
            <div class="file-structure">/your-search-engine/
├── index.php              # メインページ（階層表示対応）
├── register.php           # サイト登録ページ（階層選択対応）
├── user_login.php         # ユーザーログイン
├── user_dashboard.php     # ユーザーダッシュボード
├── user_edit.php          # サイト情報編集
├── setup.php              # このファイル（セットアップ後削除）
├── style.css              # 統合スタイルシート（階層表示CSS追加）
├── .htaccess              # Apache設定（検索避け）
├── robots.txt             # 検索避け設定
├── admin/
│   ├── login.php          # 管理画面ログイン
│   ├── dashboard.php      # ダッシュボード
│   └── manage.php         # サイト管理
├── includes/
│   ├── config.php         # 設定ファイル
│   ├── database.php       # データベース接続
│   └── functions.php      # 共通関数（階層関数追加）
└── data/
    ├── search.db          # SQLiteデータベース（階層構造）
    └── .setup_complete    # セットアップ完了フラグ</div>
        </div>
    </div>
</body>
</html>