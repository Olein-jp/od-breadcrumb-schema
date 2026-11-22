<?php
/**
 * コアブロックの表示項目から構造化データを作る。
 *
 * @package OD_Breadcrumb_Schema
 */

namespace OD_Breadcrumb_Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ブロックインスタンス単位で項目を取得し、描画結果に JSON-LD を付ける。
 */
final class Breadcrumb_Schema {

	/**
	 * 描画フィルターをまだ通過していない結果。キーはブロックインスタンス。
	 *
	 * @var \WeakMap
	 */
	private \WeakMap $pending;

	/**
	 * このリクエストですでに出力した JSON-LD。
	 *
	 * @var array<string, bool>
	 */
	private array $emitted = array();

	/**
	 * ブロックが破棄されたら未使用の描画結果も破棄する。
	 */
	public function __construct() {
		$this->pending = new \WeakMap();
	}

	/**
	 * 対応するコアブロックを取得し、元の描画処理を呼び出すラッパーを登録する。
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'render_block_core_breadcrumbs' ) || ! function_exists( 'wp_is_rest_endpoint' ) || ! $this->supports_request() ) {
			return;
		}

		$type = \WP_Block_Type_Registry::get_instance()->get_registered( 'core/breadcrumbs' );
		if ( ! $type || 'render_block_core_breadcrumbs' !== $type->render_callback || ! is_callable( $type->render_callback ) ) {
			return;
		}

		$type->render_callback = array( $this, 'render' );
		add_filter( 'render_block_core/breadcrumbs', array( $this, 'append_schema' ), PHP_INT_MAX, 3 );
	}

	/**
	 * コアの描画を呼び出している間だけ、その経路の項目を取得する。
	 *
	 * @param array     $attributes ブロック属性.
	 * @param string    $content    ブロックの内容.
	 * @param \WP_Block $block      ブロックインスタンス.
	 * @return string
	 */
	public function render( array $attributes, string $content, \WP_Block $block ): string {
		$items   = null;
		$capture = static function ( $value ) use ( &$items ) {
			$items = $value;
			return $value;
		};

		add_filter( 'block_core_breadcrumbs_items', $capture, PHP_INT_MAX );
		try {
			$html = render_block_core_breadcrumbs( $attributes, $content, $block );
		} finally {
			remove_filter( 'block_core_breadcrumbs_items', $capture, PHP_INT_MAX );
		}

		// 同じインスタンスを描画フィルター内で再描画する場合にも、内側から処理する.
		$pending   = $this->pending[ $block ] ?? array();
		$pending[] = array( $html, $items );

		$this->pending[ $block ] = $pending;

		return $html;
	}

	/**
	 * 描画結果が残っていれば、対応する JSON-LD を追記する。
	 *
	 * @param string    $content 描画済みの内容.
	 * @param array     $parsed  パース済みのブロック.
	 * @param \WP_Block $block   ブロックインスタンス.
	 * @return string
	 */
	public function append_schema( string $content, array $parsed, \WP_Block $block ): string {
		if ( ! isset( $this->pending[ $block ] ) ) {
			return $content;
		}

		$pending              = $this->pending[ $block ];
		list( $html, $items ) = array_pop( $pending );
		if ( $pending ) {
			$this->pending[ $block ] = $pending;
		} else {
			unset( $this->pending[ $block ] );
		}

		// コアのスタイル処理では nav の属性が後から追加されることがある.
		$list_html = strstr( $html, '><ol>' );
		if ( false === $list_html || ! str_contains( $content, $list_html ) || ! $this->supports_request() ) {
			return $content;
		}

		/**
		 * パンくずの構造化データを出力するかどうかを変更する。
		 *
		 * @since 0.1.0
		 *
		 * @param bool $enabled 出力を有効にするか。既定値は true.
		 */
		if ( ! apply_filters( 'od_breadcrumb_schema_enabled', true ) ) {
			return $content;
		}

		$json = self::encode( $items );
		if ( false === $json || isset( $this->emitted[ $json ] ) ) {
			return $content;
		}

		$this->emitted[ $json ] = true;

		return $content . '<script type="application/ld+json" data-od-breadcrumb-schema>' . $json . '</script>';
	}

	/**
	 * 公開フロントエンドの対象リクエストか確認する。
	 *
	 * @return bool
	 */
	private function supports_request(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_is_rest_endpoint() || ( defined( 'WP_CLI' ) && WP_CLI ) ||
			is_feed() || is_preview() || is_customize_preview() || is_search() || is_404() || is_attachment() ) {
			return false;
		}

		if ( is_singular() ) {
			$post_type = get_post_type_object( get_post_type( get_queried_object() ) );
			return $post_type && is_post_type_viewable( $post_type );
		}

		return is_home() || is_archive() || is_front_page();
	}

	/**
	 * 経路全体を検証し、安全に埋め込める JSON に変換する。
	 *
	 * @param mixed $items コアの項目フィルターが返した値.
	 * @return string|false
	 */
	public static function encode( $items ): string|false {
		if ( ! is_array( $items ) || count( $items ) < 2 ) {
			return false;
		}

		$list = array();
		$last = count( $items );
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! is_string( $item['label'] ?? null ) ) {
				return false;
			}

			$label = empty( $item['allow_html'] ) ? $item['label'] : wp_kses_post( $item['label'] );
			$name  = html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$name  = preg_replace( '/[\p{Z}\t\n\f\r ]+/u', ' ', $name );
			if ( null === $name || '' === trim( $name ) ) {
				return false;
			}

			$entry = array(
				'@type'    => 'ListItem',
				'position' => count( $list ) + 1,
				'name'     => trim( $name ),
			);

			$url = array_key_exists( 'url', $item ) ? $item['url'] : '';
			if ( '' !== $url ) {
				if ( ! is_string( $url ) || ! preg_match( '~^https?://~i', $url ) ) {
					return false;
				}
				$link = html_entity_decode( esc_url( $url, array( 'http', 'https' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) !== $link ||
					false === filter_var( $link, FILTER_VALIDATE_URL ) ||
					preg_match( '/[\x00-\x20\x22\x3c\x3e\x5c\x5e\x60\x7b-\x7d\x7f]|%(?![a-f0-9]{2})/i', $link ) ) {
					return false;
				}
				$entry['item'] = $link;
			} elseif ( $entry['position'] !== $last ) {
				return false;
			}

			$list[] = $entry;
		}

		return wp_json_encode(
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $list,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
		);
	}
}
