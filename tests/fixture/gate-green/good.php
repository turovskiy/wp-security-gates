<?php
// Must pass the gate: the same work, done safely.
function myplugin_good_render( int $id ): void {
	global $wpdb;
	echo esc_html( get_post_meta( $id, 'title', true ) );
	if ( ! isset( $_GET['id'], $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'myplugin_view' ) ) {
		return;
	}
	$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", absint( $_GET['id'] ) ) );
}
