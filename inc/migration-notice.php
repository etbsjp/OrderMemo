<?php
/**
 * 公式版（WordPress.org）への入れ替え案内。
 *
 * この自社配布版（フォルダ ordermemo）と、WordPress.org の公式版
 * （ETBS Order Note Templates for WooCommerce・フォルダ etbs-order-note-templates）は
 * フォルダ名が違うため、WordPress 本体が片方をもう片方の更新として出すことは決してない。
 * 入れ替えは手で行うしかなく、それを既存のサイトへ伝えられる経路はこのファイルだけである。
 *
 * ★ 公式版は、この自社配布版が有効な間は自分を有効化しない／動かない（公式版の inc/legacy-guard.php）。
 *    そのため手順の順番は「公式版をインストール → 自社配布版を無効化 → 公式版を有効化 → 自社配布版を削除」。
 *    「公式版を先に有効化する」順番を案内に書かないこと（有効化が拒否される）。
 *
 * ★ ここで宣言する名前はすべて ormm_migration_ / ORMM_MIGRATION_ 接頭辞にしている。
 *    公式版の inc/func.php は ormm_ 接頭辞の関数を持つので、接頭辞の続きで重ならないようにするため。
 *
 * @package ordermemo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 公式版のスラッグ（WordPress.org のプラグインディレクトリ上の名前）。
 */
define( 'ORMM_MIGRATION_SUCCESSOR_SLUG', 'etbs-order-note-templates' );

/**
 * 公式版のメインファイル名。フォルダ名を変えて設置された場合も、このファイル名で見つける。
 */
define( 'ORMM_MIGRATION_SUCCESSOR_MAIN_FILE', 'etbs-order-note-templates.php' );

/**
 * 公式版の公開ページ。
 */
define( 'ORMM_MIGRATION_SUCCESSOR_URL', 'https://wordpress.org/plugins/etbs-order-note-templates/' );

/**
 * 公式版の表示名。日本語のサイトのプラグイン一覧に出る名前に合わせている。
 */
define( 'ORMM_MIGRATION_SUCCESSOR_NAME', 'ETBS Order Note Templates (OrderMemo)' );

/**
 * 公式版の英語名。サイトの言語が日本語でないとき、プラグイン一覧にはこちらが出る。
 */
define( 'ORMM_MIGRATION_SUCCESSOR_NAME_EN', 'ETBS Order Note Templates for WooCommerce' );

/**
 * 「今後表示しない」をユーザーごとに記録するユーザーメタのキー。
 *
 * オプションではなくユーザーメタにしているのは意図的。オプションにすると、
 * 1人が消した時点で他の管理者からも案内が消える。
 */
define( 'ORMM_MIGRATION_DISMISSED_META', 'ormm_migration_notice_dismissed' );

/**
 * 「今後表示しない」リンクが使うアクション名（admin-post.php 経由）。
 */
define( 'ORMM_MIGRATION_DISMISS_ACTION', 'ormm_migration_dismiss_notice' );

if ( ! function_exists( 'ormm_migration_find_successor' ) ) {
	/**
	 * プラグインの basename の一覧から、公式版を探す。
	 *
	 * 既定のパスだけでなく、末尾が "/etbs-order-note-templates.php" のものを拾うので、
	 * フォルダ名を変えて設置された公式版（zip を手でアップロードした場合など）も見つかる。
	 * この自社配布版のメインファイル（ordermemo/ordermemo.php）はこの末尾を持たないため、
	 * 自分自身に一致することはない。
	 *
	 * @param string[] $plugins プラグインの basename（例: "akismet/akismet.php"）の配列。
	 * @return string 公式版の basename。一覧に無ければ空文字。
	 */
	function ormm_migration_find_successor( $plugins ) {
		$suffix = '/' . ORMM_MIGRATION_SUCCESSOR_MAIN_FILE;

		foreach ( (array) $plugins as $plugin ) {
			$plugin = (string) $plugin;

			// 末尾の比較は、手で数えた数字ではなく接尾辞そのものの長さで切る。
			if ( substr( $plugin, -strlen( $suffix ) ) === $suffix ) {
				return $plugin;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'ormm_migration_is_successor_installed' ) ) {
	/**
	 * 公式版がこのサイトに設置済みかどうかを返す。
	 *
	 * 「有効か」ではなく設置済みプラグインの一覧を見る。公式版はこの自社配布版が有効な間は
	 * 有効化できないので、「有効か」だけで判定すると、続きの手順の案内が最も必要なサイト
	 * （インストールだけ済ませたサイト）で「未設置」と同じ扱いになってしまう。
	 *
	 * @return bool 公式版のメインファイルを持つプラグインが設置されていれば true。
	 */
	function ormm_migration_is_successor_installed() {
		// get_plugins() は wp-admin/includes/plugin.php にあり、管理画面以外では自動で読み込まれない。
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return '' !== ormm_migration_find_successor( array_keys( get_plugins() ) );
	}
}

if ( ! function_exists( 'ormm_migration_is_successor_active' ) ) {
	/**
	 * 公式版がこのサイト、またはネットワーク全体で有効かどうかを返す。
	 *
	 * 公式版が有効でも、この自社配布版が有効な間は公式版は動かない（休止する）。
	 * ここで見ているのは「有効化の操作が済んでいるか」であって「動いているか」ではない。
	 *
	 * @return bool 公式版が有効化されていれば true。
	 */
	function ormm_migration_is_successor_active() {
		$active = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			// ネットワーク有効化されたプラグインは値ではなく配列のキーとして保存される。
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		return '' !== ormm_migration_find_successor( $active );
	}
}

if ( ! function_exists( 'ormm_migration_decide_notice' ) ) {
	/**
	 * 3つの条件から、出すべき案内の種類を決める。
	 *
	 * 入れ替えを始めたサイト（公式版が設置済み）への案内は、途中で止まると困る手順なので、
	 * 最初の案内を「今後表示しない」で消した人にも出す。消せるのは最初の案内だけ。
	 *
	 * @param bool $successor_installed 公式版が設置済みか。
	 * @param bool $successor_active    公式版が有効化されているか。
	 * @param bool $dismissed           現在のユーザーが最初の案内を消しているか。
	 * @return string 'announce'（公式版が未設置）／'continue'（公式版が設置済みで未有効）／
	 *                'deactivate'（公式版も有効）／ ''（何も出さない）。
	 */
	function ormm_migration_decide_notice( $successor_installed, $successor_active, $dismissed ) {
		// 有効化されているなら設置もされている。設置の判定が取りこぼしても、この案内を優先する。
		if ( $successor_active ) {
			return 'deactivate';
		}

		if ( $successor_installed ) {
			return 'continue';
		}

		return $dismissed ? '' : 'announce';
	}
}

if ( ! function_exists( 'ormm_migration_is_notice_screen' ) ) {
	/**
	 * 管理画面のスクリーンが、案内を出す画面かどうかを返す。
	 *
	 * 出すのはプラグイン一覧（サイト・ネットワーク）とテンプレート一覧だけ。全画面には出さない。
	 * 注文の処理中に毎回目に入る場所へ出すと、日々の作業の邪魔になるため。
	 *
	 * @param string $screen_id スクリーン ID（get_current_screen()->id）。
	 * @return bool 案内を出す画面なら true。
	 */
	function ormm_migration_is_notice_screen( $screen_id ) {
		return in_array( $screen_id, array( 'plugins', 'plugins-network', 'edit-ormm_template' ), true );
	}
}

if ( ! function_exists( 'ormm_migration_current_user_may_see' ) ) {
	/**
	 * 現在のユーザーに案内を見せてよいかを返す。
	 *
	 * マルチサイトでは activate_plugins が manage_network_plugins を要求することがあり、
	 * activate_plugins だけで判定するとサイト管理者に案内が届かない。そのため manage_options も認める。
	 * 「今後表示しない」の処理も同じ判定を使う（見えるのに消せない状態を作らないため）。
	 *
	 * @return bool 見せてよければ true。
	 */
	function ormm_migration_current_user_may_see() {
		return current_user_can( 'activate_plugins' ) || current_user_can( 'manage_options' );
	}
}

if ( ! function_exists( 'ormm_migration_get_current_notice' ) ) {
	/**
	 * いま描画している画面と現在のユーザーに対して、出すべき案内の種類を返す。
	 *
	 * @return string ormm_migration_decide_notice() と同じ値。対象外の画面・ユーザーなら空文字。
	 */
	function ormm_migration_get_current_notice() {
		// 権限の無いユーザーには何も出さない。
		if ( ! ormm_migration_current_user_may_see() ) {
			return '';
		}

		// 対象の画面でなければ何も出さない。
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! ormm_migration_is_notice_screen( $screen->id ) ) {
			return '';
		}

		return ormm_migration_decide_notice(
			ormm_migration_is_successor_installed(),
			ormm_migration_is_successor_active(),
			(bool) get_user_meta( get_current_user_id(), ORMM_MIGRATION_DISMISSED_META, true )
		);
	}
}

if ( ! function_exists( 'ormm_migration_get_install_url' ) ) {
	/**
	 * 公式版を入手するためのリンク先を返す。
	 *
	 * プラグインを追加できるユーザーには、管理画面内のプラグイン詳細（スラッグ直指定）を渡す。
	 * スラッグ直指定なので、ディレクトリの検索結果に出るかどうかに左右されない。
	 * 追加できないユーザーには公開ページを渡す。
	 *
	 * @return string URL。出力時は esc_url() を通すこと。
	 */
	function ormm_migration_get_install_url() {
		if ( current_user_can( 'install_plugins' ) ) {
			// WordPress 本体はこのタブで IFRAME_REQUEST を定義するため、素のリンクで開くと
			// 管理メニューの無いページになる。TB_iframe を付けると本体と同じモーダルで開き、
			// スクリプトが読めなかった場合でもリンクとしては機能する。
			// マルチサイトではプラグインの追加はネットワーク管理画面でしか行えないので、そちらの URL にする。
			$path = 'plugin-install.php?tab=plugin-information&plugin=' . ORMM_MIGRATION_SUCCESSOR_SLUG
				. '&TB_iframe=true&width=772&height=622';

			return is_multisite() ? network_admin_url( $path ) : admin_url( $path );
		}

		return ORMM_MIGRATION_SUCCESSOR_URL;
	}
}

if ( ! function_exists( 'ormm_migration_enqueue_thickbox' ) ) {
	/**
	 * 最初の案内を出す画面で、プラグイン詳細のモーダルに必要なスクリプトを読み込む。
	 *
	 * プラグイン一覧では本体が既に読み込んでいるので、実質はテンプレート一覧のためのもの。
	 *
	 * @return void
	 */
	function ormm_migration_enqueue_thickbox() {
		// モーダルで開くリンクがあるのは、最初の案内を、プラグインを追加できるユーザーに出すときだけ。
		if ( ! current_user_can( 'install_plugins' ) || 'announce' !== ormm_migration_get_current_notice() ) {
			return;
		}

		add_thickbox();

		// 本体の plugins.php は add_thickbox() と一緒にこれも読み込む。無いとモーダルは開くが、
		// 本体と同じリサイズとフォーカス制御が付かない。
		wp_enqueue_script( 'plugin-install' );
	}
	add_action( 'admin_enqueue_scripts', 'ormm_migration_enqueue_thickbox' );
}

if ( ! function_exists( 'ormm_migration_get_dismiss_url' ) ) {
	/**
	 * 「今後表示しない」リンクの URL を返す。
	 *
	 * @return string admin-post.php を指す nonce 付きの URL。出力時は esc_url() を通すこと。
	 */
	function ormm_migration_get_dismiss_url() {
		$url = admin_url( 'admin-post.php?action=' . ORMM_MIGRATION_DISMISS_ACTION );

		// 現在の画面を明示的に持たせる。HTTP_REFERER だけに頼ると、それを送らないブラウザでは
		// 元の画面ではなくプラグイン一覧に戻されてしまう。
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$current_request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$url                 = add_query_arg( '_wp_http_referer', rawurlencode( $current_request_uri ), $url );
		}

		return wp_nonce_url( $url, ORMM_MIGRATION_DISMISS_ACTION );
	}
}

if ( ! function_exists( 'ormm_migration_get_plugins_screen_link' ) ) {
	/**
	 * プラグイン一覧へのリンクを、エスケープ済みの HTML で返す。
	 *
	 * @return string そのまま出力できる a 要素。
	 */
	function ormm_migration_get_plugins_screen_link() {
		return '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">'
			. esc_html__( 'プラグイン一覧', 'ordermemo' ) . '</a>';
	}
}

if ( ! function_exists( 'ormm_migration_render_notice' ) ) {
	/**
	 * 状態に応じた入れ替え案内を出力する。
	 *
	 * @return void
	 */
	function ormm_migration_render_notice() {
		// 出すべき案内の種類を決め、対応する出力関数に振り分ける。
		switch ( ormm_migration_get_current_notice() ) {
			case 'announce':
				ormm_migration_render_announcement();
				break;
			case 'continue':
				ormm_migration_render_continue_steps();
				break;
			case 'deactivate':
				ormm_migration_render_deactivate_request();
				break;
		}
	}
	add_action( 'admin_notices', 'ormm_migration_render_notice' );
	// WordPress 本体はネットワーク管理画面では admin_notices を発火しない。ネットワークの
	// プラグイン一覧は、特権管理者がこのプラグインの行を見る唯一の画面なので、専用のフックにも登録する。
	add_action( 'network_admin_notices', 'ormm_migration_render_notice' );
}

if ( ! function_exists( 'ormm_migration_render_announcement' ) ) {
	/**
	 * 公式版が未設置のサイトに出す、入れ替えの案内を出力する（状態1）。
	 *
	 * @return void
	 */
	function ormm_migration_render_announcement() {
		// 製品名は固有名詞なので翻訳関数に通さない。モーダルで開けるのはプラグインを追加できるユーザーだけ。
		$link_attributes = current_user_can( 'install_plugins' )
			? ' class="thickbox open-plugin-details-modal"'
			: ' target="_blank" rel="noopener noreferrer"';
		$successor_link  = '<a href="' . esc_url( ormm_migration_get_install_url() ) . '"' . $link_attributes . '><strong>'
			. esc_html( ORMM_MIGRATION_SUCCESSOR_NAME ) . '</strong></a>';
		?>
		<div class="notice notice-info">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: 公式版の表示名 */
						esc_html__( 'ETBS OrderMemo は、WordPress.org の公式プラグイン「%s」になりました。', 'ordermemo' ),
						esc_html( ORMM_MIGRATION_SUCCESSOR_NAME )
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				esc_html_e( '同じ作者による同じプラグインで、公式ディレクトリに掲載するために名前を変えたものです。', 'ordermemo' );
				esc_html_e( '今後の更新は公式版で届きます。', 'ordermemo' );
				esc_html_e( 'この ETBS OrderMemo の更新は、この版が最後です。', 'ordermemo' );
				?>
			</p>
			<p>
				<strong><?php esc_html_e( '入れ替えの手順（登録済みのテンプレートはそのまま引き継がれます）', 'ordermemo' ); ?></strong>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: 公式版へのリンク */
						esc_html__( '公式版 %s をインストールします。', 'ordermemo' ),
						$successor_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 上で esc_url() と esc_html() を通して組み立てている。
					);
					esc_html_e( 'ここではインストールだけを行い、有効化は手順3で行います。', 'ordermemo' );
					?>
				</li>
				<li><?php esc_html_e( 'プラグイン一覧で、この ETBS OrderMemo を無効化します。', 'ordermemo' ); ?></li>
				<li><?php esc_html_e( '続けて、公式版を有効化します。', 'ordermemo' ); ?></li>
				<li>
					<?php
					esc_html_e( 'ETBS OrderMemo を削除します。', 'ordermemo' );
					esc_html_e( '削除してもテンプレートは消えません。', 'ordermemo' );
					?>
				</li>
			</ol>
			<p>
				<?php
				esc_html_e( '手順2から手順3までの間は、注文編集画面の「テンプレートから挿入」が使えません。', 'ordermemo' );
				esc_html_e( '手順2と手順3は続けて行ってください。', 'ordermemo' );
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: 公式版の英語名 */
					esc_html__( 'サイトの言語が日本語以外のときは、公式版は「%s」という名前で表示されます。', 'ordermemo' ),
					esc_html( ORMM_MIGRATION_SUCCESSOR_NAME_EN )
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( ormm_migration_get_dismiss_url() ); ?>">
					<?php esc_html_e( '今後表示しない', 'ordermemo' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ormm_migration_render_continue_steps' ) ) {
	/**
	 * 公式版が設置済みで、まだ有効化されていないサイトに出す、続きの手順を出力する（状態2）。
	 *
	 * 公式版はこの自社配布版が有効な間は有効化できない。この状態で「有効化」を押すと拒否されるので、
	 * 先に無効化することを、こちら側からはっきり伝える。
	 *
	 * @return void
	 */
	function ormm_migration_render_continue_steps() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: 公式版の表示名 */
						esc_html__( '公式版「%s」はインストール済みです。', 'ordermemo' ),
						esc_html( ORMM_MIGRATION_SUCCESSOR_NAME )
					);
					esc_html_e( '入れ替えの続きを行ってください。', 'ordermemo' );
					?>
				</strong>
			</p>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: プラグイン一覧へのリンク */
						esc_html__( '%sで、この ETBS OrderMemo を無効化します。', 'ordermemo' ),
						ormm_migration_get_plugins_screen_link() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 関数内で esc_url() と esc_html__() を通して組み立てている。
					);
					esc_html_e( '公式版は、ETBS OrderMemo が有効な間は有効化できません。', 'ordermemo' );
					?>
				</li>
				<li><?php esc_html_e( '続けて、公式版を有効化します。', 'ordermemo' ); ?></li>
				<li>
					<?php
					esc_html_e( 'ETBS OrderMemo を削除します。', 'ordermemo' );
					esc_html_e( '削除してもテンプレートは消えません。', 'ordermemo' );
					?>
				</li>
			</ol>
			<p>
				<?php
				esc_html_e( '登録済みのテンプレートはそのまま引き継がれます。', 'ordermemo' );
				esc_html_e( '手順1から手順2までの間は、注文編集画面の「テンプレートから挿入」が使えません。', 'ordermemo' );
				esc_html_e( '手順1と手順2は続けて行ってください。', 'ordermemo' );
				?>
			</p>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ormm_migration_render_deactivate_request' ) ) {
	/**
	 * 公式版も有効化されているサイトに出す、無効化のお願いを出力する（状態3）。
	 *
	 * 公式版も同じ趣旨の通知を自分の側から出すが、こちらからも言う。どちらのプラグインの
	 * 通知を読んでも、次にすることが同じ（ETBS OrderMemo を無効化する）になるようにしておく。
	 *
	 * @return void
	 */
	function ormm_migration_render_deactivate_request() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: 公式版の表示名 */
						esc_html__( '公式版「%s」は有効化されていますが、この ETBS OrderMemo が有効な間は動きません。', 'ordermemo' ),
						esc_html( ORMM_MIGRATION_SUCCESSOR_NAME )
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: プラグイン一覧へのリンク */
					esc_html__( '%sで、この ETBS OrderMemo を無効化してください。', 'ordermemo' ),
					ormm_migration_get_plugins_screen_link() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 関数内で esc_url() と esc_html__() を通して組み立てている。
				);
				esc_html_e( '無効化すると、公式版が動き始めます。', 'ordermemo' );
				esc_html_e( '登録済みのテンプレートはそのまま引き継がれます。', 'ordermemo' );
				?>
			</p>
			<p>
				<?php
				esc_html_e( 'そのあと、ETBS OrderMemo は削除できます。', 'ordermemo' );
				esc_html_e( '削除してもテンプレートは消えません。', 'ordermemo' );
				?>
			</p>
		</div>
		<?php
	}
}

if ( ! function_exists( 'ormm_migration_handle_dismiss' ) ) {
	/**
	 * 「今後表示しない」を現在のユーザーに記録し、元の画面へ戻す。
	 *
	 * @return void
	 */
	function ormm_migration_handle_dismiss() {
		// 案内を見られるユーザーだけが消せる。
		if ( ! ormm_migration_current_user_may_see() ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'ordermemo' ), '', array( 'response' => 403 ) );
		}

		// nonce を確かめる。失敗した場合は本体がここで処理を止める。
		check_admin_referer( ORMM_MIGRATION_DISMISS_ACTION );

		update_user_meta( get_current_user_id(), ORMM_MIGRATION_DISMISSED_META, 1 );

		// 元の画面へ戻す。戻り先が分からなければプラグイン一覧へ。
		$return_url = wp_get_referer();
		wp_safe_redirect( $return_url ? $return_url : admin_url( 'plugins.php' ) );
		exit;
	}
	add_action( 'admin_post_' . ORMM_MIGRATION_DISMISS_ACTION, 'ormm_migration_handle_dismiss' );
}

if ( ! function_exists( 'ormm_migration_plugin_row_meta' ) ) {
	/**
	 * プラグイン一覧のこのプラグインの行に、公式版への常設リンクを足す。
	 *
	 * このリンクは意図的に消せないようにしている。急かす要素が無く場所も取らないので、
	 * 上の案内を「今後表示しない」で消した後も、静かな道しるべとして残す。
	 *
	 * @param string[] $links この行に登録済みのリンク。
	 * @param string   $file  行が属するプラグインの basename。
	 * @return string[] リンクの配列。自分の行なら末尾に1件足したもの。
	 */
	function ormm_migration_plugin_row_meta( $links, $file ) {
		// 自分の行でなければ何もしない。
		if ( plugin_basename( ORMM_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( ORMM_MIGRATION_SUCCESSOR_URL ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( '公式版（WordPress.org）へ入れ替える', 'ordermemo' ) . '</a>';

		return $links;
	}
	// 既存の「開発を支援」「開発のご依頼」の後ろに並べるため、優先度 11 で登録する。
	add_filter( 'plugin_row_meta', 'ormm_migration_plugin_row_meta', 11, 2 );
}
