<?php
/**
 * Price History Repository.
 *
 * @package CompetitorKnowledge\Data
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Data;

/**
 * Class PriceHistoryRepository
 *
 * Handles database operations for price history.
 *
 * @package CompetitorKnowledge\Data
 */
class PriceHistoryRepository {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * PriceHistoryRepository constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . Installer::TABLE_PRICE_HISTORY;
	}

	/**
	 * Add a price record.
	 *
	 * @param int    $product_id      WC Product ID.
	 * @param int    $analysis_id     Analysis ID.
	 * @param string $competitor_name Competitor name or domain.
	 * @param float  $price           Price amount.
	 * @param string $currency        Currency code.
	 *
	 * @return bool True on success.
	 */
	public function add_record( int $product_id, int $analysis_id, string $competitor_name, float $price, string $currency ): bool {
		global $wpdb;

		return (bool) $wpdb->insert(
			$this->table_name,
			array(
				'product_id'      => $product_id,
				'analysis_id'     => $analysis_id,
				'competitor_name' => sanitize_text_field( $competitor_name ),
				'price'           => $price,
				'currency'        => sanitize_text_field( $currency ),
				'date_recorded'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%f', '%s', '%s' )
		);
	}

	/**
	 * Get history for a product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array List of records.
	 */
	public function get_history( int $product_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via $wpdb->prefix.
				"SELECT * FROM {$this->table_name} WHERE product_id = %d ORDER BY date_recorded ASC",
				$product_id
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}
}
