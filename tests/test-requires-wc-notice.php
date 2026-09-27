<?php
/**
 * Tests for the notice shown when WooCommerce is not active (ormm_admin_notice_requires_wc() in inc/func.php).
 * WooCommerce が有効でないときの通知（inc/func.php の ormm_admin_notice_requires_wc()）のテスト。
 *
 * @package etbs-order-note-templates
 */

/**
 * Covers which screens and users get the notice.
 * どの画面・どのユーザーに通知を出すかを確かめる。
 */
class Test_Ormm_Requires_Wc_Notice extends WP_UnitTestCase {

	/**
	 * Restores the screen and the current user after each test.
	 * 各テストの後に、画面と現在のユーザーを元に戻す。
	 *
	 * @return void
	 */
	public function tear_down() {
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Tests ormm_admin_notice_requires_wc().
	 * The scratch site has no WooCommerce, so the notice is due whenever the screen and the user allow it.
	 * 使い捨てサイトには WooCommerce が無いため、画面とユーザーの条件を満たせば通知が出る状態になっている。
	 *
	 * @return void
	 */
	public function test_ormm_admin_notice_requires_wc() {
		// Guard the premise: this test means nothing if WooCommerce is loaded. / 前提の確認：WooCommerce が読み込まれていると意味をなさない.
		$this->assertFalse( class_exists( 'WooCommerce' ), 'WooCommerce が読み込まれていない前提' );

		$admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$test_cases = array(
			array(
				'test_condition_name' => 'プラグイン一覧・管理者 => 出す',
				'user_id'             => $admin_id,
				'screen'              => 'plugins',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ダッシュボード・管理者 => 出さない（全画面には出さない）',
				'user_id'             => $admin_id,
				'screen'              => 'dashboard',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'テンプレート一覧・管理者 => 出さない',
				'user_id'             => $admin_id,
				'screen'              => 'edit-ormm_template',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'プラグイン一覧・プラグインを有効化できない権限 => 出さない',
				'user_id'             => $editor_id,
				'screen'              => 'plugins',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			wp_set_current_user( $case['user_id'] );
			set_current_screen( $case['screen'] );

			// Capture what the notice prints. / 通知の出力を受け取る.
			ob_start();
			ormm_admin_notice_requires_wc();
			$output = (string) ob_get_clean();

			if ( $case['expected'] ) {
				$this->assertStringContainsString( 'notice-error', $output, $case['test_condition_name'] );
				$this->assertStringContainsString( 'requires WooCommerce', $output, $case['test_condition_name'] . '（文言）' );
			} else {
				$this->assertSame( '', $output, $case['test_condition_name'] );
			}
		}
	}
}
