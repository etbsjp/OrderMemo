<?php
/**
 * Tests for the guard that keeps this plugin and its predecessor apart (inc/legacy-guard.php).
 * このプラグインと前身を同時に動かさないためのガード（inc/legacy-guard.php）のテスト。
 *
 * @package etbs-order-note-templates
 */

/**
 * Covers detection of the predecessor, the activation refusal and the notices.
 * 前身の検出、有効化の拒否、各種の通知を確かめる。
 */
class Test_Etbs_Ont_Legacy_Guard extends WP_UnitTestCase {

	/**
	 * Basename of this plugin's own main file, as WordPress records it in active_plugins.
	 * このプラグイン自身のメインファイル。WordPress が active_plugins に記録する形式。
	 */
	const OWN_PLUGIN = 'etbs-order-note-templates/etbs-order-note-templates.php';

	/**
	 * Temporary plugin files created by a test, removed in tear_down().
	 * テストが作った一時的なプラグインファイル。tear_down() で消す。
	 *
	 * @var string[]
	 */
	private $temp_files = array();

	/**
	 * Restores the screen, the current user and the temporary files after each test.
	 * 各テストの後に、画面・現在のユーザー・一時ファイルを元に戻す。
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- A temporary file this test created.
			}
		}
		$this->temp_files = array();
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Writes a temporary file that has a plugin header.
	 * プラグインヘッダを持つ一時ファイルを書く。
	 *
	 * @param string $header_lines Header lines, such as "Version: 1.0.2".
	 * @return string Absolute path of the file.
	 */
	private function make_plugin_file( $header_lines ) {
		$file = tempnam( sys_get_temp_dir(), 'etbs-ont-test-' );
		file_put_contents( $file, "<?php\n/**\n * " . str_replace( "\n", "\n * ", $header_lines ) . "\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary file this test created.
		$this->temp_files[] = $file;
		return $file;
	}

	/**
	 * Runs a callback and returns what it printed.
	 * コールバックを実行し、出力された内容を返す。
	 *
	 * @param callable $callback Callback to run.
	 * @return string Printed output.
	 */
	private function capture_output( $callback ) {
		ob_start();
		call_user_func( $callback );
		return (string) ob_get_clean();
	}

	/**
	 * Tests etbs_ont_find_legacy_plugin().
	 *
	 * @return void
	 */
	public function test_etbs_ont_find_legacy_plugin() {
		$test_cases = array(
			array(
				'test_condition_name' => '標準のフォルダ名（ordermemo/ordermemo.php）で有効な場合 => そのパス',
				'plugins'             => array( 'ordermemo/ordermemo.php' ),
				'expected'            => 'ordermemo/ordermemo.php',
			),
			array(
				'test_condition_name' => 'フォルダ名を変えて設置された場合 => そのパス（末尾の /ordermemo.php を見る枝）',
				'plugins'             => array( 'ordermemo-renamed/ordermemo.php' ),
				'expected'            => 'ordermemo-renamed/ordermemo.php',
			),
			array(
				'test_condition_name' => '他のプラグインに混じって有効な場合 => 前身のパス',
				'plugins'             => array( 'akismet/akismet.php', 'my-copy/ordermemo.php', 'hello-dolly/hello.php' ),
				'expected'            => 'my-copy/ordermemo.php',
			),
			array(
				'test_condition_name' => 'このプラグイン自身だけが有効な場合 => 空（自分自身に誤一致しない）',
				'plugins'             => array( self::OWN_PLUGIN ),
				'expected'            => '',
			),
			array(
				'test_condition_name' => 'フォルダ名が ordermemo でも、メインファイルがこのプラグインのものの場合 => 空',
				'plugins'             => array( 'ordermemo/etbs-order-note-templates.php' ),
				'expected'            => '',
			),
			array(
				'test_condition_name' => 'このプラグインと前身が両方有効な場合 => 前身のパスだけ',
				'plugins'             => array( self::OWN_PLUGIN, 'ordermemo/ordermemo.php' ),
				'expected'            => 'ordermemo/ordermemo.php',
			),
			array(
				'test_condition_name' => 'ファイル名が ordermemo.php で終わるだけで区切りの / が無い場合 => 空',
				'plugins'             => array( 'not-ordermemo.php' ),
				'expected'            => '',
			),
			array(
				'test_condition_name' => '別のプラグインのファイル名が ordermemo で始まるだけの場合 => 空',
				'plugins'             => array( 'ordermemo-pro/ordermemo-pro.php' ),
				'expected'            => '',
			),
			array(
				'test_condition_name' => '有効なプラグインが無い場合 => 空',
				'plugins'             => array(),
				'expected'            => '',
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], etbs_ont_find_legacy_plugin( $case['plugins'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_get_active_plugin_basenames().
	 *
	 * @return void
	 */
	public function test_etbs_ont_get_active_plugin_basenames() {
		$test_cases = array(
			array(
				'test_condition_name' => 'サイトで有効なプラグインがある場合 => active_plugins の値をそのまま返す',
				'active_plugins'      => array( 'akismet/akismet.php', 'ordermemo/ordermemo.php' ),
				'sitewide_plugins'    => array(),
				'expected'            => array( 'akismet/akismet.php', 'ordermemo/ordermemo.php' ),
			),
			array(
				'test_condition_name' => '有効なプラグインが無い場合 => 空の配列',
				'active_plugins'      => array(),
				'sitewide_plugins'    => array(),
				'expected'            => array(),
			),
		);

		if ( is_multisite() ) {
			$test_cases[] = array(
				'test_condition_name' => 'マルチサイトでネットワーク有効化されたプラグインがある場合 => キーも含めて返す',
				'active_plugins'      => array( 'akismet/akismet.php' ),
				'sitewide_plugins'    => array( 'ordermemo/ordermemo.php' => 1700000000 ),
				'expected'            => array( 'akismet/akismet.php', 'ordermemo/ordermemo.php' ),
			);
		}

		foreach ( $test_cases as $case ) {
			update_option( 'active_plugins', $case['active_plugins'] );
			if ( is_multisite() ) {
				update_site_option( 'active_sitewide_plugins', $case['sitewide_plugins'] );
			}

			$this->assertSame( $case['expected'], etbs_ont_get_active_plugin_basenames(), $case['test_condition_name'] );
		}

		if ( is_multisite() ) {
			delete_site_option( 'active_sitewide_plugins' );
		}
	}

	/**
	 * Tests etbs_ont_get_active_legacy_plugin().
	 *
	 * @return void
	 */
	public function test_etbs_ont_get_active_legacy_plugin() {
		$test_cases = array(
			array(
				'test_condition_name' => '前身が有効な場合 => 前身のパス',
				'active_plugins'      => array( 'ordermemo/ordermemo.php' ),
				'expected'            => 'ordermemo/ordermemo.php',
			),
			array(
				'test_condition_name' => 'このプラグインだけが有効な場合 => 空',
				'active_plugins'      => array( self::OWN_PLUGIN ),
				'expected'            => '',
			),
		);

		foreach ( $test_cases as $case ) {
			update_option( 'active_plugins', $case['active_plugins'] );

			$this->assertSame( $case['expected'], etbs_ont_get_active_legacy_plugin(), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_is_legacy_active().
	 *
	 * @return void
	 */
	public function test_etbs_ont_is_legacy_active() {
		$test_cases = array(
			array(
				'test_condition_name' => '前身が有効な場合 => true',
				'active_plugins'      => array( 'ordermemo/ordermemo.php' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '前身とこのプラグインが両方有効な場合 => true',
				'active_plugins'      => array( self::OWN_PLUGIN, 'ordermemo/ordermemo.php' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'このプラグイン自身だけが有効な場合 => false',
				'active_plugins'      => array( self::OWN_PLUGIN ),
				'expected'            => false,
			),
			array(
				'test_condition_name' => '有効なプラグインが無い場合 => false',
				'active_plugins'      => array(),
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			update_option( 'active_plugins', $case['active_plugins'] );

			$this->assertSame( $case['expected'], etbs_ont_is_legacy_active(), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_get_plugin_details().
	 *
	 * @return void
	 */
	public function test_etbs_ont_get_plugin_details() {
		$test_cases = array(
			array(
				'test_condition_name' => '名前と版数を持つヘッダの場合 => その値',
				'plugin_file'         => $this->make_plugin_file( "Plugin Name: ETBS OrderMemo\nVersion: 1.0.2" ),
				'expected'            => array(
					'name'    => 'ETBS OrderMemo',
					'version' => '1.0.2',
				),
			),
			array(
				'test_condition_name' => '版数の無いヘッダの場合 => 名前はそのまま、版数は空',
				'plugin_file'         => $this->make_plugin_file( 'Plugin Name: OrderMemo' ),
				'expected'            => array(
					'name'    => 'OrderMemo',
					'version' => '',
				),
			),
			array(
				'test_condition_name' => '名前の無いヘッダの場合 => 名前は OrderMemo に戻す',
				'plugin_file'         => $this->make_plugin_file( 'Version: 1.0.4' ),
				'expected'            => array(
					'name'    => 'OrderMemo',
					'version' => '1.0.4',
				),
			),
			array(
				'test_condition_name' => 'ファイルが無い場合 => 名前は OrderMemo、版数は空',
				'plugin_file'         => sys_get_temp_dir() . '/etbs-ont-no-such-plugin.php',
				'expected'            => array(
					'name'    => 'OrderMemo',
					'version' => '',
				),
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], etbs_ont_get_plugin_details( $case['plugin_file'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_legacy_version_deletes_templates().
	 *
	 * @return void
	 */
	public function test_etbs_ont_legacy_version_deletes_templates() {
		$test_cases = array(
			array(
				'test_condition_name' => '1.0.2（削除でテンプレートを全件消していた最後の版） => true',
				'version'             => '1.0.2',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '1.0.0 => true',
				'version'             => '1.0.0',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '版数が読み取れない場合 => true（警告を出す側に倒す）',
				'version'             => '',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '1.0.3（撤去済みの最初の版） => false',
				'version'             => '1.0.3',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '1.0.4 => false',
				'version'             => '1.0.4',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '1.0.10（文字列比較なら 1.0.3 より小さくなる版数） => false',
				'version'             => '1.0.10',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '1.1.0 => false',
				'version'             => '1.1.0',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], etbs_ont_legacy_version_deletes_templates( $case['version'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_get_legacy_deletion_warning().
	 *
	 * @return void
	 */
	public function test_etbs_ont_get_legacy_deletion_warning() {
		$test_cases = array(
			array(
				'test_condition_name' => '通常の名前の場合 => 1.0.3 以降へ先に更新する旨と、全件消える旨の2文',
				'plugin_name'         => 'ETBS OrderMemo',
				'expected'            => array(
					'If you delete <strong>ETBS OrderMemo</strong>, update it to version 1.0.3 or later first.',
					'Earlier versions delete all of your templates when the plugin is deleted.',
				),
			),
			array(
				'test_condition_name' => '名前に HTML が含まれる場合 => エスケープして出す',
				'plugin_name'         => '<script>alert(1)</script>',
				'expected'            => array(
					'If you delete <strong>&lt;script&gt;alert(1)&lt;/script&gt;</strong>, update it to version 1.0.3 or later first.',
					'Earlier versions delete all of your templates when the plugin is deleted.',
				),
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], etbs_ont_get_legacy_deletion_warning( $case['plugin_name'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_get_legacy_guidance().
	 *
	 * @return void
	 */
	public function test_etbs_ont_get_legacy_guidance() {
		$without_warning = etbs_ont_get_legacy_guidance( 'ETBS OrderMemo', false );
		$with_warning    = etbs_ont_get_legacy_guidance( 'ETBS OrderMemo', true );

		$test_cases = array(
			array(
				'test_condition_name' => '削除の警告が要らない場合 => 段落は2つ',
				'actual'              => count( $without_warning ),
				'expected'            => 2,
			),
			array(
				'test_condition_name' => '削除の警告が要らない場合 => 無効化を勧める',
				'actual'              => false !== strpos( implode( ' ', $without_warning ), 'Deactivate <strong>ETBS OrderMemo</strong> to switch over.' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '削除の警告が要らない場合 => 「削除」を勧める文は含まない',
				'actual'              => false !== stripos( implode( ' ', $without_warning ), 'delete' ),
				'expected'            => false,
			),
			array(
				'test_condition_name' => '削除の警告が要らない場合 => テンプレートが残る旨を含む',
				'actual'              => false !== strpos( implode( ' ', $without_warning ), 'Your saved templates are kept.' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '削除の警告が要る場合 => 段落は3つ',
				'actual'              => count( $with_warning ),
				'expected'            => 3,
			),
			array(
				'test_condition_name' => '削除の警告が要る場合 => 3つ目が削除の警告',
				'actual'              => $with_warning[2],
				'expected'            => implode( ' ', etbs_ont_get_legacy_deletion_warning( 'ETBS OrderMemo' ) ),
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_get_legacy_deactivate_url().
	 *
	 * @return void
	 */
	public function test_etbs_ont_get_legacy_deactivate_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$url = etbs_ont_get_legacy_deactivate_url( 'ordermemo/ordermemo.php' );
		$this->assertStringStartsWith( admin_url( 'plugins.php' ), $url, 'プラグイン一覧の URL から始まる' );

		// Read the query string back the way WordPress does, after the HTML entities are decoded.
		// WordPress が読むのと同じ形で、HTML 実体を戻してからクエリ文字列を読み直す.
		$query = array();
		wp_parse_str( html_entity_decode( (string) wp_parse_url( $url, PHP_URL_QUERY ) ), $query );

		$test_cases = array(
			array(
				'test_condition_name' => 'action は deactivate',
				'actual'              => $query['action'] ?? null,
				'expected'            => 'deactivate',
			),
			array(
				'test_condition_name' => 'plugin は前身の basename',
				'actual'              => $query['plugin'] ?? null,
				'expected'            => 'ordermemo/ordermemo.php',
			),
			array(
				'test_condition_name' => 'nonce は WordPress 本体の無効化リンクと同じ action で検証を通る',
				'actual'              => wp_verify_nonce( $query['_wpnonce'] ?? '', 'deactivate-plugin_ordermemo/ordermemo.php' ),
				'expected'            => 1,
			),
			array(
				'test_condition_name' => '別のプラグインの action の nonce としては検証を通らない',
				'actual'              => wp_verify_nonce( $query['_wpnonce'] ?? '', 'deactivate-plugin_akismet/akismet.php' ),
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_block_activation_while_legacy_active().
	 *
	 * @return void
	 */
	public function test_etbs_ont_block_activation_while_legacy_active() {
		$test_cases = array(
			array(
				'test_condition_name' => '前身が有効な場合 => wp_die で止める',
				'active_plugins'      => array( 'akismet/akismet.php', 'ordermemo/ordermemo.php' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'フォルダ名を変えた前身が有効な場合 => wp_die で止める',
				'active_plugins'      => array( 'ordermemo-renamed/ordermemo.php' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '前身が有効でない場合 => 止めない',
				'active_plugins'      => array( 'akismet/akismet.php' ),
				'expected'            => false,
			),
			array(
				'test_condition_name' => '有効なプラグインが無い場合 => 止めない',
				'active_plugins'      => array(),
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			update_option( 'active_plugins', $case['active_plugins'] );

			$stopped = false;
			$message = '';
			$status  = null;
			try {
				etbs_ont_block_activation_while_legacy_active();
			} catch ( WPDieException $e ) {
				$stopped = true;
				$message = $e->getMessage();
				$status  = $e->getCode();
			}

			$this->assertSame( $case['expected'], $stopped, $case['test_condition_name'] );

			// Stopping must not touch active_plugins: WordPress writes it only after this hook returns.
			// 止めても active_plugins には触らない。WordPress がこれを書くのは、このフックが戻った後.
			$this->assertSame( $case['active_plugins'], get_option( 'active_plugins' ), $case['test_condition_name'] . '（active_plugins は変わらない）' );

			if ( $case['expected'] ) {
				$this->assertSame( 409, $status, $case['test_condition_name'] . '（HTTP ステータス）' );
				$this->assertStringContainsString( 'was not activated', $message, $case['test_condition_name'] . '（理由）' );
				$this->assertStringContainsString( 'action=deactivate', $message, $case['test_condition_name'] . '（無効化リンク）' );
				$this->assertStringContainsString( admin_url( 'plugins.php' ) . '">Back to Plugins', $message, $case['test_condition_name'] . '（プラグイン一覧へ戻るリンク）' );
				// The predecessor's file is not on disk in the test site, so its version is unknown and the warning is shown.
				// テスト用サイトに前身のファイルは無く、版数が読めないため、警告は出る.
				$this->assertStringContainsString( 'update it to version 1.0.3 or later first', $message, $case['test_condition_name'] . '（削除の警告）' );
			}
		}
	}

	/**
	 * Tests etbs_ont_is_legacy_notice_screen().
	 *
	 * @return void
	 */
	public function test_etbs_ont_is_legacy_notice_screen() {
		$test_cases = array(
			array(
				'test_condition_name' => 'プラグイン一覧 => true',
				'screen_id'           => 'plugins',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ネットワーク管理画面のプラグイン一覧 => true',
				'screen_id'           => 'plugins-network',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'テンプレートの一覧 => true',
				'screen_id'           => 'edit-ormm_template',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'テンプレートの編集 => true',
				'screen_id'           => 'ormm_template',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ダッシュボード => false',
				'screen_id'           => 'dashboard',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '投稿の一覧 => false',
				'screen_id'           => 'edit-post',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '注文の一覧 => false',
				'screen_id'           => 'woocommerce_page_wc-orders',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '空の ID => false',
				'screen_id'           => '',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], etbs_ont_is_legacy_notice_screen( $case['screen_id'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * Tests etbs_ont_render_legacy_notice().
	 *
	 * @return void
	 */
	public function test_etbs_ont_render_legacy_notice() {
		$admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$test_cases = array(
			array(
				'test_condition_name' => '管理者がプラグイン一覧を開き、前身が有効な場合 => 通知を出す',
				'user_id'             => $admin_id,
				'screen'              => 'plugins',
				'active_plugins'      => array( 'ordermemo/ordermemo.php' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '管理者がテンプレートの一覧を開き、前身が有効な場合 => 通知を出す',
				'user_id'             => $admin_id,
				'screen'              => 'edit-ormm_template',
				'active_plugins'      => array( 'ordermemo/ordermemo.php' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '管理者がダッシュボードを開いた場合 => 出さない（全画面には出さない）',
				'user_id'             => $admin_id,
				'screen'              => 'dashboard',
				'active_plugins'      => array( 'ordermemo/ordermemo.php' ),
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'プラグインを有効化できない権限の場合 => 出さない',
				'user_id'             => $editor_id,
				'screen'              => 'plugins',
				'active_plugins'      => array( 'ordermemo/ordermemo.php' ),
				'expected'            => false,
			),
			array(
				'test_condition_name' => '前身が有効でない場合 => 出さない',
				'user_id'             => $admin_id,
				'screen'              => 'plugins',
				'active_plugins'      => array( 'akismet/akismet.php' ),
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			wp_set_current_user( $case['user_id'] );
			set_current_screen( $case['screen'] );
			update_option( 'active_plugins', $case['active_plugins'] );

			$output = $this->capture_output( 'etbs_ont_render_legacy_notice' );

			if ( $case['expected'] ) {
				$this->assertStringContainsString( 'notice-error', $output, $case['test_condition_name'] );
				$this->assertStringContainsString( 'is not running because', $output, $case['test_condition_name'] . '（理由）' );
				$this->assertStringContainsString( 'action=deactivate', $output, $case['test_condition_name'] . '（無効化リンク）' );
			} else {
				$this->assertSame( '', $output, $case['test_condition_name'] );
			}
		}
	}

	/**
	 * Tests etbs_ont_render_legacy_plugin_row().
	 *
	 * @return void
	 */
	public function test_etbs_ont_render_legacy_plugin_row() {
		// The plugins list table class is loaded by the admin, not by the test suite.
		// プラグイン一覧のテーブルクラスは管理画面が読み込むもので、テストスイートは読み込まない.
		require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
		set_current_screen( 'plugins' );

		$test_cases = array(
			array(
				'test_condition_name' => '版数が 1.0.2 の場合 => 警告の行を出す',
				'plugin_data'         => array(
					'Name'    => 'ETBS OrderMemo',
					'Version' => '1.0.2',
				),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '版数が読み取れない場合 => 警告の行を出す',
				'plugin_data'         => array( 'Name' => 'ETBS OrderMemo' ),
				'expected'            => true,
			),
			array(
				'test_condition_name' => '版数が 1.0.3 の場合 => 出さない',
				'plugin_data'         => array(
					'Name'    => 'ETBS OrderMemo',
					'Version' => '1.0.3',
				),
				'expected'            => false,
			),
			array(
				'test_condition_name' => '版数が 1.0.4 の場合 => 出さない',
				'plugin_data'         => array(
					'Name'    => 'ETBS OrderMemo',
					'Version' => '1.0.4',
				),
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$output = $this->capture_output(
				function () use ( $case ) {
					etbs_ont_render_legacy_plugin_row( 'ordermemo/ordermemo.php', $case['plugin_data'], 'inactive' );
				}
			);

			if ( $case['expected'] ) {
				$this->assertStringContainsString( 'plugin-update-tr inactive', $output, $case['test_condition_name'] );
				$this->assertStringContainsString( 'update it to version 1.0.3 or later first', $output, $case['test_condition_name'] . '（警告）' );
			} else {
				$this->assertSame( '', $output, $case['test_condition_name'] );
			}
		}
	}

	/**
	 * Tests that the hooks are registered under the names WordPress looks for.
	 * フックが、WordPress が探す名前で登録されていることを確かめる。
	 *
	 * @return void
	 */
	public function test_hooks_are_registered() {
		$test_cases = array(
			array(
				'test_condition_name' => '有効化フックに拒否の処理が掛かっている',
				'actual'              => has_action( 'activate_' . plugin_basename( ETBS_ONT_PLUGIN_FILE ), 'etbs_ont_block_activation_while_legacy_active' ),
				'expected'            => 10,
			),
			array(
				'test_condition_name' => '前身の行の直下に出す処理が掛かっている',
				'actual'              => has_action( 'after_plugin_row_ordermemo/ordermemo.php', 'etbs_ont_render_legacy_plugin_row' ),
				'expected'            => 10,
			),
			array(
				'test_condition_name' => '翻訳の読み込みが init に掛かっている',
				'actual'              => has_action( 'init', 'etbs_ont_load_textdomain' ),
				'expected'            => 10,
			),
			array(
				'test_condition_name' => '前身が無効のとき、機能側（inc/func.php）が読み込まれている',
				'actual'              => function_exists( 'ormm_register_post_type' ),
				'expected'            => true,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], $case['actual'], $case['test_condition_name'] );
		}
	}
}
