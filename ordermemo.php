<?php
/**
 * Plugin Name:       ETBS OrderMemo
 * Description:       WooCommerceの注文編集画面にテンプレート挿入機能を追加し、定型文（差し込みタグ対応）をワンクリックで注文メモに入力できるプラグイン
 * Version:           1.0.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            ETBS (DAI)
 * Author URI:        https://etbs.jp
 * Plugin URI:        https://etbs.jp/product-category/wordpress-tools/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ordermemo
 *
 * @package ordermemo
 */

define( 'ORMM_VERSION', '1.0.5' );
define( 'ORMM_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once( dirname( __FILE__ ) . '/inc/func.php' );

// 公式版（WordPress.org）への入れ替え案内。機能には関わらない。
require_once __DIR__ . '/inc/migration-notice.php';

/*-------------------------------------------*/
/*  プラグインのアップデートチェック
/*  ★ 公式版（WordPress.org）はフォルダ名が違うため、WordPress 本体はこの版を更新できない。
/*     この経路は、入れ替えの案内を届けるためと、旧スラッグを第三者に取られたときに
/*     その更新を受け取らないために残している。外さないこと。
/*-------------------------------------------*/
require 'inc/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/etbsjp/ordermemo/',
	__FILE__,
	'ordermemo'
);
$myUpdateChecker->setBranch( 'dist' );
