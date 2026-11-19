<?php
$_tests_dir = getenv( 'WP_TESTS_DIR' );
require_once $_tests_dir . '/includes/functions.php';
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require __DIR__ . '/fixture/myplugin-demo.php';
	}
);
require $_tests_dir . '/includes/bootstrap.php';
