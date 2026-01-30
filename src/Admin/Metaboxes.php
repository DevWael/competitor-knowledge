<?php
/**
 * Product and Analysis Metaboxes.
 *
 * @package CompetitorKnowledge\Admin
 */

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
		add_action( 'add_meta_boxes', array( $this, 'add_product_metabox' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_analysis_metabox' ) );
		// Ensure assets are enqueued for admin.
		// add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );.
	}

	/**
	 * Add the metabox to products.
	 */
	public function add_product_metabox(): void {
		add_meta_box(
			'ck_competitor_analysis',
			__( 'Competitor Analysis', 'competitor-knowledge' ),
			array( $this, 'render_metabox' ),
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
			array( $this, 'render_results_metabox' ),
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
		$args = array(
			'post_type'      => AnalysisCPT::POST_TYPE,
			'posts_per_page' => 5,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_ck_target_product_id',
					'value' => $post->ID,
				),
			),
		);

		$analyses = get_posts( $args );
		?>
		<div class="ck-metabox-content">
			<p>
				<button type="button" class="button button-primary" id="ck-run-analysis" data-product-id="<?php echo esc_attr( (string) $post->ID ); ?>">
					<?php esc_html_e( 'Run New Analysis', 'competitor-knowledge' ); ?>
				</button>
				<button type="button" class="button" id="ck-view-all-analyses" data-product-id="<?php echo esc_attr( (string) $post->ID ); ?>" style="margin-left: 10px;">
					<?php esc_html_e( 'View All Analyses', 'competitor-knowledge' ); ?>
				</button>
			</p>

			<!-- Progress Bar (hidden by default) -->
			<div id="ck-progress-container" style="display: none; margin: 15px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
					<span id="ck-progress-label" style="font-weight: 600; color: #333;"><?php esc_html_e( 'Starting analysis...', 'competitor-knowledge' ); ?></span>
					<span id="ck-progress-percent" style="color: #666;">0%</span>
				</div>
				<div style="background: #e0e0e0; border-radius: 4px; height: 20px; overflow: hidden;">
					<div id="ck-progress-bar" style="background: linear-gradient(90deg, #2196f3, #21cbf3); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
				</div>
				<div id="ck-progress-status" style="margin-top: 8px; color: #666; font-size: 12px;"></div>
			</div>
			
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
							if ( ! $analysis instanceof WP_Post ) {
								continue;
							}
							$status = get_post_meta( $analysis->ID, '_ck_status', true );
							// Logic to get edit link.
							$edit_link = get_edit_post_link( $analysis->ID );
							if ( null === $edit_link ) {
								continue; // Skip if no edit link available.
							}
							?>
							<tr>
								<td><?php echo esc_html( (string) get_the_date( '', $analysis ) ); ?></td>
								<td><?php echo esc_html( ucfirst( (string) $status ) ); ?></td>
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

		// 1. Render Strategy Card.
		$this->render_strategy_section( $data );

		// Price History Visualization.
		$product_id = get_post_meta( $post->ID, '_ck_target_product_id', true );
		if ( $product_id ) {
			$repo    = new \CompetitorKnowledge\Data\PriceHistoryRepository();
			$history = $repo->get_history( (int) $product_id );

			if ( ! empty( $history ) ) {
				$chart_data = array();
				foreach ( $history as $row ) {
					$chart_data[ $row['competitor_name'] ][] = array(
						'x' => $row['date_recorded'],
						'y' => $row['price'],
					);
				}
				?>
				<div class="ck-price-chart-container" style="margin-bottom: 20px;">
					<canvas id="ckPriceChart" height="100"></canvas>
				</div>
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					if (typeof Chart === 'undefined') return;
					
					var ctx = document.getElementById('ckPriceChart').getContext('2d');
					var datasets = [];
					var colors = ['#f44336', '#2196f3', '#4caf50', '#ff9800', '#9c27b0'];
					var i = 0;

					<?php foreach ( $chart_data as $name => $points ) : ?>
						datasets.push({
							label: '<?php echo esc_js( $name ); ?>',
							data: <?php echo wp_json_encode( $points ); ?>,
							borderColor: colors[i % colors.length],
							fill: false
						});
						i++;
					<?php endforeach; ?>

					new Chart(ctx, {
						type: 'line',
						data: { datasets: datasets },
						options: {
							scales: {
								x: { type: 'time', time: { unit: 'day' } },
								y: { beginAtZero: false } // Price usually not zero
							}
						}
					});
				});
				</script>
				<?php
			}
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

		// 3. Render Content Analysis.
		$this->render_content_analysis_section( $data );

		// 4. Render Sentiment Analysis.
		$this->render_sentiment_section( $data );
	}

	/**
	 * Render the Strategy section.
	 *
	 * @param array<string, mixed> $data Analysis data.
	 */
	private function render_strategy_section( array $data ): void {
		$strategy = $data['strategy'] ?? array();
		if ( empty( $strategy['pricing_advice'] ) && empty( $strategy['action_items'] ) ) {
			return;
		}
		?>
		<div class="ck-strategy-card" style="background: #e3f2fd; padding: 15px; border-left: 5px solid #2196f3; margin-bottom: 20px;">
			<h3 style="margin-top:0; color: #1976d2;"><?php esc_html_e( '🎯 Strategic Advice', 'competitor-knowledge' ); ?></h3>
			
			<?php if ( ! empty( $strategy['pricing_advice'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Pricing Strategy:', 'competitor-knowledge' ); ?></strong> <?php echo esc_html( $strategy['pricing_advice'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $strategy['action_items'] ) ) : ?>
				<h4 style="margin-bottom: 5px;"><?php esc_html_e( 'Action Items:', 'competitor-knowledge' ); ?></h4>
				<ul style="list-style-type: disc; margin-left: 20px;">
					<?php foreach ( $strategy['action_items'] as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Content Gap Analysis section.
	 *
	 * @param array<string, mixed> $data Analysis data.
	 */
	private function render_content_analysis_section( array $data ): void {
		$content = $data['content_analysis'] ?? array();
		if ( empty( $content ) ) {
			return;
		}
		?>
		<div class="ck-section" style="margin-bottom: 20px; border: 1px solid #ccd0d4; padding: 15px; background: #fff;">
			<h3><?php esc_html_e( '📝 Content Gap Analysis', 'competitor-knowledge' ); ?></h3>
			
			<div style="display: flex; gap: 20px; margin-bottom: 15px;">
				<div style="flex: 1;">
					<strong><?php esc_html_e( 'Your Tone:', 'competitor-knowledge' ); ?></strong>
					<span class="ck-tag" style="background: #e0e0e0; padding: 2px 8px; border-radius: 4px;"><?php echo esc_html( $content['my_tone'] ?? 'N/A' ); ?></span>
				</div>
				<div style="flex: 1;">
					<strong><?php esc_html_e( 'Competitor Tone:', 'competitor-knowledge' ); ?></strong>
					<span class="ck-tag" style="background: #e0e0e0; padding: 2px 8px; border-radius: 4px;"><?php echo esc_html( $content['competitor_tone'] ?? 'N/A' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $content['missing_keywords'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Missing Keywords:', 'competitor-knowledge' ); ?></strong></p>
				<div class="ck-keywords" style="margin-bottom: 15px;">
					<?php foreach ( $content['missing_keywords'] as $keyword ) : ?>
						<span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; margin-right: 5px; display: inline-block; border: 1px solid #ffeeba;">
							<?php echo esc_html( $keyword ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $content['improvement_suggestion'] ) ) : ?>
				<div class="ck-suggestion">
					<strong><?php esc_html_e( 'Improvement Suggestion:', 'competitor-knowledge' ); ?></strong>
					<textarea readonly style="width: 100%; height: 80px; margin-top: 5px;"><?php echo esc_html( $content['improvement_suggestion'] ); ?></textarea>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Sentiment Analysis section.
	 *
	 * @param array<string, mixed> $data Analysis data.
	 */
	private function render_sentiment_section( array $data ): void {
		$sentiment = $data['sentiment_analysis'] ?? array();
		if ( empty( $sentiment['competitor_weaknesses'] ) && empty( $sentiment['market_gaps'] ) ) {
			return;
		}
		?>
		<div class="ck-section" style="margin-bottom: 20px; border: 1px solid #ccd0d4; padding: 15px; background: #fff;">
			<h3><?php esc_html_e( '🧠 Sentiment Intelligence', 'competitor-knowledge' ); ?></h3>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
				<?php if ( ! empty( $sentiment['competitor_weaknesses'] ) ) : ?>
					<div>
						<h4 style="color: #d32f2f; margin-top: 0;"><?php esc_html_e( '📉 Competitor Weaknesses', 'competitor-knowledge' ); ?></h4>
						<ul style="list-style-type: circle; margin-left: 20px;">
							<?php foreach ( $sentiment['competitor_weaknesses'] as $weakness ) : ?>
								<li><?php echo esc_html( $weakness ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $sentiment['market_gaps'] ) ) : ?>
					<div>
						<h4 style="color: #388e3c; margin-top: 0;"><?php esc_html_e( '🚀 Market Gaps (Opportunities)', 'competitor-knowledge' ); ?></h4>
						<ul style="list-style-type: circle; margin-left: 20px;">
							<?php foreach ( $sentiment['market_gaps'] as $gap ) : ?>
								<li><?php echo esc_html( $gap ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
