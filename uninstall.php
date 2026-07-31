<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

function ormm_ordermemo_uninstall() {
	$ids = get_posts( [
		'post_type'   => 'ormm_template',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	] );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

ormm_ordermemo_uninstall();
