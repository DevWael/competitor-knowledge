<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Admin;

use CompetitorKnowledge\Data\AnalysisCPT;
use CompetitorKnowledge\Data\AnalysisRepository;
use WP_Post;

/**
 * Class Metaboxes
 *
 * Handles metaboxes on the Product page.
 *
 * @package CompetitorKnowledge\Admin
 */
class Metaboxes {

	/**
	 * Initialize metaboxes.
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_product_metabox' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_analysis_metabox' ] );
		// Ensure assets are enqueued for admin
		// add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add the metabox to products.
	 */
	public function add_product_metabox(): void {
		add_meta_box(
			'ck_competitor_analysis',
			__( 'Competitor Analysis', 'competitor-knowledge' ),
			[ $this, 'render_metabox' ],
			'product',
			'normal',
			'low'
		);
	}

	/**
	 * Add the metabox to analyses.
	 */
	public function add_analysis_metabox(): void {
		add_meta_box(
			'ck_analysis_results',
			__( 'Analysis Results', 'competitor-knowledge' ),
			[ $this, 'render_results_metabox' ],
			AnalysisCPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render_metabox( WP_Post $post ): void {
		// Fetch recent analyses for this product
		// For now, simpler query. In real app, restrict by meta.
		$args = [
			'post_type'      => AnalysisCPT::POST_TYPE,
			'posts_per_page' => 5,
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => '_ck_target_product_id',
					'value' => $post->ID,
				],
			],
		];

		$analyses = get_posts( $args );
		?>
		<div class="ck-metabox-content">
			<p>
				<button type="button" class="button button-primary" id="ck-run-analysis" data-product-id="<?php echo esc_attr( (string) $post->ID ); ?>">
					<?php esc_html_e( 'Run New Analysis', 'competitor-knowledge' ); ?>
				</button>
			</p>
			
			<?php if ( ! empty( $analyses ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'competitor-knowledge' ); ?></th>
							<th><?php esc_html_e( 'Status', 'competitor-knowledge' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'competitor-knowledge' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $analyses as $analysis ) : ?>
							<?php 
							$status = get_post_meta( $analysis->ID, '_ck_status', true ); 
							// Logic to get edit link
							$edit_link = get_edit_post_link( $analysis->ID );
							?>
							<tr>
								<td><?php echo esc_html( get_the_date( '', $analysis ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $status ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( $edit_link ); ?>" class="button button-small">
										<?php esc_html_e( 'View Report', 'competitor-knowledge' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No previous analyses found.', 'competitor-knowledge' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the analysis results metabox.
	 *
	 * @param WP_Post $post The analysis post.
	 */
	public function render_results_metabox( WP_Post $post ): void {
		$data = get_post_meta( $post->ID, '_ck_analysis_data', true );

		if ( ! is_array( $data ) || empty( $data['competitors'] ) ) {
			echo '<p>' . esc_html__( 'No data available yet.', 'competitor-knowledge' ) . '</p>';
			return;
		}
		
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Competitor', 'competitor-knowledge' ); ?></th>
					<th><?php esc_html_e( 'Price', 'competitor-knowledge' ); ?></th>
					<th><?php esc_html_e( 'Stock', 'competitor-knowledge' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'competitor-knowledge' ); ?></th>
					<th><?php esc_html_e( 'Link', 'competitor-knowledge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $data['competitors'] as $competitor ) : ?>
					<tr>
						<td><?php echo esc_html( $competitor['name'] ?? 'N/A' ); ?></td>
						<td>
							<?php 
							echo esc_html( $competitor['price'] ?? '' ); 
							echo ' ' . esc_html( $competitor['currency'] ?? '' );
							?>
						</td>
						<td><?php echo esc_html( $competitor['stock_status'] ?? '' ); ?></td>
						<td><?php echo esc_html( $competitor['comparison_notes'] ?? '' ); ?></td>
						<td>
							<?php if ( ! empty( $competitor['url'] ) ) : ?>
								<a href="<?php echo esc_url( $competitor['url'] ); ?>" target="_blank" class="button button-small">
									<?php esc_html_e( 'Visit', 'competitor-knowledge' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
