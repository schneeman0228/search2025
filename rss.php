<?php
require_once 'includes/config.php';

// パラメータ取得
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;

// カテゴリ情報取得
$category_info = null;
if ($category_id) {
    $category_info = getCategory($category_id);
    if (!$category_info) {
        header('HTTP/1.1 404 Not Found');
        exit('Category not found');
    }
}

// サイト一覧取得
$sites = getSites($category_id, null, 1, $limit, 'approved');

// RSS用の情報
$feed_title = $SITE_TITLE;
$feed_description = $SITE_DESCRIPTION;
$feed_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

if ($category_info) {
    $feed_title .= ' - ' . $category_info['name'];
    $feed_description = $category_info['description'] ?: $category_info['name'] . 'カテゴリのサイト一覧';
    $feed_link .= '?category=' . $category_id;
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
    <generator><?php echo h($SITE_TITLE); ?> RSS Generator</generator>
    <atom:link href="<?php echo h((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" rel="self" type="application/rss+xml"/>
    
    <?php foreach ($sites as $site): ?>
    <item>
        <title><?php echo h($site['title']); ?></title>
        <description><![CDATA[
            <?php if ($site['description']): ?>
                <?php echo h($site['description']); ?><br><br>
            <?php endif; ?>
            URL: <a href="<?php echo h($site['url']); ?>" target="_blank"><?php echo h($site['url']); ?></a><br>
            カテゴリ: <?php echo h($site['category_name']); ?><br>
            登録日: <?php echo date('Y年m月d日', strtotime($site['created_at'])); ?>
        ]]></description>
        <link><?php echo h($site['url']); ?></link>
        <guid><?php echo h($site['url']); ?></guid>
        <pubDate><?php echo date('r', strtotime($site['created_at'])); ?></pubDate>
        <category><?php echo h($site['category_name']); ?></category>
        <source url="<?php echo h($feed_link); ?>"><?php echo h($feed_title); ?></source>
    </item>
    <?php endforeach; ?>
    
    <?php if (empty($sites)): ?>
    <item>
        <title>登録されているサイトはありません</title>
        <description>まだサイトが登録されていません。</description>
        <link><?php echo h($feed_link); ?></link>
        <guid><?php echo h($feed_link . '#no-sites'); ?></guid>
        <pubDate><?php echo date('r'); ?></pubDate>
    </item>
    <?php endif; ?>
</channel>
</rss>