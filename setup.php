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
        
        // サイト情報テーブル（複数カテゴリ対応版）
        $db->exec("
            CREATE TABLE IF NOT EXISTS sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                url TEXT NOT NULL UNIQUE,
                description TEXT,
                email TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // サイト-カテゴリ関連テーブル（多対多）
        $db->exec("
            CREATE TABLE IF NOT EXISTS site_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
                UNIQUE(site_id, category_id)
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
        $db->exec("CREATE INDEX IF NOT EXISTS idx_site_categories_site ON site_categories(site_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_site_categories_category ON site_categories(category_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_status ON sites(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_email ON sites(email)");
        
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
            // 親カテゴリの定義（複数カテゴリ対応版）
            $parent_categories = [
                [
                    'name' => 'コンテンツ',
                    'description' => 'コンテンツの種類',
                    'sort_order' => 1,
                    'children' => [
                        ['name' => '漫画', 'description' => '漫画・コミック', 'sort_order' => 1],
                        ['name' => '小説', 'description' => '小説・文章', 'sort_order' => 2],
                        ['name' => 'イラスト', 'description' => 'イラスト・絵', 'sort_order' => 3],
                        ['name' => '日記', 'description' => '日記・ブログ', 'sort_order' => 8]
                    ]
                ],
                [
                    'name' => '年齢制限',
                    'description' => '年齢制限の有無',
                    'sort_order' => 2,
                    'children' => [
                        ['name' => '全年齢', 'description' => '全年齢対象', 'sort_order' => 1],
                        ['name' => '成人向け', 'description' => '18禁・成人向け', 'sort_order' => 2],
                    ]
                ],
                [
                    'name' => '作品・ジャンル',
                    'description' => '取り扱い作品・ジャンル',
                    'sort_order' => 3,
                    'children' => [
                        ['name' => '国内ドラマ', 'description' => 'BL・やおい', 'sort_order' => 1],
                        ['name' => '海外ドラマ', 'description' => 'GL・百合', 'sort_order' => 2],
                        ['name' => '特撮', 'description' => 'NL・ノーマル', 'sort_order' => 3],
                        ['name' => 'その他半ナマ系', 'description' => 'GL・百合', 'sort_order' => 4],
                        ['name' => '俳優', 'description' => 'GL・百合', 'sort_order' => 5],
                        ['name' => '配信者・VTuber', 'description' => 'GL・百合', 'sort_order' => 6],
                        ['name' => 'お笑い芸人', 'description' => 'GL・百合', 'sort_order' => 7],
                        ['name' => 'その他ナマモノ系', 'description' => 'GL・百合', 'sort_order' => 8],
                    ]
                ],
                [
                    'name' => '特徴・備考',
                    'description' => 'サイトの特徴・備考',
                    'sort_order' => 4,
                    'children' => [
                        ['name' => 'よろずサイト', 'description' => '雑多・複数ジャンル', 'sort_order' => 1],
                        ['name' => 'オフライン活動', 'description' => 'オフライン・イベント活動', 'sort_order' => 4],
                        ['name' => 'URL請求制', 'description' => 'URL請求制', 'sort_order' => 5],
                        ['name' => 'パスワード制', 'description' => 'パスワード制', 'sort_order' => 6],
                        ['name' => '一部パスワード制', 'description' => '一部パスワード制', 'sort_order' => 7],
                        ['name' => 'モバイル対応', 'description' => 'モバイル・スマホ対応', 'sort_order' => 13],
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
            'site_description' => 'ディレクトリ型サーチエンジン（複数カテゴリ対応）',
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
        
        $message = 'データベースの初期化が完了しました！複数カテゴリ対応の階層カテゴリ（' . $result['count'] . '件）が作成されました。';
        
        // セットアップ完了ファイル作成
        file_put_contents($setup_file, date('Y-m-d H:i:s') . ' - Multiple Categories Support');
        
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
    <title>ディレクトリサーチ - 初期セットアップ（複数カテゴリ対応）</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="page-setup">
    <div class="container">
        <div class="header">
            <h1>ディレクトリサーチ</h1>
            <p>初期セットアップ（複数カテゴリ対応版）</p>
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
                <h3>🎉 複数カテゴリ対応セットアップについて</h3>
                <p>このセットアップでは以下の処理を行います：</p>
                <ul>
                    <li>SQLiteデータベースの作成</li>
                    <li>複数カテゴリ対応テーブルの作成（多対多関係）</li>
                    <li><strong>🆕 複数選択可能な2層構造カテゴリの作成</strong>
                        <ul>
                            <li>コンテンツ（漫画、小説、イラスト、音楽、ゲーム、写真、動画、日記）</li>
                            <li>年齢制限（全年齢、成人向け、一部成人向け）</li>
                            <li>作品・ジャンル（オリジナル、ファンタジー、SF、現代、歴史、BL、GL、NL）</li>
                            <li>特徴・備考（15種類の詳細分類）</li>
                        </ul>
                    </li>
                    <li>初期データの投入（管理者アカウント、基本設定）</li>
                    <li>基本設定の初期化</li>
                </ul>
            </div>

            <div class="info-box" style="background: #e7f3ff; border-color: #007cba;">
                <h4>🔥 新機能：複数カテゴリ選択</h4>
                <p>今回のバージョンでは、1つのサイトに対して複数のカテゴリを選択できるようになりました：</p>
                <ul>
                    <li><strong>例1</strong>: 「漫画 + 全年齢 + オリジナル + 交流」</li>
                    <li><strong>例2</strong>: 「小説 + イラスト + 成人向け + BL + 壁打ち」</li>
                    <li><strong>例3</strong>: 「日記 + 写真 + 全年齢 + 雑多 + マイペース」</li>
                </ul>
                <p>より詳細で柔軟なカテゴリ分類が可能になり、ユーザーが求めるサイトを見つけやすくなります。</p>
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
                    <?php echo $all_requirements_met ? '複数カテゴリ対応で初期化する' : '要件を満たしていません'; ?>
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
                    <li>テストサイトを登録して複数カテゴリ選択を試してください</li>
                </ol>
            </div>
            
            <div class="info-box">
                <h4>📋 作成されたカテゴリ構造（複数選択対応）</h4>
                <p><strong>コンテンツ</strong>: 漫画, 小説, イラスト, 音楽, ゲーム, 写真, 動画, 日記</p>
                <p><strong>年齢制限</strong>: 全年齢, 成人向け, 一部成人向け</p>
                <p><strong>作品・ジャンル</strong>: オリジナル, ファンタジー, SF, 現代, 歴史, BL, GL, NL</p>
                <p><strong>特徴・備考</strong>: 雑多, 交流, 壁打ち, オフライン活動, URL請求制, パスワード制, 一部パスワード制, 講座・メイキング, マイペース, 不定期更新, 更新休止中, アクセス対策中, モバイル対応, ポートフォリオ, 期間限定</p>
            </div>

            <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                <h4>⚠️ データベース構造の変更について</h4>
                <p>複数カテゴリ対応により、以下の変更が行われました：</p>
                <ul>
                    <li><code>sites</code>テーブルから<code>category_id</code>カラムを削除</li>
                    <li>新しい<code>site_categories</code>テーブルを追加（多対多関係）</li>
                    <li>既存のデータは移行不要（新規セットアップのため）</li>
                </ul>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>ファイル構成（複数カテゴリ対応版）</h3>
            <div class="file-structure">/your-search-engine/
├── index.php              # メインページ（複数カテゴリフィルタ対応）
├── register.php           # サイト登録ページ（複数選択対応）
├── user_login.php         # ユーザーログイン
├── user_dashboard.php     # ユーザーダッシュボード（複数カテゴリ表示）
├── user_edit.php          # サイト情報編集（複数選択対応）
├── setup.php              # このファイル（セットアップ後削除）
├── style.css              # 統合スタイルシート（複数カテゴリ用CSS追加）
├── .htaccess              # Apache設定（検索避け）
├── robots.txt             # 検索避け設定
├── admin/
│   ├── login.php          # 管理画面ログイン
│   ├── dashboard.php      # ダッシュボード
│   └── manage.php         # サイト管理（複数カテゴリ対応）
├── includes/
│   ├── config.php         # 設定ファイル
│   ├── database.php       # データベース接続（多対多対応）
│   └── functions.php      # 共通関数（複数カテゴリ関数追加）
└── data/
    ├── search.db          # SQLiteデータベース（複数カテゴリ構造）
    └── .setup_complete    # セットアップ完了フラグ</div>
        </div>
    </div>
</body>
</html>