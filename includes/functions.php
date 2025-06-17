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

// カテゴリパスを取得（パンくずナビ用）
function getCategoryPath($category_id) {
    global $db;
    
    $path = [];
    $current_id = $category_id;
    
    while ($current_id) {
        $stmt = $db->prepare("SELECT id, name, parent_id FROM categories WHERE id = ?");
        $stmt->execute([$current_id]);
        $category = $stmt->fetch();
        
        if (!$category) break;
        
        array_unshift($path, $category);
        $current_id = $category['parent_id'];
    }
    
    return $path;
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
                    'child_name' => $child['name']
                ];
            }
        } else {
            // 子カテゴリがない場合は親カテゴリを選択可能に
            $categories[] = [
                'id' => $parent['id'],
                'name' => $parent['name'],
                'parent_name' => '',
                'child_name' => $parent['name']
            ];
        }
    }
    
    return $categories;
}

// 階層カテゴリ表示HTML生成
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
                $html .= '<a href="?category=' . $child['id'] . '">';
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
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE category_id = ? AND status = 'approved'");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

// サイト一覧を取得
function getSites($category_id = null, $search = null, $page = 1, $limit = 20, $status = 'approved') {
    global $db;
    
    $offset = ($page - 1) * $limit;
    $conditions = ["status = ?"];
    $params = [$status];
    
    if ($category_id) {
        $conditions[] = "category_id = ?";
        $params[] = $category_id;
    }
    
    if ($search) {
        $conditions[] = "(title LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql = "SELECT s.*, c.name as category_name 
            FROM sites s 
            JOIN categories c ON s.category_id = c.id 
            WHERE " . implode(' AND ', $conditions) . " 
            ORDER BY s.updated_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// サイト総数を取得（検索・フィルタ条件付き）
function getSitesCount($category_id = null, $search = null, $status = 'approved') {
    global $db;
    
    $conditions = ["status = ?"];
    $params = [$status];
    
    if ($category_id) {
        $conditions[] = "category_id = ?";
        $params[] = $category_id;
    }
    
    if ($search) {
        $conditions[] = "(title LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql = "SELECT COUNT(*) as count FROM sites WHERE " . implode(' AND ', $conditions);
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
    
    $stmt = $db->prepare("
        SELECT s.*, c.name as category_name 
        FROM sites s 
        JOIN categories c ON s.category_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$_SESSION['user_site_id']]);
    return $stmt->fetch();
}

// サイト情報更新（ユーザー）
function updateUserSite($site_id, $title, $url, $description, $category_id, $user_email) {
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
    
    try {
        $stmt = $db->prepare("
            UPDATE sites 
            SET title = ?, url = ?, description = ?, category_id = ?, updated_at = datetime('now') 
            WHERE id = ? AND email = ?
        ");
        $stmt->execute([$title, $url, $description, $category_id, $site_id, $user_email]);
        return ['success' => true, 'message' => 'サイト情報を更新しました。'];
    } catch (PDOException $e) {
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

// サイト登録（修正版：メール重複OK、パスワード簡素化）
function registerSiteWithUser($title, $url, $description, $category_id, $email, $password, $ip_address) {
    global $db;
    
    // URL重複チェック
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE url = ?");
    $stmt->execute([$url]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return ['success' => false, 'message' => 'このURLは既に登録されています。'];
    }
    
    // サイト数上限チェック
    $totalSites = getSiteCount('approved') + getSiteCount('pending');
    global $MAX_SITES;
    if ($totalSites >= $MAX_SITES) {
        return ['success' => false, 'message' => 'サイト登録数が上限に達しています。'];
    }
    
    try {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO sites (title, url, description, category_id, email, password_hash, ip_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$title, $url, $description, $category_id, $email, $password_hash, $ip_address]);
        return ['success' => true, 'message' => 'サイトを登録しました。管理者の承認をお待ちください。'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
}

// 下位互換のため旧関数名も残す（必要に応じて削除）
function registerSite($title, $url, $description, $category_id, $ip_address) {
    // 旧形式での呼び出しの場合はエラーを返す
    return ['success' => false, 'message' => 'この関数は廃止されました。registerSiteWithUserを使用してください。'];
}
?>