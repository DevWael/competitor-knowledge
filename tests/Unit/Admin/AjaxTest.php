<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Admin;

use CompetitorKnowledge\Admin\Ajax;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class AjaxTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_init_registers_ajax_hook() {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with(
				'wp_ajax_ck_run_analysis',
				Mockery::type( 'array' )
			);

		$ajax = new Ajax();
		$ajax->init();

		$this->assertTrue( true );
	}
}
