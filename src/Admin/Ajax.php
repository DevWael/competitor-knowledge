<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Admin;

use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Analysis\Jobs\AnalysisJob;

/**
 * Class Ajax
 *
 * Handles AJAX requests.
 *
 * @package CompetitorKnowledge\Admin
 */
class Ajax {

	/**
	 * Initialize AJAX hooks.
	 */
	public function init(): void {
		add_action( 'wp_ajax_ck_run_analysis', array( $this, 'run_analysis' ) );
	}

	/**
	 * Run analysis via AJAX.
	 */
	public function run_analysis(): void {
		check_ajax_referer( 'ck_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

		if ( ! $product_id ) {
			wp_send_json_error( 'Invalid product ID.' );
		}

		try {
			$repo        = new AnalysisRepository();
			$analysis_id = $repo->create( $product_id );

			// Schedule the job
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), AnalysisJob::ACTION, array( 'analysis_id' => $analysis_id ) );
			} else {
				throw new \Exception( 'Action Scheduler not available.' );
			}

			wp_send_json_success( array( 'message' => __( 'Analysis started successfully.', 'competitor-knowledge' ) ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
