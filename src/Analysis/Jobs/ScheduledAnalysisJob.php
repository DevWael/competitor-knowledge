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
		$options    = get_option( Settings::OPTION_NAME );
		$categories = $options['scheduled_analysis_categories'] ?? array();

		// Get products to analyze.
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 50, // Limit per run.
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

		$product_ids = get_posts( $args );

		if ( empty( $product_ids ) ) {
			return;
		}

		$repo = new AnalysisRepository();

		foreach ( $product_ids as $product_id ) {
			try {
				$analysis_id = $repo->create( (int) $product_id );

				// Schedule individual analysis job.
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time() + 60, AnalysisJob::ACTION, array( 'analysis_id' => $analysis_id ) );
				}
			} catch ( \Exception $e ) {
				error_log( 'Scheduled Analysis Error: ' . $e->getMessage() ); // phpcs:ignore
			}
		}
	}
}
