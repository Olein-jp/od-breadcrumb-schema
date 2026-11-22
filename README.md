# OD Breadcrumb Schema

WordPress プラグインの最小開発基盤です。パンくずリスト・構造化データの出力機能は今後実装します。

## 必要環境

- Docker（起動済み）
- Node.js 24.18 以上（`.nvmrc` を同梱）と npm 11.16 以上
- ホストで配布 ZIP を作る場合は PHP 7.4 以上、Composer 2、Python 3

プラグインの動作要件は WordPress 6.0 以上・PHP 7.4 以上です。wp-env は PHP 7.4 と最新の安定版 WordPress を使用します。

## 開発

```sh
npm ci
npm run env:start
npm run composer -- install
npm run env:cli -- plugin activate od-breadcrumb-schema
npm run lint:php
```

管理画面は通常 http://localhost:8888/wp-admin/ です（ユーザー `admin`、パスワード `password`）。使用中のポートがあれば自動的に空きポートを選択します。起動ログまたは `npm run env:status` で接続先を確認できます。

```sh
npm run env:logs
npm run env:stop
```

ローカル固有の設定は Git 管理対象外の `.wp-env.override.json` に記述してください。PHP ファイルを追加した場合は `phpcs.xml.dist` の検査対象も追加します。ホストの PHP・Composer を使う場合は `composer install`、`composer lint` も利用できます。

## 多言語化

テキストドメインは `od-breadcrumb-schema` です。PHP の表示文字列は `__()`、`esc_html__()` などの翻訳関数を通します。英語を原文にし、日本語訳を `languages/od-breadcrumb-schema-ja.po` で管理します。

```sh
npm run i18n:pot
# POT の変更を PO エディターなどで日本語 PO に取り込み、翻訳する
npm run i18n:mo
```

POT・PO・生成した MO をコミットしてください。ほかの言語は `od-breadcrumb-schema-<locale>.po` と MO を追加します。サイトの言語に応じて `init` で読み込みます。JavaScript の翻訳・ビルドツールは JavaScript 導入時に追加します。

## 配布と更新

[WP GitHub Plugin Updater](https://github.com/inc2734/wp-github-plugin-updater) を Composer で導入しています。[GitHub Releases](https://github.com/Olein-jp/od-breadcrumb-schema/releases) の `od-breadcrumb-schema.zip` をインストールすると、以降は WordPress の通常の更新画面で新しいバージョンへ更新できます。公開リポジトリでの配布を前提とします。

ローカルでのパッケージ作成（PHP・Composer・Python が必要）:

```sh
npm run build
```

`dist/od-breadcrumb-schema.zip` にプラグイン専用ディレクトリ、本体、翻訳、本番の Composer 依存、ライセンスと説明書だけを収録します。開発環境や `node_modules`、WPCS は入りません。

リリース手順:

1. 本体の `Version`、`readme.txt` の `Stable tag`、`package.json` の `version` を同じ値にする。
2. `npm install --package-lock-only` でロックファイルを更新し、翻訳の更新・検証を済ませてコミット・プッシュする。
3. 同じ値のタグを push する。初回は次のとおり。

```sh
git tag 0.0.0
git push origin 0.0.0
```

`0.0.0`、`0.0.1`、`1.2.3` のような `v` なしのタグでリリース用 GitHub Actions が実行されます。タグとバージョンの照合、PHP 構文・WPCS 検証、MO 生成、本番依存だけの ZIP 作成を行い、Releases に公開します。タグ作成は通常のコミット・プッシュと分けて実行します。
