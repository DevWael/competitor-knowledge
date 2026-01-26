<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Analysis\Jobs;

use CompetitorKnowledge\Analysis\Jobs\AnalysisJob;
use PHPUnit\Framework\TestCase;

class AnalysisJobTest extends TestCase {

	public function test_action_constant_is_defined() {
		$this->assertEquals( 'ck_run_analysis_job', AnalysisJob::ACTION );
	}

	public function test_action_constant_is_string() {
		$this->assertIsString( AnalysisJob::ACTION );
		$this->assertNotEmpty( AnalysisJob::ACTION );
	}

	public function test_action_constant_has_correct_prefix() {
		$this->assertStringStartsWith( 'ck_', AnalysisJob::ACTION );
	}
}
