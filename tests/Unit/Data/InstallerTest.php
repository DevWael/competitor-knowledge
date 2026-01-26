<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Data;

use CompetitorKnowledge\Data\Installer;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class InstallerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_table_price_history_constant_is_defined() {
		$this->assertEquals( 'ck_price_history', Installer::TABLE_PRICE_HISTORY );
	}

	public function test_table_price_history_constant_has_correct_prefix() {
		$this->assertStringStartsWith( 'ck_', Installer::TABLE_PRICE_HISTORY );
	}

	public function test_table_price_history_constant_is_string() {
		$this->assertIsString( Installer::TABLE_PRICE_HISTORY );
		$this->assertNotEmpty( Installer::TABLE_PRICE_HISTORY );
	}

	public function test_table_name_contains_price_history() {
		$this->assertStringContainsString( 'price_history', Installer::TABLE_PRICE_HISTORY );
	}
}
