<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Data;

use CompetitorKnowledge\Data\AnalysisCPT;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class AnalysisCPTTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_post_type_constant_is_defined() {
		$this->assertEquals( 'ck_analysis', AnalysisCPT::POST_TYPE );
	}

	public function test_post_type_constant_is_string() {
		$this->assertIsString( AnalysisCPT::POST_TYPE );
		$this->assertNotEmpty( AnalysisCPT::POST_TYPE );
	}

	public function test_post_type_constant_has_correct_prefix() {
		$this->assertStringStartsWith( 'ck_', AnalysisCPT::POST_TYPE );
	}

	public function test_register_calls_register_post_type() {
		Monkey\Functions\expect( 'register_post_type' )
			->once()
			->with(
				'ck_analysis',
				Mockery::type( 'array' )
			);

		$cpt = new AnalysisCPT();
		$cpt->register();

		$this->assertTrue( true );
	}
}

