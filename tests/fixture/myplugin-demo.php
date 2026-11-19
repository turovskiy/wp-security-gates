<?php
/**
 * Plugin Name: wp-security-gates fixture
 * Test subject for the permission templates. GATES_MODE: secure (default) | insecure | no-permission-callback.
 */

defined( 'ABSPATH' ) || exit;

function myplugin_demo_mode(): string {
	return (string) ( getenv( 'GATES_MODE' ) ? getenv( 'GATES_MODE' ) : 'secure' );
}

add_action(
	'rest_api_init',
	static function () {
		$route = array(
			'methods'  => 'POST',
			'callback' => static fn() => new WP_REST_Response( array( 'saved' => true ), 201 ),
		);
		if ( 'secure' === myplugin_demo_mode() ) {
			$route['permission_callback'] = static fn() => current_user_can( 'edit_others_posts' );
		} elseif ( 'insecure' === myplugin_demo_mode() ) {
			$route['permission_callback'] = '__return_true';
		}
		register_rest_route( 'myplugin/v1', '/items', $route );
	}
);

add_action(
	'wp_ajax_myplugin_save_item',
	static function () {
		if ( 'secure' === myplugin_demo_mode() ) {
			check_ajax_referer( 'myplugin_save_item' );
			if ( ! current_user_can( 'edit_others_posts' ) ) {
				wp_send_json_error( null, 403 );
			}
		}
		wp_send_json_success();
	}
);
