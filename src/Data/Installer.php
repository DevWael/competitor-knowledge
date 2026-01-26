<?php

declare(strict_types=1);

namespace CompetitorKnowledge\Data;

/**
 * Class Installer
 *
 * Handles plugin installation and updates.
 *
 * @package CompetitorKnowledge\Data
 */
class Installer {

	/**
	 * Table name for price history.
	 */
	public const TABLE_PRICE_HISTORY = 'ck_price_history';

	/**
	 * Run installation tasks.
	 */
	public static function install(): void {
		self::create_tables();
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_PRICE_HISTORY;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			product_id bigint(20) NOT NULL,
			analysis_id bigint(20) NOT NULL,
			competitor_name varchar(255) NOT NULL,
			price decimal(10, 2) NOT NULL,
			currency varchar(10) NOT NULL,
			date_recorded datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY competitor_name (competitor_name)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
