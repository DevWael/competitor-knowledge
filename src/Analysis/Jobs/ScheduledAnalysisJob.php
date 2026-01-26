<?php
/**
 * Scheduled Analysis Job Handler.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis\Jobs;

use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Admin\Settings;

/**
 * Class ScheduledAnalysisJob
 *
 * Handles scheduled recurring analysis.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */
class ScheduledAnalysisJob {

	/**
	 * Job Action Name.
	 */
	public const ACTION = 'ck_scheduled_analysis_job';

	/**
	 * Initialize the job.
	 */
	public static function init(): void {
		add_action( self::ACTION, array( self::class, 'handle' ) );
		add_action( 'init', array( self::class, 'schedule_recurring_job' ) );
	}

	/**
	 * Schedule the recurring job if enabled.
	 */
	public static function schedule_recurring_job(): void {
		$options   = get_option( Settings::OPTION_NAME );
		$enabled   = $options['scheduled_analysis_enabled'] ?? false;
		$frequency = $options['scheduled_analysis_frequency'] ?? 'weekly';

		if ( ! $enabled ) {
			// Unschedule if disabled.
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::ACTION );
			}
			return;
		}

		// Check if already scheduled.
		if ( function_exists( 'as_next_scheduled_action' ) && as_next_scheduled_action( self::ACTION ) ) {
			return;
		}

		// Schedule based on frequency.
		$interval = self::get_interval( $frequency );

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( time(), $interval, self::ACTION );
		}
	}

	/**
	 * Get interval in seconds.
	 *
	 * @param string $frequency Frequency string.
	 *
	 * @return int
	 */
	private static function get_interval( string $frequency ): int {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => MONTH_IN_SECONDS,
		);

		return $intervals[ $frequency ] ?? WEEK_IN_SECONDS;
	}

	/**
	 * Handle the scheduled job.
	 */
	public static function handle(): void {
		/**
		 * Fires before the scheduled analysis job runs.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ck_before_scheduled_analysis' );

		$options    = get_option( Settings::OPTION_NAME );
		$categories = $options['scheduled_analysis_categories'] ?? array();

		/**
		 * Filters the default batch limit for scheduled analysis.
		 *
		 * @since 1.0.0
		 *
		 * @param int $limit The maximum number of products to analyze per run.
		 */
		$batch_limit = apply_filters( 'ck_scheduled_analysis_batch_limit', 50 );

		// Get products to analyze.
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $batch_limit,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( ! empty( $categories ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $categories,
				),
			);
		}

		/**
		 * Filters the query args for fetching products to analyze.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args       The WP_Query args.
		 * @param array<int, int>      $categories The category IDs to filter by.
		 */
		$args = apply_filters( 'ck_scheduled_analysis_query_args', $args, $categories );

		$product_ids = get_posts( $args );

		/**
		 * Filters the product IDs to be analyzed in the scheduled job.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, int|\WP_Post> $product_ids The product IDs to analyze.
		 */
		$product_ids = apply_filters( 'ck_scheduled_analysis_products', $product_ids );

		if ( empty( $product_ids ) ) {
			return;
		}

		$repo = new AnalysisRepository();

		/**
		 * Filters the delay between individual analysis jobs in seconds.
		 *
		 * @since 1.0.0
		 *
		 * @param int $delay Delay in seconds between job schedules.
		 */
		$job_delay = apply_filters( 'ck_scheduled_analysis_job_delay', 60 );

		foreach ( $product_ids as $product_id ) {
			// Handle both int (when fields = 'ids') and WP_Post object.
			$product_id_int = is_object( $product_id ) ? $product_id->ID : (int) $product_id;

			try {
				$analysis_id = $repo->create( $product_id_int );

				// Schedule individual analysis job.
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time() + $job_delay, AnalysisJob::ACTION, array( 'analysis_id' => $analysis_id ) );
				}

				/**
				 * Fires after a product is scheduled for analysis.
				 *
				 * @since 1.0.0
				 *
				 * @param int $product_id  The product ID.
				 * @param int $analysis_id The created analysis ID.
				 */
				do_action( 'ck_product_scheduled_for_analysis', $product_id_int, $analysis_id );
			} catch ( \Exception $e ) {
				error_log( 'Scheduled Analysis Error: ' . $e->getMessage() ); // phpcs:ignore
			}
		}

		/**
		 * Fires after the scheduled analysis job completes.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, int|\WP_Post> $product_ids The product IDs that were scheduled.
		 */
		do_action( 'ck_after_scheduled_analysis', $product_ids );
	}
}
