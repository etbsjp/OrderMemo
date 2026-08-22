<?php
/**
 * アンインストール処理。
 *
 * OrderMemo は利用者が作成した定型文を `ormm_template` 投稿として保存している。
 * 以前の実装はアンインストール時にこれを完全削除（ゴミ箱を経由しない `wp_delete_post( $id, true )`）
 * していたが、利用者が手作業で作った定型文が復旧手段なく失われてしまう。
 * そのため、このプラグインは削除時にデータを一切消さない（task-queue #108 の案A決定）。
 * cron や独自テーブルは持たないため、案Aに従うと「何もしない」が正しい実装になる。
 *
 * @package ordermemo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

// 意図的に何もしない。
