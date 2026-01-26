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
	}
}
