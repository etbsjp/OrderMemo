<?php
/**
 * Tests for the notices about the paid version, shown on Japanese sites only (inc/func.php).
 * 日本語のサイトだけに出す、有料版の案内（inc/func.php）のテスト。
 *
 * @package etbs-order-note-templates
 */

/**
 * Covers ormm_is_japanese_site(), ormm_should_show_ja_promotion(), ormm_get_pro_promotion_url(),
 * ormm_plugin_row_meta() and ormm_render_pro_promotion_below_list().
 * ormm_is_japanese_site()、ormm_should_show_ja_promotion()、ormm_get_pro_promotion_url()、
 * ormm_plugin_row_meta()、ormm_render_pro_promotion_below_list() を確かめる。
 */
class Test_Etbs_Ont_Pro_Promotion extends WP_UnitTestCase {

	/**
	 * Locale the site should report during a test. Empty means "leave it alone".
	 * テスト中にサイトが返すロケール。空なら何もしない。
	 *
	 * @var string
	 */
	private $locale = '';

	/**
	 * Makes get_locale() return the locale a test asks for.
	 * テストが指定したロケールを get_locale() に返させる。
	 *
	 * @param string $locale Locale from WordPress. / WordPress が決めたロケール。
	 * @return string Locale to use. / 使うロケール。
	 */
	public function filter_locale( $locale ) {
		return '' === $this->locale ? $locale : $this->locale;
	}

	/**
	 * Adds the locale filter before each test.
	 * 各テストの前にロケールのフィルターを足す。
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'locale', array( $this, 'filter_locale' ) );
	}

	/**
	 * Removes what a test added, so the next test starts clean.
	 * テストが足したものを外し、次のテストに持ち越さない。
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'locale', array( $this, 'filter_locale' ) );
		remove_all_filters( 'ormm_show_pro_promotion' );
		unset( $GLOBALS['current_screen'], $_GET['post_status'] );
		wp_deregister_style( 'common' );
		parent::tear_down();
	}

	/**
	 * Creates a user who may activate plugins, and logs in as that user.
	 * On multisite only a super admin has activate_plugins.
	 * プラグインを有効化できる利用者を作り、そのユーザーでログインする。
	 * マルチサイトで activate_plugins を持つのはスーパー管理者だけ。
	 *
	 * @return void
	 */
	private function login_as_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
	}

	/**
	 * Prints the paragraph on the template list screen and returns what was printed.
	 * テンプレート一覧の画面で段落を出力し、出力された HTML を返す。
	 *
	 * @param string $screen_id Screen ID to pretend to be on. / 表示中とみなす画面 ID。
	 * @param string $which     Tablenav position. / tablenav の位置。
	 * @return string Printed HTML. / 出力された HTML。
	 */
	private function render_below_list( $screen_id, $which ) {
		set_current_screen( $screen_id );
		ob_start();
		do_action( 'manage_posts_extra_tablenav', $which );
		return (string) ob_get_clean();
	}

	/**
	 * ormm_is_japanese_site(): true only for locales starting with "ja".
	 * ormm_is_japanese_site()：ロケールが ja で始まるときだけ true。
	 *
	 * @return void
	 */
	public function test_ormm_is_japanese_site() {
		$test_cases = array(
			array(
				'test_condition_name' => 'ja_JP => true',
				'locale'              => 'ja_JP',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ja（地域なし）=> true',
				'locale'              => 'ja',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'ja_JP_formal 等の長いロケール => true',
				'locale'              => 'ja_JP_formal',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'en_US => false',
				'locale'              => 'en_US',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'ja を途中に含むだけの jv_ID（ジャワ語）=> false',
				'locale'              => 'jv_ID',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '大文字の JA_JP（先頭一致だけで大文字小文字は区別する）=> false',
				'locale'              => 'JA_JP',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '1 文字だけの不正なロケール x => false（壊れずに false を返す）',
				'locale'              => 'x',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->locale = $case['locale'];
			$this->assertSame( $case['expected'], ormm_is_japanese_site(), $case['test_condition_name'] );
		}
	}

	/**
	 * ormm_should_show_ja_promotion(): needs a Japanese site and ormm_show_pro_promotion not returning false.
	 * ormm_should_show_ja_promotion()：日本語のサイトで、かつ ormm_show_pro_promotion が false でないときだけ true。
	 *
	 * @return void
	 */
	public function test_ormm_should_show_ja_promotion() {
		$test_cases = array(
			array(
				'test_condition_name' => '日本語サイトでフィルターが何もしない => true',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'expected'            => true,
			),
			array(
				'test_condition_name' => '日本語サイトでフィルターが true => true',
				'locale'              => 'ja',
				'filter_return'       => true,
				'expected'            => true,
			),
			array(
				'test_condition_name' => '日本語サイトでも、有料版がフィルターで false を返したら => false',
				'locale'              => 'ja_JP',
				'filter_return'       => false,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '日本語以外のサイト => false',
				'locale'              => 'en_US',
				'filter_return'       => null,
				'expected'            => false,
			),
			array(
				'test_condition_name' => '日本語以外のサイトでフィルターが true でも => false（フィルターで増やせない）',
				'locale'              => 'de_DE',
				'filter_return'       => true,
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->locale = $case['locale'];
			remove_all_filters( 'ormm_show_pro_promotion' );
			if ( null !== $case['filter_return'] ) {
				$return = $case['filter_return'];
				add_filter(
					'ormm_show_pro_promotion',
					static function () use ( $return ) {
						return $return;
					}
				);
			}
			$this->assertSame( $case['expected'], ormm_should_show_ja_promotion(), $case['test_condition_name'] );
		}
	}

	/**
	 * ormm_get_pro_promotion_url(): adds the UTM parameters, and utm_content tells the two places apart.
	 * ormm_get_pro_promotion_url()：UTM が付き、utm_content で 2 か所を区別できる。
	 *
	 * @return void
	 */
	public function test_ormm_get_pro_promotion_url() {
		$test_cases = array(
			array(
				'test_condition_name' => 'プラグイン一覧の行 => utm_content=plugin-row',
				'content'             => 'plugin-row',
				'expected'            => 'plugin-row',
			),
			array(
				'test_condition_name' => 'テンプレート一覧の段落 => utm_content=template-list',
				'content'             => 'template-list',
				'expected'            => 'template-list',
			),
		);

		foreach ( $test_cases as $case ) {
			$url = ormm_get_pro_promotion_url( $case['content'] );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

			$this->assertSame( $case['expected'], $query['utm_content'], $case['test_condition_name'] );
			$this->assertSame( 'etbs-order-note-templates', $query['utm_source'], $case['test_condition_name'] . '（utm_source）' );
			$this->assertSame( 'plugin', $query['utm_medium'], $case['test_condition_name'] . '（utm_medium）' );
			$this->assertSame( 'ordermemo-pro', $query['utm_campaign'], $case['test_condition_name'] . '（utm_campaign）' );
		}
	}

	/**
	 * ormm_plugin_row_meta(): Japanese sites get the paid version link and the support link; others get the support link only.
	 * ormm_plugin_row_meta()：日本語のサイトは有料版と支援の 2 本、それ以外は支援だけ。
	 *
	 * @return void
	 */
	public function test_ormm_plugin_row_meta() {
		$own_file = plugin_basename( ETBS_ONT_PLUGIN_FILE );

		$test_cases = array(
			array(
				'test_condition_name' => '日本語サイト => 有料版と開発を支援の 2 本',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'file'                => $own_file,
				'expected_count'      => 2,
				'expected_pro'        => true,
			),
			array(
				'test_condition_name' => '英語サイト => 開発を支援の 1 本だけ',
				'locale'              => 'en_US',
				'filter_return'       => null,
				'file'                => $own_file,
				'expected_count'      => 1,
				'expected_pro'        => false,
			),
			array(
				'test_condition_name' => '日本語サイトで ormm_show_pro_promotion が false => 開発を支援の 1 本だけ',
				'locale'              => 'ja_JP',
				'filter_return'       => false,
				'file'                => $own_file,
				'expected_count'      => 1,
				'expected_pro'        => false,
			),
			array(
				'test_condition_name' => '他のプラグインの行 => 何も足さない',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'file'                => 'other/other.php',
				'expected_count'      => 0,
				'expected_pro'        => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->locale = $case['locale'];
			remove_all_filters( 'ormm_show_pro_promotion' );
			if ( null !== $case['filter_return'] ) {
				$return = $case['filter_return'];
				add_filter(
					'ormm_show_pro_promotion',
					static function () use ( $return ) {
						return $return;
					}
				);
			}

			$links = ormm_plugin_row_meta( array(), $case['file'] );
			$html  = implode( '', $links );

			$this->assertCount( $case['expected_count'], $links, $case['test_condition_name'] );
			$this->assertSame( $case['expected_pro'], 0 < substr_count( $html, 'utm_content=plugin-row' ), $case['test_condition_name'] . '（有料版リンク）' );
			// 新しいタブで開くリンクには、すべて screen-reader-text が付く（有料版・開発を支援の両方）.
			$this->assertSame( $case['expected_count'], substr_count( $html, 'screen-reader-text' ), $case['test_condition_name'] . '（新しいタブで開く旨）' );
			// esc_url() が通っていれば、href に生の & は残らない.
			$this->assertSame( 0, preg_match( '/href="[^"]*&(?!#038;|amp;)/', $html ), $case['test_condition_name'] . '（生の & が残らない）' );
		}
	}

	/**
	 * ormm_render_pro_promotion_below_list(): prints one paragraph only on the template list, at the bottom, for a Japanese site.
	 * ormm_render_pro_promotion_below_list()：日本語のサイトの、テンプレート一覧の下側にだけ 1 段落を出す。
	 *
	 * @return void
	 */
	public function test_ormm_render_pro_promotion_below_list() {
		$this->login_as_admin();

		$test_cases = array(
			array(
				'test_condition_name' => '日本語サイト・一覧・下側・公開済み 1 件 => 段落が出る',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'published'           => 1,
				'post_status'         => '',
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '上側の tablenav => 出ない',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'published'           => 1,
				'post_status'         => '',
				'screen'              => 'edit-ormm_template',
				'which'               => 'top',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '別の投稿タイプの一覧（edit-post）=> 出ない',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'published'           => 1,
				'post_status'         => '',
				'screen'              => 'edit-post',
				'which'               => 'bottom',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '英語サイト => 出ない',
				'locale'              => 'en_US',
				'filter_return'       => null,
				'published'           => 1,
				'post_status'         => '',
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '日本語サイトでも ormm_show_pro_promotion が false => 出ない',
				'locale'              => 'ja_JP',
				'filter_return'       => false,
				'published'           => 1,
				'post_status'         => '',
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'ゴミ箱ビュー（post_status=trash）=> 出ない',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'published'           => 1,
				'post_status'         => 'trash',
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '公開ビュー（post_status=publish）=> 出る',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'published'           => 1,
				'post_status'         => 'publish',
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '公開済みのテンプレートが 0 件 => 出ない',
				'locale'              => 'ja_JP',
				'filter_return'       => null,
				'published'           => 0,
				'post_status'         => '',
				'screen'              => 'edit-ormm_template',
				'which'               => 'bottom',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->locale = $case['locale'];
			remove_all_filters( 'ormm_show_pro_promotion' );
			if ( null !== $case['filter_return'] ) {
				$return = $case['filter_return'];
				add_filter(
					'ormm_show_pro_promotion',
					static function () use ( $return ) {
						return $return;
					}
				);
			}

			// 公開済みのテンプレートを、条件の件数だけ作る（下書きは数えられないことも確かめる）.
			$post_ids = array();
			for ( $i = 0; $i < $case['published']; $i++ ) {
				$post_ids[] = self::factory()->post->create(
					array(
						'post_type'   => 'ormm_template',
						'post_status' => 'publish',
					)
				);
			}
			$post_ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'ormm_template',
					'post_status' => 'draft',
				)
			);

			if ( '' === $case['post_status'] ) {
				unset( $_GET['post_status'] );
			} else {
				$_GET['post_status'] = $case['post_status'];
			}

			$html = $this->render_below_list( $case['screen'], $case['which'] );

			$this->assertSame( $case['expected'], 0 < substr_count( $html, 'utm_content=template-list' ), $case['test_condition_name'] );
			// 段落は clear 用の div に包まれる（tablenav の固定高さであふれないため）.
			$this->assertSame( $case['expected'], 0 < substr_count( $html, 'class="ormm-pro-promotion"' ), $case['test_condition_name'] . '（包み div）' );
			if ( $case['expected'] ) {
				// 通知風ではなく p.description で、リンクの行き先が分かる文言になっている.
				$this->assertStringContainsString( '<p class="description">', $html, $case['test_condition_name'] . '（p.description）' );
				$this->assertStringNotContainsString( 'notice', $html, $case['test_condition_name'] . '（notice クラスを使わない）' );
				$this->assertStringContainsString( 'screen-reader-text', $html, $case['test_condition_name'] . '（新しいタブで開く旨）' );
			}

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	/**
	 * ormm_render_pro_promotion_below_list(): a user without activate_plugins does not see the paragraph.
	 * ormm_render_pro_promotion_below_list()：activate_plugins を持たない利用者には出さない。
	 *
	 * @return void
	 */
	public function test_ormm_render_pro_promotion_below_list_capability() {
		$test_cases = array(
			array(
				'test_condition_name' => '編集者（activate_plugins なし）=> 出ない',
				'role'                => 'editor',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '管理者（activate_plugins あり）=> 出る',
				'role'                => 'administrator',
				'expected'            => true,
			),
		);

		$this->locale = 'ja_JP';
		$post_id      = self::factory()->post->create(
			array(
				'post_type'   => 'ormm_template',
				'post_status' => 'publish',
			)
		);

		foreach ( $test_cases as $case ) {
			$user_id = self::factory()->user->create( array( 'role' => $case['role'] ) );
			wp_set_current_user( $user_id );
			if ( is_multisite() && 'administrator' === $case['role'] ) {
				grant_super_admin( $user_id );
			}

			$html = $this->render_below_list( 'edit-ormm_template', 'bottom' );

			$this->assertSame( $case['expected'], 0 < substr_count( $html, 'utm_content=template-list' ), $case['test_condition_name'] );
		}

		wp_delete_post( $post_id, true );
	}

	/**
	 * ormm_add_pro_promotion_style(): adds the small inline CSS only where the paragraph is shown.
	 * ormm_add_pro_promotion_style()：段落を出す画面にだけ、最小限のインライン CSS を足す。
	 *
	 * @return void
	 */
	public function test_ormm_add_pro_promotion_style() {
		$this->login_as_admin();
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ormm_template',
				'post_status' => 'publish',
			)
		);

		$test_cases = array(
			array(
				'test_condition_name' => '日本語サイトのテンプレート一覧 => CSS が足される',
				'locale'              => 'ja_JP',
				'screen'              => 'edit-ormm_template',
				'expected'            => true,
			),
			array(
				'test_condition_name' => '英語サイトのテンプレート一覧 => 足されない',
				'locale'              => 'en_US',
				'screen'              => 'edit-ormm_template',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '日本語サイトでも別の一覧 => 足されない',
				'locale'              => 'ja_JP',
				'screen'              => 'edit-post',
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->locale = $case['locale'];
			// 使い捨てのテストサイトには管理画面の CSS が登録されていないため、毎回 common を登録し直す.
			wp_deregister_style( 'common' );
			wp_register_style( 'common', false, array(), ETBS_ONT_VERSION );
			set_current_screen( $case['screen'] );

			ormm_add_pro_promotion_style();

			$css = implode( '', (array) wp_styles()->get_data( 'common', 'after' ) );
			$this->assertSame( $case['expected'], 0 < substr_count( $css, '.ormm-pro-promotion' ), $case['test_condition_name'] );
		}

		wp_delete_post( $post_id, true );
	}
}
