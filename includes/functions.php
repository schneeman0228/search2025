<?php
// XSS対策
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// CSRF対策トークン生成
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF対策トークン検証
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 管理者ログイン状態確認
function isAdminLoggedIn() {
    return isset($_SESSION[ADMIN_SESSION_NAME]) && $_SESSION[ADMIN_SESSION_NAME] === true;
}

// 管理者ログイン要求
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// URL検証
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// サイト数を取得
function getSiteCount($status = 'approved') {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE status = ?");
    $stmt->execute([$status]);
    $result = $stmt->fetch();
    return $result['count'];
}

// カテゴリ一覧を取得
function getCategories($parent_id = null) {
    global $db;
    $sql = "SELECT * FROM categories WHERE " . ($parent_id ? "parent_id = ?" : "parent_id IS NULL") . " ORDER BY sort_order, name";
    $stmt = $db->prepare($sql);
    if ($parent_id) {
        $stmt->execute([$parent_id]);
    } else {
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

// 階層構造でカテゴリを取得
function getCategoriesHierarchical() {
    global $db;
    
    // 親カテゴリを取得
    $parents = getCategories(null);
    $hierarchy = [];
    
    foreach ($parents as $parent) {
        $parent['children'] = getCategories($parent['id']);
        $parent['child_count'] = count($parent['children']);
        
        // 子カテゴリのサイト数を計算
        $total_sites = 0;
        foreach ($parent['children'] as &$child) {
            $child['site_count'] = getCategorySiteCount($child['id']);
            $total_sites += $child['site_count'];
        }
        $parent['total_sites'] = $total_sites;
        
        $hierarchy[] = $parent;
    }
    
    return $hierarchy;
}

// カテゴリセレクトボックス用の階層リストを生成
function getCategoriesForSelect() {
    global $db;
    
    $categories = [];
    
    // 親カテゴリを取得
    $parents = getCategories(null);
    
    foreach ($parents as $parent) {
        // 子カテゴリを取得
        $children = getCategories($parent['id']);
        
        if (!empty($children)) {
            // 子カテゴリがある場合は子カテゴリのみ選択可能に
            foreach ($children as $child) {
                $categories[] = [
                    'id' => $child['id'],
                    'name' => $parent['name'] . ' > ' . $child['name'],
                    'parent_name' => $parent['name'],
                    'child_name' => $child['name'],
                    'parent_id' => $parent['id']
                ];
            }
        } else {
            // 子カテゴリがない場合は親カテゴリを選択可能に
            $categories[] = [
                'id' => $parent['id'],
                'name' => $parent['name'],
                'parent_name' => '',
                'child_name' => $parent['name'],
                'parent_id' => null
            ];
        }
    }
    
    return $categories;
}

// カテゴリ情報を取得
function getCategory($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// カテゴリ内のサイト数を取得
function getCategorySiteCount($category_id) {
    global $db;
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT s.id) as count 
        FROM sites s 
        JOIN site_categories sc ON s.id = sc.site_id 
        WHERE sc.category_id = ? AND s.status = 'approved'
    ");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

// サイトのカテゴリを取得
function getSiteCategories($site_id) {
    global $db;
    $stmt = $db->prepare("
        SELECT c.id, c.name, c.parent_id, pc.name as parent_name
        FROM categories c
        JOIN site_categories sc ON c.id = sc.category_id
        LEFT JOIN categories pc ON c.parent_id = pc.id
        WHERE sc.site_id = ?
        ORDER BY pc.sort_order, c.sort_order
    ");
    $stmt->execute([$site_id]);
    return $stmt->fetchAll();
}

// サイトのカテゴリを更新（トランザクション管理は呼び出し元で行う）
function updateSiteCategories($site_id, $category_ids) {
    global $db;
    
    try {
        // 既存のカテゴリ関連を削除
        $stmt = $db->prepare("DELETE FROM site_categories WHERE site_id = ?");
        $stmt->execute([$site_id]);
        
        // 新しいカテゴリ関連を挿入
        if (!empty($category_ids)) {
            $stmt = $db->prepare("INSERT INTO site_categories (site_id, category_id) VALUES (?, ?)");
            foreach ($category_ids as $category_id) {
                $stmt->execute([$site_id, (int)$category_id]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// サイト一覧を取得（複数カテゴリ対応）
function getSites($category_ids = null, $search = null, $page = 1, $limit = 20, $status = 'approved') {
    global $db;
    
    $offset = ($page - 1) * $limit;
    $conditions = ["s.status = ?"];
    $params = [$status];
    $joins = "";
    
    if ($category_ids && !empty($category_ids)) {
        $placeholders = str_repeat('?,', count($category_ids) - 1) . '?';
        $joins .= " JOIN site_categories sc ON s.id = sc.site_id";
        $conditions[] = "sc.category_id IN ($placeholders)";
        $params = array_merge($params, $category_ids);
    }
    
    if ($search) {
        $conditions[] = "(s.title LIKE ? OR s.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql = "SELECT DISTINCT s.* 
            FROM sites s 
            {$joins}
            WHERE " . implode(' AND ', $conditions) . " 
            ORDER BY s.updated_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sites = $stmt->fetchAll();
    
    // 各サイトのカテゴリ情報を追加
    foreach ($sites as &$site) {
        $site['categories'] = getSiteCategories($site['id']);
        
        // 表示用のカテゴリ名文字列を生成
        $category_names = [];
        foreach ($site['categories'] as $cat) {
            if ($cat['parent_name']) {
                $category_names[] = $cat['parent_name'] . ' > ' . $cat['name'];
            } else {
                $category_names[] = $cat['name'];
            }
        }
        $site['category_names'] = implode(', ', $category_names);
    }
    
    return $sites;
}

// サイト総数を取得（検索・フィルタ条件付き）
function getSitesCount($category_ids = null, $search = null, $status = 'approved') {
    global $db;
    
    $conditions = ["s.status = ?"];
    $params = [$status];
    $joins = "";
    
    if ($category_ids && !empty($category_ids)) {
        $placeholders = str_repeat('?,', count($category_ids) - 1) . '?';
        $joins .= " JOIN site_categories sc ON s.id = sc.site_id";
        $conditions[] = "sc.category_id IN ($placeholders)";
        $params = array_merge($params, $category_ids);
    }
    
    if ($search) {
        $conditions[] = "(s.title LIKE ? OR s.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql = "SELECT COUNT(DISTINCT s.id) as count FROM sites s {$joins} WHERE " . implode(' AND ', $conditions);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['count'];
}

// ページネーション生成
function generatePagination($current_page, $total_items, $items_per_page, $base_url) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // 前のページ
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . '&page=' . ($current_page - 1) . '">&laquo; 前</a>';
    }
    
    // ページ番号
    for ($i = max(1, $current_page - 3); $i <= min($total_pages, $current_page + 3); $i++) {
        if ($i == $current_page) {
            $html .= '<span class="current">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . '&page=' . $i . '">' . $i . '</a>';
        }
    }
    
    // 次のページ
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . '&page=' . ($current_page + 1) . '">次 &raquo;</a>';
    }
    
    $html .= '</div>';
    return $html;
}

// IP制限チェック
function checkIpLimit($ip_address, $limit = 5, $timeframe = 3600) {
    global $db;
    $cutoff_time = date('Y-m-d H:i:s', time() - $timeframe);
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE ip_address = ? AND created_at > ?");
    $stmt->execute([$ip_address, $cutoff_time]);
    $result = $stmt->fetch();
    
    return $result['count'] < $limit;
}

// 管理者認証
function authenticateAdmin($username, $password) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION[ADMIN_SESSION_NAME] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    
    return false;
}

// ========================================
// ユーザー認証関連の関数
// ========================================

// パスワードの検証（半角英数字3〜8文字）
function isValidPassword($password) {
    return preg_match('/^[a-zA-Z0-9]{3,8}$/', $password);
}

// ユーザーログイン状態確認
function isUserLoggedIn() {
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

// ユーザーログイン要求
function requireUserLogin() {
    if (!isUserLoggedIn()) {
        header('Location: user_login.php');
        exit;
    }
}

// ユーザー認証
function authenticateUser($email, $password) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM sites WHERE email = ? AND status = 'approved'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_site_id'] = $user['id'];
        return true;
    }
    
    return false;
}

// ログイン中ユーザーのサイト情報取得
function getCurrentUserSite() {
    global $db;
    if (!isUserLoggedIn()) {
        return null;
    }
    
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$_SESSION['user_site_id']]);
    $site = $stmt->fetch();
    
    if ($site) {
        // カテゴリ情報を追加
        $site['categories'] = getSiteCategories($site['id']);
        
        // 表示用のカテゴリ名文字列を生成
        $category_names = [];
        foreach ($site['categories'] as $cat) {
            if ($cat['parent_name']) {
                $category_names[] = $cat['parent_name'] . ' > ' . $cat['name'];
            } else {
                $category_names[] = $cat['name'];
            }
        }
        $site['category_names'] = implode(', ', $category_names);
    }
    
    return $site;
}

// サイト情報更新（ユーザー）
function updateUserSite($site_id, $title, $url, $description, $category_ids, $user_email) {
    global $db;
    
    // 権限チェック
    $stmt = $db->prepare("SELECT email FROM sites WHERE id = ?");
    $stmt->execute([$site_id]);
    $site = $stmt->fetch();
    
    if (!$site || $site['email'] !== $user_email) {
        return ['success' => false, 'message' => '編集権限がありません。'];
    }
    
    // URL重複チェック（自分以外）
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE url = ? AND id != ?");
    $stmt->execute([$url, $site_id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return ['success' => false, 'message' => 'このURLは既に他のサイトで使用されています。'];
    }
    
    // カテゴリが選択されているかチェック
    if (empty($category_ids)) {
        return ['success' => false, 'message' => '少なくとも1つのカテゴリを選択してください。'];
    }
    
    try {
        $db->beginTransaction();
        
        // サイト情報更新
        $stmt = $db->prepare("
            UPDATE sites 
            SET title = ?, url = ?, description = ?, updated_at = datetime('now') 
            WHERE id = ? AND email = ?
        ");
        $stmt->execute([$title, $url, $description, $site_id, $user_email]);
        
        // カテゴリ更新
        if (!updateSiteCategories($site_id, $category_ids)) {
            throw new Exception('カテゴリの更新に失敗しました。');
        }
        
        $db->commit();
        return ['success' => true, 'message' => 'サイト情報を更新しました。'];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
}

// パスワード更新
function updateUserPassword($site_id, $current_password, $new_password, $user_email) {
    global $db;
    
    // 現在のパスワード確認
    $stmt = $db->prepare("SELECT password_hash FROM sites WHERE id = ? AND email = ?");
    $stmt->execute([$site_id, $user_email]);
    $site = $stmt->fetch();
    
    if (!$site || !password_verify($current_password, $site['password_hash'])) {
        return ['success' => false, 'message' => '現在のパスワードが正しくありません。'];
    }
    
    // 新しいパスワードの検証
    if (!isValidPassword($new_password)) {
        return ['success' => false, 'message' => 'パスワードは半角英数字3〜8文字で入力してください。'];
    }
    
    try {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE sites SET password_hash = ?, updated_at = datetime('now') WHERE id = ? AND email = ?");
        $stmt->execute([$new_hash, $site_id, $user_email]);
        return ['success' => true, 'message' => 'パスワードを変更しました。'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
}

// メールアドレスの検証
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// サイト登録（複数カテゴリ対応）
function registerSiteWithUser($title, $url, $description, $category_ids, $email, $password, $ip_address) {
    global $db;
    
    // URL重複チェック
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE url = ?");
    $stmt->execute([$url]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return ['success' => false, 'message' => 'このURLは既に登録されています。'];
    }
    
    // カテゴリが選択されているかチェック
    if (empty($category_ids)) {
        return ['success' => false, 'message' => '少なくとも1つのカテゴリを選択してください。'];
    }
    
    // サイト数上限チェック
    $totalSites = getSiteCount('approved') + getSiteCount('pending');
    global $MAX_SITES;
    if ($totalSites >= $MAX_SITES) {
        return ['success' => false, 'message' => 'サイト登録数が上限に達しています。'];
    }
    
    try {
        $db->beginTransaction();
        
        // サイト登録
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO sites (title, url, description, email, password_hash, ip_address, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$title, $url, $description, $email, $password_hash, $ip_address]);
        $site_id = $db->lastInsertId();
        
        // カテゴリ関連付け
        if (!updateSiteCategories($site_id, $category_ids)) {
            throw new Exception('カテゴリの関連付けに失敗しました。');
        }
        
        $db->commit();
        return ['success' => true, 'message' => 'サイトを登録しました。管理者の承認をお待ちください。'];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
}

// 階層カテゴリ表示HTML生成（複数カテゴリ対応）
function generateHierarchicalCategoryHTML($categories) {
    $html = '<div class="categories-hierarchical">';
    
    foreach ($categories as $parent) {
        $html .= '<div class="category-group">';
        $html .= '<div class="category-parent">';
        $html .= '<h3>' . h($parent['name']) . '</h3>';
        if ($parent['description']) {
            $html .= '<p class="category-description">' . h($parent['description']) . '</p>';
        }
        $html .= '<span class="total-count">(' . $parent['total_sites'] . '件)</span>';
        $html .= '</div>';
        
        if (!empty($parent['children'])) {
            $html .= '<div class="category-children">';
            foreach ($parent['children'] as $child) {
                $html .= '<div class="category-child">';
                $html .= '<a href="?categories[]=' . $child['id'] . '">';
                $html .= '<span class="child-name">' . h($child['name']) . '</span>';
                $html .= '<span class="child-count">(' . $child['site_count'] . ')</span>';
                $html .= '</a>';
                if ($child['description']) {
                    $html .= '<div class="child-description">' . h($child['description']) . '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

// ====================================================================
// カテゴリ管理関数（管理画面用）
// ====================================================================

// カテゴリを追加
function addCategory($name, $description = '', $parent_id = null, $sort_order = 0) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO categories (name, description, parent_id, sort_order) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $description, $parent_id ?: null, $sort_order]);
    } catch (PDOException $e) {
        return false;
    }
}

// カテゴリを更新
function updateCategory($id, $name, $description = '', $sort_order = 0) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE categories SET name = ?, description = ?, sort_order = ? WHERE id = ?");
        return $stmt->execute([$name, $description, $sort_order, $id]);
    } catch (PDOException $e) {
        return false;
    }
}

// カテゴリを削除（安全性チェック付き）
function deleteCategory($id) {
    global $db;
    try {
        $db->beginTransaction();
        
        // 子カテゴリがあるかチェック
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $db->rollback();
            return ['success' => false, 'message' => '子カテゴリが存在するため削除できません。'];
        }
        
        // 関連サイトがあるかチェック
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM site_categories WHERE category_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $db->rollback();
            return ['success' => false, 'message' => 'このカテゴリに登録されているサイトがあるため削除できません。'];
        }
        
        // カテゴリ削除
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        return ['success' => true, 'message' => 'カテゴリを削除しました。'];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
}

// 親カテゴリのリストを取得
function getParentCategories() {
    global $db;
    $stmt = $db->prepare("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name");
    $stmt->execute();
    return $stmt->fetchAll();
}

// カテゴリの詳細情報を取得
function getCategoryDetails($id) {
    global $db;
    $stmt = $db->prepare("
        SELECT c.*, pc.name as parent_name 
        FROM categories c 
        LEFT JOIN categories pc ON c.parent_id = pc.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// カテゴリの並び順を更新
function updateCategoryOrder($id, $sort_order) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
        return $stmt->execute([$sort_order, $id]);
    } catch (PDOException $e) {
        return false;
    }
}

// カテゴリの並び順を自動調整
function reorderCategories($parent_id = null) {
    global $db;
    try {
        $sql = "SELECT id FROM categories WHERE " . ($parent_id ? "parent_id = ?" : "parent_id IS NULL") . " ORDER BY sort_order, name";
        $stmt = $db->prepare($sql);
        if ($parent_id) {
            $stmt->execute([$parent_id]);
        } else {
            $stmt->execute();
        }
        
        $categories = $stmt->fetchAll();
        $update_stmt = $db->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
        
        foreach ($categories as $index => $category) {
            $update_stmt->execute([($index + 1) * 10, $category['id']]);
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// カテゴリ名重複チェック
function isCategoryNameExists($name, $parent_id = null, $exclude_id = null) {
    global $db;
    try {
        $sql = "SELECT COUNT(*) as count FROM categories WHERE name = ? AND " . 
               ($parent_id ? "parent_id = ?" : "parent_id IS NULL");
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
        }
        
        $stmt = $db->prepare($sql);
        $params = [$name];
        if ($parent_id) $params[] = $parent_id;
        if ($exclude_id) $params[] = $exclude_id;
        
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// ====================================================================
// 設定管理関数
// ====================================================================

// 全設定を取得
function getAllSettings() {
    global $db;
    try {
        $stmt = $db->query("SELECT key, value FROM settings ORDER BY key");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

// 複数設定を一括更新
function updateSettings($settings) {
    global $db;
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        
        $db->commit();
        return ['success' => true, 'message' => '設定を更新しました。'];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
}

// 設定値のバリデーション
function validateSettings($settings) {
    $errors = [];
    
    // サイトタイトル
    if (empty($settings['site_title'])) {
        $errors[] = 'サイトタイトルは必須です。';
    } elseif (strlen($settings['site_title']) > 100) {
        $errors[] = 'サイトタイトルは100文字以内で入力してください。';
    }
    
    // サイト説明
    if (strlen($settings['site_description']) > 500) {
        $errors[] = 'サイト説明は500文字以内で入力してください。';
    }
    
    // 最大サイト数
    if (!is_numeric($settings['max_sites']) || $settings['max_sites'] < 1) {
        $errors[] = '最大サイト数は1以上の数値で入力してください。';
    } elseif ($settings['max_sites'] > 10000) {
        $errors[] = '最大サイト数は10000以下で入力してください。';
    }
    
    // 1ページあたりサイト数
    if (!is_numeric($settings['sites_per_page']) || $settings['sites_per_page'] < 1) {
        $errors[] = '1ページあたりサイト数は1以上の数値で入力してください。';
    } elseif ($settings['sites_per_page'] > 100) {
        $errors[] = '1ページあたりサイト数は100以下で入力してください。';
    }
    
    // 承認制
    if (!in_array($settings['require_approval'], ['0', '1'])) {
        $errors[] = '承認制の設定が不正です。';
    }
    
    return $errors;
}
?>