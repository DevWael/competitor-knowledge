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
	 * Log a message with context.
	 *
	 * @param string $message     The log message.
	 * @param int    $analysis_id The analysis ID.
	 * @param string $level       Log level (info, error, debug).
	 */
	private static function log( string $message, int $analysis_id, string $level = 'info' ): void {
		$prefix = sprintf( '[CK SaveStep #%d] [%s]', $analysis_id, strtoupper( $level ) );
		error_log( $prefix . ' ' . $message ); // phpcs:ignore
	}

	/**
	 * Handle the save results step.
	 *
	 * @param int $analysis_id The analysis ID.
	 */
	public static function handle( int $analysis_id ): void {
		self::log( 'Starting save results step...', $analysis_id );
		$repo = new AnalysisRepository();

		try {
			// Update current step.
			self::log( 'Setting current step to saving', $analysis_id );
			update_post_meta( $analysis_id, '_ck_current_step', 'saving' );
			update_post_meta( $analysis_id, '_ck_progress', 3 );

			$ai_results = get_post_meta( $analysis_id, '_ck_ai_results', true );

			if ( empty( $ai_results ) ) {
				throw new Exception( 'No AI results found to save' );
			}
			self::log( sprintf( 'AI results loaded (%d competitors)', count( $ai_results['competitors'] ?? array() ) ), $analysis_id );

			// Save the final results.
			self::log( 'Saving results to repository...', $analysis_id );
			$repo->save_results( $analysis_id, $ai_results );
			self::log( 'Results saved successfully', $analysis_id );

			// Record price history if competitor prices available.
			$product_id = $repo->get_target_product_id( $analysis_id );
			self::log( sprintf( 'Recording price history for product %d...', $product_id ), $analysis_id );
			$recorded_count = self::record_price_history( $product_id, $analysis_id, $ai_results );
			self::log( sprintf( 'Recorded %d price history entries', $recorded_count ), $analysis_id );

			// Clean up intermediate meta.
			self::log( 'Cleaning up intermediate meta...', $analysis_id );
			delete_post_meta( $analysis_id, '_ck_search_results' );
			delete_post_meta( $analysis_id, '_ck_ai_results' );
			delete_post_meta( $analysis_id, '_ck_current_step' );
			delete_post_meta( $analysis_id, '_ck_progress' );
			delete_post_meta( $analysis_id, '_ck_total_steps' );
			self::log( 'Intermediate meta cleaned up', $analysis_id );

			/**
			 * Fires after save results step completes successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $analysis_id The analysis ID.
			 * @param array $ai_results  The saved analysis results.
			 */
			do_action( 'ck_save_step_completed', $analysis_id, $ai_results );

			self::log( 'Save results step completed successfully - ANALYSIS COMPLETE', $analysis_id );

		} catch ( Exception $e ) {
			self::log( 'Save results step FAILED: ' . $e->getMessage(), $analysis_id, 'error' );
			$repo->update_status( $analysis_id, 'failed' );
			update_post_meta( $analysis_id, '_ck_error_message', $e->getMessage() );
			update_post_meta( $analysis_id, '_ck_error_stack_trace', $e->getTraceAsString() );
		}
	}

	/**
	 * Record price history from competitor data.
	 *
	 * @param int                  $product_id  The product ID.
	 * @param int                  $analysis_id The analysis ID.
	 * @param array<string, mixed> $ai_results  The AI analysis results.
	 * @return int Number of records added.
	 */
	private static function record_price_history( int $product_id, int $analysis_id, array $ai_results ): int {
		if ( empty( $ai_results['competitors'] ) ) {
			return 0;
		}

		$price_repo = new PriceHistoryRepository();
		$count      = 0;

		foreach ( $ai_results['competitors'] as $competitor ) {
			if ( ! isset( $competitor['name'], $competitor['price'] ) ) {
				self::log( sprintf( 'Skipping competitor without name/price: %s', wp_json_encode( $competitor ) ), $analysis_id, 'debug' );
				continue;
			}

			try {
				self::log( sprintf( 'Recording price for %s: %s', $competitor['name'], $competitor['price'] ), $analysis_id );
				$price_repo->add_record(
					$product_id,
					$analysis_id,
					$competitor['name'],
					(float) $competitor['price'],
					get_woocommerce_currency()
				);
				$count++;
			} catch ( Exception $e ) {
				// Log but don't fail the whole step.
				self::log( 'Price history recording failed for ' . $competitor['name'] . ': ' . $e->getMessage(), $analysis_id, 'error' );
			}
		}

		return $count;
	}
}

