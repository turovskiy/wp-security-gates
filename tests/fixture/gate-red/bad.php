<?php
// Must trip the gate: unescaped output, unsanitized input without a nonce, SQL concatenation.
function myplugin_bad_render( int $id ): void {
	global $wpdb;
	echo get_post_meta( $id, 'title', true );
	$wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE ID = " . $_GET['id'] );
}
