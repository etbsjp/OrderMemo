<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
echo $_GET['probe'];

/*-------------------------------------------*/
/* WooCommerce 未導入時の通知
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_admin_notice_requires_wc' ) ) {
	function ormm_admin_notice_requires_wc() {
		if ( class_exists( 'WooCommerce' ) ) { return; }
		if ( ! current_user_can( 'activate_plugins' ) ) { return; }
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'OrderMemoの利用にはWooCommerceが必要です。WooCommerceをインストール・有効化してください。', 'ordermemo' )
			. '</p></div>';
	}
	add_action( 'admin_notices', 'ormm_admin_notice_requires_wc' );
}

/*-------------------------------------------*/
/* HPOS（高性能注文ストレージ）互換の宣言
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_declare_hpos_compat' ) ) {
	function ormm_declare_hpos_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ORMM_PLUGIN_FILE, true );
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
	function ormm_register_post_type() {
		register_post_type( 'ormm_template', [
			'labels' => [
				'name'               => __( '注文メモテンプレート', 'ordermemo' ),
				// メニュー名は「注文」で始めない。WooCommerceが処理中件数バッジを
				// 「注文」前方一致の最初のサブメニューに付けるため、横取りしてしまう。
				'menu_name'          => __( 'メモテンプレート', 'ordermemo' ),
				'singular_name'      => __( '注文メモテンプレート', 'ordermemo' ),
				'add_new'            => __( '新規追加', 'ordermemo' ),
				'add_new_item'       => __( 'テンプレートを追加', 'ordermemo' ),
				'edit_item'          => __( 'テンプレートを編集', 'ordermemo' ),
				'new_item'           => __( '新規テンプレート', 'ordermemo' ),
				'search_items'       => __( 'テンプレートを検索', 'ordermemo' ),
				'not_found'          => __( 'テンプレートが見つかりません', 'ordermemo' ),
				'not_found_in_trash' => __( 'ゴミ箱にテンプレートはありません', 'ordermemo' ),
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
	function ormm_get_tag_descriptions() {
		return [
			'{customer_name}'   => __( '請求先の氏名', 'ordermemo' ),
			'{order_number}'    => __( '注文番号', 'ordermemo' ),
			'{order_date}'      => __( '注文日', 'ordermemo' ),
			'{order_total}'     => __( '合計金額（通貨記号付き）', 'ordermemo' ),
			'{payment_method}'  => __( '支払方法', 'ordermemo' ),
			'{shipping_method}' => __( '配送方法', 'ordermemo' ),
			'{site_name}'       => __( 'サイト名', 'ordermemo' ),
		];
	}
}

/*-------------------------------------------*/
/* テンプレート編集画面のメタボックス
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_add_meta_boxes' ) ) {
	function ormm_add_meta_boxes() {
		add_meta_box(
			'ormm_content_box',
			__( 'テンプレート本文', 'ordermemo' ),
			'ormm_render_content_box',
			'ormm_template',
			'normal',
			'high'
		);
		add_meta_box(
			'ormm_tags_box',
			__( '利用できる差し込みタグ', 'ordermemo' ),
			'ormm_render_tags_box',
			'ormm_template',
			'side',
			'default'
		);
	}
	add_action( 'add_meta_boxes_ormm_template', 'ormm_add_meta_boxes' );
}

if ( ! function_exists( 'ormm_render_content_box' ) ) {
	function ormm_render_content_box( $post ) {
		wp_nonce_field( 'ormm_save_content', 'ormm_content_nonce' );
		echo '<textarea name="ormm_content" rows="10" style="width:100%;" placeholder="'
			. esc_attr__( '例：{customer_name} 様　ご注文番号 {order_number} の商品を本日発送いたしました。', 'ordermemo' )
			. '">' . esc_textarea( $post->post_content ) . '</textarea>';
		echo '<p class="description">'
			. esc_html__( 'プレーンテキストで入力してください。差し込みタグは注文編集画面での挿入時に、その注文のデータに展開されます。', 'ordermemo' )
			. '</p>';
	}
}

if ( ! function_exists( 'ormm_render_tags_box' ) ) {
	function ormm_render_tags_box() {
		echo '<table class="widefat striped"><tbody>';
		foreach ( ormm_get_tag_descriptions() as $tag => $desc ) {
			echo '<tr><td><code>' . esc_html( $tag ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">'
			. esc_html__( '開発者向け：ormm_tags フィルターで独自タグを追加できます。', 'ordermemo' )
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
			ORMM_VERSION,
			true
		);

		wp_localize_script( 'ormm-admin', 'OrmmData', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'ormm_nonce' ),
			'orderId'   => $order_id,
			'templates' => $list,
			'i18n'      => [
				'selectLabel'  => __( 'テンプレートから挿入', 'ordermemo' ),
				'placeholder'  => __( 'テンプレートを選択', 'ordermemo' ),
				'insertButton' => __( '挿入', 'ordermemo' ),
				'insertError'  => __( 'テンプレートの読み込みに失敗しました。ページを再読み込みしてやり直してください。', 'ordermemo' ),
			],
		] );
	}
	add_action( 'admin_enqueue_scripts', 'ormm_enqueue_order_script' );
}

/*-------------------------------------------*/
/* AJAX: テンプレート本文を注文データで展開して返す
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_ajax_render_template' ) ) {
	function ormm_ajax_render_template() {
		check_ajax_referer( 'ormm_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( [ 'message' => __( '権限がありません', 'ordermemo' ) ] );
		}
		if ( ! function_exists( 'wc_get_order' ) ) {
			wp_send_json_error( [ 'message' => __( 'WooCommerceが有効ではありません', 'ordermemo' ) ] );
		}

		$template_id = (int) ( $_POST['template_id'] ?? 0 );
		$order_id    = (int) ( $_POST['order_id'] ?? 0 );

		$template = get_post( $template_id );
		if ( ! $template || 'ormm_template' !== $template->post_type || 'publish' !== $template->post_status ) {
			wp_send_json_error( [ 'message' => __( 'テンプレートが見つかりません', 'ordermemo' ) ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( '注文が見つかりません', 'ordermemo' ) ] );
		}

		$text = strtr( $template->post_content, ormm_get_tags( $order ) );
		wp_send_json_success( [ 'text' => $text ] );
	}
	add_action( 'wp_ajax_ormm_render_template', 'ormm_ajax_render_template' );
}

/*-------------------------------------------*/
/* ダッシュボードウィジェット（使い方・サポート案内）
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_add_dashboard_widget' ) ) {
	function ormm_add_dashboard_widget() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) { return; }
		wp_add_dashboard_widget(
			'ormm_dashboard_widget',
			'OrderMemo',
			'ormm_render_dashboard_widget'
		);
	}
	add_action( 'wp_dashboard_setup', 'ormm_add_dashboard_widget' );
}

if ( ! function_exists( 'ormm_render_dashboard_widget' ) ) {
	function ormm_render_dashboard_widget() {
		$list_url = admin_url( 'edit.php?post_type=ormm_template' );
		?>
		<p>WooCommerceの注文編集画面で、登録済みテンプレートから定型文を「注文メモ」にワンクリックで挿入できます。</p>

		<strong>使い方</strong>
		<ul style="margin:6px 0 12px 1.2em;list-style:disc;">
			<li><strong>WooCommerce &gt; メモテンプレート</strong>でテンプレートを登録します。</li>
			<li>注文編集画面の「メモを追加」欄の上に表示されるセレクトから選んで「挿入」をクリックします。</li>
			<li>本文には差し込みタグが使えます：<code>{customer_name}</code> <code>{order_number}</code> など（挿入時にその注文のデータへ展開されます）。</li>
		</ul>

		<strong>注意事項</strong>
		<ul style="margin:6px 0 12px 1.2em;list-style:disc;">
			<li>「顧客へのメモ」はそのままメール送信されます。挿入後に文面を確認してから「追加」をクリックしてください。</li>
			<li>テンプレート本文はプレーンテキストとして保存されます（HTMLタグは保存時に除去されます）。</li>
		</ul>

		<strong>サポート</strong>
		<p style="margin:6px 0 12px;">有償サポートやカスタマイズは<a href="https://etbs.jp/product-category/wordpress-tools/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener">こちらのページ</a>からお問い合わせください。開発の継続は<a href="https://etbs.jp/product/donate/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener">ご支援</a>で応援いただけます。</p>

		<a href="<?php echo esc_url( $list_url ); ?>" class="button button-primary">メモテンプレート一覧を開く</a>
		<?php
	}
}

/*-------------------------------------------*/
/* 寄付・開発依頼リンク（プラグイン一覧行）
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_plugin_row_meta' ) ) {
	function ormm_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( ORMM_PLUGIN_FILE ) !== $file ) { return $links; }
		$links[] = '<a href="https://etbs.jp/product/donate/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener noreferrer">'
			. esc_html__( '開発を支援', 'ordermemo' ) . '</a>';
		$links[] = '<a href="https://etbs.jp/product-category/wordpress-tools/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener noreferrer">'
			. esc_html__( '開発のご依頼', 'ordermemo' ) . '</a>';
		return $links;
	}
	add_filter( 'plugin_row_meta', 'ormm_plugin_row_meta', 10, 2 );
}

/*-------------------------------------------*/
/* 寄付・開発依頼リンク（テンプレート一覧・編集画面のフッター）
/*-------------------------------------------*/
if ( ! function_exists( 'ormm_admin_footer_text' ) ) {
	function ormm_admin_footer_text( $text ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'ormm_template' !== $screen->post_type ) { return $text; }
		return 'OrderMemoが役に立ったら <a href="https://etbs.jp/product/donate/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener noreferrer">開発を支援</a>、カスタマイズは <a href="https://etbs.jp/product-category/wordpress-tools/?utm_source=ordermemo&utm_medium=plugin" target="_blank" rel="noopener noreferrer">開発のご依頼</a> からどうぞ。';
	}
	add_filter( 'admin_footer_text', 'ormm_admin_footer_text' );
}
