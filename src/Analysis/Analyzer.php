<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use CompetitorKnowledge\Data\AnalysisRepository;
use Exception;
use WC_Product;

/**
 * Class Analyzer
 *
 * Coordinates the analysis process: Search -> AI Analysis -> Save.
 *
 * @package CompetitorKnowledge\Analysis
 */
class Analyzer {

	/**
	 * Search Provider.
	 *
	 * @var SearchProviderInterface
	 */
	private SearchProviderInterface $search_provider;

	/**
	 * AI Provider.
	 *
	 * @var AIProviderInterface
	 */
	private AIProviderInterface $ai_provider;

	/**
	 * Repository.
	 *
	 * @var AnalysisRepository
	 */
	private AnalysisRepository $repository;

	/**
	 * Analyzer constructor.
	 *
	 * @param SearchProviderInterface $search_provider Search service.
	 * @param AIProviderInterface     $ai_provider     AI service.
	 * @param AnalysisRepository      $repository      Data repository.
	 */
	public function __construct(
		SearchProviderInterface $search_provider,
		AIProviderInterface $ai_provider,
		AnalysisRepository $repository
	) {
		$this->search_provider = $search_provider;
		$this->ai_provider     = $ai_provider;
		$this->repository      = $repository;
	}

	/**
	 * Process an analysis request.
	 *
	 * @param int $analysis_id The ID of the analysis post.
	 *
	 * @throws Exception If processing fails.
	 */
	public function process( int $analysis_id ): void {
		// 1. Get Analysis & Product
		$product_id = $this->repository->get_target_product_id( $analysis_id );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			$this->repository->update_status( $analysis_id, 'failed' );
			throw new Exception( "Target product #{$product_id} not found." );
		}

		$this->repository->update_status( $analysis_id, 'processing' );

		try {
			// 2. Prepare Context (Product Data)
			$product_data = $this->get_product_data( $product );
			$query        = sprintf( 'Buy %s %s online price specs', $product->get_name(), $product->get_sku() );

			// 3. Search for Competitors
			$search_results = $this->search_provider->search( $query, 5 );

			if ( $search_results->is_empty() ) {
				throw new Exception( 'No search results found.' );
			}

			// 4. AI Analysis
			$context = [
				'my_product' => $product_data,
				'web_search' => $search_results->get_results(),
			];

			$prompt = "Analyze the search results to find competitors selling the same product. \n" .
					"Compare prices, specifications, and availability. \n" .
					"Return a structured JSON with 'competitors' list, each having 'name', 'url', 'price', 'currency', 'stock_status', and 'comparison_notes'.";

			$analysis_result = $this->ai_provider->analyze( $prompt, $context );

			// 5. Save Results
			$this->repository->save_results( $analysis_id, $analysis_result->to_array() );

		} catch ( Exception $e ) {
			$this->repository->update_status( $analysis_id, 'failed' );
			// Log error via Action Scheduler or WP Log
			error_log( 'Analysis Failed: ' . $e->getMessage() ); // phpcs:ignore
			throw $e;
		}
	}

	/**
	 * Extract relevant product data.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return array
	 */
	private function get_product_data( WC_Product $product ): array {
		return [
			'name'  => $product->get_name(),
			'sku'   => $product->get_sku(),
			'price' => $product->get_price(),
			'desc'  => wp_strip_all_tags( $product->get_short_description() ),
		];
	}
}
