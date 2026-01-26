<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Unit\Data;

use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Data\AnalysisCPT;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Mockery;

class AnalysisRepositoryTest extends TestCase {

	private AnalysisRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->repository = new AnalysisRepository();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_create_inserts_new_analysis_post() {
		$product_id = 123;
		$post_id    = 456;

		Monkey\Functions\expect( 'wp_insert_post' )
			->once()
			->with( Mockery::on( function ( $args ) use ( $product_id ) {
				return $args['post_type'] === AnalysisCPT::POST_TYPE
					&& $args['post_status'] === 'publish'
					&& strpos( $args['post_title'], "Product #{$product_id}" ) !== false;
			} ) )
			->andReturn( $post_id );

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->with( $post_id )
			->andReturn( false );

		Monkey\Functions\expect( 'update_post_meta' )
			->twice(); // Once for status, once for product_id

		Monkey\Functions\expect( 'current_time' )
			->once()
			->with( 'mysql' )
			->andReturn( '2026-01-26 00:00:00' );

		$result = $this->repository->create( $product_id );

		$this->assertEquals( $post_id, $result );
	}

	public function test_create_throws_exception_for_invalid_product_id() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid product ID' );

		$this->repository->create( 0 );
	}

	public function test_create_throws_exception_for_negative_product_id() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid product ID' );

		$this->repository->create( -1 );
	}

	public function test_create_throws_exception_when_wp_insert_post_fails() {
		$product_id = 123;

		Monkey\Functions\expect( 'wp_insert_post' )
			->once()
			->andReturn( 0 );

		Monkey\Functions\expect( 'is_wp_error' )
			->once()
			->with( 0 )
			->andReturn( true );

		Monkey\Functions\expect( 'current_time' )
			->once()
			->andReturn( '2026-01-26 00:00:00' );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Failed to create analysis post' );

		$this->repository->create( $product_id );
	}

	public function test_update_status_updates_meta() {
		$analysis_id = 456;
		$status      = 'processing';

		Monkey\Functions\expect( 'update_post_meta' )
			->once()
			->with( $analysis_id, '_ck_status', $status );

		$this->repository->update_status( $analysis_id, $status );

		// If no exception is thrown, the test passes
		$this->assertTrue( true );
	}

	public function test_save_results_stores_data_and_updates_status() {
		$analysis_id = 456;
		$data        = array(
			'competitors' => array(
				array(
					'name'  => 'Competitor A',
					'price' => 99.99,
				),
			),
		);

		Monkey\Functions\expect( 'update_post_meta' )
			->twice(); // Once for data, once for status

		$this->repository->save_results( $analysis_id, $data );

		$this->assertTrue( true );
	}

	public function test_get_results_returns_array() {
		$analysis_id = 456;
		$data        = array(
			'competitors' => array(
				array(
					'name'  => 'Competitor A',
					'price' => 99.99,
				),
			),
		);

		Monkey\Functions\expect( 'get_post_meta' )
			->once()
			->with( $analysis_id, '_ck_analysis_data', true )
			->andReturn( $data );

		$result = $this->repository->get_results( $analysis_id );

		$this->assertEquals( $data, $result );
		$this->assertIsArray( $result );
	}

	public function test_get_results_returns_null_for_non_array() {
		$analysis_id = 456;

		Monkey\Functions\expect( 'get_post_meta' )
			->once()
			->with( $analysis_id, '_ck_analysis_data', true )
			->andReturn( '' );

		$result = $this->repository->get_results( $analysis_id );

		$this->assertNull( $result );
	}

	public function test_get_target_product_id_returns_integer() {
		$analysis_id = 456;
		$product_id  = 123;

		Monkey\Functions\expect( 'get_post_meta' )
			->once()
			->with( $analysis_id, '_ck_target_product_id', true )
			->andReturn( (string) $product_id );

		$result = $this->repository->get_target_product_id( $analysis_id );

		$this->assertEquals( $product_id, $result );
		$this->assertIsInt( $result );
	}

	public function test_get_target_product_id_returns_zero_when_empty() {
		$analysis_id = 456;

		Monkey\Functions\expect( 'get_post_meta' )
			->once()
			->with( $analysis_id, '_ck_target_product_id', true )
			->andReturn( '' );

		$result = $this->repository->get_target_product_id( $analysis_id );

		$this->assertEquals( 0, $result );
		$this->assertIsInt( $result );
	}
}
