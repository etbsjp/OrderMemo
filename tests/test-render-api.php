<?php
/**
 * Tests for the entry points that OrderMemo Pro calls (inc/func.php).
 * 有料版が呼ぶ入口（inc/func.php）のテスト。
 *
 * @package etbs-order-note-templates
 */

// Loads a stand-in for WC_Order only when WooCommerce is not loaded (the scratch test site has none).
// WooCommerce が読み込まれていないときだけ、WC_Order の代役を読み込む（使い捨てのテストサイトには無い）.
if ( ! class_exists( 'WC_Order' ) ) {
	require_once __DIR__ . '/class-wc-order.php';
}

/**
 * Covers ormm_render_template(), ormm_get_templates(), ormm_get_tag_descriptions()
 * and ormm_should_show_pro_promotion().
 * ormm_render_template()、ormm_get_templates()、ormm_get_tag_descriptions()、
 * ormm_should_show_pro_promotion() を確かめる。
 */
class Test_Etbs_Ont_Render_Api extends WP_UnitTestCase {

	/**
	 * Removes the filters a test added, so the next test starts clean.
	 * テストが足したフィルターを外し、次のテストに持ち越さない。
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_all_filters( 'ormm_tags' );
		remove_all_filters( 'ormm_tag_descriptions' );
		remove_all_filters( 'ormm_show_pro_promotion' );
		parent::tear_down();
	}

	/**
	 * Creates a template post.
	 * テンプレートの投稿を作る。
	 *
	 * @param array $args Arguments for wp_insert_post(). / wp_insert_post() に渡す引数。
	 * @return int The post ID. / 投稿 ID。
	 */
	private function create_template( array $args ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'ormm_template',
					'post_status' => 'publish',
				),
				$args
			)
		);
	}

	/**
	 * Returns an order stand-in that answers the methods ormm_get_tags() calls.
	 * WooCommerce is not loaded in the scratch test site, and ormm_render_template() only reads these methods.
	 * ormm_get_tags() が呼ぶメソッドだけに答える、注文の代役を返す。
	 * 使い捨てのテストサイトには WooCommerce が無く、ormm_render_template() はこれらしか読まないため。
	 *
	 * @return object The stand-in order. / 注文の代役。
	 */
	private function create_order() {
		return new WC_Order();
	}

	/**
	 * ormm_render_template(): expands tags, expands added tags, returns WP_Error, and expands once.
	 * ormm_render_template()：タグの展開、足したタグの展開、WP_Error、再展開されないこと。
	 *
	 * @return void
	 */
	public function test_ormm_render_template() {
		$order = $this->create_order();
		$pm    = $order->get_payment_method_title();
		$num   = (string) $order->get_order_number();

		$published_id = $this->create_template( array( 'post_content' => '#{order_number} / {payment_method}' ) );
		$draft_id     = $this->create_template(
			array(
				'post_status'  => 'draft',
				'post_content' => 'draft {order_number}',
			)
		);
		$trash_id     = $this->create_template(
			array(
				'post_status'  => 'trash',
				'post_content' => 'trash {order_number}',
			)
		);
		$other_id     = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => 'post {order_number}',
			)
		);
		$plain_id     = $this->create_template( array( 'post_content' => 'no tags here' ) );
		$unknown_id   = $this->create_template( array( 'post_content' => 'keep {unknown_tag} as is' ) );
		$added_id     = $this->create_template( array( 'post_content' => 'Tracking: {tracking_number}' ) );
		$nested_id    = $this->create_template( array( 'post_content' => '{first} and {second}' ) );

		$test_cases = array(
			array(
				'test_condition_name' => '公開済みのテンプレートを ID で渡すと、タグが展開される',
				'template'            => $published_id,
				'filter_tags'         => array(),
				'expected'            => '#' . $num . ' / ' . $pm,
			),
			array(
				'test_condition_name' => '公開済みのテンプレートを WP_Post で渡しても、同じ結果になる',
				'template'            => get_post( $published_id ),
				'filter_tags'         => array(),
				'expected'            => '#' . $num . ' / ' . $pm,
			),
			array(
				'test_condition_name' => 'タグの無い本文は、そのまま返る',
				'template'            => $plain_id,
				'filter_tags'         => array(),
				'expected'            => 'no tags here',
			),
			array(
				'test_condition_name' => '未知のタグは、そのまま残る',
				'template'            => $unknown_id,
				'filter_tags'         => array(),
				'expected'            => 'keep {unknown_tag} as is',
			),
			array(
				'test_condition_name' => 'ormm_tags で足したタグも展開される',
				'template'            => $added_id,
				'filter_tags'         => array( '{tracking_number}' => 'JP123456' ),
				'expected'            => 'Tracking: JP123456',
			),
			array(
				'test_condition_name' => '値の中に書かれたタグは再展開されない',
				'template'            => $nested_id,
				'filter_tags'         => array(
					'{first}'  => '[{second}]',
					'{second}' => 'B',
				),
				'expected'            => '[{second}] and B',
			),
			array(
				'test_condition_name' => '値が数値のタグは文字列として展開される',
				'template'            => $added_id,
				'filter_tags'         => array( '{tracking_number}' => 42 ),
				'expected'            => 'Tracking: 42',
			),
			array(
				'test_condition_name' => 'スカラーでない値のタグは無視され、タグはそのまま残る',
				'template'            => $added_id,
				'filter_tags'         => array( '{tracking_number}' => array( 'x' ) ),
				'expected'            => 'Tracking: {tracking_number}',
			),
			array(
				'test_condition_name' => '空のキーは無視され、ほかのタグは展開される',
				'template'            => $added_id,
				'filter_tags'         => array(
					''                  => 'X',
					'{tracking_number}' => 'JP1',
				),
				'expected'            => 'Tracking: JP1',
			),
			array(
				'test_condition_name' => '下書きのテンプレートは WP_Error',
				'template'            => $draft_id,
				'filter_tags'         => array(),
				'expected'            => 'ormm_template_not_found',
			),
			array(
				'test_condition_name' => 'ゴミ箱のテンプレートは WP_Error',
				'template'            => $trash_id,
				'filter_tags'         => array(),
				'expected'            => 'ormm_template_not_found',
			),
			array(
				'test_condition_name' => '他の投稿タイプの投稿は WP_Error',
				'template'            => $other_id,
				'filter_tags'         => array(),
				'expected'            => 'ormm_template_not_found',
			),
			array(
				'test_condition_name' => '存在しない ID は WP_Error',
				'template'            => 999999999,
				'filter_tags'         => array(),
				'expected'            => 'ormm_template_not_found',
			),
			array(
				'test_condition_name' => 'ID が 0 のときは WP_Error（グローバルの投稿を読まない）',
				'template'            => 0,
				'filter_tags'         => array(),
				'expected'            => 'ormm_template_not_found',
			),
			array(
				'test_condition_name' => '注文が WC_Order でないときは WP_Error',
				'template'            => $published_id,
				'filter_tags'         => array(),
				'order'               => new stdClass(),
				'expected'            => 'ormm_invalid_order',
			),
		);

		foreach ( $test_cases as $case ) {
			// Add the tags of the case with the public filter.
			// ケースのタグを、公開フィルターで足す.
			$callback = function ( $tags ) use ( $case ) {
				return array_merge( $tags, $case['filter_tags'] );
			};
			add_filter( 'ormm_tags', $callback );

			$actual = ormm_render_template( $case['template'], $case['order'] ?? $order );

			remove_filter( 'ormm_tags', $callback );

			if ( is_wp_error( $actual ) ) {
				// An error is compared by its code.
				// エラーはコードで比べる.
				$this->assertSame( $case['expected'], $actual->get_error_code(), $case['test_condition_name'] );
			} else {
				$this->assertSame( $case['expected'], $actual, $case['test_condition_name'] );
			}
		}
	}

	/**
	 * ormm_get_templates(): published templates only, ordered by menu_order and then title.
	 * ormm_get_templates()：公開済みのみ、menu_order、次にタイトルの順。
	 *
	 * @return void
	 */
	public function test_ormm_get_templates() {
		$this->create_template(
			array(
				'post_title' => 'B second by title',
				'menu_order' => 1,
			)
		);
		$this->create_template(
			array(
				'post_title' => 'A first by title',
				'menu_order' => 1,
			)
		);
		$this->create_template(
			array(
				'post_title' => 'Z lowest menu order',
				'menu_order' => 0,
			)
		);
		$this->create_template(
			array(
				'post_title'  => 'Draft is excluded',
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Other post type is excluded',
			)
		);

		$actual = wp_list_pluck( ormm_get_templates(), 'post_title' );

		$this->assertSame(
			array( 'Z lowest menu order', 'A first by title', 'B second by title' ),
			$actual,
			'公開済みのテンプレートだけが、menu_order、次にタイトルの順で返る'
		);
	}

	/**
	 * ormm_get_tag_descriptions(): the default list and the ormm_tag_descriptions filter.
	 * ormm_get_tag_descriptions()：標準の一覧と、ormm_tag_descriptions フィルター。
	 *
	 * @return void
	 */
	public function test_ormm_get_tag_descriptions() {
		$default = ormm_get_tag_descriptions();
		$this->assertArrayHasKey( '{order_number}', $default, '標準のタグの説明が含まれる' );
		$this->assertArrayNotHasKey( '{tracking_number}', $default, '足していないタグは含まれない' );

		add_filter(
			'ormm_tag_descriptions',
			function ( $descriptions ) {
				$descriptions['{tracking_number}'] = 'Tracking number';
				return $descriptions;
			}
		);
		$filtered = ormm_get_tag_descriptions();

		$this->assertSame( 'Tracking number', $filtered['{tracking_number}'], 'フィルターで足した説明が返る' );
		$this->assertSame( $default['{order_number}'], $filtered['{order_number}'], '標準の説明は残る' );

		// A filter that returns a non-array must not break the edit screen.
		// 配列でない値を返すフィルターでも、編集画面を壊さない.
		remove_all_filters( 'ormm_tag_descriptions' );
		add_filter( 'ormm_tag_descriptions', '__return_null' );
		$this->assertSame( array(), ormm_get_tag_descriptions(), '配列でない戻り値は空配列として扱われる' );
	}

	/**
	 * ormm_should_show_pro_promotion(): true by default, and follows the filter.
	 * ormm_should_show_pro_promotion()：既定は true で、フィルターに従う。
	 *
	 * @return void
	 */
	public function test_ormm_should_show_pro_promotion() {
		$test_cases = array(
			array(
				'test_condition_name' => 'フィルターが無ければ true',
				'filter_value'        => null,
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'フィルターが false を返せば false',
				'filter_value'        => false,
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'フィルターが true を返せば true',
				'filter_value'        => true,
				'expected'            => true,
			),
		);

		foreach ( $test_cases as $case ) {
			remove_all_filters( 'ormm_show_pro_promotion' );
			if ( null !== $case['filter_value'] ) {
				$value = $case['filter_value'];
				add_filter(
					'ormm_show_pro_promotion',
					function () use ( $value ) {
						return $value;
					}
				);
			}
			$this->assertSame( $case['expected'], ormm_should_show_pro_promotion(), $case['test_condition_name'] );
		}
	}
}
