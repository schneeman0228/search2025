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

// サイト登録
function registerSite($title, $url, $description, $category_id, $ip_address) {
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
    if ($totalSites >= $GLOBALS['MAX_SITES']) {
        return ['success' => false, 'message' => 'サイト登録数が上限に達しています。'];
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO sites (title, url, description, category_id, ip_address, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$title, $url, $description, $category_id, $ip_address]);
        return ['success' => true, 'message' => 'サイトを登録しました。管理者の承認をお待ちください。'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'データベースエラーが発生しました。'];
    }
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
?>