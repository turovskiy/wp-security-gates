<?php
/**
 * Permission tests for one REST route. Copy to tests/phpunit/, change the constants.
 */

declare(strict_types=1);

final class Test_Rest_Permissions extends WP_UnitTestCase {

	private const NAMESPACE    = 'myplugin/v1';
	private const ROUTE        = '/myplugin/v1/items';
	private const METHOD       = 'POST';
	private const ALLOWED_ROLE = 'editor';
	private const DENIED_ROLE  = 'subscriber';

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $this->server );
	}

	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	private function dispatch(): WP_REST_Response {
		return $this->server->dispatch( new WP_REST_Request( self::METHOD, self::ROUTE ) );
	}

	private function login_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	public function test_route_is_registered(): void {
		$this->assertArrayHasKey( self::ROUTE, $this->server->get_routes() );
	}

	public function test_every_route_in_namespace_declares_permission_callback(): void {
		foreach ( $this->server->get_routes( self::NAMESPACE ) as $route => $handlers ) {
			if ( '/' . self::NAMESPACE === $route ) {
				continue; // Namespace index, registered by core.
			}
			foreach ( $handlers as $handler ) {
				$this->assertNotEmpty( $handler['permission_callback'] ?? null, "{$route} has no permission_callback." );
			}
		}
	}

	public function test_anonymous_gets_401(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch()->get_status() );
	}

	public function test_user_without_capability_gets_403(): void {
		$this->login_as( self::DENIED_ROLE );
		$this->assertSame( 403, $this->dispatch()->get_status() );
	}

	public function test_allowed_role_passes_permission_check(): void {
		$this->login_as( self::ALLOWED_ROLE );
		$this->assertNotContains( $this->dispatch()->get_status(), array( 401, 403 ) );
	}
}
