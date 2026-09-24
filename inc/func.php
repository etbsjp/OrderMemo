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
		return apply_filters( 'ormm_tags', $tags, $order );
	}
}

if ( ! function_exists( 'ormm_get_tag_descriptions' ) ) {
	/**
	 * Returns the description of each merge tag, keyed by tag.
	 * 差し込みタグごとの説明を、タグをキーにして返す。
	 *
	 * @return string[] Descriptions keyed by tag, such as "{order_number}".
	 */
	function ormm_get_tag_descriptions() {
		return [
			'{customer_name}'   => __( 'Billing name', 'etbs-order-note-templates' ),
			'{order_number}'    => __( 'Order number', 'etbs-order-note-templates' ),
			'{order_date}'      => __( 'Order date', 'etbs-order-note-templates' ),
			'{order_total}'     => __( 'Order total, with the currency symbol', 'etbs-order-note-templates' ),
			'{payment_method}'  => __( 'Payment method', 'etbs-order-note-templates' ),
			'{shipping_method}' => __( 'Shipping method', 'etbs-order-note-templates' ),
			'{site_name}'       => __( 'Site title', 'etbs-order-note-templates' ),
		];
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

		$templates = get_posts( [
			'post_type'   => 'ormm_template',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );
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

		$template = get_post( $template_id );
		if ( ! $template || 'ormm_template' !== $template->post_type || 'publish' !== $template->post_status ) {
			wp_send_json_error( array( 'message' => __( 'The template could not be found; it may have been deleted or unpublished.', 'etbs-order-note-templates' ) . ' ' . __( 'Reload the page and try again.', 'etbs-order-note-templates' ) ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'The order could not be found; it may have been deleted.', 'etbs-order-note-templates' ) . ' ' . __( 'Reload the page and try again.', 'etbs-order-note-templates' ) ) );
		}

		$text = strtr( $template->post_content, ormm_get_tags( $order ) );
		wp_send_json_success( array( 'text' => $text ) );
	}
	add_action( 'wp_ajax_ormm_render_template', 'ormm_ajax_render_template' );
}

/*-------------------------------------------*/
/* Support link (plugins list row)
/* 支援リンク（プラグイン一覧行）
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_plugin_row_meta' ) ) {
	/**
	 * Adds the support links to this plugin's row in the plugins list.
	 * プラグイン一覧の、このプラグインの行にサポート用のリンクを足す。
	 *
	 * @param string[] $links Row meta links.
	 * @param string   $file  Plugin basename of the row.
	 * @return string[] Row meta links.
	 */
	function ormm_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( ETBS_ONT_PLUGIN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="https://etbs.jp/product/donate/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'Support development', 'etbs-order-note-templates' ) . '</a>';
		return $links;
	}
	add_filter( 'plugin_row_meta', 'ormm_plugin_row_meta', 10, 2 );
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
