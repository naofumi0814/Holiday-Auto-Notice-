# Holiday Auto Notice

WordPress用 年間休業カレンダー管理 & 自動お知らせ投稿プラグイン

## 概要

管理画面で年間の休業日カレンダーを設定すると、休業日の指定日数前（デフォルト10日前）に休業案内の記事を自動公開し、休業当日はサイト上の営業ステータス表示を自動切替するプラグインです。

## 動作要件

- PHP 8.1 以上
- WordPress 6.0 以上

## インストール

1. `holiday-auto-notice` ディレクトリごと `wp-content/plugins/` に配置
2. WordPress管理画面の「プラグイン」から「Holiday Auto Notice」を有効化
3. サイドバーに「休業カレンダー」メニューが追加されます

## 機能一覧

### カレンダー管理
- 月別カレンダーで各日付のステータスを設定（通常営業 / 休業 / 短縮営業 / 特別営業）
- 休業種別の設定（祝日 / 祝日以外の休業 / お盆 / 年末年始 / 個別特例）
- 各日付にメモ、個別タイトル、個別本文、個別当日表示文を設定可能
- 翌年度へのカレンダー複製
- CSVインポート / エクスポート

### 自動投稿
- 休業日の指定日数前に自動で告知記事を投稿
- 休業種別ごとのテンプレート切り替え
- 投稿タイプ / カテゴリ / 投稿者 / 公開ステータスを設定可能
- 重複投稿の防止
- カレンダー変更時の自動再計算
- 直前登録時の例外処理（即時公開 / 指定時刻 / スキップ）

### 当日表示
- 休業日当日にサイト上の営業ステータスを自動切替
- ショートコード `[han_status]` で出力
- ウィジェット対応
- Gutenbergブロック対応
- 固定ページのプレースホルダ置換

### テンプレート管理
- 事前告知投稿用 / 当日表示用 / 営業再開案内用の3種類
- 休業種別ごとに個別テンプレートを設定可能
- テンプレート変数による動的コンテンツ生成

### 便利機能
- テスト実行 / 手動実行ボタン
- 更新ログの保存と確認
- 投稿履歴の確認
- プレビュー機能

## ショートコード

### 営業ステータス表示
```
[han_status]
```

通常営業日は非表示にする場合:
```
[han_status show_normal="false"]
```

### 次回休業日表示
```
[han_next_holiday]
```

### 固定ページ用プレースホルダ
固定ページの本文に以下を記述:
```html
<!-- han_status --><!-- /han_status -->
```

## テンプレート変数

| 変数 | 内容 |
|------|------|
| `{site_name}` | サイト名 |
| `{date}` | 日付（YYYY年M月D日形式） |
| `{year}` | 年 |
| `{month}` | 月 |
| `{day}` | 日 |
| `{weekday}` | 曜日（日本語） |
| `{holiday_name}` | 休業種別名 |
| `{next_business_date}` | 次の営業日 |
| `{next_business_weekday}` | 次の営業日の曜日 |
| `{business_hours}` | 営業時間 |
| `{notice_days_before}` | 事前告知日数 |
| `{note}` | メモ |

## CSV形式

ヘッダー:
```
日付,営業ステータス,休業種別,メモ,個別タイトル,個別本文,個別当日表示文,自動投稿
```

- 営業ステータス: `normal` / `holiday` / `short` / `special`
- 休業種別: `public_holiday` / `regular` / `obon` / `yearend` / `special_case`
- 自動投稿: `1`（有効） / `0`（無効）

## ファイル構成

```
holiday-auto-notice/
├── holiday-auto-notice.php    # メインプラグインファイル
├── uninstall.php              # アンインストール処理
├── includes/
│   ├── class-han-settings.php       # 設定管理
│   ├── class-han-calendar.php       # カレンダーデータ管理
│   ├── class-han-template.php       # テンプレート管理
│   ├── class-han-post-generator.php # 投稿生成
│   ├── class-han-cron.php           # WP-Cron処理
│   ├── class-han-shortcode.php      # ショートコード・ブロック
│   ├── class-han-logger.php         # ログ管理
│   ├── class-han-csv.php            # CSV入出力
│   └── admin/
│       ├── class-han-admin.php           # 管理画面統括
│       ├── class-han-admin-calendar.php  # カレンダー管理画面
│       ├── class-han-admin-templates.php # テンプレート管理画面
│       ├── class-han-admin-settings.php  # 設定画面
│       └── class-han-admin-logs.php      # ログ画面
├── assets/
│   ├── css/
│   │   └── admin.css          # 管理画面スタイル
│   └── js/
│       └── admin.js           # 管理画面JavaScript
└── languages/                 # 翻訳ファイル用
```

## 当日表示の優先順位

1. 個別上書き
2. 年末年始
3. お盆
4. 祝日
5. 祝日以外の休業
6. 短縮営業
7. 特別営業
8. 通常営業

## 今後の改善ポイント

- 祝日APIとの連携による自動祝日設定
- REST API対応
- 複数年度の一括表示
- メール通知機能
- 連続休業日のグループ化（お盆期間をまとめて1投稿にする等）
- 多言語対応

## ライセンス

GPL-2.0-or-later
