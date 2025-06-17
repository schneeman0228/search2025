<?php
class Database {
    private $db;
    
    public function __construct() {
        $dbFile = __DIR__ . '/../data/search.db';
        
        // dataディレクトリが存在しない場合は作成
        $dataDir = dirname($dbFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        try {
            $this->db = new PDO('sqlite:' . $dbFile);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // 初回実行時にテーブルを作成
            $this->createTables();
        } catch (PDOException $e) {
            die('データベース接続エラー: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    private function createTables() {
        // カテゴリテーブル
        $this->db->exec("
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
        
        // サイト情報テーブル
        $this->db->exec("
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
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 設定テーブル
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // インデックス作成
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_sites_category ON sites(category_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_sites_status ON sites(status)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_sites_email ON sites(email)");
        
        // 初期データの挿入（初回のみ）
        $this->insertInitialData();
    }
    
    private function insertInitialData() {
        // 管理者アカウントの確認
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM admins");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // デフォルト管理者アカウント作成
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $this->db->exec("INSERT INTO admins (username, password_hash, email) VALUES ('admin', '$defaultPassword', 'admin@example.com')");
        }
        
        // 階層カテゴリの挿入
        $this->insertHierarchicalCategories();
        
        // 基本設定の挿入
        $settings = [
            'site_title' => 'ディレクトリサーチ',
            'site_description' => 'ディレクトリ型サーチエンジン',
            'max_sites' => '2000',
            'sites_per_page' => '20',
            'require_approval' => '1'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $stmt = $this->db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
    }
    
    private function insertHierarchicalCategories() {
        // 基本カテゴリの確認
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM categories");
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
                $stmt = $this->db->prepare("INSERT INTO categories (name, description, parent_id, sort_order) VALUES (?, ?, NULL, ?)");
                $stmt->execute([$parent['name'], $parent['description'], $parent['sort_order']]);
                $parent_id = $this->db->lastInsertId();
                
                // 子カテゴリを挿入
                foreach ($parent['children'] as $child) {
                    $stmt = $this->db->prepare("INSERT INTO categories (name, description, parent_id, sort_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$child['name'], $child['description'], $parent_id, $child['sort_order']]);
                }
            }
        }
    }
}
?>