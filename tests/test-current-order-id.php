<?php
/**
 * Tests for the order edit screen detection (inc/func.php).
 * 注文編集画面の判定（inc/func.php）のテスト。
 *
 * @package etbs-order-note-templates
 */

/**
 * Covers ormm_get_current_order_id().
 * ormm_get_current_order_id() を確かめる。
 */
class Test_Etbs_Ont_Current_Order_Id extends WP_UnitTestCase {

	/**
	 * Registers the shop_order post type when WooCommerce is not loaded (the scratch test site has none).
	 * WooCommerce が読み込まれていないときだけ shop_order 投稿タイプを登録する（使い捨てのテストサイトには無い）。
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		if ( ! post_type_exists( 'shop_order' ) ) {
			register_post_type( 'shop_order' );
		}
	}

	/**
	 * Clears the query string the test set, so the next test starts clean.
	 * テストが入れたクエリ文字列を消し、次のテストに持ち越さない。
	 *
	 * @return void
	 */
	public function tear_down() {
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * ormm_get_current_order_id(): returns the order ID only on the legacy and HPOS order edit screens.
	 * ormm_get_current_order_id()：従来方式と HPOS の注文編集画面でだけ注文 ID を返す。
	 *
	 * @return void
	 */
	public function test_ormm_get_current_order_id() {
		$order_id = self::factory()->post->create( array( 'post_type' => 'shop_order' ) );
		$post_id  = self::factory()->post->create( array( 'post_type' => 'post' ) );

		$test_cases = array(
			array(
				'test_condition_name' => '従来方式の注文編集画面 => 注文 ID',
				'hook'                => 'post.php',
				'get'                 => array( 'post' => (string) $order_id ),
				'expected'            => $order_id,
			),
			array(
				'test_condition_name' => 'post.php でも注文以外の投稿 => 0',
				'hook'                => 'post.php',
				'get'                 => array( 'post' => (string) $post_id ),
				'expected'            => 0,
			),
			array(
				'test_condition_name' => 'post.php で post が無い => 0',
				'hook'                => 'post.php',
				'get'                 => array(),
				'expected'            => 0,
			),
			array(
				'test_condition_name' => 'post.php で post が数字でない => 0',
				'hook'                => 'post.php',
				'get'                 => array( 'post' => 'abc' ),
				'expected'            => 0,
			),
			array(
				'test_condition_name' => 'HPOS の注文編集画面 => 注文 ID',
				'hook'                => 'woocommerce_page_wc-orders',
				'get'                 => array(
					'action' => 'edit',
					'id'     => '123',
				),
				'expected'            => 123,
			),
			array(
				'test_condition_name' => 'HPOS の画面で action が edit 以外（一覧）=> 0',
				'hook'                => 'woocommerce_page_wc-orders',
				'get'                 => array( 'id' => '123' ),
				'expected'            => 0,
			),
			array(
				'test_condition_name' => 'HPOS の編集画面で id が無い => 0',
				'hook'                => 'woocommerce_page_wc-orders',
				'get'                 => array( 'action' => 'edit' ),
				'expected'            => 0,
			),
			array(
				'test_condition_name' => '別の画面 => 0',
				'hook'                => 'edit.php',
				'get'                 => array( 'post' => (string) $order_id ),
				'expected'            => 0,
			),
		);

		foreach ( $test_cases as $case ) {
			// 各ケースのクエリ文字列だけが見える状態にする.
			$_GET = $case['get'];

			$this->assertSame( $case['expected'], ormm_get_current_order_id( $case['hook'] ), $case['test_condition_name'] );
		}
	}
}
