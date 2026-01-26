<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Tests\Helpers;

use Mockery;

/**
 * Mock Factory for creating common test objects.
 */
class MockFactory {

	/**
	 * Create a mock WC_Product.
	 *
	 * @param array $data Product data.
	 * @return \Mockery\MockInterface
	 */
	public static function createMockProduct( array $data = array() ): \Mockery\MockInterface {
		$defaults = array(
			'id'                 => 123,
			'name'               => 'Test Product',
			'sku'                => 'TEST-SKU-123',
			'price'              => '99.99',
			'short_description'  => 'Test product description',
		);

		$data    = array_merge( $defaults, $data );
		$product = Mockery::mock( 'WC_Product' );

		$product->shouldReceive( 'get_id' )->andReturn( $data['id'] );
		$product->shouldReceive( 'get_name' )->andReturn( $data['name'] );
		$product->shouldReceive( 'get_sku' )->andReturn( $data['sku'] );
		$product->shouldReceive( 'get_price' )->andReturn( $data['price'] );
		$product->shouldReceive( 'get_short_description' )->andReturn( $data['short_description'] );

		return $product;
	}

	/**
	 * Create mock search results.
	 *
	 * @param int $count Number of results.
	 * @return array
	 */
	public static function createMockSearchResults( int $count = 3 ): array {
		$results = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$results[] = array(
				'url'     => "https://example{$i}.com/product",
				'title'   => "Product {$i}",
				'content' => "Description for product {$i}",
				'score'   => 0.9 - ( $i * 0.1 ),
			);
		}

		return $results;
	}

	/**
	 * Create mock analysis result data.
	 *
	 * @param int $competitor_count Number of competitors.
	 * @return array
	 */
	public static function createMockAnalysisResult( int $competitor_count = 2 ): array {
		$competitors = array();

		for ( $i = 1; $i <= $competitor_count; $i++ ) {
			$competitors[] = array(
				'name'             => "Competitor {$i}",
				'url'              => "https://competitor{$i}.com",
				'price'            => 99.99 - ( $i * 10 ),
				'currency'         => 'USD',
				'stock_status'     => 'in_stock',
				'comparison_notes' => "Notes for competitor {$i}",
			);
		}

		return array(
			'competitors' => $competitors,
			'summary'     => 'Analysis completed successfully',
			'analyzed_at' => '2026-01-26 00:00:00',
		);
	}

	/**
	 * Create a mock $wpdb object.
	 *
	 * @return \Mockery\MockInterface
	 */
	public static function createMockWpdb(): \Mockery\MockInterface {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'insert' )->andReturn( 1 )->byDefault();
		$wpdb->shouldReceive( 'update' )->andReturn( 1 )->byDefault();
		$wpdb->shouldReceive( 'delete' )->andReturn( 1 )->byDefault();
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() )->byDefault();
		$wpdb->shouldReceive( 'get_var' )->andReturn( null )->byDefault();
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( function ( $query ) {
			return $query;
		} )->byDefault();

		return $wpdb;
	}
}
