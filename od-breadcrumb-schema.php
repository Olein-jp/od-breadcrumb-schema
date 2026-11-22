<?php
/**
 * Plugin Name: OD Breadcrumb Schema
 * Plugin URI: https://github.com/Olein-jp/od-breadcrumb-schema
 * Description: Development foundation for OD Breadcrumb Schema.
 * Version: 0.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Koji Kuno
 * Author URI: https://olein-design.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: od-breadcrumb-schema
 * Domain Path: /languages
 * Update URI: https://github.com/Olein-jp/od-breadcrumb-schema
 *
 * @package OD_Breadcrumb_Schema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 翻訳と GitHub 更新機能を初期化する。
 *
 * @return void
 */
function od_breadcrumb_schema_init() {
	load_plugin_textdomain( 'od-breadcrumb-schema', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$autoload = __DIR__ . '/vendor/autoload.php';
	if ( ! file_exists( $autoload ) ) {
		add_action( 'admin_notices', 'od_breadcrumb_schema_dependencies_notice' );
		return;
	}

	require_once $autoload;

	new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
		plugin_basename( __FILE__ ),
		'Olein-jp',
		'od-breadcrumb-schema'
	);
}
add_action( 'init', 'od_breadcrumb_schema_init' );

/**
 * 開発用チェックアウトで依存関係が未導入の場合に案内する。
 *
 * @return void
 */
function od_breadcrumb_schema_dependencies_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'OD Breadcrumb Schema requires Composer dependencies. Run composer install or install the ZIP from GitHub Releases.', 'od-breadcrumb-schema' );
	echo '</p></div>';
}
