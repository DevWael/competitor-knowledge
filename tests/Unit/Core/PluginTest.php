<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Core;

use CompetitorKnowledge\Core\Plugin;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class PluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_creates_plugin_instance() {
		$plugin = new Plugin();

		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	public function test_run_registers_hooks() {
		Monkey\Functions\expect( 'add_action' )
			->atLeast()
			->once();

		Monkey\Functions\expect( 'is_admin' )
			->once()
			->andReturn( false );

		$plugin = new Plugin();
		$plugin->run();

		$this->assertTrue( true );
	}

	public function test_run_with_admin_context() {
		Monkey\Functions\expect( 'add_action' )
			->atLeast()
			->once();

		Monkey\Functions\expect( 'add_filter' )
			->atLeast()
			->once();

		Monkey\Functions\expect( 'is_admin' )
			->once()
			->andReturn( true );

		$plugin = new Plugin();
		$plugin->run();

		$this->assertTrue( true );
	}

	public function test_register_cpt_initializes_cpt() {
		Monkey\Functions\expect( 'register_post_type' )
			->once();

		$plugin = new Plugin();
		$plugin->register_cpt();

		$this->assertTrue( true );
	}

	public function test_enqueue_assets_registers_scripts() {
		if ( ! defined( 'COMPETITOR_KNOWLEDGE_URL' ) ) {
			define( 'COMPETITOR_KNOWLEDGE_URL', 'https://example.com/wp-content/plugins/competitor-knowledge/' );
		}
		if ( ! defined( 'COMPETITOR_KNOWLEDGE_VERSION' ) ) {
			define( 'COMPETITOR_KNOWLEDGE_VERSION', '1.0.0' );
		}

		Monkey\Functions\expect( 'wp_enqueue_script' )
			->times( 3 );

		Monkey\Functions\expect( 'wp_localize_script' )
			->once();

		Monkey\Functions\expect( 'admin_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin-ajax.php' );

		Monkey\Functions\expect( 'wp_create_nonce' )
			->once()
			->andReturn( 'test-nonce' );

		$plugin = new Plugin();
		$plugin->enqueue_assets();

		$this->assertTrue( true );
	}
}
