<?php

namespace CompetitorKnowledge\Tests\Unit\Analysis;

use CompetitorKnowledge\Analysis\Analyzer;
use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use CompetitorKnowledge\Search\DTO\SearchResults;
use Mockery;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class AnalyzerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	public function test_process_runs_analysis_flow() {
		// Mocks
		$search_provider = Mockery::mock( SearchProviderInterface::class );
		$ai_provider     = Mockery::mock( AIProviderInterface::class );
		$repository      = Mockery::mock( AnalysisRepository::class );
		$product         = Mockery::mock( 'WC_Product' );

		// Expectations
		$analysis_id = 123;
		$product_id  = 456;

		$repository->shouldReceive( 'get_target_product_id' )
			->once()
			->with( $analysis_id )
			->andReturn( $product_id );
		
		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->with( $product_id )
			->andReturn( $product );

		$repository->shouldReceive( 'update_status' )
			->once() // processing
			->with( $analysis_id, 'processing' );

		// Product data extraction expectations
		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_sku' )->andReturn( 'SKU123' );
		$product->shouldReceive( 'get_price' )->andReturn( '99.00' );
		$product->shouldReceive( 'get_short_description' )->andReturn( 'Desc' );
		Monkey\Functions\expect( 'wp_strip_all_tags' )->andReturn( 'Desc' );

		// Search
		$search_results = new SearchResults( [['url' => 'http://example.com']] );
		$search_provider->shouldReceive( 'search' )
			->once()
			->andReturn( $search_results );

		// AI
		$ai_result = new AnalysisResult( ['competitors' => []] );
		$ai_provider->shouldReceive( 'analyze' )
			->once()
			->andReturn( $ai_result );

		$repository->shouldReceive( 'save_results' )
			->once()
			->with( $analysis_id, ['competitors' => []] );


		// Run
		$analyzer = new Analyzer( $search_provider, $ai_provider, $repository );
		$analyzer->process( $analysis_id );
		
		$this->assertTrue( true ); // Assert reached end without exception
	}
}
