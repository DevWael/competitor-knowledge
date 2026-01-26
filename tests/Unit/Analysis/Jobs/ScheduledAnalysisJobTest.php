<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Analysis\Jobs;

use CompetitorKnowledge\Analysis\Jobs\ScheduledAnalysisJob;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class ScheduledAnalysisJobTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define time constants if not already defined.
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}
		if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
			define( 'WEEK_IN_SECONDS', 604800 );
		}
		if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
			define( 'MONTH_IN_SECONDS', 2592000 );
		}
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_action_constant_is_defined() {
		$this->assertEquals( 'ck_scheduled_analysis_job', ScheduledAnalysisJob::ACTION );
	}

	public function test_action_constant_has_correct_prefix() {
		$this->assertStringStartsWith( 'ck_', ScheduledAnalysisJob::ACTION );
	}

	public function test_init_registers_hooks() {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with(
				'ck_scheduled_analysis_job',
				Mockery::type( 'array' )
			);

		Monkey\Functions\expect( 'add_action' )
			->once()
			->with(
				'init',
				Mockery::type( 'array' )
			);

		ScheduledAnalysisJob::init();

		$this->assertTrue( true );
	}

	public function test_schedule_recurring_job_unschedules_when_disabled() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array( 'scheduled_analysis_enabled' => false ) );

		Monkey\Functions\expect( 'as_unschedule_all_actions' )
			->once()
			->with( ScheduledAnalysisJob::ACTION );

		ScheduledAnalysisJob::schedule_recurring_job();

		$this->assertTrue( true );
	}

	public function test_schedule_recurring_job_skips_when_already_scheduled() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					'scheduled_analysis_enabled'   => true,
					'scheduled_analysis_frequency' => 'daily',
				)
			);

		Monkey\Functions\expect( 'as_next_scheduled_action' )
			->once()
			->andReturn( 12345 );

		ScheduledAnalysisJob::schedule_recurring_job();

		$this->assertTrue( true );
	}

	public function test_schedule_recurring_job_schedules_when_enabled() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn(
				array(
					'scheduled_analysis_enabled'   => true,
					'scheduled_analysis_frequency' => 'weekly',
				)
			);

		Monkey\Functions\expect( 'as_next_scheduled_action' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'as_schedule_recurring_action' )
			->once()
			->with(
				Mockery::type( 'int' ),
				WEEK_IN_SECONDS,
				ScheduledAnalysisJob::ACTION
			);

		ScheduledAnalysisJob::schedule_recurring_job();

		$this->assertTrue( true );
	}

	public function test_handle_returns_early_when_no_products() {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->andReturn( array() );

		Monkey\Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		ScheduledAnalysisJob::handle();

		$this->assertTrue( true );
	}
}
