<?php
require_once 'includes/config.php';

// パラメータ取得
$category_ids = isset($_GET['categories']) && is_array($_GET['categories']) ? array_map('intval', $_GET['categories']) : [];
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null; // 後方互換性のため
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;

// 後方互換性：単一カテゴリパラメータがある場合は配列に変換
if ($category_id && empty($category_ids)) {
    $category_ids = [$category_id];
}

// カテゴリ情報取得
$selected_categories = [];
$category_names = [];
if (!empty($category_ids)) {
    foreach ($category_ids as $cat_id) {
        $category = getCategory($cat_id);
        if ($category) {
            $selected_categories[] = $category;
            $parent = $category['parent_id'] ? getCategory($category['parent_id']) : null;
            $name = $parent ? $parent['name'] . ' > ' . $category['name'] : $category['name'];
            $category_names[] = $name;
        }
    }
}

// サイト一覧取得
$sites = getSites($category_ids, null, 1, $limit, 'approved');

// RSS用の情報
$feed_title = $SITE_TITLE;
$feed_description = $SITE_DESCRIPTION;
$feed_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

if (!empty($selected_categories)) {
    $feed_title .= ' - ' . implode(', ', $category_names);
    $feed_description = implode(', ', $category_names) . 'カテゴリのサイト一覧';
    $feed_link .= '?';
    foreach ($category_ids as $cat_id) {
        $feed_link .= 'categories[]=' . $cat_id . '&';
    }
    $feed_link = rtrim($feed_link, '&');
}

// XMLヘッダー設定
header('Content-Type: application/rss+xml; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

// RSS XML生成
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?php echo h($feed_title); ?></title>
    <description><?php echo h($feed_description); ?></description>
    <link><?php echo h($feed_link); ?></link>
    <language>ja</language>
    <copyright>Copyright (c) <?php echo date('Y'); ?> <?php echo h($SITE_TITLE); ?></copyright>
    <managingEditor>webmaster@example.com</managingEditor>
    <webMaster>webmaster@example.com</webMaster>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <generator><?php echo h($SITE_TITLE); ?> RSS Generator (Multiple Categories Support)</generator>
    <atom:link href="<?php echo h((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" rel="self" type="application/rss+xml"/>
    
    <?php foreach ($sites as $site): ?>
    <item>
        <title><?php echo h($site['title']); ?></title>
        <description><![CDATA[
            <?php if ($site['description']): ?>
                <?php echo h($site['description']); ?><br><br>
            <?php endif; ?>
            URL: <a href="<?php echo h($site['url']); ?>" target="_blank"><?php echo h($site['url']); ?></a><br>
            カテゴリ: <?php 
                $cat_list = [];
                foreach ($site['categories'] as $cat) {
                    if ($cat['parent_name']) {
                        $cat_list[] = $cat['parent_name'] . ' > ' . $cat['name'];
                    } else {
                        $cat_list[] = $cat['name'];
                    }
                }
                echo h(implode(', ', $cat_list));
            ?><br>
            登録日: <?php echo date('Y年m月d日', strtotime($site['created_at'])); ?>
        ]]></description>
        <link><?php echo h($site['url']); ?></link>
        <guid><?php echo h($site['url']); ?></guid>
        <pubDate><?php echo date('r', strtotime($site['created_at'])); ?></pubDate>
        <?php foreach ($site['categories'] as $cat): ?>
            <category><?php 
                if ($cat['parent_name']) {
                    echo h($cat['parent_name'] . ' > ' . $cat['name']);
                } else {
                    echo h($cat['name']);
                }
            ?></category>
        <?php endforeach; ?>
        <source url="<?php echo h($feed_link); ?>"><?php echo h($feed_title); ?></source>
    </item>
    <?php endforeach; ?>
    
    <?php if (empty($sites)): ?>
    <item>
        <title>登録されているサイトはありません</title>
        <description><?php echo !empty($selected_categories) ? 'このカテゴリにはまだサイトが登録されていません。' : 'まだサイトが登録されていません。'; ?></description>
        <link><?php echo h($feed_link); ?></link>
        <guid><?php echo h($feed_link . '#no-sites'); ?></guid>
        <pubDate><?php echo date('r'); ?></pubDate>
    </item>
    <?php endif; ?>
</channel>
</rss>