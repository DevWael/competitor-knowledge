<?php
/**
 * Save Results Step Job for Analysis Pipeline.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis\Jobs;

use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Data\PriceHistoryRepository;
use Exception;

/**
 * Class SaveResultsStepJob
 *
 * Handles the final step of the analysis pipeline - saving results.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */
class SaveResultsStepJob {

	/**
	 * Job Action Name.
	 */
	public const ACTION = 'ck_save_results_step_job';

	/**
	 * Initialize the job listener.
	 */
	public static function init(): void {
		add_action( self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Handle the save results step.
	 *
	 * @param int $analysis_id The analysis ID.
	 */
	public static function handle( int $analysis_id ): void {
		$repo = new AnalysisRepository();

		try {
			// Update current step.
			update_post_meta( $analysis_id, '_ck_current_step', 'saving' );
			update_post_meta( $analysis_id, '_ck_progress', 3 );

			$ai_results = get_post_meta( $analysis_id, '_ck_ai_results', true );

			if ( empty( $ai_results ) ) {
				throw new Exception( 'No AI results to save.' );
			}

			// Save the final results.
			$repo->save_results( $analysis_id, $ai_results );

			// Record price history if competitor prices available.
			$product_id = $repo->get_target_product_id( $analysis_id );
			self::record_price_history( $product_id, $analysis_id, $ai_results );

			// Clean up intermediate meta.
			delete_post_meta( $analysis_id, '_ck_search_results' );
			delete_post_meta( $analysis_id, '_ck_ai_results' );
			delete_post_meta( $analysis_id, '_ck_current_step' );
			delete_post_meta( $analysis_id, '_ck_progress' );
			delete_post_meta( $analysis_id, '_ck_total_steps' );

			/**
			 * Fires after save results step completes successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $analysis_id The analysis ID.
			 * @param array $ai_results  The saved analysis results.
			 */
			do_action( 'ck_save_step_completed', $analysis_id, $ai_results );

		} catch ( Exception $e ) {
			$repo->update_status( $analysis_id, 'failed' );
			update_post_meta( $analysis_id, '_ck_error_message', $e->getMessage() );
			error_log( 'Save Results Step Failed: ' . $e->getMessage() ); // phpcs:ignore
		}
	}

	/**
	 * Record price history from competitor data.
	 *
	 * @param int                  $product_id The product ID.
	 * @param array<string, mixed> $ai_results The AI analysis results.
	 */
	private static function record_price_history( int $product_id, int $analysis_id, array $ai_results ): void {
		if ( empty( $ai_results['competitors'] ) ) {
			return;
		}

		$price_repo = new PriceHistoryRepository();

		foreach ( $ai_results['competitors'] as $competitor ) {
			if ( ! isset( $competitor['name'], $competitor['price'] ) ) {
				continue;
			}

			try {
				$price_repo->add_record(
					$product_id,
					$analysis_id,
					$competitor['name'],
					(float) $competitor['price'],
					get_woocommerce_currency()
				);
			} catch ( Exception $e ) {
				// Log but don't fail the whole step.
				error_log( 'Price history recording failed: ' . $e->getMessage() ); // phpcs:ignore
			}
		}
	}
}
