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

	public function test_run_analysis_denies_unauthorized_user() {
		Monkey\Functions\expect( 'check_ajax_referer' )
			->once();

		Monkey\Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_products' )
			->andReturn( false );

		Monkey\Functions\expect( 'wp_send_json_error' )
			->once()
			->with( 'Permission denied.' );

		$ajax = new Ajax();
		$ajax->run_analysis();

		$this->assertTrue( true );
	}

	public function test_run_analysis_validates_product_id() {
		$_POST['product_id'] = 0;

		Monkey\Functions\expect( 'check_ajax_referer' )
			->once();

		Monkey\Functions\expect( 'current_user_can' )
			->once()
			->andReturn( true );

		Monkey\Functions\expect( 'wp_send_json_error' )
			->atLeast()
			->once()
			->with( 'Invalid product ID.' );

		$ajax = new Ajax();
		$ajax->run_analysis();

		unset( $_POST['product_id'] );
		$this->assertTrue( true );
	}

	public function test_run_analysis_throws_when_scheduler_unavailable() {
		$_POST['product_id'] = 123;

		Monkey\Functions\expect( 'check_ajax_referer' )
			->once();

		Monkey\Functions\expect( 'current_user_can' )
			->once()
			->andReturn( true );

		Monkey\Functions\expect( 'current_time' )
			->once()
			->andReturn( '2026-01-26 00:00:00' );

		Monkey\Functions\expect( 'wp_insert_post' )
			->once()
			->andReturn( 456 );

		Monkey\Functions\expect( 'update_post_meta' )
			->twice();

		// as_schedule_single_action doesn't exist.
		Monkey\Functions\expect( 'wp_send_json_error' )
			->once()
			->with( 'Action Scheduler not available.' );

		$ajax = new Ajax();
		$ajax->run_analysis();

		unset( $_POST['product_id'] );
		$this->assertTrue( true );
	}

	public function test_run_analysis_schedules_job_successfully() {
		$_POST['product_id'] = 123;

		Monkey\Functions\expect( 'check_ajax_referer' )
			->once();

		Monkey\Functions\expect( 'current_user_can' )
			->once()
			->andReturn( true );

		Monkey\Functions\expect( 'current_time' )
			->once()
			->andReturn( '2026-01-26 00:00:00' );

		Monkey\Functions\expect( 'wp_insert_post' )
			->once()
			->andReturn( 456 );

		Monkey\Functions\expect( 'update_post_meta' )
			->twice();

		Monkey\Functions\expect( 'as_schedule_single_action' )
			->once();

		Monkey\Functions\expect( 'wp_send_json_success' )
			->once();

		$ajax = new Ajax();
		$ajax->run_analysis();

		unset( $_POST['product_id'] );
		$this->assertTrue( true );
	}
}
