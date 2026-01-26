<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Admin;

use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Analysis\Jobs\AnalysisJob;

/**
 * Class BulkActions
 *
 * Handles bulk actions for products.
 *
 * @package CompetitorKnowledge\Admin
 */
class BulkActions {

	/**
	 * Initialize bulk actions.
	 */
	public function init(): void {
		add_filter( 'bulk_actions-edit-product', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_admin_notice' ) );
	}

	/**
	 * Register the bulk action.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 *
	 * @return array
	 */
	public function register_bulk_action( array $bulk_actions ): array {
		$bulk_actions['ck_run_analysis'] = __( 'Run Competitor Analysis', 'competitor-knowledge' );
		return $bulk_actions;
	}

	/**
	 * Handle the bulk action.
	 *
	 * @param string $redirect_to The redirect URL.
	 * @param string $action      The action name.
	 * @param array  $post_ids    The product IDs.
	 *
	 * @return string
	 */
	public function handle_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( 'ck_run_analysis' !== $action ) {
			return $redirect_to;
		}

		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}

		$repo  = new AnalysisRepository();
		$count = 0;

		foreach ( $post_ids as $product_id ) {
			try {
				$analysis_id = $repo->create( (int) $product_id );

				// Schedule the job
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time(), AnalysisJob::ACTION, array( 'analysis_id' => $analysis_id ) );
					++$count;
				}
			} catch ( \Exception $e ) {
				// Log error and continue
				error_log( 'Bulk Analysis Error: ' . $e->getMessage() ); // phpcs:ignore
			}
		}

		// Add query arg for admin notice
		$redirect_to = add_query_arg( 'ck_bulk_analysis', $count, $redirect_to );

		return $redirect_to;
	}

	/**
	 * Display admin notice after bulk action.
	 */
	public function bulk_action_admin_notice(): void {
		if ( ! isset( $_REQUEST['ck_bulk_analysis'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$count = (int) $_REQUEST['ck_bulk_analysis']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $count > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of products */
					esc_html( _n( 'Analysis started for %d product.', 'Analysis started for %d products.', $count, 'competitor-knowledge' ) ),
					esc_html( (string) $count )
				)
			);
		}
	}
}
