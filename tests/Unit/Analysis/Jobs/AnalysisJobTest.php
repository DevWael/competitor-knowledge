<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Analysis\Jobs;

use CompetitorKnowledge\Analysis\Jobs\AnalysisJob;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class AnalysisJobTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_action_constant_is_defined() {
		$this->assertEquals( 'ck_run_analysis_job', AnalysisJob::ACTION );
	}

	public function test_action_constant_has_correct_prefix() {
		$this->assertStringStartsWith( 'ck_', AnalysisJob::ACTION );
	}

	public function test_init_registers_action_hook() {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with(
				AnalysisJob::ACTION,
				Mockery::type( 'array' )
			);

		AnalysisJob::init();

		$this->assertTrue( true );
	}

	public function test_action_constant_is_string() {
		$this->assertIsString( AnalysisJob::ACTION );
		$this->assertNotEmpty( AnalysisJob::ACTION );
	}

	public function test_action_constant_matches_expected_format() {
		$this->assertMatchesRegularExpression( '/^ck_[a-z_]+$/', AnalysisJob::ACTION );
	}
}
