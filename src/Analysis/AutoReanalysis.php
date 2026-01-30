<?php
/**
 * Auto Re-analysis Event Handler.
 *
 * @package CompetitorKnowledge\Analysis
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Analysis;

use CompetitorKnowledge\Admin\Settings;
use CompetitorKnowledge\Data\AnalysisCPT;
use CompetitorKnowledge\Data\AnalysisRepository;
use CompetitorKnowledge\Analysis\Jobs\SearchStepJob;

/**
 * Class AutoReanalysis
 *
 * Triggers automatic re-analysis based on configured events.
 *
 * @package CompetitorKnowledge\Analysis
 */
class AutoReanalysis {

	/**
	 * Trigger types.
	 */
	public const TRIGGER_PRICE_CHANGE    = 'price_change';
	public const TRIGGER_STOCK_CHANGE    = 'stock_change';
	public const TRIGGER_PRODUCT_UPDATE  = 'product_update';
	public const TRIGGER_TIME_BASED      = 'time_based';

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Initialize the auto re-analysis hooks.
	 */
	public function init(): void {
		// Product update hooks.
		add_action( 'woocommerce_update_product', array( $this, 'on_product_update' ), 10, 2 );

		// Price change hook.
		add_action( 'woocommerce_product_set_price', array( $this, 'on_price_change' ), 10, 2 );

		// Stock change hook.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_change' ), 10, 3 );
	}

	/**
	 * Handle product update event.
	 *
	 * @param int          $product_id The product ID.
	 * @param \WC_Product  $product    The product object.
	 */
	public function on_product_update( int $product_id, \WC_Product $product ): void {
		if ( ! $this->is_trigger_enabled( self::TRIGGER_PRODUCT_UPDATE ) ) {
			return;
		}

		$this->maybe_schedule_reanalysis( $product_id, self::TRIGGER_PRODUCT_UPDATE );
	}

	/**
	 * Handle price change event.
	 *
	 * @param \WC_Product $product The product.
	 * @param string      $price   The new price.
	 */
	public function on_price_change( \WC_Product $product, string $price ): void {
		if ( ! $this->is_trigger_enabled( self::TRIGGER_PRICE_CHANGE ) ) {
			return;
		}

		$this->maybe_schedule_reanalysis( $product->get_id(), self::TRIGGER_PRICE_CHANGE );
	}

	/**
	 * Handle stock status change event.
	 *
	 * @param int    $product_id   The product ID.
	 * @param string $stock_status The new stock status.
	 * @param object $product      The product.
	 */
	public function on_stock_change( int $product_id, string $stock_status, object $product ): void {
		if ( ! $this->is_trigger_enabled( self::TRIGGER_STOCK_CHANGE ) ) {
			return;
		}

		$this->maybe_schedule_reanalysis( $product_id, self::TRIGGER_STOCK_CHANGE );
	}

	/**
	 * Check if a trigger type is enabled.
	 *
	 * @param string $trigger_type The trigger type.
	 * @return bool True if enabled.
	 */
	private function is_trigger_enabled( string $trigger_type ): bool {
		$triggers = $this->get_setting( 'auto_reanalysis_triggers', array() );

		return is_array( $triggers ) && in_array( $trigger_type, $triggers, true );
	}

	/**
	 * Maybe schedule a re-analysis for a product.
	 *
	 * @param int    $product_id   The product ID.
	 * @param string $trigger_type The trigger type.
	 */
	private function maybe_schedule_reanalysis( int $product_id, string $trigger_type ): void {
		// Check cooldown - don't re-analyze too frequently.
		$cooldown_hours = (int) $this->get_setting( 'auto_reanalysis_cooldown', 24 );
		$last_analysis  = $this->get_last_analysis_time( $product_id );

		if ( $last_analysis && ( time() - $last_analysis ) < ( $cooldown_hours * HOUR_IN_SECONDS ) ) {
			/**
			 * Fires when an auto-reanalysis is skipped due to cooldown.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $product_id   The product ID.
			 * @param string $trigger_type The trigger type.
			 * @param int    $last_analysis Timestamp of last analysis.
			 */
			do_action( 'ck_auto_reanalysis_cooldown', $product_id, $trigger_type, $last_analysis );
			return;
		}

		// Schedule the analysis.
		$this->schedule_analysis( $product_id, $trigger_type );
	}

	/**
	 * Get the last analysis time for a product.
	 *
	 * @param int $product_id The product ID.
	 * @return int|null Timestamp or null.
	 */
	private function get_last_analysis_time( int $product_id ): ?int {
		$args = array(
			'post_type'      => AnalysisCPT::POST_TYPE,
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_ck_target_product_id',
					'value' => $product_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return null;
		}

		$post = get_post( $posts[0] );
		return $post ? strtotime( $post->post_date ) : null;
	}

	/**
	 * Schedule an analysis for a product.
	 *
	 * @param int    $product_id   The product ID.
	 * @param string $trigger_type The trigger type.
	 */
	private function schedule_analysis( int $product_id, string $trigger_type ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$repo        = new AnalysisRepository();
		$analysis_id = $repo->create( $product_id );

		// Store trigger source for tracking.
		update_post_meta( $analysis_id, '_ck_trigger_source', $trigger_type );

		// Schedule with a small delay to batch multiple updates.
		as_schedule_single_action(
			time() + 60, // 1 minute delay.
			SearchStepJob::ACTION,
			array( 'analysis_id' => $analysis_id ),
			'competitor-knowledge'
		);

		/**
		 * Fires when an auto-reanalysis is scheduled.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $product_id   The product ID.
		 * @param int    $analysis_id  The analysis ID.
		 * @param string $trigger_type The trigger type.
		 */
		do_action( 'ck_auto_reanalysis_scheduled', $product_id, $analysis_id, $trigger_type );
	}

	/**
	 * Get a plugin setting.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default Default value.
	 * @return mixed The setting value.
	 */
	private function get_setting( string $key, $default = null ) {
		$options = get_option( Settings::OPTION_NAME, array() );
		return $options[ $key ] ?? $default;
	}
}
