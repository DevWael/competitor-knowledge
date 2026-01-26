<?php
/**
 * Analysis Repository.
 *
 * @package CompetitorKnowledge\Data
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Data;

use InvalidArgumentException;
use WP_Post;

/**
 * Class AnalysisRepository
 *
 * Data Access Layer for Analysis records.
 *
 * @package CompetitorKnowledge\Data
 */
class AnalysisRepository {

	/**
	 * Meta Key for Target Product ID.
	 */
	private const META_PRODUCT_ID = '_ck_target_product_id';

	/**
	 * Meta Key for Status.
	 */
	private const META_STATUS = '_ck_status';

	/**
	 * Meta Key for Analysis Data (JSON).
	 */
	private const META_DATA = '_ck_analysis_data';

	/**
	 * Create a new analysis record.
	 *
	 * @param int $product_id The target WC Product ID.
	 *
	 * @return int The new Analysis ID.
	 * @throws InvalidArgumentException If product ID is invalid.
	 */
	public function create( int $product_id ): int {
		if ( $product_id <= 0 ) {
			throw new InvalidArgumentException( 'Invalid product ID.' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => AnalysisCPT::POST_TYPE,
				'post_title'  => sprintf( 'Analysis for Product #%d - %s', $product_id, current_time( 'mysql' ) ),
				'post_status' => 'publish',
			)
		);

		//phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @phpstan-ignore-next-line - Defensive check for edge cases */
		if ( is_wp_error( $post_id ) ) {
			throw new InvalidArgumentException( 'Failed to create analysis post.' );
		}

		$this->update_status( $post_id, 'pending' );
		update_post_meta( $post_id, self::META_PRODUCT_ID, $product_id );

		return $post_id;
	}

	/**
	 * Update the status of an analysis.
	 *
	 * @param int    $analysis_id The Analysis ID.
	 * @param string $status      New status (pending, processing, completed, failed).
	 */
	public function update_status( int $analysis_id, string $status ): void {
		update_post_meta( $analysis_id, self::META_STATUS, $status );
	}

	/**
	 * Store analysis results.
	 *
	 * @param int   $analysis_id The Analysis ID.
	 * @param array $data        The structured analysis data.
	 */
	public function save_results( int $analysis_id, array $data ): void {
		update_post_meta( $analysis_id, self::META_DATA, $data );
		$this->update_status( $analysis_id, 'completed' );
	}

	/**
	 * Get analysis results.
	 *
	 * @param int $analysis_id The Analysis ID.
	 *
	 * @return array|null The data or null if not found.
	 */
	public function get_results( int $analysis_id ): ?array {
		$data = get_post_meta( $analysis_id, self::META_DATA, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get the target product ID for an analysis.
	 *
	 * @param int $analysis_id Analysis ID.
	 *
	 * @return int
	 */
	public function get_target_product_id( int $analysis_id ): int {
		return (int) get_post_meta( $analysis_id, self::META_PRODUCT_ID, true );
	}
}
