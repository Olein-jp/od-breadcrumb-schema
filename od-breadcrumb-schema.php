<?php
/**
 * Plugin Name: OD Breadcrumb Schema
 * Plugin URI: https://github.com/Olein-jp/od-breadcrumb-schema
 * Description: WordPress 標準のパンくずブロックに BreadcrumbList の構造化データを追加します。
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Author: Koji Kuno
 * Author URI: https://olein-design.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: od-breadcrumb-schema
 * Update URI: https://github.com/Olein-jp/od-breadcrumb-schema
 *
 * @package OD_Breadcrumb_Schema
 */

namespace OD_Breadcrumb_Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( PHP_VERSION_ID < 80200 ) {
	return;
}

require_once __DIR__ . '/includes/class-breadcrumb-schema.php';

add_action( 'wp', array( new Breadcrumb_Schema(), 'register' ) );
