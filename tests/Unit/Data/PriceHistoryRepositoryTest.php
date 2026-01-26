<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Data;

use CompetitorKnowledge\Data\PriceHistoryRepository;
use CompetitorKnowledge\Data\Installer;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class PriceHistoryRepositoryTest extends TestCase {

	private PriceHistoryRepository $repository;
	private $mock_wpdb;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Create a more sophisticated mock for $wpdb
		$this->mock_wpdb         = Mockery::mock( 'wpdb' );
		$this->mock_wpdb->prefix = 'wp_';
		$GLOBALS['wpdb']         = $this->mock_wpdb;

		$this->repository = new PriceHistoryRepository();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_add_record_inserts_price_data() {
		$product_id      = 123;
		$analysis_id     = 456;
		$competitor_name = 'Competitor A';
		$price           = 99.99;
		$currency        = 'USD';

		Monkey\Functions\expect( 'sanitize_text_field' )
			->twice()
			->andReturnUsing( function ( $value ) {
				return $value;
			} );

		Monkey\Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2026-01-26 00:00:00' );

		$this->mock_wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_' . Installer::TABLE_PRICE_HISTORY,
				Mockery::on( function ( $data ) use ( $product_id, $analysis_id, $competitor_name, $price, $currency ) {
					return $data['product_id'] === $product_id
						&& $data['analysis_id'] === $analysis_id
						&& $data['competitor_name'] === $competitor_name
						&& $data['price'] === $price
						&& $data['currency'] === $currency
						&& $data['date_recorded'] === '2026-01-26 00:00:00';
				} ),
				array( '%d', '%d', '%s', '%f', '%s', '%s' )
			)
			->andReturn( 1 );

		$result = $this->repository->add_record( $product_id, $analysis_id, $competitor_name, $price, $currency );

		$this->assertTrue( $result );
	}

	public function test_add_record_sanitizes_inputs() {
		$product_id      = 123;
		$analysis_id     = 456;
		$competitor_name = '<script>alert("xss")</script>Competitor';
		$price           = 99.99;
		$currency        = 'USD<script>';

		Monkey\Functions\expect( 'sanitize_text_field' )
			->twice()
			->andReturnUsing( function ( $value ) {
				return strip_tags( $value );
			} );

		Monkey\Functions\expect( 'current_time' )
			->once()
			->andReturn( '2026-01-26 00:00:00' );

		$this->mock_wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->repository->add_record( $product_id, $analysis_id, $competitor_name, $price, $currency );

		$this->assertTrue( $result );
	}

	public function test_add_record_returns_false_on_failure() {
		$product_id      = 123;
		$analysis_id     = 456;
		$competitor_name = 'Competitor A';
		$price           = 99.99;
		$currency        = 'USD';

		Monkey\Functions\expect( 'sanitize_text_field' )
			->twice()
			->andReturnUsing( function ( $value ) {
				return $value;
			} );

		Monkey\Functions\expect( 'current_time' )
			->once()
			->andReturn( '2026-01-26 00:00:00' );

		$this->mock_wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$result = $this->repository->add_record( $product_id, $analysis_id, $competitor_name, $price, $currency );

		$this->assertFalse( $result );
	}

	public function test_get_history_returns_records_for_product() {
		$product_id = 123;
		$records    = array(
			array(
				'id'              => 1,
				'product_id'      => $product_id,
				'analysis_id'     => 456,
				'competitor_name' => 'Competitor A',
				'price'           => 99.99,
				'currency'        => 'USD',
				'date_recorded'   => '2026-01-26 00:00:00',
			),
			array(
				'id'              => 2,
				'product_id'      => $product_id,
				'analysis_id'     => 457,
				'competitor_name' => 'Competitor B',
				'price'           => 89.99,
				'currency'        => 'USD',
				'date_recorded'   => '2026-01-26 01:00:00',
			),
		);

		$this->mock_wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_ck_price_history WHERE product_id = {$product_id} ORDER BY date_recorded ASC" );

		$this->mock_wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( $records );

		$result = $this->repository->get_history( $product_id );

		$this->assertEquals( $records, $result );
		$this->assertCount( 2, $result );
	}

	public function test_get_history_returns_empty_array_when_no_records() {
		$product_id = 123;

		$this->mock_wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_ck_price_history WHERE product_id = {$product_id} ORDER BY date_recorded ASC" );

		$this->mock_wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( null );

		$result = $this->repository->get_history( $product_id );

		$this->assertEquals( array(), $result );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_get_history_handles_database_errors() {
		$product_id = 123;

		$this->mock_wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( "SELECT * FROM wp_ck_price_history WHERE product_id = {$product_id} ORDER BY date_recorded ASC" );

		$this->mock_wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( false );

		$result = $this->repository->get_history( $product_id );

		$this->assertEquals( array(), $result );
	}
}
