<?php
/**
 * Nonce and capability tests for one admin-ajax action. Copy to tests/phpunit/, change the constants.
 *
 * @group ajax
 */

declare(strict_types=1);

final class Test_Ajax_Permissions extends WP_Ajax_UnitTestCase {

	private const ACTION       = 'myplugin_save_item';
	private const NONCE_ACTION = 'myplugin_save_item';
	private const ALLOWED_ROLE = 'editor';
	private const DENIED_ROLE  = 'subscriber';

	private function login_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private function call_ajax(): array {
		try {
			$this->_handleAjax( self::ACTION );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_* ends with wp_die(): the normal exit.
			unset( $e );
		}
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Handler must answer with JSON.' );
		return $response;
	}

	public function test_no_anonymous_handler(): void {
		$this->assertFalse( has_action( 'wp_ajax_nopriv_' . self::ACTION ) );
	}

	public function test_invalid_nonce_dies_with_minus_one(): void {
		$this->login_as( self::ALLOWED_ROLE );
		$_POST['_ajax_nonce'] = 'invalid';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( self::ACTION );
	}

	public function test_user_without_capability_is_rejected(): void {
		$this->login_as( self::DENIED_ROLE );
		$_POST['_ajax_nonce'] = wp_create_nonce( self::NONCE_ACTION ); // After login: nonces are per user.

		$this->assertFalse( $this->call_ajax()['success'] );
	}

	public function test_allowed_role_succeeds(): void {
		$this->login_as( self::ALLOWED_ROLE );
		$_POST['_ajax_nonce'] = wp_create_nonce( self::NONCE_ACTION );

		$this->assertTrue( $this->call_ajax()['success'] );
	}
}
