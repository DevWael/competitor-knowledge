<?php
/**
 * Analysis Job handler for Action Scheduler.
 *
 * @package CompetitorKnowledge
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis\Jobs;

use CompetitorKnowledge\Analysis\Analyzer;
use CompetitorKnowledge\Core\Container;
use Exception;

/**
 * Class AnalysisJob
 *
 * Handles the background analysis job.
 *
 * @package CompetitorKnowledge\Analysis\Jobs
 */
class AnalysisJob {

	/**
	 * Job Action Name.
	 */
	public const ACTION = 'ck_run_analysis_job';

	/**
	 * Initialize the job listener.
	 */
	public static function init(): void {
		add_action( self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Handle the analysis job by resolving Analyzer from container and processing.
	 *
	 * @param int $analysis_id The analysis ID.
	 */
	public static function handle( int $analysis_id ): void {
		try {
			$container = Container::get_instance();
			$analyzer  = $container->get( Analyzer::class );
			$analyzer->process( $analysis_id );
		} catch ( Exception $e ) {
			// Log error.
			error_log( 'Analysis Job Failed: ' . $e->getMessage() ); // phpcs:ignore
		}
	}
}
