<?php
/**
 * AI Analysis Step Job for Analysis Pipeline.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis\Jobs;

use CompetitorKnowledge\Core\Container;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\AI\Contracts\AIProviderInterface;
use CompetitorKnowledge\Analysis\PromptBuilder;
use Exception;

/**
 * Class AIAnalysisStepJob
 *
 * Handles the AI analysis step of the pipeline.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */
class AIAnalysisStepJob {

	/**
	 * Job Action Name.
	 */
	public const ACTION = 'ck_ai_analysis_step_job';

	/**
	 * Initialize the job listener.
	 */
	public static function init(): void {
		add_action( self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Handle the AI analysis step.
	 *
	 * @param int $analysis_id The analysis ID.
	 */
	public static function handle( int $analysis_id ): void {
		$repo = new AnalysisRepository();

		try {
			// Update current step.
			update_post_meta( $analysis_id, '_ck_current_step', 'analyzing' );
			update_post_meta( $analysis_id, '_ck_progress', 2 );

			$product_id     = $repo->get_target_product_id( $analysis_id );
			$product        = wc_get_product( $product_id );
			$search_results = get_post_meta( $analysis_id, '_ck_search_results', true );

			if ( ! $product || empty( $search_results ) ) {
				throw new Exception( 'Missing product or search results.' );
			}

			$container    = Container::get_instance();
			$ai_provider  = $container->get( AIProviderInterface::class );
			$prompt_builder = $container->get( PromptBuilder::class );

			// Build context.
			$context = array(
				'product_name'        => $product->get_name(),
				'product_description' => $product->get_description(),
				'product_price'       => $product->get_price(),
				'product_sku'         => $product->get_sku(),
				'search_results'      => $search_results,
			);

			/**
			 * Filters the AI context before analysis.
			 *
			 * @since 1.0.0
			 *
			 * @param array       $context     The context data.
			 * @param int         $analysis_id The analysis ID.
			 * @param \WC_Product $product     The product object.
			 */
			$context = apply_filters( 'ck_ai_step_context', $context, $analysis_id, $product );

			// Build prompt and run analysis.
			$prompt = $prompt_builder->build();
			$result = $ai_provider->analyze( $prompt, $context );

			// Store AI results for next step.
			update_post_meta( $analysis_id, '_ck_ai_results', $result->to_array() );

			/**
			 * Fires after AI analysis step completes successfully.
			 *
			 * @since 1.0.0
			 *
			 * @param int                                           $analysis_id The analysis ID.
			 * @param \CompetitorKnowledge\AI\DTO\AnalysisResult    $result      The analysis result.
			 */
			do_action( 'ck_ai_step_completed', $analysis_id, $result );

			// Schedule the next step.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), SaveResultsStepJob::ACTION, array( 'analysis_id' => $analysis_id ) );
			}
		} catch ( Exception $e ) {
			$repo->update_status( $analysis_id, 'failed' );
			update_post_meta( $analysis_id, '_ck_error_message', $e->getMessage() );
			error_log( 'AI Analysis Step Failed: ' . $e->getMessage() ); // phpcs:ignore
		}
	}
}
