<?php
/**
 * Tests for the notice about the paid version, shown on Japanese sites only (inc/func.php).
 * 日本語サイトだけに出す、有料版の案内（inc/func.php）のテスト。
 *
 * @package etbs-order-note-templates
 */

/**
 * Covers the locale check, the URL, the plugins list row and the paragraph under the template list.
 * ロケールの判定、URL、プラグイン一覧の行、テンプレート一覧の下の段落を確かめる。
 */
class Test_Etbs_Ont_Pro_Promotion extends WP_UnitTestCase {

	/**
	 * Restores the locale, the filter, the screen and the user after each test.
	 * 各テストの後に、ロケール・フィルター・画面・ユーザーを元に戻す。
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'locale' );
		remove_all_filters( 'ormm_show_pro_promotion' );
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Makes get_locale() return the given locale.
	 * get_locale() が指定のロケールを返すようにする。
	 *
	 * @param string $locale Locale such as ja_JP. / ja_JP のようなロケール。
	 * @return void
	 */
	private function set_locale( $locale ) {
		add_filter(
			'locale',
			function () use ( $locale ) {
				return $locale;
			}
		);
	}

	/**
	 * Returns the output of the paragraph under the template list.
	 * テンプレート一覧の下の段落の出力を返す。
	 *
	 * @param string $which Position of the tablenav. / tablenav の位置。
	 * @return string Printed HTML. / 出力された HTML。
	 */
	private function get_paragraph( $which ) {
		ob_start();
		ormm_render_pro_promotion_paragraph( $which );
		return (string) ob_get_clean();
	}

	/**
	 * ormm_is_japanese_site(): true only when the locale starts with "ja".
	 * ormm_is_japanese_site()：ロケールが ja で始まるときだけ true。
	 *
	 * @return void
	 */
	public function test_ormm_is_japanese_site() {
		$test_cases = array(
			array(
				'test_condition_name' => 'ja_JP は日本語サイト',
				'locale'              => 'ja_JP',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ja だけでも日本語サイト',
				'locale'              => 'ja',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'en_US は日本語サイトではない',
				'locale'              => 'en_US',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'ロケールの途中に ja を含むだけ（jv_ID など）でも日本語サイトではない',
				'locale'              => 'jv_ID',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '空のロケールは日本語サイトではない',
				'locale'              => '',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			remove_all_filters( 'locale' );
			$this->set_locale( $case['locale'] );
			$this->assertSame( $case['expected'], ormm_is_japanese_site(), $case['test_condition_name'] );
		}
	}

	/**
	 * ormm_is_pro_promotion_visible(): Japanese site and not hidden by ormm_show_pro_promotion.
	 * ormm_is_pro_promotion_visible()：日本語サイトで、ormm_show_pro_promotion に隠されていない。
	 *
	 * @return void
	 */
	public function test_ormm_is_pro_promotion_visible() {
		$test_cases = array(
			array(
				'test_condition_name' => '日本語サイトでフィルターが無ければ表示',
				'locale'              => 'ja_JP',
				'filter_value'        => null,
				'expected'            => true,
			),
			array(
				'test_condition_name' => '日本語サイトでもフィルターが false なら非表示',
				'locale'              => 'ja_JP',
				'filter_value'        => false,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '英語サイトは非表示',
				'locale'              => 'en_US',
				'filter_value'        => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '英語サイトはフィルターが true でも非表示',
				'locale'              => 'en_US',
				'filter_value'        => true,
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			remove_all_filters( 'locale' );
			remove_all_filters( 'ormm_show_pro_promotion' );
			$this->set_locale( $case['locale'] );
			if ( null !== $case['filter_value'] ) {
				$value = $case['filter_value'];
				add_filter(
					'ormm_show_pro_promotion',
					function () use ( $value ) {
						return $value;
					}
				);
			}
			$this->assertSame( $case['expected'], ormm_is_pro_promotion_visible(), $case['test_condition_name'] );
		}
	}

	/**
	 * ormm_get_pro_promotion_url(): carries the UTM parameters, and utm_content tells the two places apart.
	 * ormm_get_pro_promotion_url()：UTM を付け、utm_content で2か所を区別する。
	 *
	 * @return void
	 */
	public function test_ormm_get_pro_promotion_url() {
		$test_cases = array(
			array(
				'test_condition_name' => 'プラグイン一覧の行は utm_content=plugin-row',
				'content'             => 'plugin-row',
			),
			array(
				'test_condition_name' => 'テンプレート一覧の段落は utm_content=template-list',
				'content'             => 'template-list',
			),
		);

		foreach ( $test_cases as $case ) {
			$url   = ormm_get_pro_promotion_url( $case['content'] );
			$query = array();
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$this->assertSame(
				array(
					'utm_source'   => 'etbs-order-note-templates',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'ordermemo-pro',
					'utm_content'  => $case['content'],
				),
				$query,
				$case['test_condition_name']
			);
			// esc_url() が生の & を &#038; に直すこと（出力側の確認）.
			$this->assertStringContainsString( '&#038;utm_medium=plugin', esc_url( $url ), $case['test_condition_name'] . '（esc_url 後に生の & が残らない）' );
		}
	}

	/**
	 * ormm_plugin_row_meta(): the Pro link only on Japanese sites; "Support development" always; other rows untouched.
	 * ormm_plugin_row_meta()：Pro のリンクは日本語サイトだけ。「開発を支援」は常に。他のプラグインの行は触らない。
	 *
	 * @return void
	 */
	public function test_ormm_plugin_row_meta() {
		$own_file   = plugin_basename( ETBS_ONT_PLUGIN_FILE );
		$test_cases = array(
			array(
				'test_condition_name' => '日本語サイトの自分の行は Pro と開発を支援の2本',
				'locale'              => 'ja_JP',
				'filter_value'        => null,
				'file'                => $own_file,
				'expected_count'      => 2,
				'expected_pro'        => true,
			),
			array(
				'test_condition_name' => '英語サイトの自分の行は開発を支援だけ',
				'locale'              => 'en_US',
				'filter_value'        => null,
				'file'                => $own_file,
				'expected_count'      => 1,
				'expected_pro'        => false,
			),
			array(
				'test_condition_name' => 'フィルターで隠すと日本語サイトでも開発を支援だけ',
				'locale'              => 'ja_JP',
				'filter_value'        => false,
				'file'                => $own_file,
				'expected_count'      => 1,
				'expected_pro'        => false,
			),
			array(
				'test_condition_name' => '他のプラグインの行には何も足さない',
				'locale'              => 'ja_JP',
				'filter_value'        => null,
				'file'                => 'other-plugin/other-plugin.php',
				'expected_count'      => 0,
				'expected_pro'        => false,
			),
		);

		foreach ( $test_cases as $case ) {
			remove_all_filters( 'locale' );
			remove_all_filters( 'ormm_show_pro_promotion' );
			$this->set_locale( $case['locale'] );
			if ( null !== $case['filter_value'] ) {
				$value = $case['filter_value'];
				add_filter(
					'ormm_show_pro_promotion',
					function () use ( $value ) {
						return $value;
					}
				);
			}

			$links = ormm_plugin_row_meta( array(), $case['file'] );
			$this->assertCount( $case['expected_count'], $links, $case['test_condition_name'] );
			$has_pro_link = false !== strpos( implode( '', $links ), 'utm_content=plugin-row' );
			$this->assertSame( $case['expected_pro'], $has_pro_link, $case['test_condition_name'] . '（Pro リンクの有無）' );
			$this->assertStringNotContainsString( '&utm_medium', implode( '', $links ), $case['test_condition_name'] . '（生の & が残らない）' );
		}
	}

	/**
	 * ormm_render_pro_promotion_paragraph(): shown only at the bottom, on the template list, for an administrator,
	 * with at least one published template, on a Japanese site.
	 * ormm_render_pro_promotion_paragraph()：下部の tablenav・テンプレート一覧・管理者・公開済み1件以上・日本語サイトのときだけ出る。
	 *
	 * @return void
	 */
	public function test_ormm_render_pro_promotion_paragraph() {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		$test_cases = array(
			array(
				'test_condition_name' => '日本語・管理者・公開済み1件・一覧の下部なら出る',
				'locale'              => 'ja_JP',
				'user'                => $admin,
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'published'           => 1,
				'filter_value'        => null,
				'expected'            => true,
			),
			array(
				'test_condition_name' => '公開済みが0件なら出さない',
				'locale'              => 'ja_JP',
				'user'                => $admin,
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'published'           => 0,
				'filter_value'        => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '上部の tablenav には出さない',
				'locale'              => 'ja_JP',
				'user'                => $admin,
				'screen'              => 'edit-ormm_template',
				'which'               => 'top',
				'published'           => 1,
				'filter_value'        => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '別の投稿タイプの一覧には出さない',
				'locale'              => 'ja_JP',
				'user'                => $admin,
				'screen'              => 'edit-post',
				'which'               => 'bottom',
				'published'           => 1,
				'filter_value'        => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'activate_plugins が無いユーザーには出さない',
				'locale'              => 'ja_JP',
				'user'                => $editor,
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'published'           => 1,
				'filter_value'        => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '英語サイトでは出さない',
				'locale'              => 'en_US',
				'user'                => $admin,
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'published'           => 1,
				'filter_value'        => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'ormm_show_pro_promotion が false なら日本語サイトでも出さない',
				'locale'              => 'ja_JP',
				'user'                => $admin,
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'published'           => 1,
				'filter_value'        => false,
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			remove_all_filters( 'locale' );
			remove_all_filters( 'ormm_show_pro_promotion' );
			$this->set_locale( $case['locale'] );
			if ( null !== $case['filter_value'] ) {
				$value = $case['filter_value'];
				add_filter(
					'ormm_show_pro_promotion',
					function () use ( $value ) {
						return $value;
					}
				);
			}
			wp_set_current_user( $case['user'] );
			set_current_screen( $case['screen'] );

			// 公開済みテンプレートの件数を、ケースごとに作り直す.
			$post_ids = array();
			for ( $i = 0; $i < $case['published']; $i++ ) {
				$post_ids[] = self::factory()->post->create(
					array(
						'post_type'   => 'ormm_template',
						'post_status' => 'publish',
					)
				);
			}
			// 下書きだけでは「公開済み」に数えないことも同時に確かめる.
			$post_ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'ormm_template',
					'post_status' => 'draft',
				)
			);

			$html     = $this->get_paragraph( $case['which'] );
			$is_shown = '' !== trim( $html );
			$this->assertSame( $case['expected'], $is_shown, $case['test_condition_name'] );
			if ( $case['expected'] ) {
				$this->assertStringContainsString( 'utm_content=template-list', $html, $case['test_condition_name'] . '（UTM）' );
				$this->assertStringNotContainsString( 'notice', $html, $case['test_condition_name'] . '（notice クラスを使わない）' );
				$this->assertStringContainsString( 'screen-reader-text', $html, $case['test_condition_name'] . '（新しいタブの案内）' );
			}

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}
	}
}
