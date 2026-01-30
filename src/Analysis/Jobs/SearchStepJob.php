<?php
/**
 * Search Step Job for Analysis Pipeline.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis\Jobs;

use CompetitorKnowledge\Core\Container;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Search\Contracts\SearchProviderInterface;
use Exception;

/**
 * Class SearchStepJob
 *
 * Handles the search step of the analysis pipeline.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */
class SearchStepJob {

	/**
	 * Job Action Name.
	 */
	public const ACTION = 'ck_search_step_job';

	/**
	 * Initialize the job listener.
	 */
	public static function init(): void {
		add_action( self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Handle the search step.
	 *
	 * @param int $analysis_id The analysis ID.
	 */
	public static function handle( int $analysis_id ): void {
		$repo = new AnalysisRepository();

		try {
			// Update status to processing and set current step.
			$repo->update_status( $analysis_id, 'processing' );
			update_post_meta( $analysis_id, '_ck_current_step', 'searching' );
			update_post_meta( $analysis_id, '_ck_progress', 1 );
			update_post_meta( $analysis_id, '_ck_total_steps', 3 );

			$product_id = $repo->get_target_product_id( $analysis_id );
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				throw new Exception( 'Product not found.' );
			}

			$container       = Container::get_instance();
			$search_provider = $container->get( SearchProviderInterface::class );

			// Build search query from product data.
			$query = sprintf(
				'%s %s competitors pricing features reviews',
				$product->get_name(),
				implode( ' ', wp_list_pluck( wc_get_product_terms( $product_id, 'product_cat' ), 'name' ) )
			);

			/**
			 * Filters the search query before execution.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $query       The search query.
			 * @param int         $analysis_id The analysis ID.
			 * @param \WC_Product $product     The product object.
			 */
			$query = apply_filters( 'ck_search_step_query', $query, $analysis_id, $product );

			// Execute search.
			$results = $search_provider->search( $query, 10 );

			// Store search results for next step.
			update_post_meta( $analysis_id, '_ck_search_results', $results->get_results() );

			/**
			 * Fires after search step completes successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $analysis_id The analysis ID.
			 * @param array $results     The search results.
			 */
			do_action( 'ck_search_step_completed', $analysis_id, $results->get_results() );

			// Schedule the next step.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), AIAnalysisStepJob::ACTION, array( 'analysis_id' => $analysis_id ) );
			}
		} catch ( Exception $e ) {
			$repo->update_status( $analysis_id, 'failed' );
			update_post_meta( $analysis_id, '_ck_error_message', $e->getMessage() );
			error_log( 'Search Step Failed: ' . $e->getMessage() ); // phpcs:ignore
		}
	}
}
