=== OD Breadcrumb Schema ===
Contributors: olein
Requires at least: 7.0
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress 標準のパンくずブロックに BreadcrumbList の構造化データを追加します。

== Description ==

コアのパンくずブロック（core/breadcrumbs）の表示項目から JSON-LD を生成し、ブロックの直後に追加します。同じ経路はページ内で一度だけ出力します。有効な表示項目が2件以上ある場合に動作します。

投稿、固定ページ、公開カスタム投稿タイプ、投稿一覧、各種アーカイブとそのページ分割に対応します。トップページはコアの表示設定に従います。検索結果、404、添付ファイルの個別ページ、管理画面・サイトエディター、REST API、フィード、Ajax、WP-CLI、投稿・カスタマイザーのプレビューは対象外です。

他のプラグインなどから同じ構造化データを出力する場合は、独自プラグインなどで次のフィルターを利用して、このプラグインの出力を停止できます。

`add_filter( 'od_breadcrumb_schema_enabled', '__return_false' );`

アンインストール時に削除するプラグイン固有のデータベースデータはありません。構造化データによる検索結果の表示形式や順位上昇は保証されません。

== Installation ==

[GitHub Releases](https://github.com/Olein-jp/od-breadcrumb-schema/releases) の od-breadcrumb-schema.zip を WordPress 管理画面からアップロードして有効化してください。コアのパンくずブロックが配置されている公開ページで動作します。更新時も Releases から ZIP を取得してアップロードしてください。

== Changelog ==

= 0.1.0 =
* コアのパンくずブロックを使うページに BreadcrumbList を追加。
