<?php
/**
 * Analysis Custom Post Type registration.
 *
 * @package CompetitorKnowledge
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Data;

/**
 * Class AnalysisCPT
 *
 * Registers the 'ck_analysis' Custom Post Type.
 *
 * @package CompetitorKnowledge\Data
 */
class AnalysisCPT {

	/**
	 * Post Type Slug.
	 */
	public const POST_TYPE = 'ck_analysis';

	/**
	 * Register the Custom Post Type.
	 */
	public function register(): void {
		$labels = array(
			'name'               => _x( 'Competitor Analyses', 'post type general name', 'competitor-knowledge' ),
			'singular_name'      => _x( 'Analysis', 'post type singular name', 'competitor-knowledge' ),
			'menu_name'          => _x( 'Competitor Analysis', 'admin menu', 'competitor-knowledge' ),
			'name_admin_bar'     => _x( 'Analysis', 'add new on admin bar', 'competitor-knowledge' ),
			'add_new'            => _x( 'Add New', 'analysis', 'competitor-knowledge' ),
			'add_new_item'       => __( 'Add New Analysis', 'competitor-knowledge' ),
			'new_item'           => __( 'New Analysis', 'competitor-knowledge' ),
			'edit_item'          => __( 'Edit Analysis', 'competitor-knowledge' ),
			'view_item'          => __( 'View Analysis', 'competitor-knowledge' ),
			'all_items'          => __( 'All Analyses', 'competitor-knowledge' ),
			'search_items'       => __( 'Search Analyses', 'competitor-knowledge' ),
			'not_found'          => __( 'No analyses found.', 'competitor-knowledge' ),
			'not_found_in_trash' => __( 'No analyses found in Trash.', 'competitor-knowledge' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true, // Parent menu or separate?
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'ck-analysis' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title' ), // We store data in meta.
		);

		register_post_type( self::POST_TYPE, $args );

		// Register custom columns.
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
	}

	/**
	 * Add custom columns to the CPT list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 *
	 * @return array<string, string> Modified columns.
	 */
	public function add_custom_columns( array $columns ): array {
		$new_columns = array();

		// Reorder columns: add status and product after title.
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['ck_status']  = __( 'Status', 'competitor-knowledge' );
				$new_columns['ck_product'] = __( 'Product', 'competitor-knowledge' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_custom_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'ck_status':
				$this->render_status_column( $post_id );
				break;
			case 'ck_product':
				$this->render_product_column( $post_id );
				break;
		}
	}

	/**
	 * Render the status column with color badges.
	 *
	 * @param int $post_id Post ID.
	 */
	private function render_status_column( int $post_id ): void {
		$status = get_post_meta( $post_id, '_ck_status', true );
		$step   = get_post_meta( $post_id, '_ck_current_step', true );

		$status_labels = array(
			'pending'    => __( 'Pending', 'competitor-knowledge' ),
			'processing' => __( 'Processing', 'competitor-knowledge' ),
			'completed'  => __( 'Completed', 'competitor-knowledge' ),
			'failed'     => __( 'Failed', 'competitor-knowledge' ),
		);

		$status_colors = array(
			'pending'    => '#f0ad4e',
			'processing' => '#5bc0de',
			'completed'  => '#5cb85c',
			'failed'     => '#d9534f',
		);

		$label = $status_labels[ $status ] ?? $status;
		$color = $status_colors[ $status ] ?? '#999';

		printf(
			'<span class="ck-status-badge" style="display:inline-block;padding:3px 8px;border-radius:3px;color:#fff;background-color:%s;font-size:12px;">%s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);

		// Show current step if processing.
		if ( 'processing' === $status && $step ) {
			printf( '<br><small style="color:#666;">%s</small>', esc_html( $step ) );
		}
	}

	/**
	 * Render the product column with link.
	 *
	 * @param int $post_id Post ID.
	 */
	private function render_product_column( int $post_id ): void {
		$product_id = (int) get_post_meta( $post_id, '_ck_target_product_id', true );

		if ( ! $product_id ) {
			echo '—';
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			printf( '#%d <em>(%s)</em>', $product_id, esc_html__( 'deleted', 'competitor-knowledge' ) );
			return;
		}

		printf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $product_id ) ),
			esc_html( $product->get_name() )
		);
	}
}
