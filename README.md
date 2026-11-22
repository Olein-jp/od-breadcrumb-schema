# OD Breadcrumb Schema

WordPress 標準のパンくずブロック（`core/breadcrumbs`）の直後に、表示経路に対応する `BreadcrumbList` の JSON-LD を追加します。

## インストール

WordPress 7.0 以上、PHP 8.2 以上が必要です。[GitHub Releases](https://github.com/Olein-jp/od-breadcrumb-schema/releases) の `od-breadcrumb-schema.zip` を WordPress 管理画面の「プラグイン → 新規プラグインを追加 → プラグインのアップロード」からインストールし、有効化してください。

公開ページのテンプレート、テンプレートパーツ、本文などにコアのパンくずブロックがあり、表示項目が有効な2件以上の場合に出力します。すでに配置されているブロックには追加操作も設定変更も不要です。

このプラグインによる外部通信はありません。更新時も GitHub Releases から ZIP を取得し、管理画面で既存のプラグインを置き換えてください。アンインストール時に削除するプラグイン固有のデータベースデータはありません。

## 出力対象

- 投稿・固定ページ・公開カスタム投稿タイプの個別ページ
- 投稿一覧、タクソノミー・投稿タイプ・日付・投稿者のアーカイブ
- 上記のページ分割と、コアの設定でブロックが表示されるトップページ

検索結果、404、添付ファイルの個別ページ、管理画面・サイトエディター、REST API・フィード・Ajax・WP-CLI、投稿・カスタマイザーのプレビューは対象外です。

同じ表示経路は1リクエストにつき1回出力します。表示設定が異なり、経路にも違いがあるブロックにはそれぞれ出力します。現在項目がリンクなしで表示されるときは最後の項目の `item` を省略します。最後が上位ページへのリンクの場合は URL を保持します。空のラベル、リンクのない途中項目、絶対 HTTP(S) URL として扱えないリンクなどが含まれる場合は、その経路の出力を見送ります。

## 他の構造化データとの調整

コアや SEO プラグインからも同じ `BreadcrumbList` が出力される場合は、使用する出力元を選んでください。このプラグイン側の出力を止めるには、独自プラグインなどに次を追加します。

```php
add_filter( 'od_breadcrumb_schema_enabled', '__return_false' );
```

フィルターの既定値は `true` です。停止してもコアブロックの表示と編集は続けられます。構造化データを追加しても、Google での検索結果の表示形式や順位の上昇は保証されません。[Google の構造化データガイドライン](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)

## 対応するカスタマイズ

WordPress 7.0 のコア描画関数 `render_block_core_breadcrumbs` を使うブロックに対応しています。描画関数の呼び出し中だけ `block_core_breadcrumbs_items` を優先度 `PHP_INT_MAX` で監視し、コアが表示設定を反映した項目をそのまま返します。描画されたブロックのインスタンスを照合し、`render_block_core/breadcrumbs` の優先度 `PHP_INT_MAX` でブロックのHTMLに JSON-LD を追記します。

通常の項目フィルターで加えた経路の変更を反映します。描画関数を差し替える実装や、項目取得後にパンくずHTMLを書き換える実装は対応範囲外です。描画フィルターがコアの項目一覧を取り除いた場合は出力を見送ります。さらに後段で親ブロックやページ全体が書き換えられる場合の整合性は保証されません。[WordPress のブロック描画フック](https://developer.wordpress.org/reference/hooks/render_block_this-name/)

## 開発とテスト

開発環境は Docker、Node.js 24 と npm、ホストから PHP の検査を実行する場合は PHP 8.2 以上と Composer 2 を使います。wp-env は WordPress 7.0 と PHP 8.2 に固定しています。

```sh
npm ci
npm run env:start
npm run env:cli -- plugin activate od-breadcrumb-schema
npm test
composer install
composer lint
```

`npm test` は wp-env の英語のテスト用サイトで実行してください。実際の WordPress とコアの描画処理を使って、JSON の正規化、エスケープ、不正な経路、カテゴリー階層、表示設定、重複排除、別経路、ページ分割、停止フィルター、検索・プレビュー・REST を確認します。テスト用の投稿とカテゴリーは同じデータベースセッションでロールバックします。別の検証用 WordPress では、プラグインを有効化したうえで `composer test -- /path/to/wordpress` を実行できます。

サイトエディター・公開画面の確認と Google リッチリザルトテストは [検証手順と結果](#検証手順と結果) を参照してください。

## 配布 ZIP

Python 3 がある環境で `npm run build` を実行すると、`dist/od-breadcrumb-schema.zip` が作成されます。ZIP に収録するのはプラグイン本体、ライセンス、説明書です。

リリース時はプラグインヘッダー、`readme.txt` の Stable tag、`package.json` と `package-lock.json` のバージョンをそろえてコミットします。そのコミットの検証ワークフローが成功した後、同じバージョンの `v` なしのタグ（例 `0.1.0`）を push すると、リリース用ワークフローが ZIP を作成して GitHub Releases に公開します。

## 検証手順と結果

- WordPress 7.0・PHP 8.2 の wp-env で自動テストを実行。WordPress 7.0 のコアソースで項目フィルターとブロック設定を確認。[パンくずの描画処理](https://github.com/WordPress/WordPress/blob/7.0/wp-includes/blocks/breadcrumbs.php)・[ブロック定義](https://github.com/WordPress/WordPress/blob/7.0/wp-includes/blocks/breadcrumbs/block.json)
- WordPress 7.0・PHP 8.2.36・Twenty Twenty-Five の公開ページで、ブロック3件に対して異なる2経路の JSON-LD を確認。無効化前後でパンくずの HTML が一致し、追加した JSON-LD だけが消えることを確認。サイトエディターでは有効化・無効化の両方で3件のブロックが表示された。
- Google のリッチリザルトテストの「コード」で、検証ページのパンくずと JSON-LD を確認。検証用 URL を `https://example.com` に置き換えた HTML で、有効なパンくずリスト2件を検出。再確認する場合は公開ページの HTML ソースを「コード」に貼り付け、「パンくずリスト」の詳細を確認してください。[リッチリザルトテスト](https://search.google.com/test/rich-results)・[パンくずの構造化データ仕様](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb)
- 本番環境の URL 検査と Search Console での確認は、サイトへの導入後に行います。
