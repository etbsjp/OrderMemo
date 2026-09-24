<?php
/**
 * PHPUnit bootstrap for ETBS Order Note Templates for WooCommerce.
 * ETBS Order Note Templates for WooCommerce の PHPUnit ブートストラップ。
 *
 * The WordPress test suite location is read from the WP_TESTS_DIR environment variable, and its
 * database settings from the wp-tests-config.php that suite is pointed at (WP_PHPUNIT__TESTS_CONFIG
 * when wp-phpunit is used). Neither belongs to this repository: the test suite DROPs and re-creates
 * every table in the database it connects to, so point it at a scratch database, never at a site.
 * See the "PHPUnit" section of CLAUDE.md for the procedure.
 * WordPress テストスイートの場所は環境変数 WP_TESTS_DIR から、DB の設定はそのスイートが指す
 * wp-tests-config.php（wp-phpunit なら WP_PHPUNIT__TESTS_CONFIG）から読む。どちらもこのリポジトリには
 * 置かない。テストスイートは接続先 DB のテーブルを削除して作り直すため、必ず使い捨ての DB を指すこと。
 * 実サイトの DB には絶対に向けない。手順は CLAUDE.md の「PHPUnit」節を参照。
 *
 * @package etbs-order-note-templates
 */

$etbs_ont_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $etbs_ont_tests_dir || ! is_readable( $etbs_ont_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WP_TESTS_DIR is not set, or does not point at a WordPress test suite (includes/functions.php not found).\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

require_once $etbs_ont_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin before WordPress finishes booting.
 * WordPress の起動が終わる前に、プラグインを読み込む。
 *
 * @return void
 */
function etbs_ont_tests_load_plugin() {
	require dirname( __DIR__ ) . '/etbs-order-note-templates.php';
}
tests_add_filter( 'muplugins_loaded', 'etbs_ont_tests_load_plugin' );

// Installs WordPress into the scratch database, then loads it.
// 使い捨て DB に WordPress をインストールしてから読み込む.
require $etbs_ont_tests_dir . '/includes/bootstrap.php';
