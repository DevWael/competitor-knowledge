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
	 * Log a message with context.
	 *
	 * @param string $message     The log message.
	 * @param int    $analysis_id The analysis ID.
	 * @param string $level       Log level (info, error, debug).
	 */
	private static function log( string $message, int $analysis_id, string $level = 'info' ): void {
		$prefix = sprintf( '[CK AIStep #%d] [%s]', $analysis_id, strtoupper( $level ) );
		error_log( $prefix . ' ' . $message ); // phpcs:ignore
	}

	/**
	 * Handle the AI analysis step.
	 *
	 * @param int $analysis_id The analysis ID.
	 */
	public static function handle( int $analysis_id ): void {
		self::log( 'Starting AI analysis step...', $analysis_id );
		$repo = new AnalysisRepository();

		try {
			// Update current step.
			self::log( 'Setting current step to analyzing', $analysis_id );
			update_post_meta( $analysis_id, '_ck_current_step', 'analyzing' );
			update_post_meta( $analysis_id, '_ck_progress', 2 );

			$product_id     = $repo->get_target_product_id( $analysis_id );
			self::log( sprintf( 'Target product ID: %d', $product_id ), $analysis_id );

			$product        = wc_get_product( $product_id );
			$search_results = get_post_meta( $analysis_id, '_ck_search_results', true );

			if ( ! $product ) {
				throw new Exception( sprintf( 'Product not found (ID: %d)', $product_id ) );
			}
			if ( empty( $search_results ) ) {
				throw new Exception( 'No search results found from previous step' );
			}

			self::log( sprintf( 'Product loaded: %s', $product->get_name() ), $analysis_id );
			self::log( sprintf( 'Search results count: %d', count( $search_results ) ), $analysis_id );

			$container      = Container::get_instance();
			$ai_provider    = $container->get( AIProviderInterface::class );
			$prompt_builder = $container->get( PromptBuilder::class );

			self::log( sprintf( 'AI provider: %s', get_class( $ai_provider ) ), $analysis_id );

			// Truncate search results to limit payload size.
			$truncated_results = self::truncate_search_results( $search_results );
			self::log( sprintf( 'Truncated search results from %d to %d chars', strlen( wp_json_encode( $search_results ) ), strlen( wp_json_encode( $truncated_results ) ) ), $analysis_id );

			// Build context.
			$context = array(
				'product_name'        => $product->get_name(),
				'product_description' => wp_trim_words( $product->get_description(), 100 ), // Limit description.
				'product_price'       => $product->get_price(),
				'product_sku'         => $product->get_sku(),
				'search_results'      => $truncated_results,
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
			self::log( 'Context built successfully', $analysis_id );

			// Build prompt and run analysis.
			self::log( 'Building prompt...', $analysis_id );
			$prompt = $prompt_builder->build();
			self::log( sprintf( 'Prompt length: %d chars', strlen( $prompt ) ), $analysis_id );

			self::log( 'Sending to AI provider...', $analysis_id );
			$result = $ai_provider->analyze( $prompt, $context );
			self::log( 'AI analysis completed', $analysis_id );

			// Store AI results for next step.
			$result_array = $result->to_array();
			update_post_meta( $analysis_id, '_ck_ai_results', $result_array );
			self::log( sprintf( 'AI results saved (%d competitors found)', count( $result_array['competitors'] ?? array() ) ), $analysis_id );

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
				self::log( 'Scheduled save results step', $analysis_id );
			}

			self::log( 'AI analysis step completed successfully', $analysis_id );

		} catch ( Exception $e ) {
			self::log( 'AI analysis step FAILED: ' . $e->getMessage(), $analysis_id, 'error' );
			$repo->update_status( $analysis_id, 'failed' );
			update_post_meta( $analysis_id, '_ck_error_message', $e->getMessage() );
			update_post_meta( $analysis_id, '_ck_error_stack_trace', $e->getTraceAsString() );
		}
	}

	/**
	 * Truncate search results to limit payload size.
	 *
	 * @param array<mixed> $search_results The original search results.
	 * @return array<mixed> Truncated search results.
	 */
	private static function truncate_search_results( array $search_results ): array {
		$truncated = array();
		$max_content_length = 2000; // Max chars per result content.
		$max_results = 5; // Limit to top 5 results.

		$count = 0;
		foreach ( $search_results as $result ) {
			if ( $count >= $max_results ) {
				break;
			}

			$truncated_result = array(
				'title' => isset( $result['title'] ) ? substr( (string) $result['title'], 0, 200 ) : '',
				'url'   => $result['url'] ?? '',
			);

			// Truncate content/snippet.
			if ( ! empty( $result['content'] ) ) {
				$truncated_result['content'] = substr( (string) $result['content'], 0, $max_content_length );
			} elseif ( ! empty( $result['snippet'] ) ) {
				$truncated_result['content'] = substr( (string) $result['snippet'], 0, $max_content_length );
			}

			// Keep score if available.
			if ( isset( $result['score'] ) ) {
				$truncated_result['score'] = $result['score'];
			}

			$truncated[] = $truncated_result;
			++$count;
		}

		return $truncated;
	}
}

