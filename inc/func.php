<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/*-------------------------------------------*/
/* WooCommerce 未導入時の通知
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_admin_notice_requires_wc' ) ) {
	/**
	 * Prints an admin notice when WooCommerce is not active.
	 * WooCommerce が有効でないとき、管理画面に通知を出す。
	 *
	 * @return void
	 */
	function ormm_admin_notice_requires_wc() {
		if ( class_exists( 'WooCommerce' ) ) { return; }
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }
		// One sentence per translatable string. / 翻訳文字列は 1 文ずつ.
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Order Note Templates requires WooCommerce.', 'etbs-order-note-templates' )
			. ' '
			. esc_html__( 'Please install and activate WooCommerce.', 'etbs-order-note-templates' )
			. '</p></div>';
	}
	add_action( 'admin_notices', 'ormm_admin_notice_requires_wc' );
}

/*-------------------------------------------*/
/* HPOS（高性能注文ストレージ）互換の宣言
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_declare_hpos_compat' ) ) {
	/**
	 * Declares compatibility with WooCommerce High-Performance Order Storage (HPOS).
	 * WooCommerce の高性能注文ストレージ（HPOS）への対応を宣言する。
	 *
	 * @return void
	 */
	function ormm_declare_hpos_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ETBS_ONT_PLUGIN_FILE, true );
		}
	}
	add_action( 'before_woocommerce_init', 'ormm_declare_hpos_compat' );
}

/*-------------------------------------------*/
/* テンプレート用カスタム投稿タイプ
/* WooCommerceメニュー配下に表示。本文は専用メタボックスの
/* プレーンテキストエリアで編集する（エディタは使わない）。
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_register_post_type' ) ) {
	/**
	 * Registers the post type that stores the templates.
	 * テンプレートを保存する投稿タイプを登録する。
	 *
	 * @return void
	 */
	function ormm_register_post_type() {
		register_post_type( 'ormm_template', [
			'labels' => [
				'name'               => __( 'Order Note Templates', 'etbs-order-note-templates' ),
				// Do not start the menu name with "Orders" (or its translation). WooCommerce puts the
				// processing-orders count badge on the first submenu whose title starts with "Orders"
				// (prefix match), so a menu name that starts with it would take the badge away.
				// メニュー名は「注文」で始めない。WooCommerce が処理中件数バッジを、名前が
				// "Orders" で始まる最初のサブメニューに付ける（前方一致）ため、横取りしてしまう。
				/* translators: Do not start with the translation of "Orders" (for example "注文" in Japanese). WooCommerce attaches the processing-orders count badge to the first submenu whose title starts with "Orders", so a title starting with it would take the badge. */
				'menu_name'          => __( 'Note Templates', 'etbs-order-note-templates' ),
				'singular_name'      => __( 'Order Note Template', 'etbs-order-note-templates' ),
				'add_new'            => __( 'Add New', 'etbs-order-note-templates' ),
				'add_new_item'       => __( 'Add Template', 'etbs-order-note-templates' ),
				'edit_item'          => __( 'Edit Template', 'etbs-order-note-templates' ),
				'new_item'           => __( 'New Template', 'etbs-order-note-templates' ),
				'search_items'       => __( 'Search Templates', 'etbs-order-note-templates' ),
				'not_found'          => __( 'No templates found.', 'etbs-order-note-templates' ),
				'not_found_in_trash' => __( 'No templates found in Trash.', 'etbs-order-note-templates' ),
			],
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'woocommerce',
			'show_in_rest'    => false,
			'supports'        => [ 'title', 'page-attributes' ],
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		] );
	}
	add_action( 'init', 'ormm_register_post_type' );
}

/*-------------------------------------------*/
/* 差し込みタグ
/* ormm_tags フィルターで独自タグを追加できる。
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_get_tags' ) ) {
	/**
	 * Returns the merge tags and their values for an order.
	 * 注文に対する、差し込みタグと値の配列を返す。
	 *
	 * Part of the public API (frozen): OrderMemo Pro and other add-ons depend on it.
	 * 公開 API（凍結）。有料版などの拡張が依存している。
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order The order the tags are resolved for.
	 * @return string[] Values keyed by tag. The key is the tag itself, including the curly braces, such as "{order_number}".
	 */
	function ormm_get_tags( $order ) {
		$date = $order->get_date_created();
		$tags = [
			'{customer_name}'   => $order->get_formatted_billing_full_name(),
			'{order_number}'    => $order->get_order_number(),
			'{order_date}'      => $date ? wc_format_datetime( $date ) : '',
			'{order_total}'     => wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total(), ENT_QUOTES, 'UTF-8' ) ),
			'{payment_method}'  => $order->get_payment_method_title(),
			'{shipping_method}' => $order->get_shipping_method(),
			'{site_name}'       => get_bloginfo( 'name' ),
		];

		/**
		 * Filters the merge tags that are replaced in a template.
		 * テンプレートの中で置き換える差し込みタグを絞り込む。
		 *
		 * Part of the public API (frozen). It runs every time a template is rendered.
		 * Each key is the tag itself, including the curly braces (for example "{tracking_number}"),
		 * and each value must be a scalar, which is used as a string.
		 * A template is expanded with a single pass of strtr(), so a tag written inside a value
		 * is NOT expanded again.
		 * A companion plugin can tell that this plugin (not the older ordermemo plugin) is active
		 * with function_exists( 'ormm_render_template' ).
		 * 公開 API（凍結）。テンプレートを展開するたびに呼ばれる。キーは波括弧を含むタグそのもの
		 * （例: "{tracking_number}"）、値はスカラーで、文字列として使われる。
		 * 展開は strtr() の 1 回の走査なので、値の中に書かれたタグは再展開されない。
		 * 有料版などは function_exists( 'ormm_render_template' ) で、この版か（旧版の ordermemo に
		 * はこの関数が無い）を判別できる。
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $tags  Values keyed by tag.
		 * @param WC_Order $order The order the tags are resolved for.
		 */
		$tags = apply_filters( 'ormm_tags', $tags, $order );

		// Guard against a filter that returns something other than an array, so every caller can foreach it.
		// 配列以外を返すフィルターから、呼び出し側の foreach を守る.
		return is_array( $tags ) ? $tags : array();
	}
}

if ( ! function_exists( 'ormm_get_tag_descriptions' ) ) {
	/**
	 * Returns the description of each merge tag, keyed by tag.
	 * 差し込みタグごとの説明を、タグをキーにして返す。
	 *
	 * @since 1.1.0
	 *
	 * @return string[] Descriptions keyed by tag, such as "{order_number}".
	 */
	function ormm_get_tag_descriptions() {
		$descriptions = array(
			'{customer_name}'   => __( 'Billing name', 'etbs-order-note-templates' ),
			'{order_number}'    => __( 'Order number', 'etbs-order-note-templates' ),
			'{order_date}'      => __( 'Order date', 'etbs-order-note-templates' ),
			'{order_total}'     => __( 'Order total, with the currency symbol', 'etbs-order-note-templates' ),
			'{payment_method}'  => __( 'Payment method', 'etbs-order-note-templates' ),
			'{shipping_method}' => __( 'Shipping method', 'etbs-order-note-templates' ),
			'{site_name}'       => __( 'Site title', 'etbs-order-note-templates' ),
		);

		/**
		 * Filters the descriptions listed under "Available merge tags" on the template edit screen.
		 * テンプレート編集画面の「利用できる差し込みタグ」欄に出す説明を絞り込む。
		 *
		 * Part of the public API (frozen). Use it together with the ormm_tags filter so a tag you
		 * add is also explained. This only changes the list on screen; it does not add a tag.
		 * 公開 API（凍結）。ormm_tags フィルターで足したタグを、この欄にも出すために使う。
		 * 画面の一覧が変わるだけで、タグそのものは足されない。
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $descriptions Descriptions keyed by tag, such as "{order_number}". Plain text; escaped when printed.
		 */
		$descriptions = apply_filters( 'ormm_tag_descriptions', $descriptions );

		// Guard against a filter that returns something other than an array.
		// 配列以外を返すフィルターから、呼び出し側の foreach を守る.
		return is_array( $descriptions ) ? $descriptions : array();
	}
}

/*-------------------------------------------*/
/* テンプレートの取得と展開（有料版など外部から呼ばれる入口）
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_get_templates' ) ) {
	/**
	 * Returns the published templates in display order.
	 * 公開済みのテンプレートを、表示順で返す。
	 *
	 * Part of the public API (frozen). The order is menu_order, then title, ascending. Drafts,
	 * trashed posts and other post types are never included.
	 * 公開 API（凍結）。順序は menu_order、次にタイトルの昇順。下書き・ゴミ箱・他の投稿タイプは含まない。
	 *
	 * @since 1.1.0
	 *
	 * @return WP_Post[] Published templates. An empty array when there is none.
	 */
	function ormm_get_templates() {
		return get_posts(
			array(
				'post_type'   => 'ormm_template',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
	}
}

if ( ! function_exists( 'ormm_get_publishable_template' ) ) {
	/**
	 * Resolves a template ID or post to a published template.
	 * テンプレートの ID または投稿を、公開済みのテンプレートとして解決する。
	 *
	 * Not part of the frozen contract: it is an internal helper and may change or go away.
	 * Call ormm_render_template() instead.
	 * 凍結対象外。内部の補助関数で、変更・削除されることがある。ormm_render_template() を使うこと。
	 *
	 * @internal
	 *
	 * @since 1.1.0
	 *
	 * @param int|WP_Post $template Template post or its ID.
	 * @return WP_Post|WP_Error The template, or a WP_Error with the code "ormm_template_not_found"
	 *                          when it does not exist, is not published or is not an ormm_template.
	 */
	function ormm_get_publishable_template( $template ) {
		// An ID of 0 (or null) would make get_post() read the global $post, so refuse it first.
		// ID が 0（や null）だと get_post() がグローバルの $post を読むため、先に弾く.
		if ( ! $template instanceof WP_Post && ! absint( $template ) ) {
			return new WP_Error(
				'ormm_template_not_found',
				__( 'The template could not be found; it may have been deleted or unpublished.', 'etbs-order-note-templates' )
			);
		}
		$post = get_post( $template );
		if ( ! $post || 'ormm_template' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error(
				'ormm_template_not_found',
				__( 'The template could not be found; it may have been deleted or unpublished.', 'etbs-order-note-templates' )
			);
		}
		return $post;
	}
}

if ( ! function_exists( 'ormm_render_template' ) ) {
	/**
	 * Returns a template body with the order's data filled in.
	 * テンプレート本文を、注文のデータで展開した文字列を返す。
	 *
	 * Part of the public API (frozen). A companion plugin (OrderMemo Pro) can tell that this
	 * plugin is active, and not the older ordermemo plugin, with function_exists( 'ormm_render_template' ).
	 * The tags come from ormm_get_tags(), so tags added with the ormm_tags filter are expanded too.
	 * The text is expanded with a single pass of strtr(); a tag written inside a tag value is not expanded again.
	 * 公開 API（凍結）。有料版などは function_exists( 'ormm_render_template' ) で、この版であること
	 * （旧版の ordermemo にはこの関数が無い）を判別できる。タグは ormm_get_tags() から取るので、
	 * ormm_tags フィルターで足したタグも展開される。展開は strtr() の 1 回の走査で、
	 * タグの値の中に書かれたタグは再展開されない。
	 *
	 * @since 1.1.0
	 *
	 * @param int|WP_Post $template Template post or its ID.
	 * @param WC_Order    $order    The order whose data is filled in.
	 * @return string|WP_Error The expanded text. A WP_Error with the code "ormm_template_not_found"
	 *                         when the template does not exist, is not published (draft, trash and so on)
	 *                         or is not an ormm_template; with the code "ormm_invalid_order" when
	 *                         $order is not a WC_Order.
	 */
	function ormm_render_template( $template, $order ) {
		// Refuse anything that is not a WC_Order, so a wrong argument is reported and not fatal.
		// WC_Order 以外は、Fatal にせずエラーとして返す.
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error(
				'ormm_invalid_order',
				'The order is not a valid WooCommerce order (WC_Order). This message is for developers and is not translated.'
			);
		}

		$post = ormm_get_publishable_template( $template );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Keep only usable pairs: strtr() needs string values and does not accept empty keys.
		// strtr() が扱えるペアだけ残す（値は文字列、キーは空でないこと）.
		$replacements = array();
		foreach ( ormm_get_tags( $order ) as $tag => $value ) {
			if ( '' === (string) $tag || ! is_scalar( $value ) ) {
				continue;
			}
			$replacements[ (string) $tag ] = (string) $value;
		}

		return strtr( $post->post_content, $replacements );
	}
}

if ( ! function_exists( 'ormm_should_show_pro_promotion' ) ) {
	/**
	 * Tells whether this plugin may show its notice about the paid version.
	 * 公式版が有料版の案内を出してよいかを返す。
	 *
	 * @since 1.1.0
	 *
	 * @return bool True to show the notice. Filtered with ormm_show_pro_promotion.
	 */
	function ormm_should_show_pro_promotion() {
		/**
		 * Filters whether this plugin shows its notice about the paid version (OrderMemo Pro).
		 * 公式版が有料版（OrderMemo Pro）の案内を出すかを絞り込む。
		 *
		 * Part of the public API (frozen). OrderMemo Pro returns false to hide the notice.
		 * 公開 API（凍結）。有料版が false を返して、案内を隠す。
		 *
		 * @since 1.1.0
		 *
		 * @param bool $show Whether to show the notice. Default true.
		 */
		return (bool) apply_filters( 'ormm_show_pro_promotion', true );
	}
}

/*-------------------------------------------*/
/* テンプレート編集画面のメタボックス
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_add_meta_boxes' ) ) {
	/**
	 * Adds the meta boxes to the template edit screen.
	 * テンプレート編集画面にメタボックスを追加する。
	 *
	 * @return void
	 */
	function ormm_add_meta_boxes() {
		add_meta_box(
			'ormm_content_box',
			__( 'Template body', 'etbs-order-note-templates' ),
			'ormm_render_content_box',
			'ormm_template',
			'normal',
			'high'
		);
		add_meta_box(
			'ormm_tags_box',
			__( 'Available placeholders', 'etbs-order-note-templates' ),
			'ormm_render_tags_box',
			'ormm_template',
			'side',
			'default'
		);
	}
	add_action( 'add_meta_boxes_ormm_template', 'ormm_add_meta_boxes' );
}

if ( ! function_exists( 'ormm_render_content_box' ) ) {
	/**
	 * Prints the template body meta box.
	 * テンプレート本文のメタボックスを出力する。
	 *
	 * @param WP_Post $post The template being edited.
	 * @return void
	 */
	function ormm_render_content_box( $post ) {
		wp_nonce_field( 'ormm_save_content', 'ormm_content_nonce' );
		echo '<textarea name="ormm_content" rows="10" class="widefat" placeholder="'
			. esc_attr__( 'Hi {customer_name}, your order #{order_number} has been shipped today.', 'etbs-order-note-templates' )
			. '">' . esc_textarea( $post->post_content ) . '</textarea>';
		// One sentence per translatable string. / 翻訳文字列は 1 文ずつ.
		echo '<p class="description">'
			. esc_html__( 'Enter plain text.', 'etbs-order-note-templates' )
			. ' '
			. esc_html__( 'Placeholders are replaced with the data of the order when you insert the template on the order edit screen.', 'etbs-order-note-templates' )
			. ' '
			. esc_html__( 'HTML tags are removed when the template is saved.', 'etbs-order-note-templates' )
			. '</p>';
		// The heavier warning gets its own paragraph so it is not buried at the end.
		// 重い注意は別の段落にして、末尾に埋もれさせない.
		echo '<p class="description">'
			. esc_html__( 'If you add the note as a "Note to customer", it is emailed to the customer, so check the text before you click "Add note".', 'etbs-order-note-templates' )
			. '</p>';
	}
}

if ( ! function_exists( 'ormm_render_tags_box' ) ) {
	/**
	 * Prints the meta box that lists the available merge tags.
	 * 利用できる差し込みタグの一覧のメタボックスを出力する。
	 *
	 * @return void
	 */
	function ormm_render_tags_box() {
		echo '<table class="widefat striped"><tbody>';
		foreach ( ormm_get_tag_descriptions() as $tag => $desc ) {
			echo '<tr><td><code>' . esc_html( $tag ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">'
			. sprintf(
				/* translators: %s: name of the filter hook, wrapped in a code tag. Do not translate it. */
				esc_html__( 'For developers: you can add your own placeholders with the %s filter.', 'etbs-order-note-templates' ),
				'<code>ormm_tags</code>'
			)
			. '</p>';
	}
}

/*-------------------------------------------*/
/* テンプレート本文の保存
/* エディタ非対応CPTのため、メタボックスの値を post_content に書き戻す。
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_save_template' ) ) {
	function ormm_save_template( $post_id, $post ) {
		if ( ! isset( $_POST['ormm_content_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ormm_content_nonce'] ) ), 'ormm_save_content' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( ! isset( $_POST['ormm_content'] ) ) { return; }

		$content = sanitize_textarea_field( wp_unslash( $_POST['ormm_content'] ) );
		if ( $content === $post->post_content ) { return; }

		remove_action( 'save_post_ormm_template', 'ormm_save_template', 10 );
		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $content,
		] );
		add_action( 'save_post_ormm_template', 'ormm_save_template', 10, 2 );
	}
	add_action( 'save_post_ormm_template', 'ormm_save_template', 10, 2 );
}

/*-------------------------------------------*/
/* 注文編集画面の判定
/* 従来（post.php + shop_order）とHPOS（admin.php?page=wc-orders）の両対応。
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_get_current_order_id' ) ) {
	function ormm_get_current_order_id( $hook ) {
		if ( 'post.php' === $hook ) {
			$post_id = (int) ( $_GET['post'] ?? 0 );
			if ( $post_id && 'shop_order' === get_post_type( $post_id ) ) {
				return $post_id;
			}
		}
		if ( 'woocommerce_page_wc-orders' === $hook ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
			if ( 'edit' === $action ) {
				return (int) ( $_GET['id'] ?? 0 );
			}
		}
		return 0;
	}
}

/*-------------------------------------------*/
/* 注文編集画面へのスクリプト読み込み
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_enqueue_order_script' ) ) {
	/**
	 * Loads the insert script on the order edit screen.
	 * 注文編集画面に、挿入用のスクリプトを読み込む。
	 *
	 * @param string $hook Hook suffix of the current admin screen.
	 * @return void
	 */
	function ormm_enqueue_order_script( $hook ) {
		$order_id = ormm_get_current_order_id( $hook );
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) { return; }
		if ( ! current_user_can( 'edit_shop_orders' ) ) { return; }

		$templates = ormm_get_templates();
		if ( ! $templates ) { return; }

		$list = [];
		foreach ( $templates as $template ) {
			$list[] = [
				'id'    => (int) $template->ID,
				'title' => $template->post_title,
			];
		}

		wp_enqueue_script(
			'ormm-admin',
			plugins_url( 'js/ordermemo-admin.js', __FILE__ ),
			[],
			ETBS_ONT_VERSION,
			true
		);

		wp_localize_script( 'ormm-admin', 'OrmmData', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'ormm_nonce' ),
			'orderId'   => $order_id,
			'templates' => $list,
			'i18n'      => [
				'selectLabel'  => __( 'Insert from template', 'etbs-order-note-templates' ),
				'placeholder'  => __( 'Select a template', 'etbs-order-note-templates' ),
				'insertButton' => __( 'Insert', 'etbs-order-note-templates' ),
				// One sentence per translatable string. / 翻訳文字列は 1 文ずつ.
				'insertError'  => __( 'Failed to load the template.', 'etbs-order-note-templates' ) . ' ' . __( 'Reload the page and try again.', 'etbs-order-note-templates' ),
			],
		] );
	}
	add_action( 'admin_enqueue_scripts', 'ormm_enqueue_order_script' );
}

/*-------------------------------------------*/
/* AJAX: テンプレート本文を注文データで展開して返す
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_ajax_render_template' ) ) {
	/**
	 * AJAX handler: returns a template body with the order's data filled in.
	 * AJAX の受け口。テンプレート本文を、注文のデータで展開して返す。
	 *
	 * @return void
	 */
	function ormm_ajax_render_template() {
		check_ajax_referer( 'ormm_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to insert templates.', 'etbs-order-note-templates' ) . ' ' . __( 'Ask a site administrator to grant you access.', 'etbs-order-note-templates' ) ) );
		}
		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'etbs-order-note-templates' ) . ' ' . __( 'Activate WooCommerce and try again.', 'etbs-order-note-templates' ) ) );
		}

		$template_id = (int) ( $_POST['template_id'] ?? 0 );
		$order_id    = (int) ( $_POST['order_id'] ?? 0 );

		// The template is checked before the order, as before, so the messages stay the same.
		// 従来どおりテンプレート、注文の順に確かめる（メッセージを変えないため）.
		$template = ormm_get_publishable_template( $template_id );
		if ( is_wp_error( $template ) ) {
			wp_send_json_error( array( 'message' => $template->get_error_message() . ' ' . __( 'Reload the page and try again.', 'etbs-order-note-templates' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'The order could not be found; it may have been deleted.', 'etbs-order-note-templates' ) . ' ' . __( 'Reload the page and try again.', 'etbs-order-note-templates' ) ) );
		}

		$text = ormm_render_template( $template, $order );
		if ( is_wp_error( $text ) ) {
			wp_send_json_error( array( 'message' => $text->get_error_message() . ' ' . __( 'Reload the page and try again.', 'etbs-order-note-templates' ) ) );
		}
		wp_send_json_success( array( 'text' => $text ) );
	}
	add_action( 'wp_ajax_ormm_render_template', 'ormm_ajax_render_template' );
}

/*-------------------------------------------*/
/* Notice about the paid version (OrderMemo Pro): Japanese sites only
/* 有料版（OrderMemo Pro）の案内：日本語サイトだけ
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_is_japanese_site' ) ) {
	/**
	 * Tells whether the site language is Japanese (the locale starts with "ja").
	 * サイトの表示言語が日本語（ロケールが ja で始まる）かを返す。
	 *
	 * strpos() is used on purpose: str_starts_with() is PHP 8 only, and this plugin declares PHP 7.4.
	 * strpos() を使うのは意図的。str_starts_with() は PHP 8 の関数で、宣言している PHP 7.4 では使えない。
	 *
	 * @return bool True when the locale is ja, ja_JP, and so on.
	 */
	function ormm_is_japanese_site() {
		return 0 === strpos( (string) get_locale(), 'ja' );
	}
}

if ( ! function_exists( 'ormm_get_pro_promotion_url' ) ) {
	/**
	 * Builds the link to the OrderMemo Pro page, with UTM parameters. Private: not a public API.
	 * OrderMemo Pro のページへのリンクを、UTM 付きで組み立てる。private：公開 API ではない。
	 *
	 * The base URL is a placeholder until the sales page is decided. It is kept in this one place,
	 * and deliberately not exposed as a filter (a filter would be one more frozen contract).
	 * 基の URL は販売ページが決まるまでの仮置き。ここ1か所だけに置き、フィルターにはしない
	 * （フィルターにすると凍結する契約が増えるため）。
	 *
	 * @access private
	 *
	 * @param string $content Where the link is shown: 'plugin-row' or 'template-list'. Goes to utm_content.
	 * @return string URL. Escape it with esc_url() on output. / 出力時に esc_url() を通すこと。
	 */
	function ormm_get_pro_promotion_url( $content ) {
		// TODO: 販売ページの URL は未確定。決まったらこの1か所を差し替える.
		$base_url = 'https://etbs.jp/product/ordermemo-pro/';

		return add_query_arg(
			array(
				'utm_source'   => 'etbs-order-note-templates',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'ordermemo-pro',
				'utm_content'  => $content,
			),
			$base_url
		);
	}
}

if ( ! function_exists( 'ormm_get_pro_promotion_link' ) ) {
	/**
	 * Builds an <a> element to the OrderMemo Pro page that opens in a new tab.
	 * 新しいタブで開く、OrderMemo Pro のページへの <a> 要素を組み立てる。
	 *
	 * @access private
	 *
	 * @param string $text    Link text, already translated. / 翻訳済みのリンク文言。
	 * @param string $content Where the link is shown (utm_content). / リンクを出す場所（utm_content）。
	 * @return string HTML of the link, escaped. / エスケープ済みのリンクの HTML。
	 */
	function ormm_get_pro_promotion_link( $text, $content ) {
		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( ormm_get_pro_promotion_url( $content ) ),
			esc_html( $text ),
			// Reuses WordPress core's translation. / WordPress 本体の翻訳を流用する.
			esc_html__( '(opens in a new tab)' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		);
	}
}

if ( ! function_exists( 'ormm_is_pro_promotion_visible' ) ) {
	/**
	 * Tells whether the notice about the paid version may be shown: a Japanese site, and not hidden by the filter.
	 * 有料版の案内を出してよいかを返す：日本語サイトで、かつフィルターで隠されていない。
	 *
	 * @return bool True to show the notice.
	 */
	function ormm_is_pro_promotion_visible() {
		return ormm_is_japanese_site() && ormm_should_show_pro_promotion();
	}
}

/*-------------------------------------------*/
/* Links in the plugins list row
/* プラグイン一覧行のリンク
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_plugin_row_meta' ) ) {
	/**
	 * Adds the links to this plugin's row in the plugins list.
	 * A link to OrderMemo Pro comes first, on Japanese sites only. "Support development" is always shown.
	 * プラグイン一覧の、このプラグインの行にリンクを足す。
	 * OrderMemo Pro へのリンクは日本語サイトにだけ、先頭に出す。「開発を支援」は常に出す。
	 *
	 * @param string[] $links Row meta links.
	 * @param string   $file  Plugin basename of the row.
	 * @return string[] Row meta links.
	 */
	function ormm_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( ETBS_ONT_PLUGIN_FILE ) !== $file ) {
			return $links;
		}
		if ( ormm_is_pro_promotion_visible() ) {
			$links[] = ormm_get_pro_promotion_link( __( 'OrderMemo Pro (paid add-on)', 'etbs-order-note-templates' ), 'plugin-row' );
		}
		$links[] = '<a href="' . esc_url( 'https://etbs.jp/product/donate/?utm_source=ordermemo&utm_medium=plugin' ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Support development', 'etbs-order-note-templates' ) . '</a>';
		return $links;
	}
	add_filter( 'plugin_row_meta', 'ormm_plugin_row_meta', 10, 2 );
}

/*-------------------------------------------*/
/* A paragraph under the template list
/* テンプレート一覧の表の下の1段落
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_render_pro_promotion_paragraph' ) ) {
	/**
	 * Prints one paragraph about OrderMemo Pro under the template list table.
	 * Not shown when no template is published yet, so that the first thing a new user sees is the way to create one.
	 * テンプレート一覧の表の下に、OrderMemo Pro の案内を1段落出す。
	 * 公開済みのテンプレートが0件のときは出さない（初めて開いた人に最初に見せるのは、最初のテンプレートを作る導線）。
	 *
	 * @param string $which Position of the tablenav: 'top' or 'bottom'.
	 * @return void
	 */
	function ormm_render_pro_promotion_paragraph( $which ) {
		if ( 'bottom' !== $which || ! ormm_is_pro_promotion_visible() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-ormm_template' !== $screen->id ) {
			return;
		}
		// wp_count_posts() is cached and needs no query per post. / wp_count_posts() はキャッシュされ、投稿ごとのクエリが要らない.
		$counts = wp_count_posts( 'ormm_template' );
		if ( empty( $counts->publish ) ) {
			return;
		}
		?>
		<p class="description">
			<?php
			esc_html_e( 'More automation is available with OrderMemo Pro, a paid add-on: add notes automatically when an order status changes, add notes to several orders at once from the order list, and insert tracking numbers.', 'etbs-order-note-templates' );
			echo ' ';
			echo wp_kses_post( ormm_get_pro_promotion_link( __( 'Learn more about OrderMemo Pro', 'etbs-order-note-templates' ), 'template-list' ) );
			?>
		</p>
		<?php
	}
	add_action( 'manage_posts_extra_tablenav', 'ormm_render_pro_promotion_paragraph' );
}

/*-------------------------------------------*/
/* Support link (footer of the template list and edit screens)
/* 支援リンク（テンプレート一覧・編集画面のフッター）
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_admin_footer_text' ) ) {
	/**
	 * Replaces the admin footer text on the template screens with a support link.
	 * テンプレートの画面のフッター文言を、支援リンクだけのものに差し替える。
	 *
	 * @param string $text Original footer text.
	 * @return string Footer text.
	 */
	function ormm_admin_footer_text( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'ormm_template' !== $screen->post_type ) { return $text; }
		$link = '<a href="' . esc_url( 'https://etbs.jp/product/donate/?utm_source=ordermemo&utm_medium=plugin' ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'consider supporting its development', 'etbs-order-note-templates' ) . '</a>';
		return sprintf(
			/* translators: %s: link to the donation page. The link text is "consider supporting its development". */
			esc_html__( 'If you find this plugin useful, %s.', 'etbs-order-note-templates' ),
			$link
		);
	}
	add_filter( 'admin_footer_text', 'ormm_admin_footer_text' );
}
