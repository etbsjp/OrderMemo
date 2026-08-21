<?php
/**
 * Plugin Name:       OrderMemo
 * Description:       WooCommerceの注文編集画面にテンプレート挿入機能を追加し、定型文（差し込みタグ対応）をワンクリックで注文メモに入力できるプラグイン
 * Version:           1.0.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            DAI
 * Author URI:        https://etbs.jp
 * Plugin URI:        https://etbs.jp/product-category/wordpress-tools/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ordermemo
 *
 * @package ordermemo
 */

define( 'ORMM_VERSION', '1.0.2' );
define( 'ORMM_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once( dirname( __FILE__ ) . '/inc/func.php' );

/*-------------------------------------------*/
/*  プラグインのアップデートチェック
/*-------------------------------------------*/
require 'inc/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/etbsjp/ordermemo/',
	__FILE__,
	'ordermemo'
);
$myUpdateChecker->setBranch( 'dist' );
