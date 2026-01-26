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
