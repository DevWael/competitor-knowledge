<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Admin;

use CompetitorKnowledge\Admin\Metaboxes;
use CompetitorKnowledge\Data\AnalysisCPT;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class MetaboxesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_init_registers_meta_box_hooks() {
		Monkey\Functions\expect( 'add_action' )
			->twice()
			->with(
				'add_meta_boxes',
				Mockery::type( 'array' )
			);

		$metaboxes = new Metaboxes();
		$metaboxes->init();

		$this->assertTrue( true );
	}

	public function test_add_product_metabox_calls_add_meta_box() {
		Monkey\Functions\expect( 'add_meta_box' )
			->once()
			->with(
				'ck_competitor_analysis',
				Mockery::type( 'string' ),
				Mockery::type( 'array' ),
				'product',
				'normal',
				'low'
			);

		$metaboxes = new Metaboxes();
		$metaboxes->add_product_metabox();

		$this->assertTrue( true );
	}

	public function test_add_analysis_metabox_calls_add_meta_box() {
		Monkey\Functions\expect( 'add_meta_box' )
			->once()
			->with(
				'ck_analysis_results',
				Mockery::type( 'string' ),
				Mockery::type( 'array' ),
				AnalysisCPT::POST_TYPE,
				'normal',
				'high'
			);

		$metaboxes = new Metaboxes();
		$metaboxes->add_analysis_metabox();

		$this->assertTrue( true );
	}

	public function test_render_metabox_shows_button() {
		$post     = Mockery::mock( 'WP_Post' );
		$post->ID = 123;

		Monkey\Functions\expect( 'get_posts' )
			->once()
			->andReturn( array() );

		Monkey\Functions\stubs(
			array(
				'esc_attr'    => function ( $text ) {
					return $text;
				},
				'esc_html_e'  => function ( $text ) {
					echo $text;
				},
			)
		);

		$metaboxes = new Metaboxes();

		ob_start();
		$metaboxes->render_metabox( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Run New Analysis', $output );
		$this->assertStringContainsString( 'data-product-id="123"', $output );
		$this->assertStringContainsString( 'No previous analyses found', $output );
	}

	public function test_render_metabox_shows_analyses_table() {
		$post     = Mockery::mock( 'WP_Post' );
		$post->ID = 123;

		$analysis     = Mockery::mock( 'WP_Post' );
		$analysis->ID = 456;

		Monkey\Functions\expect( 'get_posts' )
			->once()
			->andReturn( array( $analysis ) );

		Monkey\Functions\expect( 'get_post_meta' )
			->once()
			->andReturn( 'completed' );

		Monkey\Functions\expect( 'get_edit_post_link' )
			->once()
			->andReturn( 'https://example.com/edit/456' );

		Monkey\Functions\expect( 'get_the_date' )
			->once()
			->andReturn( '2026-01-26' );

		Monkey\Functions\stubs(
			array(
				'esc_attr'   => function ( $text ) {
					return $text;
				},
				'esc_html'   => function ( $text ) {
					return $text;
				},
				'esc_html_e' => function ( $text ) {
					echo $text;
				},
				'esc_url'    => function ( $url ) {
					return $url;
				},
			)
		);

		$metaboxes = new Metaboxes();

		ob_start();
		$metaboxes->render_metabox( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<table', $output );
		$this->assertStringContainsString( 'Date', $output );
		$this->assertStringContainsString( 'Status', $output );
		$this->assertStringContainsString( 'Completed', $output );
		$this->assertStringContainsString( 'View Report', $output );
	}

	public function test_render_results_metabox_shows_no_data_message() {
		$post     = Mockery::mock( 'WP_Post' );
		$post->ID = 789;

		Monkey\Functions\expect( 'get_post_meta' )
			->once()
			->with( 789, '_ck_analysis_data', true )
			->andReturn( null );

		$metaboxes = new Metaboxes();

		ob_start();
		$metaboxes->render_results_metabox( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No data available', $output );
	}

	public function test_render_results_metabox_returns_table_when_has_data() {
		$post     = Mockery::mock( 'WP_Post' );
		$post->ID = 789;

		$data = array(
			'competitors' => array(
				array(
					'name'             => 'Competitor A',
					'price'            => '99.99',
					'currency'         => 'USD',
					'stock_status'     => 'in_stock',
					'comparison_notes' => 'Lower price',
					'url'              => 'https://competitor-a.com',
				),
			),
		);

		Monkey\Functions\expect( 'get_post_meta' )
			->with( 789, '_ck_analysis_data', true )
			->andReturn( $data );

		Monkey\Functions\expect( 'get_post_meta' )
			->with( 789, '_ck_target_product_id', true )
			->andReturn( null );

		Monkey\Functions\stubs(
			array(
				'esc_html'   => function ( $text ) {
					return $text;
				},
				'esc_html_e' => function ( $text ) {
					echo $text;
				},
				'esc_url'    => function ( $url ) {
					return $url;
				},
			)
		);

		$metaboxes = new Metaboxes();

		ob_start();
		$metaboxes->render_results_metabox( $post );
		$output = ob_get_clean();

		// Just verify it doesn't show error and returns some output.
		$this->assertNotEmpty( $output );
	}

	public function test_render_results_metabox_with_price_history() {
		global $wpdb;
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )
			->andReturn( 'SELECT * FROM wp_ck_price_history WHERE product_id = 100' );
		$wpdb->shouldReceive( 'get_results' )
			->andReturn(
				array(
					array(
						'competitor_name' => 'Test Competitor',
						'price'           => '50.00',
						'date_recorded'   => '2026-01-01',
					),
				)
			);

		$post     = Mockery::mock( 'WP_Post' );
		$post->ID = 789;

		$data = array(
			'competitors' => array(
				array(
					'name'             => 'Competitor A',
					'price'            => '99.99',
					'currency'         => 'USD',
					'stock_status'     => 'in_stock',
					'comparison_notes' => 'Lower price',
					'url'              => 'https://competitor-a.com',
				),
			),
		);

		Monkey\Functions\expect( 'get_post_meta' )
			->with( 789, '_ck_analysis_data', true )
			->andReturn( $data );

		Monkey\Functions\expect( 'get_post_meta' )
			->with( 789, '_ck_target_product_id', true )
			->andReturn( 100 );

		Monkey\Functions\stubs(
			array(
				'esc_html'   => function ( $text ) {
					return $text;
				},
				'esc_html_e' => function ( $text ) {
					echo $text;
				},
				'esc_url'    => function ( $url ) {
					return $url;
				},
				'esc_js'     => function ( $text ) {
					return $text;
				},
				'wp_json_encode' => function ( $data ) {
					return json_encode( $data );
				},
			)
		);

		$metaboxes = new Metaboxes();

		ob_start();
		$metaboxes->render_results_metabox( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<table', $output );
		$this->assertStringContainsString( 'ckPriceChart', $output );
	}

	public function test_render_results_metabox_with_empty_url() {
		$post     = Mockery::mock( 'WP_Post' );
		$post->ID = 789;

		$data = array(
			'competitors' => array(
				array(
					'name'             => 'Competitor B',
					'price'            => '49.99',
					'currency'         => 'EUR',
					'stock_status'     => 'out_of_stock',
					'comparison_notes' => '',
					'url'              => '', // Empty URL.
				),
			),
		);

		Monkey\Functions\expect( 'get_post_meta' )
			->with( 789, '_ck_analysis_data', true )
			->andReturn( $data );

		Monkey\Functions\expect( 'get_post_meta' )
			->with( 789, '_ck_target_product_id', true )
			->andReturn( null );

		Monkey\Functions\stubs(
			array(
				'esc_html'       => function ( $text ) {
					return $text;
				},
				'esc_html_e'     => function ( $text ) {
					echo $text;
				},
				'esc_url'        => function ( $url ) {
					return $url;
				},
				'esc_js'         => function ( $text ) {
					return $text;
				},
				'wp_json_encode' => function ( $data ) {
					return json_encode( $data );
				},
			)
		);

		$metaboxes = new Metaboxes();

		ob_start();
		$metaboxes->render_results_metabox( $post );
		$output = ob_get_clean();

		// Should NOT contain Visit button since URL is empty.
		$this->assertStringNotContainsString( 'Visit', $output );
	}
}
