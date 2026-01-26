<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Admin;

use CompetitorKnowledge\Admin\BulkActions;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class BulkActionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_init_registers_hooks() {
		Monkey\Functions\expect( 'add_filter' )
			->twice();

		Monkey\Functions\expect( 'add_action' )
			->once();

		$bulk_actions = new BulkActions();
		$bulk_actions->init();

		$this->assertTrue( true );
	}

	public function test_register_bulk_action_adds_action() {
		$existing_actions = array(
			'edit' => 'Edit',
			'delete' => 'Delete',
		);

		$bulk_actions = new BulkActions();
		$result = $bulk_actions->register_bulk_action( $existing_actions );

		$this->assertArrayHasKey( 'ck_run_analysis', $result );
		$this->assertArrayHasKey( 'edit', $result );
		$this->assertArrayHasKey( 'delete', $result );
	}

	public function test_register_bulk_action_preserves_existing() {
		$existing_actions = array(
			'trash' => 'Move to Trash',
		);

		$bulk_actions = new BulkActions();
		$result = $bulk_actions->register_bulk_action( $existing_actions );

		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'trash', $result );
		$this->assertArrayHasKey( 'ck_run_analysis', $result );
	}

	public function test_handle_bulk_action_returns_redirect_for_wrong_action() {
		$redirect_to = 'https://example.com/wp-admin/edit.php';
		$action      = 'delete';
		$post_ids    = array( 1, 2, 3 );

		$bulk_actions = new BulkActions();
		$result       = $bulk_actions->handle_bulk_action( $redirect_to, $action, $post_ids );

		$this->assertEquals( $redirect_to, $result );
	}

	public function test_handle_bulk_action_returns_redirect_for_empty_post_ids() {
		$redirect_to = 'https://example.com/wp-admin/edit.php';
		$action      = 'ck_run_analysis';
		$post_ids    = array();

		$bulk_actions = new BulkActions();
		$result       = $bulk_actions->handle_bulk_action( $redirect_to, $action, $post_ids );

		$this->assertEquals( $redirect_to, $result );
	}

	public function test_bulk_action_admin_notice_returns_early_without_query_arg() {
		$_REQUEST = array();

		$bulk_actions = new BulkActions();

		ob_start();
		$bulk_actions->bulk_action_admin_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_bulk_action_admin_notice_displays_message_with_count() {
		$_REQUEST['ck_bulk_analysis'] = 5;

		Monkey\Functions\stubs(
			array(
				'esc_html' => function ( $text ) {
					return $text;
				},
			)
		);

		$bulk_actions = new BulkActions();

		ob_start();
		$bulk_actions->bulk_action_admin_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( '5', $output );

		unset( $_REQUEST['ck_bulk_analysis'] );
	}

	public function test_bulk_action_admin_notice_skips_for_zero_count() {
		$_REQUEST['ck_bulk_analysis'] = 0;

		$bulk_actions = new BulkActions();

		ob_start();
		$bulk_actions->bulk_action_admin_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_REQUEST['ck_bulk_analysis'] );
	}
}
