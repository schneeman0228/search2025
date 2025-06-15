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
        
        // 基本カテゴリの確認
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM categories");
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
                $this->db->exec("INSERT INTO categories (name, description, sort_order) VALUES ('{$category['name']}', '{$category['description']}', $i)");
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
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $stmt = $this->db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }
    }
}
?>