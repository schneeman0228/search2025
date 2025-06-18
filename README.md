# ディレクトリサーチ 設置・運用ガイド

## 概要

ディレクトリ型サーチエンジンです。同人サイトサーチ向けに設計され、検索避け対応、簡単設置、軽量動作を重視しています。

### 機能の拡張

1. **メール通知機能**の追加
3. **タグ機能**の追加
- サイトごとにユーザー管理画面を設置し、登録サイトへリンクする。そこからユーザーが情報を編集できるようにする（同一ユーザーが複数サイト運営している場合、ユーザー用管理画面ログイン用のメールアドレスとパスワードが重複する恐れがあるため）
- サイト情報に、更新日、登録日、バナー(最大200px*100px、100KBまで)、上記サイト情報管理画面へのリンクを設置
- 最大サイト数による制限を廃止

## 今後の開発時の注意点

- トランザクション制御の一元化: 一つの処理フローでは一箇所でトランザクション制御
- inTransaction()チェック: ロールバック前に必ずチェック
- エラーログ: デバッグ情報を適切に記録
- 例外処理: 具体的なエラーメッセージで問題を特定

## 主な機能

### 基本機能
- ✅ カテゴリー分類システム（階層対応）
- ✅ サイト登録・編集機能
- ✅ 検索機能（タイトル・説明文）
- ✅ 管理者承認システム
- ✅ レスポンシブデザイン

### 追加機能
- ✅ RSS配信機能
- ✅ スパム対策（IP制限、重複チェック）
- ✅ 検索避け対応（robots.txt、X-Robots-Tag）
- ✅ セキュリティ対策（CSRF、XSS対策）

## システム要件

- **PHP**: 7.4以上（8.0以上推奨）
- **データベース**: SQLite（標準搭載）
- **Webサーバー**: Apache（.htaccess対応）またはNginx
- **メモリ**: 64MB以上
- **ディスク容量**: 10MB以上

## インストール手順

### 1. ファイルアップロード

すべてのファイルをレンタルサーバーにアップロードします：

```
/your-search-engine/
├── index.php
├── register.php
├── setup.php
├── rss.php
├── .htaccess
├── robots.txt
├── admin/
│   ├── login.php
│   ├── dashboard.php
│   └── manage.php
├── includes/
│   ├── config.php
│   ├── database.php
│   └── functions.php
└── data/ (自動作成)
```

### 2. 権限設定

```bash
chmod 755 /your-search-engine/
chmod 755 /your-search-engine/data/
chmod 644 /your-search-engine/*.php
chmod 644 /your-search-engine/admin/*.php
chmod 644 /your-search-engine/includes/*.php
```

### 3. 初期セットアップ

1. ブラウザで `http://yoursite.com/setup.php` にアクセス
2. システム要件をチェック
3. 「データベースを初期化する」ボタンをクリック
4. セットアップ完了後、**setup.phpファイルを削除**

### 4. 初期設定

1. 管理画面にログイン（`http://yoursite.com/admin/`）
   - ユーザー名: `admin`
   - パスワード: `admin123`

2. **必須：管理者パスワードを変更**
   - データベースの `admins` テーブルで直接変更
   - または新しい管理者を追加して初期管理者を削除

3. サイト設定の確認・変更
   - データベースの `settings` テーブルで設定変更

## 基本的な使い方

### サイト登録

1. トップページの「サイト登録」リンクから登録フォームへ
2. 必要事項を入力して送信
3. 管理者による承認待ち状態になる

### 管理者による承認

1. 管理画面のダッシュボードで承認待ちサイトを確認
2. 「承認」または「削除」を選択
3. 一括操作も可能

### RSS配信

以下のURLでRSSフィードを取得できます：

- 全サイト: `http://yoursite.com/rss.php`
- カテゴリ別: `http://yoursite.com/rss.php?category=1`
- 件数制限: `http://yoursite.com/rss.php?limit=10`

## 設定変更

### データベース設定

SQLiteデータベース（`data/search.db`）の `settings` テーブルで設定を変更：

```sql
-- サイトタイトルの変更
UPDATE settings SET value = '新しいタイトル' WHERE key = 'site_title';

-- サイト説明の変更
UPDATE settings SET value = '新しい説明文' WHERE key = 'site_description';

-- 最大サイト数の変更
UPDATE settings SET value = '3000' WHERE key = 'max_sites';

-- 1ページあたりのサイト数
UPDATE settings SET value = '30' WHERE key = 'sites_per_page';
```

### カテゴリ管理

`categories` テーブルで直接編集：

```sql
-- カテゴリ追加
INSERT INTO categories (name, description, sort_order) 
VALUES ('新カテゴリ', '説明文', 10);

-- カテゴリ名変更
UPDATE categories SET name = '新しい名前' WHERE id = 1;

-- カテゴリ削除（関連サイトも削除されるので注意）
DELETE FROM categories WHERE id = 1;
```

## セキュリティ対策

### 実装済み対策

1. **SQLインジェクション対策**: PDOプリペアドステートメント使用
2. **XSS対策**: HTMLエスケープ処理
3. **CSRF対策**: CSRFトークンによる検証
4. **ファイルアクセス制限**: .htaccessによる保護
5. **セッションセキュリティ**: httponly、secure設定

### 追加推奨設定

```php
// includes/config.php で本番環境用設定
error_reporting(0);
ini_set('display_errors', 0);

// HTTPSの強制
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $redirect_url", true, 301);
    exit;
}
```

## トラブルシューティング

### よくある問題

**Q: データベースエラーが発生する**
```
A: 
1. dataディレクトリの書き込み権限を確認
2. PHPのPDO SQLite拡張が有効か確認
3. エラーログを確認
```

**Q: 管理画面にアクセスできない**
```
A:
1. .htaccessが正しく設置されているか確認
2. admin/ディレクトリの権限を確認
3. セッション機能が有効か確認
```

**Q: サイト登録ができない**
```
A:
1. CSRFトークンエラー → ブラウザのキャッシュをクリア
2. URLエラー → 正しいURL形式で入力
3. 重複エラー → 既に登録済みのURLでないか確認
```

### ログの確認

```bash
# エラーログの確認
tail -f /path/to/php-error.log

# Apacheログの確認
tail -f /path/to/apache/error.log
```

## バックアップ

### 定期バックアップの設定

```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/path/to/backup"
SOURCE_DIR="/path/to/search-engine"

# データベースとファイルをバックアップ
tar -czf "$BACKUP_DIR/search-engine-$DATE.tar.gz" "$SOURCE_DIR"

# 古いバックアップを削除（30日以上前）
find "$BACKUP_DIR" -name "search-engine-*.tar.gz" -mtime +30 -delete
```

crontabに登録：
```bash
# 毎日午前3時にバックアップ実行
0 3 * * * /path/to/backup.sh
```

## ライセンス

このソフトウェアはMITライセンスで公開されています。自由に改変・配布していただけます。

## サポート

- **GitHub Issues**: バグ報告・機能要求
- **ドキュメント**: このREADMEファイル

---

## 更新履歴

### v1.0.0 (2025-06-15)
- 初回リリース
- 基本機能実装完了
- RSS配信機能追加
- 検索避け対応
- セキュリティ対策実装