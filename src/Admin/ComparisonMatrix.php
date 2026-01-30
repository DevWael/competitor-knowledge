<?php
/**
 * Comparison Matrix Admin Page.
 *
 * @package CompetitorKnowledge\Admin
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Admin;

use CompetitorKnowledge\Data\AnalysisCPT;

/**
 * Class ComparisonMatrix
 *
 * Displays a products × competitors comparison matrix view.
 *
 * @package CompetitorKnowledge\Admin
 */
class ComparisonMatrix {

	/**
	 * Menu Slug.
	 */
	public const MENU_SLUG = 'ck-comparison-matrix';

	/**
	 * Initialize the comparison matrix.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Add the submenu page under WooCommerce.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Competitor Matrix', 'competitor-knowledge' ),
			__( 'Competitor Matrix', 'competitor-knowledge' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the comparison matrix page.
	 */
	public function render_page(): void {
		$products_data = $this->get_products_with_competitors();
		$all_competitors = $this->extract_all_competitors( $products_data );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Competitor Comparison Matrix', 'competitor-knowledge' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Compare your products with competitors at a glance. Click column headers to sort.', 'competitor-knowledge' ); ?></p>
			
			<?php if ( empty( $products_data ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No completed analyses found. Run analyses on your products to see comparison data.', 'competitor-knowledge' ); ?></p>
				</div>
			<?php else : ?>
				<div class="ck-matrix-container" style="margin-top: 20px; overflow-x: auto;">
					<table class="widefat striped ck-matrix-table" id="ck-comparison-matrix">
						<thead>
							<tr>
								<th class="ck-col-product" data-sort="string" style="cursor:pointer;min-width:180px;">
									<?php esc_html_e( 'Product', 'competitor-knowledge' ); ?>
									<span class="dashicons dashicons-sort" style="font-size:14px;vertical-align:middle;"></span>
								</th>
								<th class="ck-col-price" data-sort="number" style="cursor:pointer;">
									<?php esc_html_e( 'Your Price', 'competitor-knowledge' ); ?>
									<span class="dashicons dashicons-sort" style="font-size:14px;vertical-align:middle;"></span>
								</th>
								<?php foreach ( $all_competitors as $competitor ) : ?>
									<th class="ck-col-competitor" data-sort="number" style="cursor:pointer;min-width:120px;" title="<?php echo esc_attr( $competitor ); ?>">
										<?php echo esc_html( $this->truncate_name( $competitor, 15 ) ); ?>
										<span class="dashicons dashicons-sort" style="font-size:14px;vertical-align:middle;"></span>
									</th>
								<?php endforeach; ?>
								<th class="ck-col-actions"><?php esc_html_e( 'Actions', 'competitor-knowledge' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $products_data as $item ) : ?>
								<?php
								$product         = $item['product'];
								$analysis        = $item['analysis'];
								$competitors_map = $item['competitors_map'];
								$your_price      = (float) $product->get_price();
								?>
								<tr>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>">
											<?php echo esc_html( $product->get_name() ); ?>
										</a>
									</td>
									<td data-value="<?php echo esc_attr( (string) $your_price ); ?>">
										<?php echo wc_price( $your_price ); ?>
									</td>
									<?php foreach ( $all_competitors as $competitor ) : ?>
										<?php
										$comp_price = $competitors_map[ $competitor ] ?? null;
										$diff_class = '';
										$diff_text  = '';
										if ( null !== $comp_price && $your_price > 0 ) {
											$diff = ( ( $comp_price - $your_price ) / $your_price ) * 100;
											if ( $diff > 5 ) {
												$diff_class = 'color: #4caf50;'; // Green - competitor more expensive
												$diff_text  = sprintf( '+%.0f%%', $diff );
											} elseif ( $diff < -5 ) {
												$diff_class = 'color: #f44336;'; // Red - competitor cheaper
												$diff_text  = sprintf( '%.0f%%', $diff );
											} else {
												$diff_class = 'color: #999;';
												$diff_text  = '~';
											}
										}
										?>
										<td data-value="<?php echo esc_attr( null !== $comp_price ? (string) $comp_price : '' ); ?>">
											<?php if ( null !== $comp_price ) : ?>
												<?php echo wc_price( $comp_price ); ?>
												<span style="font-size:11px;display:block;<?php echo esc_attr( $diff_class ); ?>">
													<?php echo esc_html( $diff_text ); ?>
												</span>
											<?php else : ?>
												<span style="color:#999;">—</span>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $analysis['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View Analysis', 'competitor-knowledge' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="ck-matrix-legend" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
					<h4 style="margin-top:0;"><?php esc_html_e( 'Legend', 'competitor-knowledge' ); ?></h4>
					<ul style="margin-bottom:0;">
						<li><span style="color:#4caf50;">●</span> <?php esc_html_e( 'Green: Competitor is more expensive than you', 'competitor-knowledge' ); ?></li>
						<li><span style="color:#f44336;">●</span> <?php esc_html_e( 'Red: Competitor is cheaper than you', 'competitor-knowledge' ); ?></li>
						<li><span style="color:#999;">●</span> <?php esc_html_e( 'Gray: Similar price (within 5%)', 'competitor-knowledge' ); ?></li>
					</ul>
				</div>

				<script>
				jQuery(document).ready(function($) {
					// Simple table sorting
					$('#ck-comparison-matrix th[data-sort]').on('click', function() {
						var table = $(this).closest('table');
						var tbody = table.find('tbody');
						var rows = tbody.find('tr').toArray();
						var col = $(this).index();
						var type = $(this).data('sort');
						var asc = $(this).hasClass('asc');

						rows.sort(function(a, b) {
							var aVal = $(a).find('td').eq(col).data('value') || $(a).find('td').eq(col).text().trim();
							var bVal = $(b).find('td').eq(col).data('value') || $(b).find('td').eq(col).text().trim();

							if (type === 'number') {
								aVal = parseFloat(aVal) || 0;
								bVal = parseFloat(bVal) || 0;
							}

							return asc ? (aVal > bVal ? -1 : 1) : (aVal > bVal ? 1 : -1);
						});

						table.find('th').removeClass('asc desc');
						$(this).addClass(asc ? 'desc' : 'asc');

						$.each(rows, function(i, row) {
							tbody.append(row);
						});
					});
				});
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get products with their latest completed analysis and competitors.
	 *
	 * @return array<int, array<string, mixed>> Products with competitor data.
	 */
	private function get_products_with_competitors(): array {
		$products_data = array();

		$args = array(
			'post_type'      => AnalysisCPT::POST_TYPE,
			'posts_per_page' => 100,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_ck_status',
					'value' => 'completed',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$analyses = get_posts( $args );
		$processed_products = array();

		foreach ( $analyses as $analysis ) {
			$product_id = (int) get_post_meta( $analysis->ID, '_ck_target_product_id', true );

			// Only use the latest analysis per product.
			if ( isset( $processed_products[ $product_id ] ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$data = get_post_meta( $analysis->ID, '_ck_analysis_data', true );
			if ( ! is_array( $data ) || empty( $data['competitors'] ) ) {
				continue;
			}

			// Build competitors map.
			$competitors_map = array();
			foreach ( $data['competitors'] as $comp ) {
				if ( isset( $comp['name'], $comp['price'] ) ) {
					$competitors_map[ $comp['name'] ] = (float) $comp['price'];
				}
			}

			$products_data[] = array(
				'product'         => $product,
				'analysis'        => array( 'id' => $analysis->ID, 'date' => $analysis->post_date ),
				'competitors_map' => $competitors_map,
			);

			$processed_products[ $product_id ] = true;
		}

		return $products_data;
	}

	/**
	 * Extract all unique competitor names from products data.
	 *
	 * @param array<int, array<string, mixed>> $products_data Products data.
	 * @return array<int, string> Unique competitor names.
	 */
	private function extract_all_competitors( array $products_data ): array {
		$competitors = array();

		foreach ( $products_data as $item ) {
			foreach ( array_keys( $item['competitors_map'] ) as $name ) {
				$competitors[ $name ] = true;
			}
		}

		return array_keys( $competitors );
	}

	/**
	 * Truncate a name to a max length.
	 *
	 * @param string $name       The name to truncate.
	 * @param int    $max_length Max length.
	 * @return string Truncated name.
	 */
	private function truncate_name( string $name, int $max_length ): string {
		if ( strlen( $name ) <= $max_length ) {
			return $name;
		}
		return substr( $name, 0, $max_length - 1 ) . '…';
	}
}
