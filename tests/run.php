<?php
/**
 * WordPress の実際のブロック描画を使う回帰テスト。
 *
 * @package OD_Breadcrumb_Schema
 */

namespace OD_Breadcrumb_Schema\Tests;

use OD_Breadcrumb_Schema\Breadcrumb_Schema;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI のテスト失敗を例外で報告する。
// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions -- テスト中の警告を検出する。

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

if ( empty( $argv[1] ) || ! is_file( $argv[1] . '/wp-load.php' ) ) {
	echo "WordPress のルートディレクトリを指定してください。\n";
	exit( 1 );
}

require $argv[1] . '/wp-load.php';

/**
 * 条件を満たさなければ失敗とする。
 *
 * @param bool   $condition 条件.
 * @param string $message   確認事項.
 * @throws \RuntimeException 条件を満たさない場合.
 */
function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new \RuntimeException( $message );
	}
}

/**
 * WordPress のメインクエリとブロックのコンテキストを用意する。
 *
 * @param array $args クエリ引数.
 */
function query( array $args ): void {
	$query = new \WP_Query();
	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- テスト内でメインクエリを再現する。
	$GLOBALS['wp_query']     = $query;
	$GLOBALS['wp_the_query'] = $query;
	$query->query( $args );
	$GLOBALS['post'] = is_singular() ? get_queried_object() : null;
	// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
}

/**
 * 描画した HTML からこのプラグインの経路だけを取得する。
 *
 * @param string $html 描画結果.
 * @return array
 */
function trails( string $html ): array {
	preg_match_all( '~<script type="application/ld\+json" data-od-breadcrumb-schema>(.*?)</script>~s', $html, $matches );
	return array_map(
		static fn( $json ) => json_decode( $json, true, 512, JSON_THROW_ON_ERROR )['itemListElement'],
		$matches[1]
	);
}

/**
 * 正規化、JSON の安全性、無効な経路の扱いを確認する。
 */
function test_encoding(): void {
	$home = array(
		'label' => '<b>ホーム</b> &amp; &#34;案内&#34;',
		'url'   => 'http://localhost:8888/?a=1&b=2',
	);
	$page = array( 'label' => '記事 &lt;/script&gt; &#39;A&#39;' );

	$json = Breadcrumb_Schema::encode(
		array(
			8 => $home,
			2 => $page,
		)
	);
	check( is_string( $json ) && ! str_contains( $json, '<' ), 'JSON 内で HTML タグがエスケープされる。' );
	$schema = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
	check(
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => 'ホーム & "案内"',
					'item'     => $home['url'],
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => "記事 </script> 'A'",
				),
			),
		) === $schema,
		'順序、連番、日本語、文字参照、末尾の URL 省略を維持する。'
	);

	$last_link = array(
		'label'      => '<strong>親ページ</strong>',
		'url'        => 'https://example.test/parent/',
		'allow_html' => true,
	);
	$list      = json_decode( Breadcrumb_Schema::encode( array( $home, $last_link ) ), true )['itemListElement'];
	check( '親ページ' === $list[1]['name'] && $last_link['url'] === $list[1]['item'], 'HTML ラベルの文字と末尾にあるリンクを維持する。' );

	foreach (
		array(
			null,
			'',
			array( $home ),
			array( $page, $home ),
			array( $home, false ),
			array( $home, array() ),
			array( $home, array( 'label' => array( '記事' ) ) ),
			array( $home, array( 'label' => '<b>&nbsp;</b>' ) ),
			array( $home, array( 'label' => "\xFF" ) ),
			array( $home, array_merge( $page, array( 'url' => new \stdClass() ) ) ),
			array( $home, array_merge( $page, array( 'url' => null ) ) ),
			array( $home, array_merge( $page, array( 'url' => 'javascript:void(0)' ) ) ),
			array( $home, array_merge( $page, array( 'url' => 'https://' ) ) ),
			array( $home, array_merge( $page, array( 'url' => 'https://example.test/a b' ) ) ),
			array( $home, array_merge( $page, array( 'url' => 'https://example.test/a%0ab' ) ) ),
			array( $home, array_merge( $page, array( 'url' => '/relative/' ) ) ),
		) as $items
	) {
		check( false === Breadcrumb_Schema::encode( $items ), '不正な項目があれば経路全体を見送る。' );
	}
}

/**
 * ブロックの表示、描画ごとの取得、出力対象の条件を確認する。
 *
 * @param string $slug テスト用データに使う固有のスラッグ.
 */
function test_rendering( string $slug ): void {
	check( function_exists( 'render_block_core_breadcrumbs' ), 'WordPress 7.0 以降で実行する。' );
	check( is_plugin_active( 'od-breadcrumb-schema/od-breadcrumb-schema.php' ), '先にプラグインを有効化する。' );

	$parent_label = '親カテゴリー ' . $slug;
	$child_label  = '子カテゴリー ' . $slug;
	$parent       = wp_insert_term( $parent_label, 'category' );
	$child        = wp_insert_term( $child_label, 'category', array( 'parent' => $parent['term_id'] ) );
	$post         = wp_insert_post(
		array(
			'post_title'    => 'パンくず ' . $slug,
			'post_status'   => 'publish',
			'post_category' => array( $child['term_id'] ),
			'post_content'  => '前半<!--nextpage-->後半',
		),
		true
	);
	check( is_int( $post ) && $post > 0, '投稿を作成できる。' );

	$markup = '<!-- wp:breadcrumbs /-->';
	$hidden = '<!-- wp:breadcrumbs {"showHomeItem":false,"showCurrentItem":false} /-->';
	query( array( 'p' => $post ) );
	$base = do_blocks( $markup );
	do_action( 'wp', $GLOBALS['wp'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress のフロント描画前のフックを再現する。

	$first = do_blocks( $markup );
	$list  = trails( $first )[0];
	check( str_starts_with( $first, $base . '<script' ), 'コアの HTML の直後に JSON-LD が付く。' );
	check(
		array( 'Home', $parent_label, $child_label, 'パンくず ' . $slug ) === array_column( $list, 'name' ),
		'コアのカテゴリー階層と投稿タイトルを表示順に使う。'
	);
	check(
		array( home_url( '/' ), get_category_link( $parent['term_id'] ), get_category_link( $child['term_id'] ) ) === array_column( $list, 'item' ),
		'各上位項目のリンクが一致し、現在項目には URL がない。'
	);
	check( do_blocks( $markup ) === $base, '同じ経路を再描画しても重複出力しない。' );
	check( array() === trails( do_blocks( '<!-- wp:paragraph --><p>本文</p><!-- /wp:paragraph -->' ) ), 'ブロックがない場合は出力しない。' );

	$list = trails( do_blocks( $hidden ) )[0];
	check( 2 === count( $list ) && get_category_link( $child['term_id'] ) === $list[1]['item'], 'ホームと現在項目の非表示、末尾の上位リンクを反映する。' );

	$inside = false;
	$change = static function ( $items ) use ( $markup, &$inside ) {
		if ( $inside ) {
			return $items;
		}

		$inside = true;
		check( array() === trails( do_blocks( $markup ) ), '入れ子の描画は既出の別経路を再出力しない。' );
		$inside = false;

		$items[0]['label']      = '<strong>独自 &amp; 案内</strong>';
		$items[0]['allow_html'] = true;
		return $items;
	};
	add_filter( 'block_core_breadcrumbs_items', $change, 999 );
	$styled = '<!-- wp:breadcrumbs {"style":{"elements":{"link":{"color":{"text":"#123456"}}}}} /-->';
	$html   = do_blocks( $styled );
	$list   = trails( $html )[0];
	check( str_contains( $html, 'wp-elements-' ), 'コアが適用したリンク色のクラスを維持する。' );
	check( '独自 & 案内' === $list[0]['name'], '別の描画の経路と混同せず、後段の項目変更を反映する。' );
	remove_filter( 'block_core_breadcrumbs_items', $change, 999 );

	$invalid = static function ( $items ) {
		$items[0]['url'] = '/relative/';
		return $items;
	};
	add_filter( 'block_core_breadcrumbs_items', $invalid );
	$html = do_blocks( $markup );
	check( str_contains( $html, 'href="/relative/"' ) && array() === trails( $html ), '相対 URL を含むコアの表示を保ち、経路の出力を見送る。' );
	remove_filter( 'block_core_breadcrumbs_items', $invalid );

	$one = static fn( $items ) => array_slice( $items, 0, 1 );
	add_filter( 'block_core_breadcrumbs_items', $one );
	check( array() === trails( do_blocks( $markup ) ), '1 項目だけの場合は出力しない。' );
	remove_filter( 'block_core_breadcrumbs_items', $one );
	add_filter( 'block_core_breadcrumbs_items', '__return_empty_array' );
	check( '' === do_blocks( $markup ), '項目が空なら HTML と JSON-LD は空。' );
	remove_filter( 'block_core_breadcrumbs_items', '__return_empty_array' );

	query(
		array(
			'p'    => $post,
			'page' => 2,
		)
	);
	add_filter( 'od_breadcrumb_schema_enabled', '__return_false' );
	$html = do_blocks( $markup );
	check( str_contains( $html, block_core_breadcrumbs_create_page_number_item( 'page' )['label'] ) && array() === trails( $html ), '停止フィルターがパンくず表示を維持する。' );
	remove_filter( 'od_breadcrumb_schema_enabled', '__return_false' );

	add_filter( 'render_block_core/breadcrumbs', '__return_empty_string' );
	check( '' === do_blocks( $markup ), '描画フィルターで非表示にした場合は出力しない。' );
	remove_filter( 'render_block_core/breadcrumbs', '__return_empty_string' );

	$list = trails( do_blocks( $markup ) )[0];
	check( block_core_breadcrumbs_create_page_number_item( 'page' )['label'] === $list[4]['name'] && get_permalink( $post ) === $list[3]['item'], '分割された投稿のページ番号と現在項目のリンクを維持する。' );

	query( array( 'cat' => $child['term_id'] ) );
	$list = trails( do_blocks( $markup ) )[0];
	check( 0 === strcmp( $child_label, end( $list )['name'] ), 'カテゴリーアーカイブを処理する。' );

	query( array( 's' => $slug ) );
	check( is_search() && array() === trails( do_blocks( $markup ) ), '検索結果は対象外。' );

	query( array( 'p' => $post ) );
	$different = static function ( $items ) {
		$items[0]['label'] = 'プレビューと REST の確認';
		return $items;
	};
	add_filter( 'block_core_breadcrumbs_items', $different );
	$GLOBALS['wp_query']->is_preview = true;
	check( array() === trails( do_blocks( $markup ) ), '投稿プレビューは対象外。' );
	$GLOBALS['wp_query']->is_preview = false;

	add_action(
		'rest_api_init',
		static function () use ( $markup ) {
			register_rest_route(
				'od-breadcrumb-schema-tests/v1',
				'/render',
				array(
					'methods'             => 'GET',
					'callback'            => static fn() => do_blocks( $markup ),
					'permission_callback' => '__return_true',
				)
			);
		}
	);
	$response = rest_do_request( '/od-breadcrumb-schema-tests/v1/render' );
	check( 200 === $response->get_status() && str_contains( $response->get_data(), '<nav' ) && array() === trails( $response->get_data() ), 'REST での描画は対象外。' );
	remove_filter( 'block_core_breadcrumbs_items', $different );
}

global $wpdb;
$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
try {
	set_error_handler(
		static function ( int $level, string $message, string $file, int $line ): bool {
			if ( ! ( error_reporting() & $level ) ) {
				return false;
			}
			throw new \ErrorException( $message, 0, $level, $file, $line );
		},
		E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE
	);
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	test_encoding();
	test_rendering( 'od-breadcrumb-test-' . wp_generate_password( 8, false ) );
} finally {
	restore_error_handler();
	$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

echo "JSON-LD の生成と WordPress の描画テストが成功しました。\n";
