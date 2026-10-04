<?php
/**
 * アンインストール処理。
 *
 * OrderMemo は利用者が作成した定型文を `ormm_template` 投稿として保存している。
 * 以前の実装はアンインストール時にこれを完全削除（ゴミ箱を経由しない `wp_delete_post( $id, true )`）
 * していたが、利用者が手作業で作った定型文が復旧手段なく失われてしまう。
 * そのため、このプラグインは削除時に、利用者が作ったもの・設定したものを一切消さない
 * （task-queue #108 の案A決定）。cron や独自テーブルは持たない。
 *
 * 消すのは一時的な状態だけ。1.0.5 で入れた「公式版への入れ替え案内」を、各ユーザーが
 * 「今後表示しない」で消したかどうかの記録（ユーザーメタ `ormm_migration_notice_dismissed`）が
 * これに当たる。案内そのものがこのプラグインと一緒に無くなるので、残しても意味を持たない。
 *
 * ★ テンプレート（`ormm_template` の投稿）を消す処理を足し直さないこと。公式版
 *    （ETBS Order Note Templates for WooCommerce）は同じ投稿をそのまま使うため、
 *    入れ替えの最後にこのプラグインを削除したときに消すと、引き継いだテンプレートが失われる。
 *
 * @package ordermemo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// 入れ替え案内を消したかどうかの記録を、全ユーザー分まとめて消す。
// キーは inc/migration-notice.php の ORMM_MIGRATION_DISMISSED_META と同じ値。
// アンインストール時はプラグイン本体が読み込まれず定数が無いので、ここに直接書いている。
delete_metadata( 'user', 0, 'ormm_migration_notice_dismissed', '', true );
