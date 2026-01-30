<?php
/**
 * Synchronous Analysis Runner.
 *
 * Runs analysis synchronously for active users instead of via Action Scheduler.
 *
 * @package CompetitorKnowledge\Analysis
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis;

use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Core\Container;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use Exception;

/**
 * Class SyncAnalysisRunner
 *
 * Executes all analysis steps synchronously in a single request.
 *
 * @package CompetitorKnowledge\Analysis
 */
class SyncAnalysisRunner {

	/**
	 * Run analysis synchronously.
	 *
	 * @param int $analysis_id The analysis ID.
	 * @return array{success: bool, message: string, data?: array<string, mixed>} Result.
	 */
	public static function run( int $analysis_id ): array {
		error_log( sprintf( '[CK Sync #%d] Starting synchronous analysis...', $analysis_id ) );
		$start_time = microtime( true );
		$repo       = new AnalysisRepository();

		try {
			// Step 1: Search.
			error_log( sprintf( '[CK Sync #%d] Step 1: Starting search...', $analysis_id ) );
			update_post_meta( $analysis_id, '_ck_status', 'processing' );
			update_post_meta( $analysis_id, '_ck_current_step', 'searching' );
			update_post_meta( $analysis_id, '_ck_progress', 1 );

			$product_id = $repo->get_target_product_id( $analysis_id );
			error_log( sprintf( '[CK Sync #%d] Target product ID: %d', $analysis_id, $product_id ) );
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				throw new Exception( sprintf( 'Product not found (ID: %d)', $product_id ) );
			}

			error_log( sprintf( '[CK Sync #%d] Product loaded: %s', $analysis_id, $product->get_name() ) );
			$container       = Container::get_instance();
			$search_provider = $container->get( SearchProviderInterface::class );
			$prompt_builder  = $container->get( PromptBuilder::class );

			// Build search query from product data (same as SearchStepJob).
			$query = sprintf(
				'%s %s competitors pricing features reviews',
				$product->get_name(),
				implode( ' ', wp_list_pluck( wc_get_product_terms( $product_id, 'product_cat' ), 'name' ) )
			);

			error_log( sprintf( '[CK Sync #%d] Search query: %s', $analysis_id, $query ) );
			$search_start    = microtime( true );
			$search_result   = $search_provider->search( $query, 10 );
			$search_results  = $search_result->get_results();
			$search_elapsed  = round( ( microtime( true ) - $search_start ) * 1000 );
			error_log( sprintf( '[CK Sync #%d] Search completed in %dms, found %d results', $analysis_id, $search_elapsed, count( $search_results ) ) );

			if ( empty( $search_results ) ) {
				throw new Exception( 'Search returned no results' );
			}

			// Store search results.
			update_post_meta( $analysis_id, '_ck_search_results', $search_results );
			error_log( sprintf( '[CK Sync #%d] Search results saved to meta', $analysis_id ) );

			// Step 2: AI Analysis.
			error_log( sprintf( '[CK Sync #%d] Step 2: Starting AI analysis...', $analysis_id ) );
			update_post_meta( $analysis_id, '_ck_current_step', 'analyzing' );
			update_post_meta( $analysis_id, '_ck_progress', 2 );

			$ai_provider = $container->get( AIProviderInterface::class );
			error_log( sprintf( '[CK Sync #%d] AI provider: %s', $analysis_id, get_class( $ai_provider ) ) );

			// Truncate search results for AI.
			$truncated_results = self::truncate_search_results( $search_results );
			error_log( sprintf( '[CK Sync #%d] Truncated search results from %d to %d chars', $analysis_id, strlen( wp_json_encode( $search_results ) ), strlen( wp_json_encode( $truncated_results ) ) ) );

			$context = array(
				'product_name'        => $product->get_name(),
				'product_description' => wp_trim_words( $product->get_description(), 100 ),
				'product_price'       => $product->get_price(),
				'product_sku'         => $product->get_sku(),
				'search_results'      => $truncated_results,
			);

			error_log( sprintf( '[CK Sync #%d] Building prompt...', $analysis_id ) );
			$prompt = $prompt_builder->build();
			error_log( sprintf( '[CK Sync #%d] Prompt length: %d chars', $analysis_id, strlen( $prompt ) ) );

			error_log( sprintf( '[CK Sync #%d] Sending to AI provider...', $analysis_id ) );
			$ai_start = microtime( true );
			$result   = $ai_provider->analyze( $prompt, $context );
			$ai_elapsed = round( ( microtime( true ) - $ai_start ) * 1000 );
			error_log( sprintf( '[CK Sync #%d] AI analysis completed in %dms', $analysis_id, $ai_elapsed ) );

			if ( ! $result->is_valid() ) {
				throw new Exception( 'AI returned invalid response' );
			}

			// Step 3: Save Results.
			error_log( sprintf( '[CK Sync #%d] Step 3: Saving results...', $analysis_id ) );
			update_post_meta( $analysis_id, '_ck_current_step', 'saving' );
			update_post_meta( $analysis_id, '_ck_progress', 3 );

			$analysis_data = $result->to_array();
			update_post_meta( $analysis_id, '_ck_analysis_data', $analysis_data );
			error_log( sprintf( '[CK Sync #%d] Analysis data saved, found %d competitors', $analysis_id, count( $analysis_data['competitors'] ?? array() ) ) );

			// Save price history.
			if ( ! empty( $analysis_data['competitors'] ) ) {
				$price_repo = new \CompetitorKnowledge\Data\PriceHistoryRepository();
				$currency   = get_woocommerce_currency();
				$saved      = 0;
				foreach ( $analysis_data['competitors'] as $competitor ) {
					if ( ! empty( $competitor['price'] ) ) {
						$price_repo->add_record(
							$product_id,
							$analysis_id,
							$competitor['name'] ?? 'Unknown',
							(float) $competitor['price'],
							$currency
						);
					}
				}
			}

			// Mark complete.
			$repo->update_status( $analysis_id, 'completed' );
			update_post_meta( $analysis_id, '_ck_progress', 100 );
			delete_post_meta( $analysis_id, '_ck_current_step' );

			$total_elapsed = round( ( microtime( true ) - $start_time ) * 1000 );
			error_log( sprintf( '[CK Sync #%d] Analysis completed successfully in %dms', $analysis_id, $total_elapsed ) );

			return array(
				'success' => true,
				'message' => __( 'Analysis completed successfully.', 'competitor-knowledge' ),
				'data'    => array(
					'analysis_id'  => $analysis_id,
					'competitors'  => count( $analysis_data['competitors'] ?? array() ),
				),
			);
		} catch ( Exception $e ) {
			$elapsed = round( ( microtime( true ) - $start_time ) * 1000 );
			error_log( sprintf( '[CK Sync #%d] FAILED after %dms: %s', $analysis_id, $elapsed, $e->getMessage() ) );
			error_log( sprintf( '[CK Sync #%d] Stack trace: %s', $analysis_id, $e->getTraceAsString() ) );
			
			$repo->update_status( $analysis_id, 'failed' );
			update_post_meta( $analysis_id, '_ck_error_message', $e->getMessage() );
			update_post_meta( $analysis_id, '_ck_error_stack_trace', $e->getTraceAsString() );

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Truncate search results to limit payload size.
	 *
	 * @param array<mixed> $search_results The original search results.
	 * @return array<mixed> Truncated search results.
	 */
	private static function truncate_search_results( array $search_results ): array {
		$truncated          = array();
		$max_content_length = 2000;
		$max_results        = 5;

		$count = 0;
		foreach ( $search_results as $result ) {
			if ( $count >= $max_results ) {
				break;
			}

			$truncated_result = array(
				'title' => isset( $result['title'] ) ? substr( (string) $result['title'], 0, 200 ) : '',
				'url'   => $result['url'] ?? '',
			);

			if ( ! empty( $result['content'] ) ) {
				$truncated_result['content'] = substr( (string) $result['content'], 0, $max_content_length );
			} elseif ( ! empty( $result['snippet'] ) ) {
				$truncated_result['content'] = substr( (string) $result['snippet'], 0, $max_content_length );
			}

			if ( isset( $result['score'] ) ) {
				$truncated_result['score'] = $result['score'];
			}

			$truncated[] = $truncated_result;
			++$count;
		}

		return $truncated;
	}
}
