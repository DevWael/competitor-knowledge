<?php

namespace CompetitorKnowledge\Tests\Integration;

use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Data\PriceHistoryRepository;
use CompetitorKnowledge\Analysis\Jobs\AnalysisJob;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class AnalysisFlowTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_analysis_repository_exists() {
		$repo = new AnalysisRepository();
		$this->assertInstanceOf( AnalysisRepository::class, $repo );
	}

	public function test_price_history_repository_exists() {
		$repo = new PriceHistoryRepository();
		$this->assertInstanceOf( PriceHistoryRepository::class, $repo );
	}

	public function test_analysis_job_constant_defined() {
		$this->assertEquals( 'ck_run_analysis_job', AnalysisJob::ACTION );
	}
}
