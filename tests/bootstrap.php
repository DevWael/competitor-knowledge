<?php

use Brain\Monkey;

require_once __DIR__ . '/../vendor/autoload.php';

Monkey\setUp();

// WordPress database output type constants
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

/**
 * Teardown Brain Monkey after each test.
 */
function tear_down() {
	Monkey\tearDown();
}

// Stubs for WP Translation functions
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook_name, $callback = false ) {
		return false;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return $number === 1 ? $single : $plural;
	}
}

// Mock global $wpdb for repository tests
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public $prefix = 'wp_';

		public function insert( $table, $data, $format = null ) {
			return 1;
		}

		public function get_results( $query, $output = OBJECT ) {
			return array();
		}

		public function prepare( $query, ...$args ) {
			return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
		}

		public function get_var( $query, $column_offset = 0, $row_offset = 0 ) {
			return null;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			return 1;
		}
	};
}
