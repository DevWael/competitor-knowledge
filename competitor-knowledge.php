<?php
/**
 * Plugin Name: Competitor Knowledge
 * Plugin URI:  https://www.bbioon.com
 * Description: AI-powered competitor analysis for WooCommerce products. Uses Google AI, Ollama, and Tavily to provide market insights.
 * Version:     1.0.0
 * Author:      Ahmad Wael
 * Author URI:  https://www.bbioon.com
 * Text Domain: competitor-knowledge
 * Domain Path: /languages
 * License:     GPL-3.0-or-later
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 */

declare(strict_types=1);

namespace CompetitorKnowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Plugin Constants
define( 'COMPETITOR_KNOWLEDGE_VERSION', '1.0.0' );
define( 'COMPETITOR_KNOWLEDGE_FILE', __FILE__ );
define( 'COMPETITOR_KNOWLEDGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'COMPETITOR_KNOWLEDGE_URL', plugin_dir_url( __FILE__ ) );

// Require Messenger Autoloader
if ( file_exists( COMPETITOR_KNOWLEDGE_PATH . 'vendor/autoload.php' ) ) {
	require_once COMPETITOR_KNOWLEDGE_PATH . 'vendor/autoload.php';
}

/**
 * Initialize the plugin.
 */
function init(): void {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\admin_notice_missing_woocommerce' );
		return;
	}

	// Initialize Container / Bootstrap
	$plugin = new Core\Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

register_activation_hook( COMPETITOR_KNOWLEDGE_FILE, [ __NAMESPACE__ . '\\Data\\Installer', 'install' ] );

/**
 * Admin notice for missing WooCommerce dependency.
 */
function admin_notice_missing_woocommerce(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Competitor Knowledge requires WooCommerce to be installed and active.', 'competitor-knowledge' ); ?></p>
	</div>
	<?php
}
