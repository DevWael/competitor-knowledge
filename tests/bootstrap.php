<?php

use Brain\Monkey;

require_once __DIR__ . '/../vendor/autoload.php';

Monkey\setUp();

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

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return date( 'Y-m-d H:i:s' );
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
