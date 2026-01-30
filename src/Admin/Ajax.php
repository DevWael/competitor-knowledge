<?php
/**
 * AJAX Handler.
 *
 * @package CompetitorKnowledge\Admin
 */

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
		add_action( 'wp_ajax_ck_get_analysis_progress', array( $this, 'get_analysis_progress' ) );
		add_action( 'wp_ajax_ck_get_product_analyses_modal', array( $this, 'get_product_analyses_modal' ) );
		add_action( 'wp_ajax_ck_test_zai_api', array( $this, 'test_zai_api' ) );
		add_action( 'wp_ajax_ck_retry_analysis', array( $this, 'retry_analysis' ) );
	}

	/**
	 * Test Z.AI API connection.
	 */
	public function test_zai_api(): void {
		check_ajax_referer( 'ck_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$options = get_option( Settings::OPTION_NAME, array() );
		$api_key = Settings::get_decrypted( 'zai_api_key' ); // Use decrypted key!
		$model   = $options['model_name'] ?? 'glm-4.7';

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'No Z.AI API key configured.' );
		}

		// Simple test request.
		$url = $options['zai_endpoint_url'] ?? 'https://api.z.ai/api/coding/paas/v4/chat/completions';
		$body = wp_json_encode(
			array(
				'model'    => $model,
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => 'Say "Hello" in one word.',
					),
				),
			)
		);

		$start_time = microtime( true );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => $body,
				'timeout' => 30,
			)
		);

		$elapsed = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message'  => 'Connection failed: ' . $response->get_error_message(),
					'elapsed'  => $elapsed . 'ms',
				)
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		wp_send_json_success(
			array(
				'status_code'   => $status_code,
				'elapsed'       => $elapsed . 'ms',
				'model'         => $model,
				'url'           => $url,
				'response'      => $data,
				'raw_response'  => substr( $response_body, 0, 2000 ),
			)
		);
	}

	/**
	 * Run analysis via AJAX.
	 *
	 * @throws \Exception If Action Scheduler is not available.
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

		/**
		 * Fires before an AJAX analysis request is processed.
		 *
		 * @since 1.0.0
		 *
		 * @param int $product_id The product ID to analyze.
		 */
		do_action( 'ck_before_ajax_analysis', $product_id );

		try {
			$repo        = new AnalysisRepository();
			$analysis_id = $repo->create( $product_id );

			// Schedule the first step job instead of monolithic job.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time(),
					\CompetitorKnowledge\Analysis\Jobs\SearchStepJob::ACTION,
					array( 'analysis_id' => $analysis_id )
				);
			} else {
				throw new \Exception( 'Action Scheduler not available.' );
			}

			/**
			 * Fires after an AJAX analysis request is successfully processed.
			 *
			 * @since 1.0.0
			 *
			 * @param int $product_id  The product ID.
			 * @param int $analysis_id The created analysis ID.
			 */
			do_action( 'ck_after_ajax_analysis', $product_id, $analysis_id );

			wp_send_json_success(
				array(
					'message'     => __( 'Analysis started successfully.', 'competitor-knowledge' ),
					'analysis_id' => $analysis_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Retry a failed analysis via AJAX.
	 */
	public function retry_analysis(): void {
		check_ajax_referer( 'ck_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$analysis_id = isset( $_POST['analysis_id'] ) ? (int) $_POST['analysis_id'] : 0;

		if ( ! $analysis_id ) {
			wp_send_json_error( 'Invalid analysis ID.' );
		}

		// Verify it's a failed analysis.
		$status = get_post_meta( $analysis_id, '_ck_status', true );
		if ( 'failed' !== $status ) {
			wp_send_json_error( 'Analysis is not in failed state.' );
		}

		try {
			// Reset status and clear error.
			update_post_meta( $analysis_id, '_ck_status', 'pending' );
			delete_post_meta( $analysis_id, '_ck_error_message' );
			delete_post_meta( $analysis_id, '_ck_error_stack_trace' );
			delete_post_meta( $analysis_id, '_ck_current_step' );
			delete_post_meta( $analysis_id, '_ck_progress' );

			// Reschedule the first step job.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time(),
					\CompetitorKnowledge\Analysis\Jobs\SearchStepJob::ACTION,
					array( 'analysis_id' => $analysis_id )
				);
			} else {
				throw new \Exception( 'Action Scheduler not available.' );
			}

			wp_send_json_success(
				array(
					'message'     => __( 'Analysis retry started.', 'competitor-knowledge' ),
					'analysis_id' => $analysis_id,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Get analysis progress via AJAX.
	 */
	public function get_analysis_progress(): void {
		check_ajax_referer( 'ck_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$analysis_id = isset( $_GET['analysis_id'] ) ? (int) $_GET['analysis_id'] : 0;

		if ( ! $analysis_id ) {
			wp_send_json_error( 'Invalid analysis ID.' );
		}

		$status       = get_post_meta( $analysis_id, '_ck_status', true );
		$current_step = get_post_meta( $analysis_id, '_ck_current_step', true );
		$progress     = (int) get_post_meta( $analysis_id, '_ck_progress', true );
		$total_steps  = (int) get_post_meta( $analysis_id, '_ck_total_steps', true );
		$error_msg    = get_post_meta( $analysis_id, '_ck_error_message', true );

		$step_labels = array(
			'searching' => __( 'Searching competitors...', 'competitor-knowledge' ),
			'analyzing' => __( 'Analyzing with AI...', 'competitor-knowledge' ),
			'saving'    => __( 'Saving results...', 'competitor-knowledge' ),
		);

		wp_send_json_success(
			array(
				'status'       => $status ?: 'pending',
				'current_step' => $current_step,
				'step_label'   => $step_labels[ $current_step ] ?? '',
				'progress'     => $progress,
				'total_steps'  => $total_steps ?: 3,
				'percentage'   => $total_steps ? round( ( $progress / $total_steps ) * 100 ) : 0,
				'error'        => $error_msg,
				'completed'    => 'completed' === $status,
				'failed'       => 'failed' === $status,
			)
		);
	}

	/**
	 * Get all analyses for a product in modal format.
	 */
	public function get_product_analyses_modal(): void {
		check_ajax_referer( 'ck_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;

		if ( ! $product_id ) {
			wp_send_json_error( 'Invalid product ID.' );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( 'Product not found.' );
		}

		$args = array(
			'post_type'      => \CompetitorKnowledge\Data\AnalysisCPT::POST_TYPE,
			'posts_per_page' => 20,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_ck_target_product_id',
					'value' => $product_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$analyses = get_posts( $args );

		ob_start();
		?>
		<div class="ck-modal-content">
			<h2 style="margin-top:0;"><?php printf( esc_html__( 'All Analyses for %s', 'competitor-knowledge' ), esc_html( $product->get_name() ) ); ?></h2>
			
			<?php if ( empty( $analyses ) ) : ?>
				<p><?php esc_html_e( 'No analyses found for this product.', 'competitor-knowledge' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top: 15px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'competitor-knowledge' ); ?></th>
							<th><?php esc_html_e( 'Status', 'competitor-knowledge' ); ?></th>
							<th><?php esc_html_e( 'Competitors', 'competitor-knowledge' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'competitor-knowledge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $analyses as $analysis ) : ?>
							<?php
							$status         = get_post_meta( $analysis->ID, '_ck_status', true );
							$data           = get_post_meta( $analysis->ID, '_ck_analysis_data', true );
							$competitor_cnt = is_array( $data ) && isset( $data['competitors'] ) ? count( $data['competitors'] ) : 0;
							$edit_link      = get_edit_post_link( $analysis->ID );

							$status_colors = array(
								'pending'    => '#f0ad4e',
								'processing' => '#5bc0de',
								'completed'  => '#5cb85c',
								'failed'     => '#d9534f',
							);
							$color = $status_colors[ $status ] ?? '#999';
							?>
							<tr>
								<td><?php echo esc_html( get_the_date( 'M j, Y g:i A', $analysis ) ); ?></td>
								<td>
									<span style="display:inline-block;padding:2px 8px;border-radius:3px;color:#fff;background:<?php echo esc_attr( $color ); ?>;font-size:11px;">
										<?php echo esc_html( ucfirst( $status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $competitor_cnt ); ?></td>
								<td>
									<?php if ( $edit_link ) : ?>
										<a href="<?php echo esc_url( $edit_link ); ?>" class="button button-small" target="_blank">
											<?php esc_html_e( 'View', 'competitor-knowledge' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
