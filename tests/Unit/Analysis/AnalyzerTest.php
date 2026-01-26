<?php

namespace CompetitorKnowledge\Tests\Unit\Analysis;

use CompetitorKnowledge\Analysis\Analyzer;
use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\AI\DTO\AnalysisResult;
use CompetitorKnowledge\Search\DTO\SearchResults;
use CompetitorKnowledge\Admin\Settings;
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
		$price_history   = Mockery::mock( \CompetitorKnowledge\Data\PriceHistoryRepository::class );
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
		$ai_result = new AnalysisResult( ['competitors' => [
			[
				'name' => 'Competitor X',
				'price' => '80.00', // Cheaper than 99.00
				'currency' => 'USD'
			]
		]] );
		$ai_provider->shouldReceive( 'analyze' )
			->once()
			->andReturn( $ai_result );

		$repository->shouldReceive( 'save_results' )
			->once();

		// Price History & Notification Expectations
		$price_history->shouldReceive( 'add_record' )
			->once()
			->with( $product_id, $analysis_id, 'Competitor X', 80.00, 'USD' );

		Monkey\Functions\expect( 'get_option' )
			->with( Settings::OPTION_NAME )
			->andReturn( [ 'notification_email' => 'admin@test.com', 'price_drop_threshold' => 10 ] );
		
		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->with( 'admin@test.com', Mockery::type('string'), Mockery::type('string') );

		// Run
		$analyzer = new Analyzer( $search_provider, $ai_provider, $repository, $price_history );
		$analyzer->process( $analysis_id );
		
		$this->assertTrue( true );
	}

	public function test_process_fails_when_product_not_found() {
		$search_provider = Mockery::mock( SearchProviderInterface::class );
		$ai_provider     = Mockery::mock( AIProviderInterface::class );
		$repository      = Mockery::mock( AnalysisRepository::class );
		$price_history   = Mockery::mock( \CompetitorKnowledge\Data\PriceHistoryRepository::class );

		$analysis_id = 123;
		$product_id  = 456;

		$repository->shouldReceive( 'get_target_product_id' )
			->once()
			->andReturn( $product_id );

		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->andReturn( false );

		$repository->shouldReceive( 'update_status' )
			->once()
			->with( $analysis_id, 'failed' );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Target product #456 not found.' );

		$analyzer = new Analyzer( $search_provider, $ai_provider, $repository, $price_history );
		$analyzer->process( $analysis_id );
	}

	public function test_process_fails_when_no_search_results() {
		$search_provider = Mockery::mock( SearchProviderInterface::class );
		$ai_provider     = Mockery::mock( AIProviderInterface::class );
		$repository      = Mockery::mock( AnalysisRepository::class );
		$price_history   = Mockery::mock( \CompetitorKnowledge\Data\PriceHistoryRepository::class );
		$product         = Mockery::mock( 'WC_Product' );

		$analysis_id = 123;
		$product_id  = 456;

		$repository->shouldReceive( 'get_target_product_id' )
			->once()
			->andReturn( $product_id );

		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->andReturn( $product );

		$repository->shouldReceive( 'update_status' )
			->twice(); // processing + failed

		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_sku' )->andReturn( 'SKU123' );
		$product->shouldReceive( 'get_price' )->andReturn( '99.00' );
		$product->shouldReceive( 'get_short_description' )->andReturn( 'Desc' );
		Monkey\Functions\expect( 'wp_strip_all_tags' )->andReturn( 'Desc' );

		// Empty search results
		$search_results = new SearchResults( [] );
		$search_provider->shouldReceive( 'search' )
			->once()
			->andReturn( $search_results );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No search results found.' );

		$analyzer = new Analyzer( $search_provider, $ai_provider, $repository, $price_history );
		$analyzer->process( $analysis_id );
	}

	public function test_process_skips_notification_when_no_email() {
		$search_provider = Mockery::mock( SearchProviderInterface::class );
		$ai_provider     = Mockery::mock( AIProviderInterface::class );
		$repository      = Mockery::mock( AnalysisRepository::class );
		$price_history   = Mockery::mock( \CompetitorKnowledge\Data\PriceHistoryRepository::class );
		$product         = Mockery::mock( 'WC_Product' );

		$analysis_id = 123;
		$product_id  = 456;

		$repository->shouldReceive( 'get_target_product_id' )
			->once()
			->andReturn( $product_id );

		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->andReturn( $product );

		$repository->shouldReceive( 'update_status' )
			->once()
			->with( $analysis_id, 'processing' );

		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_sku' )->andReturn( 'SKU123' );
		$product->shouldReceive( 'get_price' )->andReturn( '99.00' );
		$product->shouldReceive( 'get_short_description' )->andReturn( 'Desc' );
		Monkey\Functions\expect( 'wp_strip_all_tags' )->andReturn( 'Desc' );

		$search_results = new SearchResults( [['url' => 'http://example.com']] );
		$search_provider->shouldReceive( 'search' )
			->once()
			->andReturn( $search_results );

		$ai_result = new AnalysisResult( ['competitors' => [
			[
				'name' => 'Competitor X',
				'price' => '80.00',
				'currency' => 'USD'
			]
		]] );
		$ai_provider->shouldReceive( 'analyze' )
			->once()
			->andReturn( $ai_result );

		$repository->shouldReceive( 'save_results' )
			->once();

		$price_history->shouldReceive( 'add_record' )
			->once();

		// No email configured
		Monkey\Functions\expect( 'get_option' )
			->with( Settings::OPTION_NAME )
			->andReturn( [ 'notification_email' => '', 'price_drop_threshold' => 10 ] );

		// wp_mail should NOT be called
		Monkey\Functions\expect( 'wp_mail' )
			->never();

		$analyzer = new Analyzer( $search_provider, $ai_provider, $repository, $price_history );
		$analyzer->process( $analysis_id );

		$this->assertTrue( true );
	}
}
