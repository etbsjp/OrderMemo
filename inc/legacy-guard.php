<?php
/**
 * Keeps this plugin and its predecessor (ETBS OrderMemo, folder "ordermemo") from running together.
 * このプラグインと前身（ETBS OrderMemo・フォルダ ordermemo）を同時に動かさないための処理。
 *
 * The predecessor shares this plugin's post type (ormm_template) and registers the same hooks, so the
 * two cannot run at the same time. Every name declared here uses the etbs_ont_ / ETBS_ONT_ prefix on
 * purpose: this file is read while the predecessor is still active, and a name shared with it would
 * either be a fatal error or make the predecessor's function_exists() guard skip silently. Nothing
 * here is wrapped in function_exists() for the same reason: a future collision should be a visible
 * error, not a silent skip.
 *
 * 前身は投稿タイプ（ormm_template）を共有し、同じフックを登録するため、両方を同時に動かすことは
 * できない。ここで宣言する名前はすべて意図的に etbs_ont_ / ETBS_ONT_ 接頭辞にしている。このファイルは
 * 前身が有効なままでも読み込まれ、前身と同じ名前だと Fatal になるか、前身の function_exists() ガードが
 * 黙ってスキップしてしまうため。function_exists() で包まないのも同じ理由で、将来の衝突は
 * 黙った無視ではなく、見えるエラーにしたい。
 *
 * @package etbs-order-note-templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basename of the predecessor's main file, as WordPress records it in active_plugins.
 * 前身のメインファイル。WordPress が active_plugins に記録する形式。
 */
define( 'ETBS_ONT_LEGACY_PLUGIN', 'ordermemo/ordermemo.php' );

/**
 * First predecessor version that no longer deletes every template when the plugin is deleted.
 * プラグインを削除してもテンプレートを全件消さなくなった、前身の最初の版数。
 */
define( 'ETBS_ONT_LEGACY_SAFE_DELETE_VERSION', '1.0.3' );

/**
 * Loads the WordPress plugin API when the current request has not loaded it yet.
 * 現在のリクエストで未読み込みなら、WordPress のプラグイン API を読み込む。
 *
 * get_plugin_data() and is_plugin_active_for_network() live in wp-admin/includes/plugin.php, which
 * only the admin loads on its own.
 * get_plugin_data() と is_plugin_active_for_network() は wp-admin/includes/plugin.php にあり、
 * 管理画面以外では自動では読み込まれない。
 *
 * @return void
 */
function etbs_ont_require_plugin_api() {
	if ( ! function_exists( 'get_plugin_data' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
}

/**
 * Returns the basenames of every plugin that is active on this site or across the network.
 * このサイト、またはネットワーク全体で有効なプラグインの basename を返す。
 *
 * @return string[] Plugin basenames such as "akismet/akismet.php".
 */
function etbs_ont_get_active_plugin_basenames() {
	$active = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		// Network-activated plugins are stored as array keys, not values.
		// ネットワーク有効化されたプラグインは値ではなく配列のキーとして保存される.
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	return array_map( 'strval', $active );
}

/**
 * Finds the predecessor in a list of plugin basenames.
 * プラグインの basename の一覧から、前身を探す。
 *
 * Matches the default path and also any folder name that ends in "/ordermemo.php", so a renamed folder
 * is found too. This plugin's own main file (etbs-order-note-templates/etbs-order-note-templates.php)
 * does not end in "/ordermemo.php", so it never matches itself.
 * 既定のパスに加えて、末尾が "/ordermemo.php" のものも拾うので、フォルダ名を変えて設置された前身も見つかる。
 * このプラグイン自身のメインファイル（etbs-order-note-templates/etbs-order-note-templates.php）は
 * 末尾が "/ordermemo.php" ではないため、自分自身に一致することはない。
 *
 * @param string[] $plugins Plugin basenames.
 * @return string The predecessor's basename, or an empty string when it is not in the list.
 */
function etbs_ont_find_legacy_plugin( $plugins ) {
	$suffix = '/ordermemo.php';

	foreach ( (array) $plugins as $plugin ) {
		$plugin = (string) $plugin;

		// Compare against the length of the suffix itself, not a number typed by hand.
		// 末尾の比較は、手で数えた数字ではなく接尾辞そのものの長さで切る.
		if ( ETBS_ONT_LEGACY_PLUGIN === $plugin || substr( $plugin, -strlen( $suffix ) ) === $suffix ) {
			return $plugin;
		}
	}

	return '';
}

/**
 * Returns the basename of the predecessor when it is active.
 * 前身が有効なら、その basename を返す。
 *
 * @return string The predecessor's basename, or an empty string when it is not active.
 */
function etbs_ont_get_active_legacy_plugin() {
	return etbs_ont_find_legacy_plugin( etbs_ont_get_active_plugin_basenames() );
}

/**
 * Reports whether the predecessor is currently active.
 * 前身が現在有効かどうかを返す。
 *
 * @return bool True if it is active on this site or across the network.
 */
function etbs_ont_is_legacy_active() {
	return '' !== etbs_ont_get_active_legacy_plugin();
}

/**
 * Reads a plugin's name and version from its main file header.
 * プラグインのメインファイルのヘッダから、名前と版数を読む。
 *
 * @param string $plugin_file Absolute path of the plugin's main file.
 * @return array Array with the keys "name" and "version". The name falls back to "OrderMemo" and the
 *               version to an empty string when the file cannot be read.
 */
function etbs_ont_get_plugin_details( $plugin_file ) {
	$details = array(
		'name'    => 'OrderMemo',
		'version' => '',
	);

	if ( ! is_readable( $plugin_file ) ) {
		return $details;
	}

	etbs_ont_require_plugin_api();

	// Do not markup or translate: translating here would load a text domain too early.
	// マークアップも翻訳もしない。ここで翻訳すると、早すぎる時点でテキストドメインを読んでしまう.
	$data = get_plugin_data( $plugin_file, false, false );

	if ( '' !== $data['Name'] ) {
		$details['name'] = $data['Name'];
	}
	$details['version'] = $data['Version'];

	return $details;
}

/**
 * Tells whether deleting the predecessor at this version deletes every template.
 * この版数の前身を削除すると、テンプレートが全件消えるかどうかを返す。
 *
 * A version that cannot be read counts as unsafe: the warning costs one sentence, while a missed
 * warning costs the user's templates.
 * 読み取れない版数は「消える」側として扱う。警告の代償は1文で済むが、警告の取りこぼしは
 * 利用者のテンプレートを失わせる。
 *
 * @param string $version Predecessor version, or an empty string when unknown.
 * @return bool True if the version is older than 1.0.3 or unknown.
 */
function etbs_ont_legacy_version_deletes_templates( $version ) {
	return '' === $version || version_compare( $version, ETBS_ONT_LEGACY_SAFE_DELETE_VERSION, '<' );
}

/**
 * Returns the warning about deleting an old predecessor, as escaped HTML sentences.
 * 古い前身を削除する場合の警告文を、エスケープ済みの文として返す。
 *
 * @param string $plugin_name Name of the predecessor plugin.
 * @return string[] Two sentences, safe to print as they are.
 */
function etbs_ont_get_legacy_deletion_warning( $plugin_name ) {
	return array(
		sprintf(
			/* translators: 1: Name of the old plugin. 2: Version number. */
			esc_html__( 'If you delete %1$s, update it to version %2$s or later first.', 'etbs-order-note-templates' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			esc_html( ETBS_ONT_LEGACY_SAFE_DELETE_VERSION )
		),
		esc_html__( 'Earlier versions delete all of your templates when the plugin is deleted.', 'etbs-order-note-templates' ),
	);
}

/**
 * Returns the explanation shared by the activation refusal and the admin notice, as escaped HTML.
 * 有効化の拒否画面と管理画面の通知に共通の説明を、エスケープ済みの HTML として返す。
 *
 * Recommends deactivating only. It never recommends deleting, because deleting an old version
 * removes every template.
 * 勧めるのは無効化だけで、削除は勧めない。古い版は削除するとテンプレートを全件消すため。
 *
 * @param string $plugin_name           Name of the predecessor plugin.
 * @param bool   $show_deletion_warning Whether to add the warning about deleting an old version.
 * @return string[] Paragraphs, each safe to print as it is.
 */
function etbs_ont_get_legacy_guidance( $plugin_name, $show_deletion_warning ) {
	$paragraphs = array(
		esc_html__( 'The two plugins share the same note templates, so only one of them can run at a time.', 'etbs-order-note-templates' ),
		sprintf(
			/* translators: %s: Name of the old plugin. */
			esc_html__( 'Deactivate %s to switch over.', 'etbs-order-note-templates' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>'
		) . ' ' . esc_html__( 'Your saved templates are kept.', 'etbs-order-note-templates' ),
	);

	if ( $show_deletion_warning ) {
		$paragraphs[] = implode( ' ', etbs_ont_get_legacy_deletion_warning( $plugin_name ) );
	}

	return $paragraphs;
}

/**
 * Builds the WordPress link that deactivates the predecessor.
 * 前身を無効化する WordPress 本体の URL を組み立てる。
 *
 * This is the same nonce-protected URL as the Deactivate link on the plugins list. A plugin that is
 * network-activated can only be deactivated from the network admin.
 * プラグイン一覧の「無効化」リンクと同じ、nonce 付きの URL。ネットワーク有効化されたプラグインは
 * ネットワーク管理画面からしか無効化できない。
 *
 * @param string $legacy_plugin The predecessor's basename.
 * @return string URL. Escape it with esc_url() when printing.
 */
function etbs_ont_get_legacy_deactivate_url( $legacy_plugin ) {
	etbs_ont_require_plugin_api();

	$plugins_url = ( is_multisite() && is_plugin_active_for_network( $legacy_plugin ) )
		? network_admin_url( 'plugins.php' )
		: admin_url( 'plugins.php' );

	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'deactivate',
				'plugin' => $legacy_plugin,
			),
			$plugins_url
		),
		'deactivate-plugin_' . $legacy_plugin
	);
}

/**
 * Refuses to activate this plugin while the predecessor is active.
 * 前身が有効なあいだは、このプラグインの有効化を拒否する。
 *
 * Hooked to this plugin's activation hook. WordPress fires that hook before it writes active_plugins,
 * so stopping here with wp_die() leaves this plugin inactive and active_plugins untouched.
 * このプラグインの有効化フックに掛ける。WordPress は active_plugins を書く前にこのフックを呼ぶため、
 * ここで wp_die() で止めれば、このプラグインは有効にならず active_plugins も変わらない。
 *
 * @return void
 */
function etbs_ont_block_activation_while_legacy_active() {
	$legacy_plugin = etbs_ont_get_active_legacy_plugin();
	if ( '' === $legacy_plugin ) {
		return;
	}

	// An activation request runs long after init, so the init hook has already been missed.
	// Load the bundled translations here directly, or this screen would always be in English.
	// 有効化のリクエストは init をとっくに過ぎているため、init に掛けた読み込みは間に合わない。
	// ここで直接読み込まないと、この画面は常に英語になる.
	etbs_ont_load_textdomain();

	$details    = etbs_ont_get_plugin_details( WP_PLUGIN_DIR . '/' . $legacy_plugin );
	$paragraphs = etbs_ont_get_legacy_guidance( $details['name'], etbs_ont_legacy_version_deletes_templates( $details['version'] ) );

	$message = '<p>' . sprintf(
		/* translators: %s: Name of the old plugin. */
		esc_html__( 'ETBS Order Note Templates for WooCommerce was not activated because %s is still active on this site.', 'etbs-order-note-templates' ),
		'<strong>' . esc_html( $details['name'] ) . '</strong>'
	) . '</p>';

	foreach ( $paragraphs as $paragraph ) {
		$message .= '<p>' . $paragraph . '</p>';
	}

	// The Deactivate button returns to the plugins list with this plugin still inactive, so say what comes next.
	// 無効化ボタンはこのプラグインが未有効のまま一覧へ戻るため、次の手順を伝える.
	$message .= '<p>' . esc_html__( 'After deactivating it, activate this plugin again.', 'etbs-order-note-templates' ) . '</p>';

	$message .= '<p><a class="button" href="' . esc_url( etbs_ont_get_legacy_deactivate_url( $legacy_plugin ) ) . '">'
		. sprintf(
			/* translators: %s: Name of the old plugin. */
			esc_html__( 'Deactivate %s', 'etbs-order-note-templates' ),
			esc_html( $details['name'] )
		)
		. '</a> <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">'
		. esc_html__( 'Back to Plugins', 'etbs-order-note-templates' )
		. '</a></p>';

	wp_die(
		wp_kses_post( $message ),
		esc_html__( 'Cannot activate ETBS Order Note Templates for WooCommerce', 'etbs-order-note-templates' ),
		array( 'response' => 409 )
	);
}

/**
 * Tells whether an admin screen is one where the stand-down notice belongs.
 * 管理画面のスクリーンが、休止の通知を出すべき画面かどうかを返す。
 *
 * Only the plugins list and the template screens. The notice is not shown on every admin screen.
 * 出すのはプラグイン一覧とテンプレートの画面だけ。管理画面の全画面には出さない。
 *
 * @param string $screen_id Screen ID, as in get_current_screen()->id.
 * @return bool True when the notice belongs on that screen.
 */
function etbs_ont_is_legacy_notice_screen( $screen_id ) {
	return in_array( $screen_id, array( 'plugins', 'plugins-network', 'edit-ormm_template', 'ormm_template' ), true );
}

/**
 * Prints the admin notice that explains why this plugin is standing down.
 * このプラグインが休止している理由を説明する管理画面の通知を出す。
 *
 * @return void
 */
function etbs_ont_render_legacy_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! etbs_ont_is_legacy_notice_screen( $screen->id ) ) {
		return;
	}

	$legacy_plugin = etbs_ont_get_active_legacy_plugin();
	if ( '' === $legacy_plugin ) {
		return;
	}

	$details    = etbs_ont_get_plugin_details( WP_PLUGIN_DIR . '/' . $legacy_plugin );
	$paragraphs = etbs_ont_get_legacy_guidance( $details['name'], etbs_ont_legacy_version_deletes_templates( $details['version'] ) );

	// A network-activated plugin can only be deactivated by a network administrator.
	// ネットワーク有効化されたプラグインは、ネットワーク管理者にしか無効化できない.
	etbs_ont_require_plugin_api();
	$can_deactivate = ! ( is_multisite() && is_plugin_active_for_network( $legacy_plugin ) ) || current_user_can( 'manage_network_plugins' );
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: Name of the old plugin. */
					esc_html__( 'ETBS Order Note Templates for WooCommerce is not running because %s is still active on this site.', 'etbs-order-note-templates' ),
					'<strong>' . esc_html( $details['name'] ) . '</strong>'
				)
			);
			?>
		</p>
		<?php foreach ( $paragraphs as $paragraph ) : ?>
			<p><?php echo wp_kses_post( $paragraph ); ?></p>
		<?php endforeach; ?>
		<?php // Tell the user the site is not affected while this plugin stands down. / 休止中も前身が動いていることを伝える. ?>
		<p><?php echo esc_html__( 'The old plugin keeps working in the meantime.', 'etbs-order-note-templates' ); ?></p>
		<?php if ( $can_deactivate ) : ?>
			<p>
				<a class="button" href="<?php echo esc_url( etbs_ont_get_legacy_deactivate_url( $legacy_plugin ) ); ?>">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Name of the old plugin. */
							__( 'Deactivate %s', 'etbs-order-note-templates' ),
							$details['name']
						)
					);
					?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Prints a warning row under the predecessor's row in the plugins list when deleting it would be unsafe.
 * 削除すると危険な版の前身について、プラグイン一覧の前身の行の直下に警告の行を出す。
 *
 * Hooked to after_plugin_row_{plugin_file} for the predecessor's own row. It shows whether or not the
 * predecessor is active, because the moment a user is most likely to delete it is right after
 * deactivating it.
 * 前身自身の行の after_plugin_row_{plugin_file} に掛ける。前身が有効か無効かは問わず出す。
 * 利用者が削除しようとするのは、無効化した直後がいちばん多いため。
 *
 * @param string $plugin_file Plugin basename of the row.
 * @param array  $plugin_data Plugin header data of the row.
 * @param string $status      Row status: "active", "inactive" and so on.
 * @return void
 */
function etbs_ont_render_legacy_plugin_row( $plugin_file, $plugin_data, $status ) {
	$version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '';
	if ( ! etbs_ont_legacy_version_deletes_templates( $version ) ) {
		return;
	}

	$name = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : 'OrderMemo';

	$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
	?>
	<tr class="plugin-update-tr <?php echo esc_attr( $status ); ?>" data-plugin="<?php echo esc_attr( $plugin_file ); ?>">
		<td colspan="<?php echo esc_attr( $wp_list_table->get_column_count() ); ?>" class="plugin-update colspanchange">
			<div class="update-message notice inline notice-warning notice-alt">
				<p><?php echo wp_kses_post( implode( ' ', etbs_ont_get_legacy_deletion_warning( $name ) ) ); ?></p>
			</div>
		</td>
	</tr>
	<?php
}
