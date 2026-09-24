<?php
/**
 * Plugin Name:       ETBS Order Note Templates for WooCommerce
 * Description:       Adds a template picker to the WooCommerce order edit screen, so you can put reusable notes (with placeholders) into an order note in one click.
 * Version:           1.0.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            ETBS (DAI)
 * Author URI:        https://etbs.jp
 * Plugin URI:        https://etbs.jp/product-category/wordpress-tools/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       etbs-order-note-templates
 * Domain Path:       /languages
 *
 * @package etbs-order-note-templates
 */

// Exit if accessed directly. Must come before any executable code, including the defines below.
// 直接アクセスされた場合は終了する。下の define も実行されるコードなので、それより前に置く.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ETBS_ONT_VERSION', '1.0.4' );
define( 'ETBS_ONT_PLUGIN_FILE', __FILE__ );

/*
 * Everything declared above the legacy stand-down below uses the etbs_ont_ / ETBS_ONT_ prefix, not
 * ormm_. The predecessor (ETBS OrderMemo) declares its ormm_ functions itself behind function_exists()
 * guards, and this file is read even while the predecessor is active. WordPress sorts active_plugins,
 * so etbs-order-note-templates/ is read before ordermemo/. A name declared here with the ormm_ prefix
 * would be taken first, and the predecessor's own guard would then skip its declaration without any
 * error. The constants are renamed for the same reason: the predecessor defines its own without a guard.
 *
 * 下の stand-down より前で宣言するものは、ormm_ ではなく etbs_ont_ / ETBS_ONT_ 接頭辞にする。
 * 前身（ETBS OrderMemo）は ormm_ の関数を function_exists() ガード付きで自分で宣言しており、
 * このファイルは前身が有効なときも読み込まれる。WordPress は active_plugins をソートするため
 * etbs-order-note-templates/ は ordermemo/ より先に読まれる。ここで ormm_ 接頭辞の名前を宣言すると
 * 先に取られ、前身側のガードが何のエラーも出さずに宣言をスキップしてしまう。定数を改名したのも
 * 同じ理由で、前身は自分の定数をガード無しで定義している。
 */

/**
 * Loads the translations bundled with this plugin.
 * このプラグインに同梱した翻訳を読み込む。
 *
 * @return void
 */
function etbs_ont_load_textdomain() {
	load_plugin_textdomain(
		'etbs-order-note-templates',
		false,
		dirname( plugin_basename( ETBS_ONT_PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'etbs_ont_load_textdomain' );

require_once __DIR__ . '/inc/legacy-guard.php';

// Refuse to activate while the predecessor is active. This runs before active_plugins is written.
// 前身が有効なあいだは有効化を拒否する。active_plugins に書き込まれる前に走る.
register_activation_hook( __FILE__, 'etbs_ont_block_activation_while_legacy_active' );

// Warn on the predecessor's own row in the plugins list before it can be deleted.
// 前身の行の直下で、削除する前に警告を出す.
add_action( 'after_plugin_row_' . ETBS_ONT_LEGACY_PLUGIN, 'etbs_ont_render_legacy_plugin_row', 10, 3 );

/*
 * Stand down while the predecessor is still active. It registers the same post type and the same
 * hooks, so running both would duplicate them. Say why on screen instead of failing quietly.
 * This covers what the activation check cannot: a multisite network activation, WP-CLI, and the
 * predecessor being activated after this plugin.
 *
 * 前身が有効なあいだは動かない。前身は同じ投稿タイプと同じフックを登録するため、両方が動くと
 * 重複する。黙って壊れるのではなく、理由を画面に出す。
 * 有効化時の拒否では拾えないもの（マルチサイトのネットワーク有効化・WP-CLI・
 * 前身を後から有効化したとき）をここで受ける。
 */
if ( etbs_ont_is_legacy_active() ) {
	add_action( 'admin_notices', 'etbs_ont_render_legacy_notice' );
	add_action( 'network_admin_notices', 'etbs_ont_render_legacy_notice' );
	return;
}

require_once __DIR__ . '/inc/func.php';
